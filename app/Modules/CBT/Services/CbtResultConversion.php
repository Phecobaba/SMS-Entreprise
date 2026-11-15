<?php

namespace App\Modules\CBT\Services;

use App\Modules\Admissions\Models\Student;
use App\Modules\CBT\Models\Exam;
use App\Modules\CBT\Models\ExamSubmission;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Services\ResultAssessmentCatalog;
use App\Modules\Results\Services\ResultWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CbtResultConversion
{
    public function preview(Exam $exam, User $actor): array
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        app(CbtAccessScope::class)->managedExam($actor, $exam->id);
        app(ExamAttemptLifecycle::class)->submitExpiredForExam($exam, app(ExamClosing::class)->countsAsClosed($exam));
        $this->assertConvertible($exam);

        return $this->plan($exam);
    }

    public function apply(Exam $exam, User $actor): array
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        app(CbtAccessScope::class)->managedExam($actor, $exam->id);
        app(ExamAttemptLifecycle::class)->submitExpiredForExam($exam, app(ExamClosing::class)->countsAsClosed($exam));
        $this->assertConvertible($exam);

        return DB::transaction(function () use ($exam, $actor): array {
            $exam = Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            $summary = $this->plan($exam);
            $previous = [];

            foreach ($summary['writes'] as $write) {
                $result = Result::where('student_id', $write['student_id'])
                    ->where('subject_id', $exam->subject_id)
                    ->where('term_id', $exam->term_id)
                    ->with('items')->lockForUpdate()->first();
                if ($result && $result->status !== 'Draft') {
                    throw ValidationException::withMessages(['conversion' => 'Results changed after the preview. Review the conversion again.']);
                }
                $old = $result?->items->firstWhere('assessment_component_id', $exam->assessment_component_id)?->score;
                $saved = app(ResultWorkflow::class)->recordComponentScore(
                    $write['student_id'],
                    $exam->subject_id,
                    $exam->term_id,
                    $exam->assessment_component_id,
                    $write['score'],
                    $actor,
                );
                $previous[] = [
                    'student_id' => $write['student_id'],
                    'result_id' => $saved->id,
                    'previous_score' => $old,
                    'new_score' => $write['score'],
                ];
            }

            app(AuditRecorder::class)->record(
                $actor,
                'cbt_results_converted',
                $exam,
                [
                    'written' => $summary['write_count'],
                    'replaced' => $summary['replace_count'],
                    'skipped' => $summary['skipped_count'],
                    'skip_reasons' => $summary['skip_reasons'],
                    'previous_values' => $previous,
                ],
                'CBT',
                "Converted {$summary['write_count']} CBT score(s) into Draft Results.",
            );

            unset($summary['writes']);

            return $summary;
        });
    }

    public function feedsResults(Exam $exam): bool
    {
        return $exam->relationLoaded('type')
            ? (bool) $exam->type?->feeds_results
            : $exam->type()->where('feeds_results', true)->exists();
    }

    private function assertConvertible(Exam $exam): void
    {
        if (! $this->feedsResults($exam)) {
            throw ValidationException::withMessages(['conversion' => 'This exam type does not feed Results.']);
        }
        if (! $exam->class_id || ! $exam->subject_id || ! $exam->term_id || ! $exam->assessment_component_id) {
            throw ValidationException::withMessages(['conversion' => 'Choose a class, subject, term and Results component before converting scores.']);
        }
    }

    private function plan(Exam $exam): array
    {
        $students = Student::active()->whereHas(
            'classArm',
            fn ($query) => $query->where('class_id', $exam->class_id),
        )->with('user')->orderBy('id')->get();
        $attempts = ExamSubmission::where('exam_id', $exam->id)
            ->where('status', 'Submitted')->whereNotNull('student_id')
            ->orderBy('attempt_number')->get()->keyBy('student_id');
        $results = Result::whereIn('student_id', $students->pluck('id'))
            ->where('subject_id', $exam->subject_id)->where('term_id', $exam->term_id)
            ->with('items')->get()->keyBy('student_id');
        $target = app(ResultAssessmentCatalog::class)->maxScore($exam->assessment_component_id);
        $writes = [];
        $skipped = [];
        $replaced = 0;
        $reasons = ['no_attempt' => 0, 'submitted' => 0, 'approved' => 0, 'not_draft' => 0];

        foreach ($students as $student) {
            $attempt = $attempts->get($student->id);
            $result = $results->get($student->id);
            $reason = null;
            if (! $attempt) {
                $reason = 'No submitted attempt';
                $reasons['no_attempt']++;
            } elseif ($result && $result->status !== 'Draft') {
                $key = in_array(strtolower($result->status), ['submitted', 'approved'], true)
                    ? strtolower($result->status)
                    : 'not_draft';
                $reasons[$key]++;
                $reason = "Result is {$result->status}";
            }

            if ($reason !== null) {
                $skipped[] = ['student_id' => $student->id, 'name' => $student->user?->name ?? "Pupil #{$student->id}", 'reason' => $reason];

                continue;
            }

            $score = ExamWorkflow::convertScore((float) $attempt->raw_score, (float) $attempt->max_score, $target);
            $hasExisting = $result?->items->contains('assessment_component_id', $exam->assessment_component_id) ?? false;
            $replaced += $hasExisting ? 1 : 0;
            $writes[] = ['student_id' => $student->id, 'name' => $student->user?->name ?? "Pupil #{$student->id}", 'score' => $score, 'replaces_existing' => $hasExisting];
        }

        return [
            'write_count' => count($writes),
            'replace_count' => $replaced,
            'skipped_count' => count($skipped),
            'skip_reasons' => array_filter($reasons),
            'writes' => $writes,
            'skipped' => $skipped,
        ];
    }
}
