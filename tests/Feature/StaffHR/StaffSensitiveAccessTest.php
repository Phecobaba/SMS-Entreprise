<?php

namespace Tests\Feature\StaffHR;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\StaffHR\Models\Staff;
use App\Modules\StaffHR\Models\StaffDocument;
use App\Modules\StaffHR\Models\StaffSensitiveViewAudit;
use App\Modules\StaffHR\Services\StaffRecords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffSensitiveAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_salary_progression_and_every_sensitive_view_is_audited_with_split_permissions(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $employee = User::create(['name' => 'Teacher', 'email' => 'staff-teacher@example.test', 'password' => 'long-password-here']);
        $hr = User::create(['name' => 'HR Manager', 'email' => 'hr@example.test', 'password' => 'long-password-here']);
        $salaryViewer = User::create(['name' => 'Salary Viewer', 'email' => 'salary@example.test', 'password' => 'long-password-here']);
        $hrRole = Role::create(['name' => 'HR']);
        $hrRole->permissions()->attach(Permission::firstOrCreate(['name' => 'manage_staff_records']));
        $hrRole->permissions()->attach(Permission::firstOrCreate(['name' => 'view_staff_disciplinary']));
        $hr->roles()->attach($hrRole);
        $salaryRole = Role::create(['name' => 'Salary Reader']);
        $salaryRole->permissions()->attach(Permission::firstOrCreate(['name' => 'view_staff_salary']));
        $salaryViewer->roles()->attach($salaryRole);
        $staff = Staff::create(['user_id' => $employee->id, 'role_title' => 'Teacher', 'employment_date' => '2025-01-01', 'contract_type' => 'Permanent', 'status' => 'Active']);
        Storage::fake('local');
        $this->actingAs($hr)->get(route('staff.index'))->assertOk()->assertSee('Teacher');
        $this->get(route('staff.show', $staff))->assertOk();
        $this->post(route('staff.documents.store', $staff), ['document_type' => 'Contract', 'file' => UploadedFile::fake()->createWithContent('contract.txt', 'Private contract')])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($school->id);
        $document = StaffDocument::where('staff_id', $staff->id)->firstOrFail();
        $this->get(route('staff.documents.download', [$staff, $document]))->assertOk();
        $this->actingAs($salaryViewer)->get(route('staff.documents.download', [$staff, $document]))->assertForbidden();

        $otherSchool = School::create(['name' => 'Other School']);
        config()->set('tenancy.default_school_id', $otherSchool->id);
        app(TenantContext::class)->set($otherSchool->id);
        $otherHr = User::create(['name' => 'Other HR', 'email' => 'other-hr@example.test', 'password' => 'long-password-here']);
        $otherHrRole = Role::create(['name' => 'Other HR']);
        $otherHrRole->permissions()->attach(Permission::firstOrCreate(['name' => 'manage_staff_records']));
        $otherHr->roles()->attach($otherHrRole);
        $this->actingAs($otherHr)->get(route('staff.documents.download', [$staff, $document]))->assertNotFound();

        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $this->actingAs($hr)->post(route('staff.exit', $staff), ['exit_type' => 'Sacked', 'exit_date' => '2026-03-01', 'notice_period' => 0])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($school->id);
        $this->assertSame('Terminated', $staff->fresh()->status);
        $records = app(StaffRecords::class);
        $records->addSalary($staff, 12000000, '2025-01-01', $hr);
        $records->addSalary($staff, 15000000, '2026-01-01', $hr);
        $history = $staff->salaryHistory()->get();
        $this->assertCount(2, $history);
        $this->assertSame([12000000, 15000000], $history->pluck('amount_kobo')->all());
        $this->assertSame('2025-12-31', $history[0]->effective_to->toDateString());
        $this->assertNull($history[1]->effective_to);
        $this->actingAs($hr)->post(route('staff.discipline.store', $staff), ['type' => 'Warning', 'date' => '2026-02-01', 'description' => 'Late filing'])->assertSessionHasNoErrors();
        $this->get(route('staff.salary', $staff))->assertForbidden();
        app(TenantContext::class)->set($school->id);
        $this->assertSame(0, StaffSensitiveViewAudit::count());
        $this->actingAs($salaryViewer)->get(route('staff.salary', $staff))->assertOk()->assertSee('150,000.00');
        $this->get(route('staff.salary', $staff))->assertOk();
        $this->get(route('staff.discipline', $staff))->assertForbidden();
        $this->actingAs($hr)->get(route('staff.discipline', $staff))->assertOk()->assertSee('Late filing');
        app(TenantContext::class)->set($school->id);
        $this->assertSame(2, StaffSensitiveViewAudit::where('data_type', 'Salary')->where('viewed_by', $salaryViewer->id)->count());
        $this->assertSame(1, StaffSensitiveViewAudit::where('data_type', 'Disciplinary')->where('viewed_by', $hr->id)->count());
    }

    public function test_employment_edit_is_authorized_audited_status_safe_and_salary_uses_exact_naira(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $employee = User::create(['name' => 'Teacher', 'email' => 'employee@example.test', 'password' => 'long-password-here']);
        $admin = User::create(['name' => 'HR Admin', 'email' => 'hr-admin@example.test', 'password' => 'long-password-here']);
        $head = User::create(['name' => 'Head', 'email' => 'head-hr@example.test', 'password' => 'long-password-here']);
        $adminRole = Role::create(['name' => 'HR Admin']);
        $adminRole->permissions()->attach(Permission::firstOrCreate(['name' => 'manage_staff_records']));
        $admin->roles()->attach($adminRole);
        $head->roles()->attach(Role::create(['name' => 'Head']));
        $staff = Staff::create(['user_id' => $employee->id, 'role_title' => 'Teacher', 'employment_date' => '2025-01-01', 'contract_type' => 'Permanent', 'status' => 'Active']);

        $this->actingAs($head)->get(route('staff.edit', $staff))->assertForbidden();
        $this->actingAs($admin)->put(route('staff.update', $staff), [
            'role_title' => 'Senior Teacher', 'contract_type' => 'Permanent',
            'employment_date' => '2025-02-01', 'status' => 'Terminated',
        ])->assertRedirect(route('staff.show', $staff));
        app(TenantContext::class)->set($school->id);
        $this->assertSame('Active', $staff->fresh()->status);
        $this->assertSame('Senior Teacher', $staff->fresh()->role_title);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'staff_employment_record_updated', 'subject_id' => $staff->id, 'user_id' => $admin->id,
        ]);
        $this->assertSame('Active', data_get(AuditLog::where('action', 'staff_employment_record_updated')->firstOrFail()->metadata, 'after.status'));

        $this->post(route('staff.salary.store', $staff), ['amount_naira' => '125000.50', 'effective_from' => '2026-01-01'])
            ->assertSessionHasNoErrors();
        app(TenantContext::class)->set($school->id);
        $this->assertDatabaseHas('staff_salary_history', ['staff_id' => $staff->id, 'amount_kobo' => 12500050]);
        $this->post(route('staff.salary.store', $staff), ['amount_naira' => '-1', 'effective_from' => '2026-02-01'])
            ->assertSessionHasErrors('amount_naira');
        $this->post(route('staff.salary.store', $staff), ['amount_naira' => 'not money', 'effective_from' => '2026-02-01'])
            ->assertSessionHasErrors('amount_naira');
    }

    public function test_staff_picker_is_permissioned_bounded_and_excludes_portal_accounts(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $permission = Permission::firstOrCreate(['name' => 'manage_staff_records']);
        $managerRole = Role::create(['name' => 'HR Manager']);
        $managerRole->permissions()->attach([
            $permission->id,
            Permission::firstOrCreate(['name' => 'manage_assignments'])->id,
            Permission::firstOrCreate(['name' => 'mark_assignments'])->id,
        ]);
        $manager = User::create(['name' => 'HR Manager', 'email' => 'picker-hr@example.test', 'password' => 'long-password-here']);
        $manager->roles()->attach($managerRole);
        $teacherRole = Role::create(['name' => 'Teacher']);
        foreach (range(1, 25) as $number) {
            $user = User::create(['name' => 'Search Teacher '.$number, 'email' => "teacher{$number}@example.test", 'password' => 'long-password-here', 'status' => 'active']);
            $user->roles()->attach($teacherRole);
        }
        foreach (['Student', 'Parent', 'Candidate'] as $roleName) {
            $role = Role::create(['name' => $roleName]);
            $user = User::create(['name' => 'Search '.$roleName, 'email' => strtolower($roleName).'@example.test', 'password' => 'long-password-here', 'status' => 'active']);
            $user->roles()->attach($role);
        }
        $mixedPortalAccount = User::create([
            'name' => 'Search Mixed Portal', 'email' => 'mixed-portal@example.test',
            'password' => 'long-password-here', 'status' => 'active',
        ]);
        $mixedPortalAccount->roles()->attach([
            $teacherRole->id,
            Role::where('name', 'Student')->firstOrFail()->id,
        ]);

        $this->actingAs($manager)->getJson(route('staff.accounts.search', ['q' => 'S']))
            ->assertOk()->assertJsonCount(0, 'results');
        $response = $this->getJson(route('staff.accounts.search', ['q' => 'Search']))
            ->assertOk()->assertJsonCount(20, 'results');
        $this->assertSame([], collect($response->json('results'))->pluck('role')->intersect(['Student', 'Parent', 'Candidate'])->all());
        $this->assertNotContains($mixedPortalAccount->id, collect($response->json('results'))->pluck('id')->all());
        $this->getJson(route('assignments.parents.search', ['q' => 'Search']))
            ->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.role', 'Parent');
        app(TenantContext::class)->set($school->id);
        $this->actingAs(User::where('name', 'Search Student')->firstOrFail())
            ->getJson(route('staff.accounts.search', ['q' => 'Search']))->assertForbidden();
        $this->actingAs($manager)->post(route('staff.store'), [
            'user_id' => $mixedPortalAccount->id,
            'role_title' => 'Teacher',
            'employment_date' => '2026-01-01',
            'contract_type' => 'Permanent',
        ])->assertNotFound();
    }
}
