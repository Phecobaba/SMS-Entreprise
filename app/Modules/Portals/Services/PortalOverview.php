<?php

namespace App\Modules\Portals\Services;

use App\Modules\Academics\Services\AcademicCalendar;
use App\Modules\Assignments\Services\AssignmentService;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\CBT\Services\ExamWorkflow;
use App\Modules\Core\Models\User;
use App\Modules\ENotes\Models\ENote;
use App\Modules\Payments\Services\PaymentLedger;
use App\Modules\Results\Services\ResultCards;
use Illuminate\Auth\Access\AuthorizationException;

class PortalOverview
{
    public function for(User $viewer, ?int $requestedStudentId = null, ?array $portalContext = null): ?array
    {
        if ($portalContext === null) {
            return null;
        }

        $isStudent = $portalContext['kind'] === 'student';
        $studentIds = $isStudent
            ? [$portalContext['student_id']]
            : array_column($portalContext['wards'], 'id');
        if ($studentIds === []) {
            return null;
        }

        $selectedId = $requestedStudentId ?? $portalContext['student_id'] ?? $studentIds[0];
        if (! in_array($selectedId, $studentIds, true)) {
            throw new AuthorizationException('That student is not linked to your account.');
        }

        $students = $portalContext['_students'];
        $wards = $isStudent ? [$portalContext['selected']] : $portalContext['wards'];
        $selected = collect($wards)->firstWhere('id', $selectedId);
        $selectedStudent = $students->firstWhere('id', $selectedId);
        $restricted = (bool) ($selected['restricted'] ?? false);

        if ($restricted) {
            return [
                'kind' => $isStudent ? 'student' : 'parent',
                'wards' => $wards,
                'selected' => $selected,
                'restricted' => true,
                'results' => app(ResultCards::class)->portalSummary($selectedId, $viewer),
                'payments' => app(PaymentLedger::class)->portalReceiptSummary($selectedId, $viewer),
            ];
        }

        try {
            $attendance = app(AttendanceService::class)->portalSummary($selectedId, $viewer);
            $attendanceLocked = false;
        } catch (AuthorizationException $exception) {
            $attendance = ['percentage' => null, 'marked_days' => 0, 'recent' => collect()];
            $attendanceLocked = true;
        }

        $data = [
            'kind' => $isStudent ? 'student' : 'parent',
            'wards' => $wards,
            'selected' => $selected,
            'results' => app(ResultCards::class)->portalSummary($selectedId, $viewer),
            'attendance' => $attendance,
            'attendance_locked' => $attendanceLocked,
            'payments' => app(PaymentLedger::class)->portalSummary($selectedId, $viewer),
            'assignments' => app(AssignmentService::class)->portalSummary($selectedId, $viewer),
            'restricted' => false,
        ];

        if ($isStudent) {
            $classId = $selectedStudent?->classArm?->class_id;
            $term = app(AcademicCalendar::class)->currentTerm();
            $data['term_context'] = $term ? $term->name.', '.$term->session?->name : null;
            $data['exams'] = app(ExamWorkflow::class)->dashboardFor($viewer);
            $data['enotes'] = $classId
                ? ENote::with('subject')->where('class_id', $classId)->latest()->limit(2)->get()
                : collect();
        }

        return $data;
    }
}
