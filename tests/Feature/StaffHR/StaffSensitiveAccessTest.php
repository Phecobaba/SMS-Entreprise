<?php

namespace Tests\Feature\StaffHR;

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
}
