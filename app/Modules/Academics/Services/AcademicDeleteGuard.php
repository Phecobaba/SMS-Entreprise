<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Assignments\Services\AssignmentService;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\CBT\Services\ExamUsage;
use App\Modules\Payments\Services\FeeInvoicingService;
use App\Modules\Payments\Services\PaymentLedger;
use App\Modules\Results\Services\ResultUsage;
use Illuminate\Validation\ValidationException;

class AcademicDeleteGuard
{
    public function levelUsage(AcademicLevel $level): array
    {
        return [
            'classes' => $level->classes()->count(),
            'fee schedule lines' => app(FeeInvoicingService::class)->scheduleCountForLevel($level->id),
            'result templates' => app(ResultUsage::class)->templateCountForLevel($level->id),
            'admission applications' => app(StudentDirectory::class)->candidateCountForLevel($level->id),
        ];
    }

    public function armUsage(ClassArm $arm): array
    {
        $students = app(StudentDirectory::class)->idsReferencingArms([$arm->id]);
        $results = app(ResultUsage::class)->usageForArm($arm->id);
        $attendance = app(AttendanceService::class)->usageForArm($arm->id);

        return [
            'students' => count($students),
            'results' => $results['results'] + $results['result_cards'],
            'result terms' => $results['terms'],
            'attendance records' => $attendance['records'],
            'attendance alerts' => $attendance['alerts'],
            'invoices' => app(PaymentLedger::class)->invoiceCountForStudents($students),
            'assignments' => app(AssignmentService::class)->countForArm($arm->id),
        ];
    }

    public function termUsage(AcademicTerm $term): array
    {
        $results = app(ResultUsage::class)->usageForTerm($term->id);
        $payments = app(FeeInvoicingService::class)->usageForTerm($term->id);

        return [
            'results' => $results['results'],
            'report cards' => $results['result_cards'],
            'result comments' => $results['default_comments'],
            'result comment audit entries' => $results['comment_audits'],
            'attendance records' => app(AttendanceService::class)->recordCountBetween($term->start_date->toDateString(), $term->end_date->toDateString()),
            'invoices' => $payments['invoices'],
            'fee schedule lines' => $payments['schedule_lines'],
            'invoice-generation records' => $payments['generation_runs'],
            'exams' => app(ExamUsage::class)->countForTerm($term->id),
        ];
    }

    public function sessionUsage(AcademicSession $session): array
    {
        return [
            'attendance records' => app(AttendanceService::class)->recordCountBetween(
                $session->start_date->toDateString(),
                $session->end_date->toDateString(),
            ),
        ];
    }

    public function assertUnused(string $name, array $usage, string $field): void
    {
        $used = collect($usage)->filter(fn (int $count): bool => $count > 0);
        if ($used->isEmpty()) {
            return;
        }

        $parts = $used->map(fn (int $count, string $label): string => $count.' '.$label)->values()->all();
        $summary = count($parts) === 1 ? $parts[0] : implode(', ', array_slice($parts, 0, -1)).' and '.end($parts);

        throw ValidationException::withMessages([
            $field => $name.' has '.$summary.", so it can't be deleted. Keep it for history; rename it if the label needs correcting.",
        ]);
    }
}
