<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AppNavigation;
use App\Modules\Core\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthenticationAndPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_navigation_has_attendance_and_alerts_while_teacher_keeps_attendance(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);

        $permissions = collect([
            'view_all_sections', 'mark_attendance', 'view_attendance_alerts', 'enter_results',
        ])->mapWithKeys(fn (string $name) => [$name => Permission::firstOrCreate(['name' => $name])]);
        $adminRole = Role::create(['name' => 'School Admin']);
        $adminRole->permissions()->attach($permissions->only([
            'view_all_sections', 'mark_attendance', 'view_attendance_alerts',
        ])->pluck('id'));
        $teacherRole = Role::create(['name' => 'Teacher']);
        $teacherRole->permissions()->attach($permissions->only([
            'mark_attendance', 'enter_results',
        ])->pluck('id'));

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password']);
        $teacher = User::create(['name' => 'Teacher', 'email' => 'teacher@example.test', 'password' => 'password']);
        $admin->roles()->attach($adminRole);
        $teacher->roles()->attach($teacherRole);

        $navigation = app(AppNavigation::class);
        $adminItems = collect($navigation->items($admin));
        $teacherItems = collect($navigation->items($teacher));

        $this->assertSame(route('attendance.index'), $adminItems->firstWhere('label', 'Attendance')['url']);
        $this->assertSame(route('attendance.alerts'), $adminItems->firstWhere('label', 'Alerts')['url']);
        $this->assertSame(route('attendance.index'), $teacherItems->firstWhere('label', 'Attendance')['url']);
    }

    public function test_login_is_scoped_to_the_school_and_dashboard_requires_a_permission(): void
    {
        $firstSchool = School::create(['name' => 'First School']);
        $secondSchool = School::create(['name' => 'Second School']);
        config()->set('tenancy.default_school_id', $firstSchool->id);
        $context = app(TenantContext::class);

        $context->set($firstSchool->id);
        $user = User::create([
            'name' => 'First User',
            'email' => 'shared@example.test',
            'password' => 'first-password',
        ]);

        $context->set($secondSchool->id);
        User::create([
            'name' => 'Second User',
            'email' => 'shared@example.test',
            'password' => 'second-password',
        ]);
        $context->clear();

        $this->post('/login', ['email' => 'shared@example.test', 'password' => 'second-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'shared@example.test', 'password' => 'first-password'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertForbidden();

        $context->set($firstSchool->id);
        $role = Role::create(['name' => 'School Admin']);
        $permission = Permission::create(['name' => 'view_dashboard']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        $this->assertSame(1, DB::table('role_permissions')->count());
        $this->assertSame(1, DB::table('user_roles')->count());
        $this->assertSame($firstSchool->id, DB::table('role_permissions')->value('school_id'));
        $this->assertSame($firstSchool->id, DB::table('user_roles')->value('school_id'));
        $this->assertTrue($user->roles()->exists());
        $this->assertTrue($role->permissions()->exists());
        $this->assertTrue($user->hasPermission('view_dashboard'));
        $context->clear();

        $this->get('/dashboard')->assertOk()->assertSee('Good')->assertSee('First User')->assertSee('Welcome to your Oakfield workspace');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
