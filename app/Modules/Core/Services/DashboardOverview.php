<?php

namespace App\Modules\Core\Services;

use App\Modules\Academics\Services\FormTeacherAssignments;
use App\Modules\Academics\Services\ResultTeachingAssignments;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Core\Models\User;
use App\Modules\Payments\Services\PaymentLedger;
use App\Modules\Portals\Services\PortalOverview;
use App\Modules\Results\Services\ResultWorkflow;

class DashboardOverview
{
    public function for(User $actor, ?int $studentId = null): array
    {
        if (($actor->can('manage_academics') && $actor->can('manage_payments'))
            || $actor->can('approve_results') || $actor->can('view_attendance_alerts')) {
            return [
                'kind' => 'admin',
                'students' => app(StudentDirectory::class)->count($actor),
                'teachers' => app(ResultTeachingAssignments::class)->dashboardTeacherCount($actor),
                'attendance' => app(AttendanceService::class)->dashboardToday(null, $actor),
                'outstanding_kobo' => app(PaymentLedger::class)->outstandingKobo($actor),
                'approvals' => app(ResultWorkflow::class)->dashboardApprovals($actor),
            ];
        }

        if ($actor->can('mark_attendance') && $actor->can('enter_results')) {
            $arm = app(FormTeacherAssignments::class)->dashboardArm($actor);
            $context = app(ResultTeachingAssignments::class)->dashboardTeacherContext($actor);
            $roster = $arm ? app(AttendanceService::class)->dashboardRoster($arm->id) : [];

            return [
                'kind' => 'teacher',
                'context' => trim(($arm?->schoolClass?->name ?? $context['class'] ?? '').' '.($context['subject'] ?? $arm?->name ?? '')),
                'students' => $arm ? app(StudentDirectory::class)->countForArm($arm->id) : 0,
                'attendance' => $arm ? app(AttendanceService::class)->dashboardToday($arm->id) : null,
                'pending' => app(ResultWorkflow::class)->dashboardTeacherPending($actor),
                'roster' => $roster,
            ];
        }

        $portal = app(PortalOverview::class)->for($actor, $studentId);
        if ($portal !== null) {
            return $portal;
        }

        return ['kind' => 'general'];
    }
}
