<?php

namespace App\Modules\Portals\Services;

use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Services\ClassArmDirectory;
use App\Modules\Academics\Services\ResultAcademicCatalog;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Assignments\Services\AssignmentService;
use App\Modules\Assignments\Services\ParentAccountDirectory;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\CBT\Services\ExamWorkflow;
use App\Modules\Core\Models\User;
use App\Modules\ENotes\Models\ENote;
use App\Modules\Payments\Services\PaymentLedger;
use App\Modules\Results\Services\ResultCards;
use Illuminate\Auth\Access\AuthorizationException;

class PortalOverview
{
    public function for(User $viewer, ?int $requestedStudentId = null): ?array
    {
        $self = app(StudentDirectory::class)->forUser($viewer);
        $studentIds = $self ? [$self->id] : app(ParentAccountDirectory::class)->wardIds($viewer);
        if ($studentIds === []) {
            return null;
        }

        $selectedId = $requestedStudentId ?? $studentIds[0];
        if (! in_array($selectedId, $studentIds, true)) {
            throw new AuthorizationException('That student is not linked to your account.');
        }

        $students = app(StudentDirectory::class)->portalStudents($studentIds);
        $armLabels = app(ClassArmDirectory::class)->labels($students->pluck('class_arm_id')->unique()->all());
        $wards = $students->map(fn ($student) => [
            'id' => $student->id,
            'name' => $student->user?->name ?? 'Student #'.$student->id,
            'class' => $armLabels[$student->class_arm_id] ?? 'Class not assigned',
        ])->values()->all();
        $selected = collect($wards)->firstWhere('id', $selectedId);

        try {
            $attendance = app(AttendanceService::class)->portalSummary($selectedId, $viewer);
            $attendanceLocked = false;
        } catch (AuthorizationException $exception) {
            $attendance = ['percentage' => null, 'marked_days' => 0, 'recent' => collect()];
            $attendanceLocked = true;
        }

        $data = [
            'kind' => $self ? 'student' : 'parent',
            'wards' => $wards,
            'selected' => $selected,
            'results' => app(ResultCards::class)->portalSummary($selectedId, $viewer),
            'attendance' => $attendance,
            'attendance_locked' => $attendanceLocked,
            'payments' => app(PaymentLedger::class)->portalSummary($selectedId, $viewer),
            'assignments' => app(AssignmentService::class)->portalSummary($selectedId, $viewer),
        ];

        if ($self) {
            $classId = app(ResultAcademicCatalog::class)->placement($self->class_arm_id)['class_id'];
            $term = AcademicTerm::with('session')
                ->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())->first();
            $data['term_context'] = $term ? $term->name.', '.$term->session?->name : null;
            $data['exams'] = app(ExamWorkflow::class)->dashboardFor($viewer);
            $data['enotes'] = $classId
                ? ENote::with('subject')->where('class_id', $classId)->latest()->limit(2)->get()
                : collect();
        }

        return $data;
    }
}
