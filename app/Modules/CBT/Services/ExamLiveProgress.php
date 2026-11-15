<?php

namespace App\Modules\CBT\Services;

use App\Modules\Admissions\Models\Student;
use App\Modules\CBT\Models\Exam;
use App\Modules\CBT\Models\ExamResitGrant;
use App\Modules\CBT\Models\ExamSubmission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ExamLiveProgress
{
    public function data(Exam $exam, string $requestedFilter, bool $closed): array
    {
        $filter = in_array($requestedFilter, ['', 'not_started', 'writing', 'submitted', 'absent'], true)
            ? $requestedFilter : '';
        $latest = DB::table('exam_submissions')
            ->selectRaw('student_id, MAX(attempt_number) AS latest_attempt_number, COUNT(*) AS attempt_count')
            ->where('school_id', $exam->school_id)
            ->where('exam_id', $exam->id)
            ->whereNotNull('student_id')
            ->groupBy('student_id');

        $base = Student::where('students.status', Student::STATUS_ACTIVE)
            ->whereHas('classArm', fn ($query) => $query->where('class_id', $exam->class_id))
            ->leftJoinSub($latest, 'latest_attempts', fn ($join) => $join->on('latest_attempts.student_id', '=', 'students.id'))
            ->leftJoin('exam_submissions as latest_submission', function ($join) use ($exam): void {
                $join->on('latest_submission.student_id', '=', 'students.id')
                    ->on('latest_submission.attempt_number', '=', 'latest_attempts.latest_attempt_number')
                    ->where('latest_submission.exam_id', '=', $exam->id)
                    ->where('latest_submission.school_id', '=', $exam->school_id);
            });

        $summary = (clone $base)->selectRaw(
            "SUM(CASE WHEN latest_submission.id IS NULL THEN 1 ELSE 0 END) AS without_attempt,\n".
            "SUM(CASE WHEN latest_submission.status = 'Submitted' THEN 1 ELSE 0 END) AS submitted,\n".
            "SUM(CASE WHEN latest_submission.id IS NOT NULL AND latest_submission.status <> 'Submitted' THEN 1 ELSE 0 END) AS writing"
        )->first();
        $withoutAttempt = (int) ($summary?->without_attempt ?? 0);
        $counts = [
            'not_started' => $closed ? 0 : $withoutAttempt,
            'writing' => (int) ($summary?->writing ?? 0),
            'submitted' => (int) ($summary?->submitted ?? 0),
            'absent' => $closed ? $withoutAttempt : 0,
        ];

        $this->applyFilter($base, $filter, $closed);
        $pupils = $base->select([
            'students.*',
            'latest_submission.id as latest_submission_id',
            'latest_submission.status as latest_submission_status',
            DB::raw('COALESCE(latest_attempts.attempt_count, 0) as cbt_attempt_count_value'),
        ])->with(['user', 'classArm.schoolClass'])->orderBy('students.id')->paginate(25)->withQueryString();

        $studentIds = $pupils->getCollection()->pluck('id');
        $attempts = ExamSubmission::with(['resitGrant', 'exam'])
            ->whereIn('id', $pupils->getCollection()->pluck('latest_submission_id')->filter())
            ->get()->keyBy('id');
        $grants = ExamResitGrant::where('exam_id', $exam->id)->whereIn('student_id', $studentIds)
            ->whereNull('used_attempt_id')->whereNull('withdrawn_at')->where('opens_until', '>', now())
            ->latest('id')->get()->unique('student_id')->keyBy('student_id');
        $lifecycle = app(ExamAttemptLifecycle::class);

        $pupils->through(function (Student $student) use ($attempts, $grants, $closed, $lifecycle): Student {
            $attempt = $attempts->get((int) $student->latest_submission_id);
            $status = $attempt?->status === 'Submitted'
                ? 'Submitted'
                : ($attempt ? 'Writing' : ($closed ? 'Absent' : 'Not started'));
            $student->setAttribute('cbt_attempt', $attempt);
            $student->setAttribute('cbt_status', $status);
            $student->setAttribute('cbt_remaining', $attempt?->status === 'In Progress' ? $lifecycle->remainingSeconds($attempt) : null);
            $student->setAttribute('cbt_attempt_count', (int) $student->cbt_attempt_count_value);
            $student->setAttribute('cbt_grant', $grants->get($student->id));

            return $student;
        });

        return compact('pupils', 'filter', 'counts');
    }

    private function applyFilter(Builder $query, string $filter, bool $closed): void
    {
        if ($filter === 'submitted') {
            $query->where('latest_submission.status', 'Submitted');
        } elseif ($filter === 'writing') {
            $query->whereNotNull('latest_submission.id')->where('latest_submission.status', '<>', 'Submitted');
        } elseif (($filter === 'not_started' && ! $closed) || ($filter === 'absent' && $closed)) {
            $query->whereNull('latest_submission.id');
        } elseif ($filter !== '') {
            $query->whereRaw('1 = 0');
        }
    }
}
