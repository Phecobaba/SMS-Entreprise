<?php

namespace App\Modules\Portals\Services;

use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Assignments\Services\ParentAccountDirectory;
use App\Modules\Payments\Services\PaymentLedger;
use Illuminate\Http\Request;

class PortalContext
{
    private const REQUEST_ATTRIBUTE = 'oakfield.portal_context';

    public function for(Request $request): ?array
    {
        if ($request->attributes->has(self::REQUEST_ATTRIBUTE)) {
            return $request->attributes->get(self::REQUEST_ATTRIBUTE);
        }

        $context = $this->resolve($request);
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $context);

        return $context;
    }

    private function resolve(Request $request): ?array
    {
        $viewer = $request->user();
        $self = app(StudentDirectory::class)->forPortalUser($viewer);
        if ($self) {
            return [
                'kind' => 'student',
                'student_id' => $self->id,
                'selected' => $this->studentRow($self, false),
                'wards' => [],
                '_students' => collect([$self]),
            ];
        }
        if (! app(ParentAccountDirectory::class)->existsForUser($viewer->id)) {
            return null;
        }

        $ids = app(ParentAccountDirectory::class)->wardIds($viewer);
        $requested = $request->query('student');
        if ($requested !== null) {
            abort_unless((is_string($requested) || is_int($requested)) && ctype_digit((string) $requested)
                && in_array((int) $requested, $ids, true), 403);
            $selectedId = (int) $requested;
            $request->session()->put('portal.ward_id', $selectedId);
        } else {
            $remembered = (int) $request->session()->get('portal.ward_id');
            $selectedId = in_array($remembered, $ids, true) ? $remembered : ($ids[0] ?? null);
        }

        $students = app(StudentDirectory::class)->portalStudents($ids);
        $feesDue = app(PaymentLedger::class)->wardFeesDueByStudentIds($students->pluck('id')->all());
        $wards = $students->map(fn (Student $student): array => $this->studentRow(
            $student,
            $feesDue[$student->id] ?? false,
        ))->values()->all();

        return [
            'kind' => 'parent',
            'student_id' => $selectedId,
            'selected' => collect($wards)->firstWhere('id', $selectedId),
            'wards' => $wards,
            '_students' => $students,
        ];
    }

    private function studentRow(Student $student, bool $feesDue): array
    {
        $class = $student->classArm?->schoolClass;

        return [
            'id' => $student->id,
            'name' => $student->user?->name ?? 'Student #'.$student->id,
            'class' => $class ? trim($class->name.' '.($student->classArm?->name ?? '')) : 'Class not assigned',
            'section' => $class?->level?->section?->name,
            'fees_due' => $feesDue,
            'status' => $student->status,
            'restricted' => $student->status !== Student::STATUS_ACTIVE,
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
