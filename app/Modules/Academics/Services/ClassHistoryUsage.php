<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Payments\Services\PaymentLedger;
use App\Modules\Results\Services\ResultUsage;

class ClassHistoryUsage
{
    public function counts(SchoolClass $class): array
    {
        $armIds = $class->arms()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $studentIds = app(StudentDirectory::class)->idsForArms($armIds);

        return [
            'students' => count($studentIds),
            'results' => app(ResultUsage::class)->countForArms($armIds),
            'attendance' => app(AttendanceService::class)->recordCountForArms($armIds),
            'invoices' => app(PaymentLedger::class)->invoiceCountForStudents($studentIds),
        ];
    }
}
