<?php

namespace App\Modules\Core\Services;

use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Services\AdmissionPipeline;
use App\Modules\Core\Models\User;
use App\Modules\Portals\Services\PortalContext;
use Illuminate\Support\Facades\Route;

class AppNavigation
{
    private array $itemsByUser = [];

    public function staffKind(User $user): ?string
    {
        if ($user->can('view_all_sections')) {
            return 'admin';
        }
        if ($user->can('approve_results') || $user->can('view_attendance_alerts') || $user->can('manage_promotions')
            || $user->can('manage_section_subjects') || $user->can('manage_section_teacher_assignments')
            || $user->can('manage_section_levels') || $user->can('manage_section_arms')) {
            return 'head';
        }
        if ($user->can('mark_attendance') && $user->can('enter_results')) {
            return 'teacher';
        }
        if ($user->can('manage_payments')) {
            return 'bursar';
        }

        return null;
    }

    public function items(User $user): array
    {
        if (isset($this->itemsByUser[$user->id])) {
            return $this->itemsByUser[$user->id];
        }

        $kind = $this->staffKind($user);
        if (! $kind) {
            $portal = app(PortalContext::class);
            $portalContext = request()->user()?->id === $user->id ? $portal->for(request()) : null;
            if (($portalContext['kind'] ?? null) === 'student') {
                return $this->itemsByUser[$user->id] = $this->studentItems($portalContext);
            }
            if (($portalContext['kind'] ?? null) === 'parent') {
                return $this->itemsByUser[$user->id] = $this->parentItems($portal, $portalContext);
            }
        }

        $items = match ($kind) {
            'admin' => $this->adminItems($user),
            'head' => $this->headItems($user),
            'bursar' => $this->bursarItems($user),
            'teacher' => $this->teacherItems($user),
            default => $this->generalItems($user),
        };

        if (Route::has('notifications.index')) {
            $items[] = $this->item('Notifications', 'notifications.index');
        }

        return $this->itemsByUser[$user->id] = $items;
    }

    public function mobileLabels(User $user): array
    {
        return match ($this->staffKind($user)) {
            'admin' => ['Dashboard', 'Academics', 'Results', 'Payments'],
            'head' => ['Dashboard', 'Results', 'Classes', 'Attendance'],
            'bursar' => ['Dashboard', 'Payments'],
            default => [],
        };
    }

    private function adminItems(User $user): array
    {
        return $this->allowed($user, [
            ['Dashboard', 'dashboard', 'view_dashboard', null],
            ['Academics', 'academics.index', 'manage_academics', 'Teaching'],
            ['Bulk subjects', 'academics.bulk-subjects.index', 'manage_academics', 'Teaching'],
            ['Teacher assignments', 'academics.teacher-assignments.index', 'manage_result_settings', 'Teaching'],
            ['Promotion', 'academics.promotion.index', 'manage_promotions', 'Teaching'],
            ['Results', 'results.index', 'view_results_workspace', 'Teaching'],
            ['CBT', 'cbt.staff.index', 'manage_cbt', 'Teaching'],
            ['Attendance', 'attendance.index', 'mark_attendance', 'Daily ops'],
            ['Alerts', 'attendance.alerts', 'view_attendance_alerts', 'Daily ops'],
            ['Assignments', 'assignments.index', 'mark_assignments', 'Daily ops'],
            ['E-Notes', 'enotes.index', 'upload_enotes', 'Daily ops'],
            ['Admissions', 'admissions.index', 'manage_admissions', 'Daily ops'],
            ['Payments', 'payments.index', 'manage_payments', 'Administration'],
            ['Fee schedule', 'fees.schedule.index', 'manage_fee_schedule', 'Administration'],
            ['Generate invoices', 'fees.generate.index', 'manage_fee_schedule', 'Administration'],
            ['Not invoiced', 'fees.not-invoiced.index', 'manage_fee_schedule', 'Administration'],
            ['Staff HR', 'staff.index', 'manage_staff_records', 'Administration'],
            ['School Profile', 'school-profile.edit', 'manage_school_profile', 'Administration'],
        ]);
    }

    private function headItems(User $user): array
    {
        return $this->allowed($user, [
            ['Dashboard', 'dashboard', 'view_dashboard', null],
            ['Classes', 'academics.classes.index', 'manage_section_classes', 'Academics'],
            ['Levels', 'academics.levels.index', 'manage_section_levels', 'Academics'],
            ['Bulk subjects', 'academics.bulk-subjects.index', 'manage_section_subjects', 'Academics'],
            ['Teacher assignments', 'academics.teacher-assignments.index', 'manage_section_teacher_assignments', 'Academics'],
            ['Promotion', 'academics.promotion.index', 'manage_promotions', 'Academics'],
            ['Results', 'results.index', 'view_results_workspace', 'Teaching'],
            ['Attendance', 'attendance.alerts', 'view_attendance_alerts', 'Teaching'],
        ]);
    }

    private function bursarItems(User $user): array
    {
        return $this->allowed($user, [
            ['Dashboard', 'dashboard', 'view_dashboard', null],
            ['Payments', 'payments.index', 'manage_payments', null],
            ['Fee schedule', 'fees.schedule.index', 'manage_fee_schedule', null],
            ['Generate invoices', 'fees.generate.index', 'manage_fee_schedule', null],
            ['Not invoiced', 'fees.not-invoiced.index', 'manage_fee_schedule', null],
        ]);
    }

    private function teacherItems(User $user): array
    {
        return $this->allowed($user, [
            ['Dashboard', 'dashboard', 'view_dashboard', null],
            ['Attendance', 'attendance.index', 'mark_attendance', null],
            ['Results', 'results.index', 'view_results_workspace', null],
            ['Assignments', 'assignments.index', 'mark_assignments', null],
            ['E-Notes', 'enotes.index', 'upload_enotes', null],
        ]);
    }

    private function generalItems(User $user): array
    {
        $items = $this->allowed($user, [
            ['Dashboard', 'dashboard', 'view_dashboard', null],
        ]);
        if (app(AdmissionPipeline::class)->candidateForUser($user)) {
            $items[] = $this->item('My Admission', 'admissions.mine');
            $items[] = $this->item('My Exams', 'cbt.mine');
        }

        return $items;
    }

    private function studentItems(array $context): array
    {
        if (($context['selected']['restricted'] ?? false) === true) {
            $items = [
                $this->item('Dashboard', 'dashboard'),
                $this->item('Results', 'results.mine.index'),
            ];
            if (($context['selected']['status'] ?? null) === Student::STATUS_GRADUATED) {
                $items[] = $this->item('Attendance', 'attendance.mine');
            }
            $items[] = $this->item('Receipts', 'payments.mine.index');

            return $items;
        }

        return [
            $this->item('Dashboard', 'dashboard'),
            $this->item('Exams', 'cbt.mine'),
            $this->item('Results', 'results.mine.index'),
            $this->item('Assignments', 'assignments.mine'),
            $this->item('Attendance', 'attendance.mine'),
            $this->item('Fees', 'payments.mine.index'),
            $this->item('E-Notes', 'enotes.mine'),
        ];
    }

    private function parentItems(PortalContext $portal, array $context): array
    {
        if (($context['selected']['restricted'] ?? false) === true) {
            $routes = [['Dashboard', 'dashboard'], ['Results', 'results.mine.index']];
            if (($context['selected']['status'] ?? null) === Student::STATUS_GRADUATED) {
                $routes[] = ['Attendance', 'attendance.mine'];
            }
            $routes[] = ['Receipts', 'payments.mine.index'];
        } else {
            $routes = [['Dashboard', 'dashboard'], ['Results', 'results.mine.index'], ['Attendance', 'attendance.mine'], ['Fees', 'payments.mine.index'], ['Assignments', 'assignments.parent']];
        }

        return collect($routes)->map(fn (array $definition): array => [
            'label' => $definition[0],
            'url' => $portal->url($definition[1], context: $context),
            'group' => null,
        ])->all();
    }

    private function allowed(User $user, array $definitions): array
    {
        $items = [];
        foreach ($definitions as [$label, $route, $permission, $group]) {
            if (Route::has($route) && $user->can($permission)) {
                $items[] = $this->item($label, $route, $group);
            }
        }

        return $items;
    }

    private function item(string $label, string $route, ?string $group = null): array
    {
        return ['label' => $label, 'url' => route($route), 'group' => $group];
    }
}
