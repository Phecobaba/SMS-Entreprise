<?php

namespace App\Modules\Core\Services;

use App\Modules\Admissions\Services\AdmissionPipeline;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Assignments\Services\ParentAccountDirectory;
use App\Modules\Core\Models\User;
use App\Modules\Portals\Services\PortalContext;

class AppNavigation
{
    public function items(User $user): array
    {
        $items = [];
        $definitions = [
            ['Dashboard', 'dashboard', 'view_dashboard'],
            ['Academics', 'academics.index', 'manage_academics'],
            ['Admissions', 'admissions.index', 'manage_admissions'],
            ['Results', 'results.index', 'view_results_workspace'],
            ['CBT', 'cbt.staff.index', 'manage_cbt'],
            ['Attendance', 'attendance.index', 'mark_attendance'],
            ['Alerts', 'attendance.alerts', 'view_attendance_alerts'],
            ['Assignments', 'assignments.index', 'mark_assignments'],
            ['E-Notes', 'enotes.index', 'upload_enotes'],
            ['Payments', 'payments.index', 'manage_payments'],
            ['Staff HR', 'staff.index', 'manage_staff_records'],
            ['School Profile', 'school-profile.edit', 'manage_school_profile'],
        ];

        foreach ($definitions as [$label, $routeName, $permission]) {
            if ($user->can($permission)) {
                $items[] = ['label' => $label, 'url' => route($routeName)];
            }
        }

        if ($user->can('mark_attendance') && ! $user->can('manage_academics')) {
            $priority = ['Dashboard' => 0, 'Attendance' => 1, 'Results' => 2, 'Assignments' => 3, 'E-Notes' => 4];
            usort($items, fn (array $a, array $b) => ($priority[$a['label']] ?? 99) <=> ($priority[$b['label']] ?? 99));
        }

        if (app(StudentDirectory::class)->forUser($user)) {
            return [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Exams', 'url' => route('cbt.mine')],
                ['label' => 'Results', 'url' => route('results.mine.index')],
                ['label' => 'Assignments', 'url' => route('assignments.mine')],
                ['label' => 'Attendance', 'url' => route('attendance.mine')],
                ['label' => 'Fees', 'url' => route('payments.mine.index')],
                ['label' => 'E-Notes', 'url' => route('enotes.mine')],
            ];
        }
        if (app(ParentAccountDirectory::class)->existsForUser($user->id)) {
            $portal = app(PortalContext::class);
            $context = request()->user()?->id === $user->id ? $portal->for(request()) : null;

            return [
                ['label' => 'Dashboard', 'url' => $portal->url('dashboard', context: $context)],
                ['label' => 'Results', 'url' => $portal->url('results.mine.index', context: $context)],
                ['label' => 'Attendance', 'url' => $portal->url('attendance.mine', context: $context)],
                ['label' => 'Fees', 'url' => $portal->url('payments.mine.index', context: $context)],
                ['label' => 'Assignments', 'url' => $portal->url('assignments.parent', context: $context)],
            ];
        }
        if (app(AdmissionPipeline::class)->candidateForUser($user)) {
            $items[] = ['label' => 'My Admission', 'url' => route('admissions.mine')];
            $items[] = ['label' => 'My Exams', 'url' => route('cbt.mine')];
        }

        $items[] = ['label' => 'Notifications', 'url' => route('notifications.index')];

        return $items;
    }
}
