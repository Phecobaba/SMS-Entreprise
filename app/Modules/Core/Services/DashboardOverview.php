<?php

namespace App\Modules\Core\Services;

use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Services\FormTeacherAssignments;
use App\Modules\Academics\Services\LearnerTerminology;
use App\Modules\Academics\Services\ResultTeachingAssignments;
use App\Modules\Academics\Services\TeacherFacingLabel;
use App\Modules\Admissions\Services\AdmissionPipeline;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Core\Models\User;
use App\Modules\Payments\Services\PaymentLedger;
use App\Modules\Portals\Services\PortalOverview;
use App\Modules\Results\Services\ResultWorkflow;

class DashboardOverview
{
    public function staffKind(User $actor): ?string
    {
        if ($actor->can('view_all_sections')) {
            return 'admin';
        }
        if ($actor->can('approve_results') || $actor->can('view_attendance_alerts') || $actor->can('manage_promotions')) {
            return 'head';
        }
        if ($actor->can('mark_attendance') && $actor->can('enter_results')) {
            return 'teacher';
        }
        if ($actor->can('manage_payments')) {
            return 'bursar';
        }

        return null;
    }

    public function for(
        User $actor,
        ?int $studentId = null,
        ?array $portalContext = null,
        ?int $sectionId = null,
        ?Section $section = null,
    ): array {
        $kind = $this->staffKind($actor);
        if ($kind === 'head' && ! $section) {
            return ['kind' => 'head_locked'];
        }
        if ($kind === 'admin' || $kind === 'head') {
            $attendance = app(AttendanceService::class)->dashboardSummary($actor, $sectionId);

            return [
                'kind' => $kind,
                'section' => $section,
                'learners_term' => $kind === 'head'
                    ? app(LearnerTerminology::class)->word($section, true)
                    : 'Students',
                'students' => app(StudentDirectory::class)->count($actor, $sectionId),
                'teachers' => app(ResultTeachingAssignments::class)->dashboardTeacherCount($actor, $sectionId),
                'attendance' => $attendance,
                'outstanding_kobo' => app(PaymentLedger::class)->outstandingKobo($actor, $sectionId),
                'approvals' => app(ResultWorkflow::class)->dashboardApprovals($actor, 5, $sectionId),
                'approval_count' => app(ResultWorkflow::class)->dashboardApprovalCount($actor, $sectionId),
                'offers_count' => app(AdmissionPipeline::class)->dashboardPendingOffers($actor, $sectionId),
            ];
        }

        if ($kind === 'teacher') {
            $arms = app(FormTeacherAssignments::class)->dashboardArms($actor);
            $arm = $arms->first();
            $assignments = app(ResultTeachingAssignments::class)->assignedPairs($actor);
            $assignedPairs = $assignments->map(
                fn ($assignment): string => app(TeacherFacingLabel::class)->pair(
                    $assignment->subject?->name,
                    $assignment->schoolClass?->name,
                ),
            );
            $learnerScope = $arms->map->schoolClass
                ->merge($assignments->pluck('schoolClass'))
                ->filter();
            $session = now('Africa/Lagos')->hour < 12 ? 'Morning' : 'Afternoon';
            $roster = $arm ? app(AttendanceService::class)->dashboardRoster($arm->id, $session) : [];

            return [
                'kind' => 'teacher',
                'context' => null,
                'form_arms' => $arms->map(fn ($item): string => app(TeacherFacingLabel::class)->arm($item->schoolClass?->name, $item->name))->values(),
                'assigned_pairs' => $assignedPairs,
                'learner_term' => app(LearnerTerminology::class)->word($learnerScope),
                'learners_term' => app(LearnerTerminology::class)->word($learnerScope, true),
                'students' => $arm ? app(StudentDirectory::class)->countForArm($arm->id) : 0,
                'attendance' => $arm ? app(AttendanceService::class)->dashboardToday($arm->id) : null,
                'pending' => app(ResultWorkflow::class)->dashboardTeacherPending($actor),
                'roster' => $roster,
            ];
        }

        if ($kind === 'bursar') {
            return ['kind' => 'bursar', ...app(PaymentLedger::class)->dashboardSummary()];
        }

        $portal = app(PortalOverview::class)->for($actor, $studentId, $portalContext);
        if ($portal !== null) {
            return $portal;
        }

        return ['kind' => 'general'];
    }
}
