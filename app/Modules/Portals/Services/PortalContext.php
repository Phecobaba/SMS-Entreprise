<?php

namespace App\Modules\Portals\Services;

use App\Modules\Academics\Services\ClassArmDirectory;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Assignments\Services\ParentAccountDirectory;
use App\Modules\Payments\Services\PaymentLedger;
use Illuminate\Http\Request;

class PortalContext
{
    public function for(Request $request): ?array
    {
        $viewer = $request->user();
        $self = app(StudentDirectory::class)->forUser($viewer);
        if ($self) {
            return ['kind' => 'student', 'student_id' => $self->id, 'selected' => null, 'wards' => []];
        }
        if (! app(ParentAccountDirectory::class)->existsForUser($viewer->id)) {
            return null;
        }

        $ids = app(ParentAccountDirectory::class)->wardIds($viewer);
        $requested = $request->query('student');
        if ($requested !== null) {
            abort_unless((is_string($requested) || is_int($requested)) && ctype_digit((string) $requested)
                && app(ParentAccountDirectory::class)->isWard((int) $requested, $viewer->id), 403);
            $selectedId = (int) $requested;
            $request->session()->put('portal.ward_id', $selectedId);
        } else {
            $remembered = (int) $request->session()->get('portal.ward_id');
            $selectedId = in_array($remembered, $ids, true) ? $remembered : ($ids[0] ?? null);
        }

        $students = app(StudentDirectory::class)->portalStudents($ids);
        $labels = app(ClassArmDirectory::class)->labels($students->pluck('class_arm_id')->all());
        $wards = $students->map(function ($student) use ($labels): array {

            return [
                'id' => $student->id,
                'name' => $student->user?->name ?? 'Student #'.$student->id,
                'class' => $labels[$student->class_arm_id] ?? 'Class not assigned',
                'section' => app(SectionAccess::class)->sectionForArm($student->class_arm_id)?->name,
                'fees_due' => app(PaymentLedger::class)->wardFeesDue($student->id),
            ];
        })->values()->all();

        return [
            'kind' => 'parent',
            'student_id' => $selectedId,
            'selected' => collect($wards)->firstWhere('id', $selectedId),
            'wards' => $wards,
        ];
    }

    public function url(string $route, array $parameters = [], ?array $context = null): string
    {
        $context ??= request()->user() ? $this->for(request()) : null;
        if ($context && $context['kind'] === 'parent' && $context['student_id']) {
            $parameters['student'] = $context['student_id'];
        }

        return route($route, $parameters);
    }
}
