<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AppNavigation;
use App\Modules\Core\Services\DefaultRoleCatalog;
use App\Modules\Core\Services\SessionRevoker;
use App\Modules\Core\TenantContext;
use App\Modules\StaffHR\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class StaffSignInAndActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_disables_and_re_enables_sign_in_with_sessions_and_audit_snapshots(): void
    {
        $fixture = $this->fixture();
        $fixture['target']->forceFill(['remember_token' => 'remember-me'])->save();
        $this->sessionFor($fixture['target'], 'target-session');

        $this->actingAs($fixture['admin'])
            ->post(route('staff.access.disable', $fixture['target']))
            ->assertRedirect(route('staff.access.show', $fixture['target']));

        $this->setTenant($fixture);
        $this->assertSame(User::STATUS_DISABLED, $fixture['target']->fresh()->status);
        $this->assertNull($fixture['target']->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'staff_sign_in_disabled',
            'actor_name' => 'Admin Actor',
            'actor_role' => 'School Admin',
            'module' => 'Staff access',
        ]);

        $this->post(route('logout'));
        $this->post(route('login.store'), ['identifier' => $fixture['target']->username, 'password' => 'target-password'])
            ->assertSessionHasErrors('identifier');
        $this->assertGuest();

        $this->setTenant($fixture);
        $fixture['staff']->update(['status' => 'Resigned']);
        $this->actingAs($fixture['admin'])
            ->post(route('staff.access.enable', $fixture['target']))
            ->assertRedirect(route('staff.access.show', $fixture['target']));
        $this->setTenant($fixture);
        $this->assertSame(User::STATUS_ACTIVE, $fixture['target']->fresh()->status);
        $this->assertSame('Resigned', $fixture['staff']->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'staff_sign_in_enabled',
            'actor_name' => 'Admin Actor',
            'actor_role' => 'School Admin',
        ]);

        $this->post(route('logout'));
        $this->post(route('login.store'), ['identifier' => $fixture['target']->username, 'password' => 'target-password'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($fixture['target']);
    }

    public function test_disable_enable_and_exit_enforce_permission_self_and_last_admin_guards(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['head'])->post(route('staff.access.disable', $fixture['target']))->assertForbidden();
        $this->actingAs($fixture['head'])->post(route('staff.access.enable', $fixture['target']))->assertForbidden();
        $this->actingAs($fixture['admin'])->post(route('staff.access.disable', $fixture['admin']))
            ->assertSessionHasErrors('account');
        $this->setTenant($fixture);
        $this->assertSame(User::STATUS_ACTIVE, $fixture['admin']->fresh()->status);

        $managerRole = Role::create(['name' => 'Access Manager']);
        $managerRole->permissions()->attach(Permission::firstOrCreate(['name' => 'manage_staff_access']));
        $manager = $this->user('Access Manager', 'access-manager', $managerRole);
        $fixture['target']->roles()->sync([$fixture['admin_role']->id]);
        $fixture['admin']->forceFill(['status' => User::STATUS_DISABLED])->save();
        $this->actingAs($manager)->post(route('staff.access.disable', $fixture['target']))
            ->assertSessionHasErrors('account');
        $this->setTenant($fixture);
        $this->assertSame(User::STATUS_ACTIVE, $fixture['target']->fresh()->status);

        $hrRole = Role::create(['name' => 'Exit Manager']);
        $hrRole->permissions()->attach(Permission::firstOrCreate(['name' => 'manage_staff_records']));
        $hr = $this->user('Exit Manager', 'exit-manager', $hrRole);
        $selfStaff = Staff::create(['user_id' => $hr->id, 'role_title' => 'HR', 'employment_date' => '2025-01-01', 'contract_type' => 'Permanent', 'status' => 'Active']);
        $this->actingAs($hr)->post(route('staff.exit', $selfStaff), $this->exitData())
            ->assertSessionHasErrors('account');
        $this->setTenant($fixture);
        $this->assertDatabaseMissing('staff_exit_records', ['staff_id' => $selfStaff->id]);

        $soleAdminRole = Role::create(['name' => 'School Admin Alternate']);
        $soleAdminRole->permissions()->attach(Permission::firstOrCreate(['name' => 'manage_staff_records']));
        $exitActor = $this->user('Exit Actor', 'exit-actor', $soleAdminRole);
        $fixture['target']->roles()->sync([$fixture['admin_role']->id]);
        $this->actingAs($exitActor)->post(route('staff.exit', $fixture['staff']), $this->exitData())
            ->assertSessionHasErrors('account');
        $this->setTenant($fixture);
        $this->assertDatabaseMissing('staff_exit_records', ['staff_id' => $fixture['staff']->id]);
        $this->assertSame('Active', $fixture['staff']->fresh()->status);
        $this->assertSame(User::STATUS_ACTIVE, $fixture['target']->fresh()->status);
        $this->assertSame($fixture['school']->id, $fixture['target']->school_id);
    }

    public function test_exit_disables_sign_in_rejects_future_dates_and_rolls_back_on_failure(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-04 10:00:00', 'Africa/Lagos'));
        $fixture = $this->fixture();
        $fixture['target']->forceFill(['remember_token' => 'remember-me'])->save();
        $this->sessionFor($fixture['target'], 'exit-session');

        $this->actingAs($fixture['admin'])->post(route('staff.exit', $fixture['staff']), [
            'exit_type' => 'Resigned',
            'exit_date' => '2026-11-05',
            'notice_period' => 0,
        ])->assertSessionHasErrors('exit_date');
        $this->setTenant($fixture);
        $this->assertSame(User::STATUS_ACTIVE, $fixture['target']->fresh()->status);

        $this->post(route('staff.exit', $fixture['staff']), $this->exitData())
            ->assertRedirect(route('staff.show', $fixture['staff']));
        $this->setTenant($fixture);
        $this->assertSame('Resigned', $fixture['staff']->fresh()->status);
        $this->assertSame(User::STATUS_DISABLED, $fixture['target']->fresh()->status);
        $this->assertDatabaseMissing('sessions', ['id' => 'exit-session']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'staff_exited',
            'actor_name' => 'Admin Actor',
            'actor_role' => 'School Admin',
            'module' => 'Staff HR',
        ]);

        $second = $this->user('Rollback Teacher', 'rollback-teacher', $fixture['teacher_role']);
        $secondStaff = Staff::create(['user_id' => $second->id, 'role_title' => 'Teacher', 'employment_date' => '2025-01-01', 'contract_type' => 'Permanent', 'status' => 'Active']);
        $this->app->instance(SessionRevoker::class, new class extends SessionRevoker
        {
            public function all(User $user): void
            {
                throw new RuntimeException('Simulated session failure.');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($fixture['admin'])->post(route('staff.exit', $secondStaff), $this->exitData());
            $this->fail('The simulated failure did not occur.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated session failure.', $exception->getMessage());
        }
        $this->setTenant($fixture);
        $this->assertSame('Active', $secondStaff->fresh()->status);
        $this->assertSame(User::STATUS_ACTIVE, $second->fresh()->status);
        $this->assertDatabaseMissing('staff_exit_records', ['staff_id' => $secondStaff->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'staff_exited', 'subject_id' => $secondStaff->id]);
    }

    public function test_activity_log_is_admin_only_filtered_paginated_and_hides_metadata(): void
    {
        $fixture = $this->fixture();
        $this->actingAs($fixture['head'])->get(route('activity-log.index'))->assertForbidden();
        $this->actingAs($fixture['bursar'])->get(route('activity-log.index'))->assertForbidden();

        $this->setTenant($fixture);
        AuditLog::create([
            'user_id' => $fixture['admin']->id,
            'actor_name' => null,
            'actor_role' => null,
            'action' => 'staff_password_reset',
            'subject_type' => User::class,
            'subject_id' => $fixture['target']->id,
            'metadata' => ['password' => 'NEVER-SHOW-THIS'],
            'created_at' => CarbonImmutable::parse('2026-11-03 09:00:00', 'Africa/Lagos'),
        ]);
        AuditLog::create([
            'user_id' => $fixture['head']->id,
            'actor_name' => 'Filter Person',
            'actor_role' => 'Principal',
            'module' => 'Academics',
            'action' => 'class_updated',
            'description' => 'Updated JS 1.',
            'created_at' => CarbonImmutable::parse('2026-11-04 11:00:00', 'Africa/Lagos'),
        ]);
        foreach (range(1, 24) as $index) {
            AuditLog::create([
                'user_id' => $fixture['admin']->id,
                'actor_name' => 'Admin Actor',
                'actor_role' => 'School Admin',
                'module' => 'Payments',
                'action' => 'invoice.adjusted',
                'description' => 'Adjusted invoice '.$index.'.',
                'created_at' => CarbonImmutable::parse('2026-11-02 08:00:00', 'Africa/Lagos')->addMinutes($index),
            ]);
        }

        $this->actingAs($fixture['admin'])->get(route('activity-log.index'))
            ->assertOk()
            ->assertSee('Unknown user')
            ->assertSee('Staff Password Reset.')
            ->assertDontSee('NEVER-SHOW-THIS')
            ->assertViewHas('entries', fn ($entries) => $entries->perPage() === 25 && $entries->lastPage() === 2);
        $this->setTenant($fixture);
        $activityItem = collect(app(AppNavigation::class)->items($fixture['admin']))
            ->firstWhere('label', 'Activity log');
        $this->assertSame(route('activity-log.index'), $activityItem['url']);
        $this->get(route('activity-log.index', ['module' => 'Staff access']))
            ->assertOk()->assertSee('Staff Password Reset.')->assertDontSee('Updated JS 1.');
        $this->get(route('activity-log.index', ['person' => 'Filter Person']))
            ->assertOk()->assertSee('Updated JS 1.')->assertDontSee('Adjusted invoice 1.');
        $this->get(route('activity-log.index', ['from' => '2026-11-04', 'to' => '2026-11-04']))
            ->assertOk()->assertSee('Updated JS 1.')->assertDontSee('Staff Password Reset.');
    }

    public function test_additive_permission_migration_reaches_existing_admin_and_removes_nothing(): void
    {
        $school = School::create(['name' => 'Existing School']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $adminRole = Role::create(['name' => 'School Admin']);
        $custom = Permission::firstOrCreate(['name' => 'school_custom_permission']);
        $adminRole->permissions()->attach($custom);
        if ($activityPermission = Permission::where('name', 'view_activity_log')->value('id')) {
            $adminRole->permissions()->detach($activityPermission);
        }

        $migration = require app_path('Modules/Core/Database/Migrations/2026_11_04_000300_grant_activity_log_permission.php');
        $migration->up();

        $permissions = $adminRole->permissions()->pluck('name');
        $this->assertTrue($permissions->contains('view_activity_log'));
        $this->assertTrue($permissions->contains('school_custom_permission'));
        $this->assertNotContains('view_activity_log', app(DefaultRoleCatalog::class)->definitions()['Principal']);
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $permissions = collect(['manage_staff_access', 'manage_staff_records', 'view_activity_log', 'view_dashboard', 'view_all_sections'])
            ->mapWithKeys(fn (string $name) => [$name => Permission::firstOrCreate(['name' => $name])]);
        $adminRole = Role::create(['name' => 'School Admin']);
        $adminRole->permissions()->attach($permissions->pluck('id'));
        $teacherRole = Role::create(['name' => 'Teacher']);
        $headRole = Role::create(['name' => 'Principal']);
        $bursarRole = Role::create(['name' => 'Bursar']);
        $admin = $this->user('Admin Actor', 'admin-actor', $adminRole, 'admin-password');
        $target = $this->user('Target Teacher', 'target-teacher', $teacherRole, 'target-password');
        $head = $this->user('Head User', 'head-user', $headRole);
        $bursar = $this->user('Bursar User', 'bursar-user', $bursarRole);
        $staff = Staff::create(['user_id' => $target->id, 'role_title' => 'Teacher', 'employment_date' => '2025-01-01', 'contract_type' => 'Permanent', 'status' => 'Active']);

        return [
            'school' => $school,
            'admin_role' => $adminRole,
            'teacher_role' => $teacherRole,
            'admin' => $admin,
            'target' => $target,
            'head' => $head,
            'bursar' => $bursar,
            'staff' => $staff,
        ];
    }

    private function user(string $name, string $username, Role $role, string $password = 'long-password-here'): User
    {
        $user = User::create([
            'name' => $name,
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => $password,
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->roles()->attach($role);

        return $user;
    }

    private function sessionFor(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test browser',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);
    }

    private function exitData(): array
    {
        return ['exit_type' => 'Resigned', 'exit_date' => '2026-11-04', 'notice_period' => 0];
    }

    private function setTenant(array $fixture): void
    {
        app(TenantContext::class)->set($fixture['school']->id);
    }
}
