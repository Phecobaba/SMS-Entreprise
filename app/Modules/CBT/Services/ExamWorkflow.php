<?php

namespace App\Modules\CBT\Services;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Services\ResultAcademicCatalog;
use App\Modules\Admissions\Models\Candidate;
use App\Modules\Admissions\Services\AdmissionPipeline;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\CBT\Models\Exam;
use App\Modules\CBT\Models\ExamAnswer;
use App\Modules\CBT\Models\ExamResitGrant;
use App\Modules\CBT\Models\ExamSubmission;
use App\Modules\CBT\Models\ExamType;
use App\Modules\CBT\Models\Question;
use App\Modules\CBT\Models\QuestionOption;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Core\TenantContext;
use App\Modules\Results\Models\AssessmentComponent;
use App\Modules\Results\Services\ResultAssessmentCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ExamWorkflow
{
    public static function convertScore(float $raw, float $maximum, float $target): float
    {
        if ($maximum <= 0 || $target <= 0 || $raw < 0 || $raw > $maximum) {
            throw ValidationException::withMessages(['score' => 'Invalid CBT score or maximum.']);
        }

        return round($raw / $maximum * $target, 2);
    }

    public function createType(string $name, User $actor): ExamType
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        abort_unless($actor->can('view_all_sections'), 403);
        if (! in_array($name, ['School exam', 'Internal', 'Entrance', 'Mock', 'Practice', 'Assessment', 'Promotional'], true)) {
            throw ValidationException::withMessages(['name' => 'Choose a supported exam type.']);
        }

        return ExamType::firstOrCreate(
            ['name' => trim($name)],
            ['feeds_results' => trim($name) === 'School exam', 'active' => true],
        );
    }

    public function createExam(array $data, User $actor): Exam
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        $type = ExamType::findOrFail((int) $data['exam_type_id']);
        if (! $type->active) {
            throw ValidationException::withMessages(['exam_type_id' => 'Choose an active exam type.']);
        }
        $entrance = $type->name === 'Entrance';
        if ($entrance) {
            if (! app(CbtAccessScope::class)->mayManageClassless($actor)) {
                abort(403, 'Entrance CBT is managed by the school office.');
            }
            if (($data['pass_mark'] ?? null) === null) {
                throw ValidationException::withMessages(['pass_mark' => 'Set an entrance pass mark.']);
            }
        } else {
            if (! isset($data['class_id'], $data['subject_id'])) {
                throw ValidationException::withMessages(['class_id' => 'Choose a class and subject for a student exam.']);
            }
            app(CbtAccessScope::class)->assertClass($actor, (int) $data['class_id']);
            app(ResultAcademicCatalog::class)->assertExamSubject((int) $data['class_id'], (int) $data['subject_id']);
            if (isset($data['term_id'])) {
                app(ResultAcademicCatalog::class)->assertTerm((int) $data['term_id']);
            }
            if (isset($data['assessment_component_id'])) {
                if (! isset($data['term_id'])) {
                    throw ValidationException::withMessages(['term_id' => 'Choose a term before linking an exam to Results.']);
                }
                app(ResultAssessmentCatalog::class)->maxScore((int) $data['assessment_component_id']);
                $sectionId = SchoolClass::with('level')->findOrFail((int) $data['class_id'])->level?->section_id;
                if (! AssessmentComponent::whereKey((int) $data['assessment_component_id'])->where('section_id', $sectionId)->exists()) {
                    throw ValidationException::withMessages(['assessment_component_id' => 'Choose a Results component from the exam’s Section.']);
                }
            }
        }
        if (($data['result_release_type'] ?? '') === 'Scheduled' && empty($data['result_release_at'])) {
            throw ValidationException::withMessages(['result_release_at' => 'Choose a scheduled release time.']);
        }

        $exam = DB::transaction(function () use ($data, $actor, $type, $entrance): Exam {
            $exam = Exam::create([
                'exam_type_id' => $type->id,
                'class_id' => $entrance ? null : $data['class_id'],
                'subject_id' => $entrance ? null : $data['subject_id'],
                'term_id' => $entrance ? null : ($data['term_id'] ?? null),
                'assessment_component_id' => $entrance ? null : ($data['assessment_component_id'] ?? null),
                'created_by' => $actor->id,
                'title' => $data['title'], 'duration' => $data['duration'],
                'default_question_marks' => $data['default_question_marks'] ?? 1,
                'status' => 'Draft',
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'closes_at' => $data['closes_at'] ?? null,
                'closes_automatically' => (bool) ($data['closes_automatically'] ?? true),
                'pass_mark' => $entrance ? $data['pass_mark'] : null,
                'result_release_type' => $data['result_release_type'],
                'result_release_at' => $data['result_release_at'] ?? null,
                'show_score' => (bool) ($data['show_score'] ?? false),
                'show_correct_answers' => (bool) ($data['show_correct_answers'] ?? false),
                'show_explanations' => (bool) ($data['show_explanations'] ?? false),
                'requires_approval' => false,
            ]);
            $this->syncLevels($exam, $entrance ? ($data['level_ids'] ?? []) : []);

            return $exam;
        });

        return $exam->load('levels');
    }

    public function updateExam(Exam $exam, array $data, User $actor): Exam
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        app(CbtAccessScope::class)->managedExam($actor, $exam->id);
        if ($exam->status !== 'Draft') {
            throw ValidationException::withMessages(['exam' => 'Only a draft exam can be edited.']);
        }
        $type = ExamType::findOrFail((int) $data['exam_type_id']);
        if (! $type->active) {
            throw ValidationException::withMessages(['exam_type_id' => 'Choose an active exam type.']);
        }
        $entrance = $type->name === 'Entrance';
        if ($entrance && ! app(CbtAccessScope::class)->mayManageClassless($actor)) {
            abort(403, 'Entrance CBT is managed by the school office.');
        }
        if (! $entrance) {
            if (! isset($data['class_id'], $data['subject_id'])) {
                throw ValidationException::withMessages(['class_id' => 'Choose a class and subject for a student exam.']);
            }
            app(CbtAccessScope::class)->assertClass($actor, (int) $data['class_id']);
            $keepsUnassignedPair = (int) $data['class_id'] === (int) $exam->class_id
                && (int) $data['subject_id'] === (int) $exam->subject_id;
            if (! $keepsUnassignedPair) {
                app(ResultAcademicCatalog::class)->assertExamSubject((int) $data['class_id'], (int) $data['subject_id']);
            }
        } elseif (($data['pass_mark'] ?? null) === null) {
            throw ValidationException::withMessages(['pass_mark' => 'Set an entrance pass mark.']);
        }
        if (isset($data['term_id'])) {
            app(ResultAcademicCatalog::class)->assertTerm((int) $data['term_id']);
        }
        if (isset($data['assessment_component_id'])) {
            if (! isset($data['term_id'])) {
                throw ValidationException::withMessages(['term_id' => 'Choose a term before linking an exam to Results.']);
            }
            app(ResultAssessmentCatalog::class)->maxScore((int) $data['assessment_component_id']);
            $sectionId = SchoolClass::with('level')->findOrFail((int) $data['class_id'])->level?->section_id;
            if (! AssessmentComponent::whereKey((int) $data['assessment_component_id'])->where('section_id', $sectionId)->exists()) {
                throw ValidationException::withMessages(['assessment_component_id' => 'Choose a Results component from the exam’s Section.']);
            }
        }
        if (($data['result_release_type'] ?? '') === 'Scheduled' && empty($data['result_release_at'])) {
            throw ValidationException::withMessages(['result_release_at' => 'Choose a scheduled release time.']);
        }

        DB::transaction(function () use ($exam, $data, $type, $entrance): void {
            $exam->update([
                'exam_type_id' => $type->id,
                'class_id' => $entrance ? null : $data['class_id'],
                'subject_id' => $entrance ? null : $data['subject_id'],
                'term_id' => $entrance ? null : ($data['term_id'] ?? null),
                'assessment_component_id' => $entrance ? null : ($data['assessment_component_id'] ?? null),
                'title' => $data['title'], 'duration' => $data['duration'],
                'default_question_marks' => $data['default_question_marks'] ?? $exam->default_question_marks,
                'scheduled_at' => $data['scheduled_at'] ?? null, 'closes_at' => $data['closes_at'] ?? null,
                'closes_automatically' => (bool) ($data['closes_automatically'] ?? true),
                'pass_mark' => $entrance ? $data['pass_mark'] : null,
                'result_release_type' => $data['result_release_type'],
                'result_release_at' => $data['result_release_at'] ?? null,
                'show_score' => (bool) ($data['show_score'] ?? false),
                'show_correct_answers' => (bool) ($data['show_correct_answers'] ?? false),
                'show_explanations' => (bool) ($data['show_explanations'] ?? false),
                'requires_approval' => false,
            ]);
            $this->syncLevels($exam, $entrance ? ($data['level_ids'] ?? []) : []);
        });

        return $exam->refresh()->load('levels');
    }

    public function addQuestion(Exam $exam, string $text, float $marks, ?string $explanation, array $options, int|array $correctIndex, User $actor, ?array $optionMarks = null): Question
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        app(CbtAccessScope::class)->managedExam($actor, $exam->id);
        $this->assertQuestionsMutable($exam);
        if ($exam->questions()->count() >= 200) {
            throw ValidationException::withMessages(['question' => 'An exam can have at most 200 questions.']);
        }
        $validated = $this->validateQuestionInput($text, $options, $correctIndex, $optionMarks, $marks);
        $definitions = $validated['options'];
        $totalMarks = array_sum(array_column($definitions, 'marks'));

        return DB::transaction(function () use ($exam, $validated, $totalMarks, $explanation, $definitions): Question {
            $question = Question::create([
                'exam_id' => $exam->id, 'text' => $validated['text'], 'marks' => $totalMarks,
                'explanation' => $explanation, 'position' => Question::where('exam_id', $exam->id)->max('position') + 1,
            ]);
            foreach ($definitions as $option) {
                QuestionOption::create(['question_id' => $question->id, ...$option]);
            }

            return $question;
        });
    }

    public function updateQuestion(Exam $exam, Question $question, string $text, float $marks, ?string $explanation, array $options, int|array $correctIndex, User $actor, ?array $optionMarks = null): Question
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        app(CbtAccessScope::class)->managedExam($actor, $exam->id);
        abort_unless((int) $question->exam_id === (int) $exam->id, 404);
        $this->assertQuestionsMutable($exam);
        $validated = $this->validateQuestionInput($text, $options, $correctIndex, $optionMarks, $marks);
        $definitions = $validated['options'];
        $totalMarks = array_sum(array_column($definitions, 'marks'));

        return DB::transaction(function () use ($question, $validated, $totalMarks, $explanation, $definitions): Question {
            $question->update(['text' => $validated['text'], 'marks' => $totalMarks, 'explanation' => $explanation]);
            $question->options()->delete();
            foreach ($definitions as $option) {
                QuestionOption::create(['question_id' => $question->id, ...$option]);
            }

            return $question->refresh()->load('options');
        });
    }

    public function deleteQuestion(Exam $exam, Question $question, User $actor): void
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        app(CbtAccessScope::class)->managedExam($actor, $exam->id);
        abort_unless((int) $question->exam_id === (int) $exam->id, 404);
        $this->assertQuestionsMutable($exam);
        $question->delete();
    }

    public function publish(Exam $exam, User $actor): void
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        app(CbtAccessScope::class)->managedExam($actor, $exam->id);
        if ($exam->status !== 'Draft') {
            throw ValidationException::withMessages(['exam' => 'Only a draft exam can be published.']);
        }
        $questions = Question::with('options')->where('exam_id', $exam->id)->get();
        if ($questions->isEmpty() || $questions->contains(fn (Question $q) => ! $this->validQuestion($q))) {
            throw ValidationException::withMessages(['questions' => 'Every published question needs at least two options, at least one correct answer with whole-number marks, and at least one wrong answer.']);
        }
        $exam->update(['status' => 'Published']);
    }

    public function start(Exam $exam, User $actor): ExamSubmission
    {
        $identity = $this->identity($exam, $actor);
        $candidate = isset($identity['candidate_id']) ? app(AdmissionPipeline::class)->candidateForUser($actor) : null;

        return DB::transaction(function () use ($exam, $identity, $candidate): ExamSubmission {
            $lockedExam = Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            $inProgress = ExamSubmission::where('exam_id', $lockedExam->id)->where($identity)
                ->where('status', 'In Progress')->latest('attempt_number')->lockForUpdate()->first();
            if ($inProgress) {
                app(ExamAttemptLifecycle::class)->submitExpiredAttempt($inProgress->load(['exam', 'resitGrant']));
                $inProgress->refresh();
                if ($inProgress->status === 'In Progress') {
                    return $inProgress;
                }
            }

            $grant = isset($identity['student_id'])
                ? ExamResitGrant::where('exam_id', $lockedExam->id)->where('student_id', $identity['student_id'])
                    ->whereNull('used_attempt_id')->whereNull('withdrawn_at')
                    ->where('opens_until', '>', now())->latest('id')->lockForUpdate()->first()
                : null;
            if ($grant) {
                $number = ((int) ExamSubmission::where('exam_id', $lockedExam->id)
                    ->where('student_id', $identity['student_id'])->max('attempt_number')) + 1;
                $submission = ExamSubmission::create([
                    'exam_id' => $lockedExam->id, ...$identity, 'attempt_number' => $number,
                    'started_at' => now(), 'status' => 'In Progress',
                ]);
                $grant->update(['used_attempt_id' => $submission->id]);

                return $submission;
            }

            if ($lockedExam->status !== 'Published' || ($lockedExam->scheduled_at && now()->lt($lockedExam->scheduled_at))) {
                throw ValidationException::withMessages(['exam' => 'This exam is not open yet.']);
            }
            if (app(ExamClosing::class)->countsAsClosed($lockedExam)) {
                throw ValidationException::withMessages(['exam' => 'This exam has closed.']);
            }
            $previous = ExamSubmission::where('exam_id', $lockedExam->id)->where($identity)
                ->latest('attempt_number')->first();
            if ($previous) {
                return $previous;
            }
            if ($candidate) {
                app(AdmissionPipeline::class)->assertCandidateMaySit($candidate);
            }
            $submission = ExamSubmission::create(['exam_id' => $lockedExam->id, ...$identity, 'attempt_number' => 1, 'started_at' => now(), 'status' => 'In Progress']);
            if (isset($identity['candidate_id'])) {
                app(AdmissionPipeline::class)->recordCbtStart($identity['candidate_id']);
            }

            return $submission;
        });
    }

    public function submit(ExamSubmission $submission, array $answers, User $actor): ExamSubmission
    {
        $this->assertOwner($submission, $actor);

        return app(ExamAttemptLifecycle::class)->submit($submission, $answers);
    }

    public function saveAnswer(ExamSubmission $submission, int $questionId, array|int|null $optionId, bool $flagged, User $actor): void
    {
        $this->assertOwner($submission, $actor);
        app(ExamAttemptLifecycle::class)->submitExpiredAttempt($submission->load('exam'));

        DB::transaction(function () use ($submission, $questionId, $optionId, $flagged): void {
            $submission = ExamSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            if ($submission->status !== 'In Progress') {
                throw ValidationException::withMessages(['submission' => 'This exam has already been submitted.']);
            }

            $exam = Exam::findOrFail($submission->exam_id);
            if (app(ExamAttemptLifecycle::class)->isPastGrace($submission, $exam)) {
                throw ValidationException::withMessages(['submission' => 'The answer was not saved because this exam has ended.']);
            }

            $question = Question::with('options')->where('exam_id', $exam->id)->find($questionId);
            if (! $question) {
                throw ValidationException::withMessages(['answer' => 'That answer does not belong to this exam question.']);
            }
            app(ExamAttemptLifecycle::class)->replaceSelections(
                $submission,
                $question,
                is_array($optionId) ? $optionId : ($optionId === null ? [] : [$optionId]),
                $flagged,
            );
        });
    }

    private function assertQuestionsMutable(Exam $exam): void
    {
        if ($exam->status !== 'Draft') {
            throw ValidationException::withMessages(['question' => 'Only draft exam questions can be changed.']);
        }
        if ($exam->submissions()->exists()) {
            throw ValidationException::withMessages(['question' => 'Questions, options and marks cannot be changed after an attempt has started.']);
        }
    }

    public function validateQuestionInput(string $text, array $options, int|array $correctIndexes, ?array $optionMarks, float $legacyMarks): array
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > 1000) {
            throw ValidationException::withMessages(['text' => 'Question text is required and may not exceed 1,000 characters.']);
        }
        if (count($options) < 2 || count($options) > 6) {
            throw ValidationException::withMessages(['options' => 'A question needs between two and six options.']);
        }
        $options = array_map(fn ($option): string => trim((string) $option), array_values($options));
        if (collect($options)->contains(fn (string $option): bool => $option === '' || mb_strlen($option) > 300)) {
            throw ValidationException::withMessages(['options' => 'Each option is required and may not exceed 300 characters.']);
        }

        return ['text' => $text, 'options' => $this->questionDefinitions($options, $correctIndexes, $optionMarks, $legacyMarks)];
    }

    private function questionDefinitions(array $options, int|array $correctIndexes, ?array $optionMarks, float $legacyMarks): array
    {
        $options = array_values($options);
        $correctIndexes = collect(is_array($correctIndexes) ? $correctIndexes : [$correctIndexes])
            ->map(fn ($index): int => (int) $index)->unique()->values();
        if (count($options) < 2 || $correctIndexes->isEmpty() || $correctIndexes->count() >= count($options)
            || $correctIndexes->contains(fn (int $index): bool => $index < 0 || $index >= count($options))) {
            throw ValidationException::withMessages(['question' => 'A question needs at least two options, at least one correct answer and at least one wrong answer.']);
        }

        return collect($options)->map(function ($text, int $index) use ($correctIndexes, $optionMarks, $legacyMarks): array {
            $correct = $correctIndexes->contains($index);
            $marks = $correct ? ($optionMarks[$index] ?? $legacyMarks) : 0;
            if ($correct && (! is_numeric($marks) || (float) $marks < 1 || (float) $marks > 65535 || floor((float) $marks) !== (float) $marks)) {
                throw ValidationException::withMessages(['option_marks' => 'Each correct option needs a whole-number mark of at least 1.']);
            }

            return ['text' => trim((string) $text), 'is_correct' => $correct, 'marks' => $correct ? (int) $marks : 0];
        })->all();
    }

    private function validQuestion(Question $question): bool
    {
        $correct = $question->options->where('is_correct', true);

        return $question->options->count() >= 2
            && $correct->isNotEmpty()
            && $correct->count() < $question->options->count()
            && $correct->every(fn (QuestionOption $option): bool => $option->marks >= 1);
    }

    public function remainingSeconds(ExamSubmission $submission): int
    {
        return app(ExamAttemptLifecycle::class)->remainingSeconds($submission);
    }

    public function release(Exam $exam, User $actor): void
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        app(CbtAccessScope::class)->managedExam($actor, $exam->id);
        if (! in_array($exam->status, ['Published', 'Closed'], true) || $exam->result_release_type !== 'Manual') {
            throw ValidationException::withMessages(['exam' => 'Only a published or closed exam configured for manual release can be released.']);
        }
        $exam->update(['is_released' => true]);
        $this->syncEntranceOutcomes($exam);
    }

    public function isReleased(Exam $exam): bool
    {
        return match ($exam->result_release_type) {
            'Immediate' => true,
            'Scheduled' => $exam->result_release_at !== null && now()->gte($exam->result_release_at),
            'Manual' => $exam->is_released,
            default => false,
        };
    }

    public function close(Exam $exam, User $actor): int
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        app(CbtAccessScope::class)->managedExam($actor, $exam->id);
        if ($exam->status !== 'Published') {
            throw ValidationException::withMessages(['exam' => 'Only a published exam can be closed.']);
        }

        return DB::transaction(function () use ($exam, $actor): int {
            $locked = Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            $locked->update(['status' => 'Closed']);
            $submitted = app(ExamAttemptLifecycle::class)->submitExpiredForExam($locked, true);
            app(AuditRecorder::class)->record(
                $actor,
                'cbt_exam_ended',
                $locked,
                ['submitted_attempts' => $submitted],
                'CBT',
                "Exam ended by staff; {$submitted} active attempt(s) submitted.",
            );

            return $submitted;
        });
    }

    public function delete(Exam $exam, User $actor): void
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        app(CbtAccessScope::class)->managedExam($actor, $exam->id);
        if ($exam->status !== 'Draft') {
            throw ValidationException::withMessages(['exam' => 'Only a draft exam can be deleted. Close published exams instead.']);
        }
        $attempts = $exam->submissions()->count();
        if ($attempts > 0) {
            throw ValidationException::withMessages(['exam' => "This draft has {$attempts} attempt".($attempts === 1 ? '' : 's').' and cannot be deleted.']);
        }
        $exam->delete();
    }

    public function resultFor(ExamSubmission $submission, User $actor): array
    {
        $this->assertOwner($submission, $actor);
        app(ExamAttemptLifecycle::class)->submitExpiredAttempt($submission->load('exam'));
        $submission->refresh();
        $exam = Exam::findOrFail($submission->exam_id);
        if ($submission->status !== 'Submitted' || ! $this->isReleased($exam)) {
            return [
                'waiting' => true,
                'releaseAt' => $exam->result_release_type === 'Scheduled' ? $exam->result_release_at : null,
                'exam' => null, 'score' => null, 'maximum' => null, 'answers' => [],
            ];
        }
        if ($submission->candidate_id) {
            $this->recordEntranceOutcome($exam, $submission);
        }
        $data = ['waiting' => false, 'exam' => $exam, 'submission' => $submission, 'score' => $exam->show_score ? $submission->raw_score : null, 'maximum' => $exam->show_score ? $submission->max_score : null, 'answers' => []];
        if ($exam->show_correct_answers || $exam->show_explanations) {
            $questions = Question::with('options')->where('exam_id', $exam->id)->get();
            $saved = ExamAnswer::with('selections')->where('exam_submission_id', $submission->id)->get()->keyBy('question_id');
            foreach ($questions as $question) {
                $review = app(CbtAnswerReview::class)->forQuestion($question, $saved->get($question->id), $exam->show_correct_answers);
                $review['explanation'] = $exam->show_explanations ? $question->explanation : null;
                $data['answers'][] = $review;
            }
        }

        return $data;
    }

    public function syncEntranceOutcomes(Exam $exam): void
    {
        if (! $this->isReleased($exam) || $exam->pass_mark === null) {
            return;
        }
        foreach (ExamSubmission::where('exam_id', $exam->id)->whereNotNull('candidate_id')->where('status', 'Submitted')->cursor() as $submission) {
            $this->recordEntranceOutcome($exam, $submission);
        }
    }

    public function assertOwner(ExamSubmission $submission, User $actor): void
    {
        $candidate = app(AdmissionPipeline::class)->candidateForUser($actor);
        $student = app(StudentDirectory::class)->forActiveUser($actor);
        abort_unless(($submission->candidate_id && $candidate?->id === $submission->candidate_id)
            || ($submission->student_id && $student?->id === $submission->student_id), 403);
    }

    public function availableFor(User $actor)
    {
        $candidate = app(AdmissionPipeline::class)->candidateForUser($actor);
        $student = app(StudentDirectory::class)->forActiveUser($actor);
        if ($student && $candidate?->status === 'Converted to Student') {
            $classId = app(ResultAcademicCatalog::class)->placement($student->class_arm_id)['class_id'];

            $page = $this->studentExamQuery($student->id, $classId)->orderBy('scheduled_at')->paginate(20);
            app(ExamAttemptLifecycle::class)->submitExpiredForExams($page->getCollection());

            return $page;
        }
        if ($candidate) {
            $query = $this->participantQuery(['candidate_id' => $candidate->id])->whereNotNull('pass_mark')->where('status', 'Published');
            if ($candidate->application) {
                $application = $candidate->application()->with('examSession')->firstOrFail();
                $sessionDate = $application->examSession?->starts_at?->timezone('Africa/Lagos')->toDateString();
                if (! $sessionDate || $sessionDate !== now('Africa/Lagos')->toDateString()) {
                    return Exam::whereRaw('1 = 0')->paginate(20);
                }
                $query->where(function (Builder $scope) use ($candidate): void {
                    $scope->whereDoesntHave('levels')->orWhereHas('levels', fn (Builder $levels) => $levels->whereKey($candidate->applied_level_id));
                })->where(fn (Builder $window) => $window->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
                    ->where(fn (Builder $window) => $window->whereNull('closes_at')->orWhere('closes_at', '>=', now()));
            }
            if (! in_array($candidate->status, ['Payment Confirmed', 'Exam Taken'], true)) {
                $query->whereIn('id', ExamSubmission::where('candidate_id', $candidate->id)->select('exam_id'));
            }

            $page = $query->orderBy('scheduled_at')->paginate(20);
            app(ExamAttemptLifecycle::class)->submitExpiredForExams($page->getCollection());

            return $page;
        }
        if ($student) {
            $classId = app(ResultAcademicCatalog::class)->placement($student->class_arm_id)['class_id'];

            $page = $this->studentExamQuery($student->id, $classId)->orderBy('scheduled_at')->paginate(20);
            app(ExamAttemptLifecycle::class)->submitExpiredForExams($page->getCollection());

            return $page;
        }

        return Exam::whereRaw('1 = 0')->paginate(20);
    }

    public function dashboardFor(User $actor)
    {
        $candidate = app(AdmissionPipeline::class)->candidateForUser($actor);
        $student = app(StudentDirectory::class)->forActiveUser($actor);
        if (! $student) {
            return collect();
        }
        $classId = app(ResultAcademicCatalog::class)->placement($student->class_arm_id)['class_id'];

        $exams = $this->studentExamQuery($student->id, $classId)
            ->orderByRaw('CASE WHEN scheduled_at IS NULL THEN 0 ELSE 1 END')->orderBy('scheduled_at')->limit(4)->get();
        app(ExamAttemptLifecycle::class)->submitExpiredForExams($exams);

        return $exams;
    }

    public function displayStatus(Exam $exam): string
    {
        $submission = $exam->submissions->firstWhere('status', 'In Progress');
        if ($submission?->status === 'In Progress') {
            return 'Resume';
        }
        if ($exam->relationLoaded('resitGrants') && $exam->resitGrants->isNotEmpty()) {
            return 'Resit';
        }
        $submission = $exam->submissions->firstWhere('status', 'Submitted');
        if ($submission) {
            return $this->isReleased($exam) ? 'Finished' : 'Waiting for results';
        }
        if (app(ExamClosing::class)->countsAsClosed($exam)) {
            return 'Not taken';
        }
        if ($exam->scheduled_at && now()->lt($exam->scheduled_at)) {
            return 'Coming up';
        }

        return 'Open now';
    }

    private function identity(Exam $exam, User $actor): array
    {
        if ($exam->pass_mark !== null) {
            $candidate = app(AdmissionPipeline::class)->candidateForUser($actor);
            abort_unless($candidate, 403);
            $this->assertCandidateEntranceScope($exam, $candidate);

            return ['candidate_id' => $candidate->id];
        }
        $student = app(StudentDirectory::class)->forActiveUser($actor);
        abort_unless($student, 403);
        $classId = app(ResultAcademicCatalog::class)->placement($student->class_arm_id)['class_id'];
        abort_unless($classId === $exam->class_id, 403);

        return ['student_id' => $student->id];
    }

    private function syncLevels(Exam $exam, array $levelIds): void
    {
        $ids = collect($levelIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        if (AcademicLevel::whereIn('id', $ids)->count() !== $ids->count()) {
            throw ValidationException::withMessages(['level_ids' => 'Choose Levels from this school.']);
        }
        $schoolId = app(TenantContext::class)->id();
        $exam->levels()->sync($ids->mapWithKeys(fn (int $id): array => [$id => ['school_id' => $schoolId]])->all());
    }

    private function assertCandidateEntranceScope(Exam $exam, Candidate $candidate): void
    {
        if ($exam->levels()->exists() && ! $exam->levels()->whereKey($candidate->applied_level_id)->exists()) {
            abort(403);
        }
        $application = $candidate->application()->with('examSession')->first();
        if (! $application) {
            return;
        }
        abort_unless($application->examSession?->starts_at?->timezone('Africa/Lagos')->isSameDay(now('Africa/Lagos')), 403);
        if (($exam->scheduled_at && now()->lt($exam->scheduled_at)) || ($exam->closes_at && now()->gt($exam->closes_at))) {
            throw ValidationException::withMessages(['exam' => 'This exam is not open now.']);
        }
    }

    private function participantQuery(array $identity)
    {
        $relations = [
            'subject',
            'levels',
            'submissions' => fn ($query) => $query->where($identity)->orderByDesc('attempt_number'),
        ];
        if (isset($identity['student_id'])) {
            $relations['resitGrants'] = fn ($query) => $query->where('student_id', $identity['student_id'])
                ->whereNull('used_attempt_id')->whereNull('withdrawn_at')
                ->where('opens_until', '>', now())->latest('id');
        }

        return Exam::with($relations)->withCount('questions');
    }

    private function studentExamQuery(int $studentId, int $classId)
    {
        return $this->participantQuery(['student_id' => $studentId])
            ->where('class_id', $classId)->whereNull('pass_mark')
            ->where(function ($query) use ($studentId): void {
                $query->where('status', 'Published')
                    ->orWhereHas('resitGrants', fn ($grants) => $grants->where('student_id', $studentId)
                        ->whereNull('used_attempt_id')->whereNull('withdrawn_at')->where('opens_until', '>', now()))
                    ->orWhereHas('submissions', fn ($attempts) => $attempts->where('student_id', $studentId));
            });
    }

    private function recordEntranceOutcome(Exam $exam, ExamSubmission $submission): void
    {
        if (! $submission->candidate_id || $exam->pass_mark === null) {
            return;
        }
        $percent = round((float) $submission->raw_score / (float) $submission->max_score * 100, 2);
        app(AdmissionPipeline::class)->recordVerifiedCbtOutcome($submission->candidate_id, $percent >= (float) $exam->pass_mark, $exam->id, $percent);
    }

    public function hasPublishedEntranceExam(): bool
    {
        return Exam::whereNotNull('pass_mark')->where('status', 'Published')->exists();
    }
}
