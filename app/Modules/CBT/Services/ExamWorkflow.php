<?php

namespace App\Modules\CBT\Services;

use App\Modules\Academics\Services\ResultAcademicCatalog;
use App\Modules\Academics\Services\ResultTeachingAssignments;
use App\Modules\Admissions\Services\AdmissionPipeline;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\CBT\Models\Exam;
use App\Modules\CBT\Models\ExamAnswer;
use App\Modules\CBT\Models\ExamSubmission;
use App\Modules\CBT\Models\ExamType;
use App\Modules\CBT\Models\Question;
use App\Modules\CBT\Models\QuestionOption;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\SchoolUserDirectory;
use App\Modules\Results\Services\ResultAssessmentCatalog;
use App\Modules\Results\Services\ResultWorkflow;
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
        if (! in_array($name, ['Internal', 'Entrance', 'Mock', 'Practice', 'Assessment', 'Promotional'], true)) {
            throw ValidationException::withMessages(['name' => 'Choose a supported exam type.']);
        }

        return ExamType::firstOrCreate(['name' => trim($name)]);
    }

    public function createExam(array $data, User $actor): Exam
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        $type = ExamType::findOrFail((int) $data['exam_type_id']);
        $entrance = $type->name === 'Entrance';
        if ($entrance) {
            if (($data['pass_mark'] ?? null) === null) {
                throw ValidationException::withMessages(['pass_mark' => 'Set an entrance pass mark.']);
            }
        } else {
            if (! isset($data['class_id'], $data['subject_id'])) {
                throw ValidationException::withMessages(['class_id' => 'Choose a class and subject for a student exam.']);
            }
            app(ResultAcademicCatalog::class)->assertExamSubject((int) $data['class_id'], (int) $data['subject_id']);
            if (isset($data['term_id'])) {
                app(ResultAcademicCatalog::class)->assertTerm((int) $data['term_id']);
            }
            if (isset($data['assessment_component_id'])) {
                Gate::forUser($actor)->authorize('enter_results');
                app(ResultTeachingAssignments::class)->assertMayEnter($actor, (int) $data['class_id'], (int) $data['subject_id']);
                if (! isset($data['term_id'])) {
                    throw ValidationException::withMessages(['term_id' => 'Choose a term before linking an exam to Results.']);
                }
                app(ResultAssessmentCatalog::class)->maxScore((int) $data['assessment_component_id']);
            }
        }
        if (($data['result_release_type'] ?? '') === 'Scheduled' && empty($data['result_release_at'])) {
            throw ValidationException::withMessages(['result_release_at' => 'Choose a scheduled release time.']);
        }

        return Exam::create([
            'exam_type_id' => $type->id,
            'class_id' => $entrance ? null : $data['class_id'],
            'subject_id' => $entrance ? null : $data['subject_id'],
            'term_id' => $entrance ? null : ($data['term_id'] ?? null),
            'assessment_component_id' => $entrance ? null : ($data['assessment_component_id'] ?? null),
            'created_by' => $actor->id,
            'title' => $data['title'], 'duration' => $data['duration'],
            'status' => 'Draft',
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
            'pass_mark' => $entrance ? $data['pass_mark'] : null,
            'result_release_type' => $data['result_release_type'],
            'result_release_at' => $data['result_release_at'] ?? null,
            'show_score' => (bool) ($data['show_score'] ?? false),
            'show_correct_answers' => (bool) ($data['show_correct_answers'] ?? false),
            'show_explanations' => (bool) ($data['show_explanations'] ?? false),
            'requires_approval' => (bool) ($data['requires_approval'] ?? false),
        ]);
    }

    public function addQuestion(Exam $exam, string $text, float $marks, ?string $explanation, array $options, int $correctIndex, User $actor): Question
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        if ($exam->status !== 'Draft' || count($options) < 2 || $correctIndex < 0 || $correctIndex >= count($options) || $marks <= 0) {
            throw ValidationException::withMessages(['question' => 'Draft questions need positive marks, at least two options, and one correct answer.']);
        }

        return DB::transaction(function () use ($exam, $text, $marks, $explanation, $options, $correctIndex): Question {
            $question = Question::create([
                'exam_id' => $exam->id, 'text' => trim($text), 'marks' => $marks,
                'explanation' => $explanation, 'position' => Question::where('exam_id', $exam->id)->max('position') + 1,
            ]);
            foreach (array_values($options) as $index => $option) {
                QuestionOption::create(['question_id' => $question->id, 'text' => trim($option), 'is_correct' => $index === $correctIndex]);
            }

            return $question;
        });
    }

    public function publish(Exam $exam, User $actor): void
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        if ($exam->status !== 'Draft') {
            throw ValidationException::withMessages(['exam' => 'Only a draft exam can be published.']);
        }
        $questions = Question::with('options')->where('exam_id', $exam->id)->get();
        if ($questions->isEmpty() || $questions->contains(fn (Question $q) => $q->options->count() < 2 || $q->options->where('is_correct', true)->count() !== 1)) {
            throw ValidationException::withMessages(['questions' => 'Every published question needs at least two options and exactly one correct answer.']);
        }
        $exam->update(['status' => 'Published']);
    }

    public function start(Exam $exam, User $actor): ExamSubmission
    {
        if ($exam->status !== 'Published' || ($exam->scheduled_at && now()->lt($exam->scheduled_at))) {
            throw ValidationException::withMessages(['exam' => 'This exam is not open yet.']);
        }
        if ($exam->closes_at && now()->gte($exam->closes_at)) {
            throw ValidationException::withMessages(['exam' => 'This exam has closed.']);
        }
        $identity = $this->identity($exam, $actor);
        $candidate = isset($identity['candidate_id']) ? app(AdmissionPipeline::class)->candidateForUser($actor) : null;

        return DB::transaction(function () use ($exam, $identity, $candidate): ExamSubmission {
            $submission = ExamSubmission::where('exam_id', $exam->id)->where($identity)->lockForUpdate()->first();
            if ($submission) {
                return $submission;
            }
            if ($candidate) {
                app(AdmissionPipeline::class)->assertCandidateMaySit($candidate);
            }
            $submission = ExamSubmission::create(['exam_id' => $exam->id, ...$identity, 'started_at' => now(), 'status' => 'In Progress']);
            if (isset($identity['candidate_id'])) {
                app(AdmissionPipeline::class)->recordCbtStart($identity['candidate_id']);
            }

            return $submission;
        });
    }

    public function submit(ExamSubmission $submission, array $answers, User $actor): ExamSubmission
    {
        $this->assertOwner($submission, $actor);

        return DB::transaction(function () use ($submission, $answers): ExamSubmission {
            $submission = ExamSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            if ($submission->status !== 'In Progress') {
                throw ValidationException::withMessages(['submission' => 'This exam has already been submitted.']);
            }
            $exam = Exam::findOrFail($submission->exam_id);
            $questions = Question::with('options')->where('exam_id', $exam->id)->get();
            $expired = now()->greaterThan($submission->started_at->copy()->addMinutes($exam->duration)->addSeconds(30));
            if (! $expired) {
                foreach ($answers as $questionId => $optionId) {
                    $question = $questions->firstWhere('id', (int) $questionId);
                    if (! $question || ! $question->options->contains('id', (int) $optionId)) {
                        throw ValidationException::withMessages(['answers' => 'An answer does not belong to this exam question.']);
                    }
                    ExamAnswer::updateOrCreate(
                        ['exam_submission_id' => $submission->id, 'question_id' => $question->id],
                        ['selected_option_id' => (int) $optionId],
                    );
                }
            }
            $saved = ExamAnswer::where('exam_submission_id', $submission->id)->pluck('selected_option_id', 'question_id');
            $maximum = round((float) $questions->sum('marks'), 2);
            $raw = 0.0;
            foreach ($questions as $question) {
                $correct = $question->options->firstWhere('is_correct', true);
                if ($correct && (int) ($saved[$question->id] ?? 0) === $correct->id) {
                    $raw += (float) $question->marks;
                }
            }
            $converted = null;
            if ($exam->assessment_component_id && $submission->student_id) {
                $target = app(ResultAssessmentCatalog::class)->maxScore($exam->assessment_component_id);
                $converted = self::convertScore($raw, $maximum, $target);
                $creator = app(SchoolUserDirectory::class)->find($exam->created_by);
                abort_unless($creator, 422, 'The exam creator is unavailable.');
                app(ResultWorkflow::class)->recordComponentScore($submission->student_id, $exam->subject_id, $exam->term_id, $exam->assessment_component_id, $converted, $creator);
            }
            $submission->update(['status' => 'Submitted', 'submitted_at' => now(), 'raw_score' => $raw, 'max_score' => $maximum, 'converted_score' => $converted]);
            if ($submission->candidate_id && $this->isReleased($exam)) {
                $this->recordEntranceOutcome($exam, $submission);
            }

            return $submission;
        });
    }

    public function saveAnswer(ExamSubmission $submission, int $questionId, ?int $optionId, bool $flagged, User $actor): void
    {
        $this->assertOwner($submission, $actor);

        DB::transaction(function () use ($submission, $questionId, $optionId, $flagged): void {
            $submission = ExamSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            if ($submission->status !== 'In Progress') {
                throw ValidationException::withMessages(['submission' => 'This exam has already been submitted.']);
            }

            $exam = Exam::findOrFail($submission->exam_id);
            if ($this->isPastGraceWindow($submission, $exam)) {
                throw ValidationException::withMessages(['submission' => 'The answer was not saved because this exam has ended.']);
            }

            $question = Question::with('options')->where('exam_id', $exam->id)->find($questionId);
            if (! $question || ($optionId !== null && ! $question->options->contains('id', $optionId))) {
                throw ValidationException::withMessages(['answer' => 'That answer does not belong to this exam question.']);
            }

            ExamAnswer::updateOrCreate(
                ['exam_submission_id' => $submission->id, 'question_id' => $question->id],
                ['selected_option_id' => $optionId, 'flagged' => $flagged],
            );
        });
    }

    public function remainingSeconds(ExamSubmission $submission): int
    {
        $deadline = $submission->started_at->copy()->addMinutes($submission->exam->duration);

        return max(0, now()->diffInSeconds($deadline, false));
    }

    public function approveRelease(Exam $exam, User $actor): void
    {
        Gate::forUser($actor)->authorize('approve_cbt_results');
        if ($exam->status !== 'Published') {
            throw ValidationException::withMessages(['exam' => 'Publish the exam before approving release.']);
        }
        $exam->update(['approved_for_release_at' => now()]);
        $this->syncEntranceOutcomes($exam);
    }

    public function release(Exam $exam, User $actor): void
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        if ($exam->status !== 'Published' || $exam->result_release_type !== 'Manual') {
            throw ValidationException::withMessages(['exam' => 'This exam is not configured for manual release.']);
        }
        $exam->update(['is_released' => true]);
        $this->syncEntranceOutcomes($exam);
    }

    public function isReleased(Exam $exam): bool
    {
        if ($exam->requires_approval && ! $exam->approved_for_release_at) {
            return false;
        }

        return match ($exam->result_release_type) {
            'Immediate' => true,
            'Scheduled' => $exam->result_release_at !== null && now()->gte($exam->result_release_at),
            'Manual' => $exam->is_released,
            default => false,
        };
    }

    public function resultFor(ExamSubmission $submission, User $actor): array
    {
        $this->assertOwner($submission, $actor);
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
            $selectedIds = ExamAnswer::where('exam_submission_id', $submission->id)->pluck('selected_option_id', 'question_id');
            foreach ($questions as $question) {
                $selected = $exam->show_correct_answers ? $question->options->firstWhere('id', $selectedIds[$question->id] ?? null) : null;
                $data['answers'][] = [
                    'question' => $question->text,
                    'position' => $question->position,
                    'selected' => $selected?->text,
                    'was_correct' => $selected ? (bool) $selected->is_correct : null,
                    'correct' => $exam->show_correct_answers ? $question->options->firstWhere('is_correct', true)?->text : null,
                    'explanation' => $exam->show_explanations ? $question->explanation : null,
                ];
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
        $student = app(StudentDirectory::class)->forUser($actor);
        abort_unless(($submission->candidate_id && $candidate?->id === $submission->candidate_id)
            || ($submission->student_id && $student?->id === $submission->student_id), 403);
    }

    public function availableFor(User $actor)
    {
        $candidate = app(AdmissionPipeline::class)->candidateForUser($actor);
        $student = app(StudentDirectory::class)->forUser($actor);
        if ($student && $candidate?->status === 'Converted to Student') {
            $classId = app(ResultAcademicCatalog::class)->placement($student->class_arm_id)['class_id'];

            return $this->participantQuery(['student_id' => $student->id])
                ->where('class_id', $classId)->whereNull('pass_mark')->where('status', 'Published')->orderBy('scheduled_at')->paginate(20);
        }
        if ($candidate) {
            $query = $this->participantQuery(['candidate_id' => $candidate->id])->whereNotNull('pass_mark')->where('status', 'Published');
            if (! in_array($candidate->status, ['Payment Confirmed', 'Exam Taken'], true)) {
                $query->whereIn('id', ExamSubmission::where('candidate_id', $candidate->id)->select('exam_id'));
            }

            return $query->orderBy('scheduled_at')->paginate(20);
        }
        if ($student) {
            $classId = app(ResultAcademicCatalog::class)->placement($student->class_arm_id)['class_id'];

            return $this->participantQuery(['student_id' => $student->id])
                ->where('class_id', $classId)->whereNull('pass_mark')->where('status', 'Published')->orderBy('scheduled_at')->paginate(20);
        }

        return Exam::whereRaw('1 = 0')->paginate(20);
    }

    public function dashboardFor(User $actor)
    {
        $candidate = app(AdmissionPipeline::class)->candidateForUser($actor);
        $student = app(StudentDirectory::class)->forUser($actor);
        if (! $student) {
            return collect();
        }
        $classId = app(ResultAcademicCatalog::class)->placement($student->class_arm_id)['class_id'];

        return $this->participantQuery(['student_id' => $student->id])
            ->where('class_id', $classId)->whereNull('pass_mark')->where('status', 'Published')
            ->withCount('questions')
            ->orderByRaw('CASE WHEN scheduled_at IS NULL THEN 0 ELSE 1 END')->orderBy('scheduled_at')->limit(4)->get();
    }

    public function displayStatus(Exam $exam): string
    {
        $submission = $exam->submissions->first();
        if ($submission?->status === 'In Progress') {
            return 'Resume';
        }
        if ($submission?->status === 'Submitted') {
            return $this->isReleased($exam) ? 'Finished' : 'Waiting for results';
        }
        if ($exam->closes_at && now()->gte($exam->closes_at)) {
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

            return ['candidate_id' => $candidate->id];
        }
        $student = app(StudentDirectory::class)->forUser($actor);
        abort_unless($student, 403);
        $classId = app(ResultAcademicCatalog::class)->placement($student->class_arm_id)['class_id'];
        abort_unless($classId === $exam->class_id, 403);

        return ['student_id' => $student->id];
    }

    private function participantQuery(array $identity)
    {
        return Exam::with(['subject', 'submissions' => fn ($query) => $query->where($identity)])->withCount('questions');
    }

    private function isPastGraceWindow(ExamSubmission $submission, Exam $exam): bool
    {
        return now()->greaterThan($submission->started_at->copy()->addMinutes($exam->duration)->addSeconds(30));
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
