<?php

namespace App\Modules\CBT\Services;

use App\Modules\Admissions\Models\Student;
use App\Modules\CBT\Models\Exam;
use App\Modules\CBT\Models\ExamResitGrant;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ExamResitGrants
{
    public function grant(Exam $exam, int $studentId, string $reason, CarbonInterface $opensUntil, User $actor): ExamResitGrant
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        $exam = app(CbtAccessScope::class)->managedExam($actor, $exam->id);
        $reason = trim($reason);

        if (! in_array($exam->status, ['Published', 'Closed'], true)) {
            throw ValidationException::withMessages(['exam' => 'Another attempt can be given only for a published or ended exam.']);
        }
        if (! $exam->class_id || $exam->pass_mark !== null) {
            throw ValidationException::withMessages(['exam' => 'Another attempt is available only for a class exam, not an Entrance exam.']);
        }
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Enter a reason for another attempt.']);
        }
        if (mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => 'The reason must be 500 characters or fewer.']);
        }
        if ($opensUntil->lte(now())) {
            throw ValidationException::withMessages(['opens_until' => 'Choose an end time in the future.']);
        }

        $student = Student::active()->whereKey($studentId)
            ->whereHas('classArm', fn ($query) => $query->where('class_id', $exam->class_id))
            ->firstOrFail();

        return DB::transaction(function () use ($exam, $student, $reason, $opensUntil, $actor): ExamResitGrant {
            Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            $existing = ExamResitGrant::where('exam_id', $exam->id)
                ->where('student_id', $student->id)
                ->whereNull('used_attempt_id')->whereNull('withdrawn_at')
                ->where('opens_until', '>', now())->lockForUpdate()->first();
            if ($existing) {
                throw ValidationException::withMessages(['student' => 'This pupil already has an unused attempt grant.']);
            }

            $grant = ExamResitGrant::create([
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'granted_by' => $actor->id,
                'reason' => $reason,
                'opens_until' => $opensUntil,
            ]);
            app(AuditRecorder::class)->record(
                $actor,
                'cbt_resit_granted',
                $grant,
                ['exam_id' => $exam->id, 'student_id' => $student->id, 'reason' => $reason, 'opens_until' => $opensUntil->toIso8601String()],
                'CBT',
                'Another CBT attempt was granted to one pupil.',
            );

            return $grant;
        });
    }

    public function withdraw(Exam $exam, ExamResitGrant $grant, User $actor): void
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        $exam = app(CbtAccessScope::class)->managedExam($actor, $exam->id);
        abort_unless((int) $grant->exam_id === (int) $exam->id, 404);

        DB::transaction(function () use ($grant, $actor): void {
            $locked = ExamResitGrant::whereKey($grant->id)->lockForUpdate()->firstOrFail();
            if ($locked->used_attempt_id) {
                throw ValidationException::withMessages(['grant' => 'A used attempt grant cannot be withdrawn.']);
            }
            if ($locked->withdrawn_at) {
                throw ValidationException::withMessages(['grant' => 'This attempt grant was already withdrawn.']);
            }
            $locked->update(['withdrawn_at' => now()]);
            app(AuditRecorder::class)->record(
                $actor,
                'cbt_resit_withdrawn',
                $locked,
                ['exam_id' => $locked->exam_id, 'student_id' => $locked->student_id, 'reason' => $locked->reason],
                'CBT',
                'An unused CBT attempt grant was withdrawn.',
            );
        });
    }

    public function activeFor(int $examId, int $studentId): ?ExamResitGrant
    {
        return ExamResitGrant::where('exam_id', $examId)->where('student_id', $studentId)
            ->whereNull('used_attempt_id')->whereNull('withdrawn_at')
            ->where('opens_until', '>', now())->latest('id')->first();
    }
}
