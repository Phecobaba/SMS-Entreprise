<?php

namespace App\Providers;

use App\Modules\Academics\Services\FormTeacherAssignments;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AppNavigation;
use App\Modules\Core\TenantContext;
use App\Modules\Portals\Services\PortalContext;
use App\Modules\Results\Models\ResultCard;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->scoped(PortalContext::class);
        $this->app->scoped(AppNavigation::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(app_path('Modules/Core/Database/Migrations'));
        $this->loadMigrationsFrom(app_path('Modules/Academics/Database/Migrations'));
        $this->loadMigrationsFrom(app_path('Modules/Admissions/Database/Migrations'));
        $this->loadMigrationsFrom(app_path('Modules/Results/Database/Migrations'));
        $this->loadMigrationsFrom(app_path('Modules/CBT/Database/Migrations'));
        $this->loadMigrationsFrom(app_path('Modules/Attendance/Database/Migrations'));
        $this->loadMigrationsFrom(app_path('Modules/Assignments/Database/Migrations'));
        $this->loadMigrationsFrom(app_path('Modules/ENotes/Database/Migrations'));
        $this->loadMigrationsFrom(app_path('Modules/Payments/Database/Migrations'));
        $this->loadMigrationsFrom(app_path('Modules/StaffHR/Database/Migrations'));
        $this->loadMigrationsFrom(app_path('Modules/Notifications/Database/Migrations'));

        Gate::define('view_dashboard', fn (User $user): bool => $user->hasPermission('view_dashboard'));
        Gate::define('manage_academics', fn (User $user): bool => $user->hasPermission('manage_academics'));
        Gate::define('manage_section_classes', fn (User $user): bool => $user->hasPermission('manage_section_classes'));
        Gate::define('manage_section_levels', fn (User $user): bool => $user->hasPermission('manage_section_levels'));
        Gate::define('manage_section_arms', fn (User $user): bool => $user->hasPermission('manage_section_arms'));
        Gate::define('manage_section_subjects', fn (User $user): bool => $user->hasPermission('manage_section_subjects'));
        Gate::define('manage_section_teacher_assignments', fn (User $user): bool => $user->hasPermission('manage_section_teacher_assignments'));
        Gate::define('manage_promotions', fn (User $user): bool => $user->hasPermission('manage_promotions'));
        Gate::define('manage_school_profile', fn (User $user): bool => $user->hasPermission('manage_school_profile'));
        Gate::define('manage_admissions', fn (User $user): bool => $user->hasPermission('manage_admissions'));
        Gate::define('manage_payments', fn (User $user): bool => $user->hasPermission('manage_payments'));
        Gate::define('manage_fee_schedule', fn (User $user): bool => $user->hasPermission('manage_fee_schedule'));
        Gate::define('manage_staff_records', fn (User $user): bool => $user->hasPermission('manage_staff_records'));
        Gate::define('view_staff_salary', fn (User $user): bool => $user->hasPermission('view_staff_salary'));
        Gate::define('view_staff_disciplinary', fn (User $user): bool => $user->hasPermission('view_staff_disciplinary'));
        Gate::define('view_all_sections', fn (User $user): bool => $user->hasPermission('view_all_sections'));
        Gate::define('override_admission_checklist', fn (User $user): bool => $user->hasPermission('override_admission_checklist'));
        Gate::define('view_results_workspace', fn (User $user): bool => $user->hasPermission('manage_result_settings') || $user->hasPermission('enter_results') || $user->hasPermission('approve_results') || $user->hasPermission('add_principal_comment') || $user->hasPermission('add_proprietor_comment'));
        Gate::define('manage_result_settings', fn (User $user): bool => $user->hasPermission('manage_result_settings'));
        Gate::define('enter_results', fn (User $user): bool => $user->hasPermission('enter_results'));
        Gate::define('approve_results', fn (User $user): bool => $user->hasPermission('approve_results'));
        Gate::define('add_principal_comment', fn (User $user): bool => $user->hasPermission('add_principal_comment'));
        Gate::define('add_proprietor_comment', fn (User $user): bool => $user->hasPermission('add_proprietor_comment'));
        Gate::define('manage_cbt', fn (User $user): bool => $user->hasPermission('manage_cbt'));
        Gate::define('approve_cbt_results', fn (User $user): bool => $user->hasPermission('approve_cbt_results'));
        Gate::define('mark_attendance', fn (User $user): bool => $user->hasPermission('mark_attendance'));
        Gate::define('manage_attendance', fn (User $user): bool => $user->hasPermission('manage_attendance'));
        Gate::define('view_attendance_alerts', fn (User $user): bool => $user->hasPermission('view_attendance_alerts'));
        Gate::define('add_class_teacher_comment', fn (User $user, ResultCard $card): bool => $user->hasPermission('enter_results') && app(FormTeacherAssignments::class)->isFormTeacher($card->class_arm_id, $user));
        Gate::define('manage_assignments', fn (User $user): bool => $user->hasPermission('manage_assignments'));
        Gate::define('mark_assignments', fn (User $user): bool => $user->hasPermission('mark_assignments'));
        Gate::define('manage_enotes', fn (User $user): bool => $user->hasPermission('manage_enotes'));
        Gate::define('upload_enotes', fn (User $user): bool => $user->hasPermission('upload_enotes'));
    }
}
