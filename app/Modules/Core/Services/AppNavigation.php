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

    private array $staffFlagsByUser = [];

    public function staffKind(User $user): ?string
    {
        if ($user->can('view_all_sections')) {
            return 'admin';
        }
        if ($user->can('approve_results') || $user->can('view_attendance_alerts') || $user->can('manage_promotions')
            || $user->can('manage_section_subjects') || $user->can('manage_section_teacher_assignments')
            || $user->can('manage_section_levels') || $user->can('manage_section_arms')
            || $user->can('manage_section_result_settings') || $user->can('manage_exam_sessions')) {
            return 'head';
        }
        if ($this->hasTeaching($user)) {
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

        $groups = $this->staffGroups($user);
        if ($groups !== []) {
            $items = [$this->item('Dashboard', 'dashboard')];
            foreach (['teaching', 'head', 'admin'] as $group) {
                if (! in_array($group, $groups, true)) {
                    continue;
                }
                $roleItems = match ($group) {
                    'teaching' => $this->teacherItems($user),
                    'head' => $this->headItems($user),
                    'admin' => $this->adminItems($user),
                };
                foreach ($roleItems as $item) {
                    if ($item['label'] === 'Dashboard') {
                        continue;
                    }
                    $item['group'] = match ($group) {
                        'teaching' => 'Teaching',
                        'head' => 'Head',
                        'admin' => 'Admin',
                    };
                    if ($item['label'] === 'Results') {
                        $item['url'] = route('results.index', ['workspace' => $group === 'teaching' ? 'teaching' : 'review']);
                    }
                    $items[] = $item;
                }
            }
        } else {
            $items = match ($kind) {
                'bursar' => $this->bursarItems($user),
                default => $this->generalItems($user),
            };
        }

        if (Route::has('notifications.index')) {
            $items[] = $this->item('Notifications', 'notifications.index');
        }

        return $this->itemsByUser[$user->id] = $items;
    }

    public function staffGroups(User $user): array
    {
        $groups = [];
        if ($this->hasTeaching($user)) {
            $groups[] = 'teaching';
        }
        if ($this->hasHeadScope($user)) {
            $groups[] = 'head';
        }
        if ($user->can('view_all_sections')) {
            $groups[] = 'admin';
        }

        return $groups;
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
            ['Academics', 'academics.index', 'manage_academics', 'Academics'],
            ['Bulk subjects', 'academics.bulk-subjects.index', 'manage_academics', 'Academics'],
            ['Teacher assignments', 'academics.teacher-assignments.index', 'manage_result_settings', 'Academics'],
            ['Promotion', 'academics.promotion.index', 'manage_promotions', 'Academics'],
            ['Results', 'results.index', 'view_results_workspace', 'Teaching'],
            ['Result configuration', 'results.configuration', 'manage_result_settings', 'Teaching'],
            ['CBT', 'cbt.staff.index', 'manage_cbt', 'Teaching'],
            ['CBT results', 'cbt.results.index', 'view_cbt_results', 'Teaching'],
            ['Attendance', 'attendance.index', 'mark_attendance', 'Daily ops'],
            ['Alerts', 'attendance.alerts', 'view_attendance_alerts', 'Daily ops'],
            ['Assignments', 'assignments.index', 'mark_assignments', 'Daily ops'],
            ['E-Notes', 'enotes.index', 'upload_enotes', 'Daily ops'],
            ['Admissions', 'admissions.applications.index', 'manage_admissions', 'Daily ops', [
                ['Applications', 'admissions.applications.index', 'manage_admissions'],
                ['Exam sessions', 'admissions.sessions.index', 'manage_exam_sessions'],
                ['Admission settings', 'admissions.applications.settings', 'manage_admissions'],
                ['Candidates', 'admissions.index', 'manage_admissions'],
                ['Entrance exams', 'cbt.staff.entrance', 'manage_cbt'],
            ]],
            ['Students', 'students.index', 'view_students', 'Daily ops'],
            ['Parents', 'parents.index', 'view_parents', 'Daily ops'],
            ['Payments', 'payments.index', 'manage_payments', 'Administration', [
                ['Record payment', 'payments.record.index', 'manage_payments'],
                ['Awaiting approval', 'payments.approvals.index', 'manage_payments'],
                ['Receipts', 'payments.receipts.index', 'manage_payments'],
                ['Fee schedule', 'fees.schedule.index', 'manage_fee_schedule'],
                ['Generate invoices', 'fees.generate.index', 'manage_fee_schedule'],
                ['Not invoiced', 'fees.not-invoiced.index', 'manage_fee_schedule'],
                ['Payment gateway', 'payments.gateway.show', 'manage_payment_gateway'],
            ]],
            ['Staff HR', 'staff.index', 'manage_staff_records', 'Administration'],
            ['Create staff account', 'staff.accounts.create', 'create_staff', 'Administration'],
            ['Staff access', 'staff.access.index', 'manage_staff_access', 'Administration'],
            ['Sign-in rules', 'sign-in-rules.show', 'view_sign_in_rules', 'Administration'],
            ['Activity log', 'activity-log.index', 'view_activity_log', 'Administration'],
            ['Registration numbers', 'registration-numbers.edit', 'manage_registration_numbers', 'Administration'],
            ['School Profile', 'school-profile.edit', 'manage_school_profile', 'Administration'],
            ['Website settings', 'website-settings.edit', 'manage_school_profile', 'Administration'],
        ]);
    }

    private function headItems(User $user): array
    {
        return $this->allowed($user, [
            ['Dashboard', 'dashboard', 'view_dashboard', null],
            ['Classes', 'academics.classes.index', 'manage_section_classes', 'Academics'],
            ['Levels', 'academics.levels.index', 'manage_section_levels', 'Academics'],
            ['Subjects', 'academics.setup', 'manage_section_subjects', 'Academics'],
            ['Bulk subjects', 'academics.bulk-subjects.index', 'manage_section_subjects', 'Academics'],
            ['Teacher assignments', 'academics.teacher-assignments.index', 'manage_section_teacher_assignments', 'Academics'],
            ['Promotion', 'academics.promotion.index', 'manage_promotions', 'Academics'],
            ['Staff', 'head.staff.index', 'view_section_staff', 'People'],
            ['Parents', 'parents.index', 'view_parents', 'People'],
            ['Admissions', 'admissions.applications.index', 'manage_exam_sessions', 'People', [
                ['Applications', 'admissions.applications.index', 'manage_exam_sessions'],
                ['Exam sessions', 'admissions.sessions.index', 'manage_exam_sessions'],
            ]],
            ['Results', 'results.index', 'view_results_workspace', 'Teaching'],
            ['Result configuration', 'results.configuration', 'manage_section_result_settings', 'Teaching'],
            ['CBT', 'cbt.staff.index', 'manage_cbt', 'Teaching'],
            ['CBT results', 'cbt.results.index', 'view_cbt_results', 'Teaching'],
            ['Attendance', 'attendance.index', 'manage_attendance', 'Teaching'],
            ['Alerts', 'attendance.alerts', 'view_attendance_alerts', 'Teaching'],
        ]);
    }

    private function bursarItems(User $user): array
    {
        return $this->allowed($user, [
            ['Dashboard', 'dashboard', 'view_dashboard', null],
            ['Payments', 'payments.index', 'manage_payments', null],
            ['Record payment', 'payments.record.index', 'manage_payments', null],
            ['Awaiting approval', 'payments.approvals.index', 'manage_payments', null],
            ['Receipts', 'payments.receipts.index', 'manage_payments', null],
            ['Fee schedule', 'fees.schedule.index', 'manage_fee_schedule', null],
            ['Generate invoices', 'fees.generate.index', 'manage_fee_schedule', null],
            ['Not invoiced', 'fees.not-invoiced.index', 'manage_fee_schedule', null],
            ['Payment gateway', 'payments.gateway.show', 'manage_payments', null],
            ['Parents', 'parents.index', 'view_parents', null],
        ]);
    }

    private function teacherItems(User $user): array
    {
        $definitions = [
            ['Dashboard', 'dashboard', 'view_dashboard', null],
            ['Results', 'results.index', 'view_results_workspace', null],
            ['CBT results', 'cbt.results.index', 'view_cbt_results', null],
            ['Assignments', 'assignments.index', 'mark_assignments', null],
            ['E-Notes', 'enotes.index', 'upload_enotes', null],
        ];
        if ($user->can('manage_attendance') || $this->staffFlags($user)['form_teacher']) {
            array_splice($definitions, 1, 0, [[
                'Attendance', 'attendance.index', 'mark_attendance', null,
            ]]);
        }

        return $this->allowed($user, $definitions);
    }

    private function hasTeaching(User $user): bool
    {
        if (! $user->can('view_dashboard')) {
            return false;
        }

        $flags = $this->staffFlags($user);

        return $flags['teaching'];
    }

    private function hasHeadScope(User $user): bool
    {
        return $this->staffFlags($user)['head'];
    }

    private function staffFlags(User $user): array
    {
        if (isset($this->staffFlagsByUser[$user->id])) {
            return $this->staffFlagsByUser[$user->id];
        }

        $flags = User::query()->whereKey($user->id)
            ->withExists(['teacherSubjectAssignments', 'formTeacherArms', 'headSections'])
            ->firstOrFail();

        return $this->staffFlagsByUser[$user->id] = [
            'teaching' => (bool) ($flags->teacher_subject_assignments_exists || $flags->form_teacher_arms_exists),
            'form_teacher' => (bool) $flags->form_teacher_arms_exists,
            'head' => (bool) $flags->head_sections_exists,
        ];
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
            $items[] = $this->item('Account', 'account.show');

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
            $this->item('Account', 'account.show'),
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

        $items = collect($routes)->map(fn (array $definition): array => [
            'label' => $definition[0],
            'url' => $portal->url($definition[1], context: $context),
            'group' => null,
        ])->all();
        $items[] = $this->item('My details', 'account.show');

        return $items;
    }

    private function allowed(User $user, array $definitions): array
    {
        $items = [];
        foreach ($definitions as $definition) {
            [$label, $route, $permission, $group] = $definition;
            if (Route::has($route) && $user->can($permission)) {
                $children = collect($definition[4] ?? [])->filter(
                    fn (array $child): bool => Route::has($child[1]) && $user->can($child[2]),
                )->map(fn (array $child): array => $this->item($child[0], $child[1]))->values()->all();
                $items[] = $this->item($label, $route, $group, $children);
            }
        }

        return $items;
    }

    private function item(string $label, string $route, ?string $group = null, array $children = []): array
    {
        return ['label' => $label, 'url' => route($route), 'group' => $group, 'children' => $children];
    }
}
