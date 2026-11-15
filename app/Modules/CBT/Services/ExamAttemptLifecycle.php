<?php

namespace App\Modules\CBT\Services;

use App\Modules\Admissions\Services\AdmissionPipeline;
use App\Modules\CBT\Models\Exam;
use App\Modules\CBT\Models\ExamAnswer;
use App\Modules\CBT\Models\ExamAnswerSelection;
use App\Modules\CBT\Models\ExamSubmission;
use App\Modules\CBT\Models\Question;
use App\Modules\Results\Services\ResultAssessmentCatalog;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamAttemptLifecycle
{
    public const GRACE_SECONDS = 30;

    public function effectiveEnd(ExamSubmission $submission, ?Exam $exam = null): CarbonInterface
    {
        $exam ??= $submission->relationLoaded('exam') ? $submission->exam : Exam::findOrFail($submission->exam_id);
        $personalEnd = $submission->started_at->copy()->addMinutes($exam->duration);
        $grant = $submission->relationLoaded('resitGrant')
            ? $submission->resitGrant
            : $submission->resitGrant()->first();

        if ($grant) {
            return $grant->opens_until->lt($personalEnd) ? $grant->opens_until->copy() : $personalEnd;
        }

        if ($exam->closes_automatically && $exam->closes_at && $exam->closes_at->lt($personalEnd)) {
            return $exam->closes_at->copy();
        }

        return $personalEnd;
    }

    public function remainingSeconds(ExamSubmission $submission): int
    {
        return max(0, now()->diffInSeconds($this->effectiveEnd($submission), false));
    }

    public function isPastGrace(ExamSubmission $submission, ?Exam $exam = null): bool
    {
        return now()->greaterThan($this->effectiveEnd($submission, $exam)->addSeconds(self::GRACE_SECONDS));
    }

    public function submitExpiredAttempt(ExamSubmission $submission): bool
    {
        if ($submission->status !== 'In Progress' || now()->lt($this->effectiveEnd($submission))) {
            return false;
        }

        return $this->submitSaved($submission);
    }

    public function submitExpiredForExam(Exam $exam, bool $force = false): int
    {
        $count = 0;
        foreach (ExamSubmission::where('exam_id', $exam->id)->where('status', 'In Progress')->pluck('id') as $id) {
            $submission = ExamSubmission::with(['exam', 'resitGrant'])->find($id);
            $forceThisAttempt = $force && ! $submission?->resitGrant;
            if ($submission && ($forceThisAttempt || now()->gte($this->effectiveEnd($submission, $exam)))) {
                $count += $this->submitSaved($submission) ? 1 : 0;
            }
        }

        return $count;
    }

    public function submitExpiredForExams(iterable $exams): int
    {
        $records = collect($exams)->keyBy('id');
        if ($records->isEmpty()) {
            return 0;
        }

        $count = 0;
        $submissions = ExamSubmission::with(['exam', 'resitGrant'])->whereIn('exam_id', $records->keys())
            ->where('status', 'In Progress')->get();
        foreach ($submissions as $submission) {
            $exam = $submission->exam;
            $force = $exam->status === 'Closed'
                || ($exam->status === 'Published' && $exam->closes_automatically && $exam->closes_at && now()->gte($exam->closes_at));
            if (($force && ! $submission->resitGrant) || now()->gte($this->effectiveEnd($submission, $exam))) {
                $changed = $this->submitSaved($submission);
                $count += $changed ? 1 : 0;
                $loadedExam = $records->get($submission->exam_id);
                if ($changed && $loadedExam?->relationLoaded('submissions')) {
                    $loaded = $loadedExam->submissions->firstWhere('id', $submission->id);
                    $loaded?->setRawAttributes($submission->fresh()->getAttributes(), true);
                }
            }
        }

        return $count;
    }

    public function submit(ExamSubmission $submission, array $answers): ExamSubmission
    {
        return DB::transaction(function () use ($submission, $answers): ExamSubmission {
            $locked = ExamSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'In Progress') {
                throw ValidationException::withMessages(['submission' => 'This exam has already been submitted.']);
            }
            $exam = Exam::findOrFail($locked->exam_id);
            $questions = Question::with('options')->where('exam_id', $exam->id)->get();
            if (! $this->isPastGrace($locked, $exam)) {
                foreach ($answers as $questionId => $optionIds) {
                    $question = $questions->firstWhere('id', (int) $questionId);
                    if (! $question) {
                        throw ValidationException::withMessages(['answers' => 'An answer does not belong to this exam question.']);
                    }
                    $this->replaceSelections($locked, $question, is_array($optionIds) ? $optionIds : [$optionIds]);
                }
            }

            return $this->scoreLocked($locked, $exam, $questions);
        });
    }

    private function submitSaved(ExamSubmission $submission): bool
    {
        return DB::transaction(function () use ($submission): bool {
            $locked = ExamSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'In Progress') {
                return false;
            }
            $exam = Exam::findOrFail($locked->exam_id);
            $questions = Question::with('options')->where('exam_id', $exam->id)->get();
            $this->scoreLocked($locked, $exam, $questions);

            return true;
        });
    }

    private function scoreLocked(ExamSubmission $submission, Exam $exam, $questions): ExamSubmission
    {
        $saved = ExamAnswer::with('selections')->where('exam_submission_id', $submission->id)->get()->keyBy('question_id');
        $maximum = (float) $questions->sum(fn (Question $question): int => $question->totalMarks());
        $raw = 0.0;
        foreach ($questions as $question) {
            $selectedIds = collect($saved->get($question->id)?->selectedOptionIds() ?? []);
            $raw += (float) $question->options
                ->where('is_correct', true)
                ->whereIn('id', $selectedIds)
                ->sum('marks');
        }
        $converted = null;
        if ($exam->assessment_component_id && $submission->student_id && $exam->type()->where('feeds_results', true)->exists()) {
            $converted = ExamWorkflow::convertScore(
                $raw,
                $maximum,
                app(ResultAssessmentCatalog::class)->maxScore($exam->assessment_component_id),
            );
        }
        $submission->update([
            'status' => 'Submitted', 'submitted_at' => now(), 'raw_score' => $raw,
            'max_score' => $maximum, 'converted_score' => $converted,
        ]);
        if ($submission->candidate_id && app(ExamWorkflow::class)->isReleased($exam) && $exam->pass_mark !== null && $maximum > 0) {
            $percent = round($raw / $maximum * 100, 2);
            app(AdmissionPipeline::class)->recordVerifiedCbtOutcome(
                $submission->candidate_id,
                $percent >= (float) $exam->pass_mark,
                $exam->id,
                $percent,
            );
        }

        return $submission->refresh();
    }

    public function replaceSelections(ExamSubmission $submission, Question $question, array $optionIds, ?bool $flagged = null): ExamAnswer
    {
        $optionIds = collect($optionIds)->filter(fn ($id): bool => $id !== null && $id !== '')
            ->map(fn ($id): int => (int) $id)->unique()->values();
        $validIds = $question->options->pluck('id')->map(fn ($id): int => (int) $id);
        if ($optionIds->diff($validIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['answer' => 'That answer does not belong to this exam question.']);
        }
        $limit = $question->answerLimit();
        if ($optionIds->count() > $limit) {
            throw ValidationException::withMessages(['answers' => "Choose no more than {$limit} answer".($limit === 1 ? '' : 's').' for this question.']);
        }

        $values = ['selected_option_id' => $optionIds->first()];
        if ($flagged !== null) {
            $values['flagged'] = $flagged;
        }
        $answer = ExamAnswer::updateOrCreate(
            ['exam_submission_id' => $submission->id, 'question_id' => $question->id],
            $values,
        );
        $answer->selections()->delete();
        foreach ($optionIds as $optionId) {
            ExamAnswerSelection::create(['exam_answer_id' => $answer->id, 'question_option_id' => $optionId]);
        }

        return $answer->refresh()->load('selections');
    }
}
