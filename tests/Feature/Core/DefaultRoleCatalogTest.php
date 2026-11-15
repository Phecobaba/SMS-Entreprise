<?php

namespace Tests\Feature\Core;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeacherSubjectAssignment;
use App\Modules\Admissions\Models\Student;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\DefaultRoleCatalog;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Services\ResultConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultRoleCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_demo_default_staff_can_perform_their_core_work_without_admin_permissions(): void
    {
        $this->artisan('oakfield:bootstrap-admin', [
            'school-name' => 'Oakfield International Schools',
            'admin-name' => 'School Admin',
            'email' => 'admin@oakfield.test',
        ])->expectsQuestion('Administrator password (at least 8 characters)', 'a-secure-test-password')
            ->assertSuccessful();

        app(TenantContext::class)->set(1);
        $admin = User::where('email', 'admin@oakfield.test')->firstOrFail();
        $head = $this->userWithRole('Head', 'Principal');
        $teacher = $this->userWithRole('Teacher', 'Teacher');
        $bursar = $this->userWithRole('Bursar', 'Bursar');
        $learner = $this->userWithRole('Learner', 'Student');

        foreach (['Principal', 'Teacher', 'Bursar'] as $roleName) {
            $actual = Role::where('name', $roleName)->firstOrFail()->permissions()->pluck('name')->sort()->values()->all();
            $expected = collect(app(DefaultRoleCatalog::class)->definitions()[$roleName])->sort()->values()->all();
            $this->assertSame($expected, $actual, "{$roleName} permissions differ from the catalog.");
            foreach (['approve_payments', 'manage_payment_gateway', 'manage_academics', 'view_all_sections'] as $adminOnly) {
                $this->assertNotContains($adminOnly, $actual, "{$roleName} unexpectedly has {$adminOnly}.");
            }
        }

        app(TenantContext::class)->set(1);
        $section = Section::create(['name' => 'Secondary School', 'head_title' => 'Principal']);
        $section->heads()->attach($head, ['school_id' => 1]);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Secondary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JS 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A', 'form_teacher_user_id' => $teacher->id]);
        $subject = Subject::create(['section_id' => $section->id, 'name' => 'Mathematics']);
        ClassSubject::create(['class_id' => $class->id, 'subject_id' => $subject->id]);
        TeacherSubjectAssignment::create(['user_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id]);
        $student = Student::create(['user_id' => $learner->id, 'class_arm_id' => $arm->id, 'admission_date' => today()]);
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => today()->subMonth(), 'end_date' => today()->addMonths(8)]);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => today()->subMonth(), 'end_date' => today()->addMonths(2)]);

        $configuration = app(ResultConfiguration::class);
        $exam = $configuration->addComponent($section->id, 'Exam', 100);
        $scheme = $configuration->addScheme($section->id, 'Default');
        $configuration->addBand($scheme->id, 0, 100, 'A', 'Pass');
        $configuration->addTemplate('Secondary default', $section->id, $level->id, null, $scheme->id, [$exam->id]);

        $this->actingAs($teacher)->get(route('dashboard'))->assertOk();
        $this->post(route('results.teacher.draft', [$class, $subject, $student]), [
            'scores' => [$exam->id => 80],
        ])->assertSessionHasNoErrors();
        app(TenantContext::class)->set(1);
        $result = Result::firstOrFail();
        $this->post(route('results.teacher.submit', [$class, $subject, $student]))->assertSessionHasNoErrors();

        $this->actingAs($head)->get(route('dashboard'))->assertOk();
        $this->post(route('results.approve', $result))->assertSessionHasNoErrors();
        app(TenantContext::class)->set(1);
        $this->assertSame('Approved', $result->fresh()->status);

        app(TenantContext::class)->set(1);
        $invoice = Invoice::create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'purpose' => 'Uniform',
            'kind' => Invoice::KIND_ONE_OFF,
            'creation_source' => 'single',
            'amount_kobo' => 1250000,
            'due_date' => today(),
            'status' => 'Unpaid',
            'created_by' => $admin->id,
        ]);
        $this->actingAs($bursar)->get(route('dashboard'))->assertOk();
        $this->post(route('payments.manual', $invoice), [
            'bank' => 'GTBank',
            'teller_number' => 'CATALOG-001',
            'paid_on' => today()->toDateString(),
            'amount_naira' => '12,500.00',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('manual_payment_submissions', [
            'invoice_id' => $invoice->id,
            'recorded_by' => $bursar->id,
            'status' => 'Awaiting approval',
        ]);
    }

    public function test_catalog_migration_adds_missing_permissions_and_removes_nothing(): void
    {
        $school = School::create(['name' => 'Existing School']);
        app(TenantContext::class)->set($school->id);
        $role = Role::create(['name' => 'Principal']);
        $custom = Permission::firstOrCreate(['name' => 'school_custom_permission']);
        $role->permissions()->attach($custom);

        $migration = require app_path('Modules/Core/Database/Migrations/2026_10_25_000100_sync_default_role_catalog.php');
        $migration->up();

        $actual = $role->permissions()->pluck('name')->all();
        $this->assertContains('school_custom_permission', $actual);
        foreach (app(DefaultRoleCatalog::class)->definitions()['Principal'] as $permission) {
            $this->assertContains($permission, $actual);
        }
        $this->assertNotContains('approve_payments', $actual);
        $this->assertNotContains('manage_payment_gateway', $actual);
    }

    public function test_parent_directory_migration_adds_read_and_manage_permissions_to_existing_roles(): void
    {
        $school = School::create(['name' => 'Existing Directory School']);
        app(TenantContext::class)->set($school->id);
        foreach (['School Admin', 'Principal', 'Bursar', 'Teacher'] as $name) {
            Role::create(['name' => $name]);
        }

        $migration = require app_path('Modules/Core/Database/Migrations/2026_11_11_000100_grant_parent_directory_permissions.php');
        $migration->up();

        $this->assertTrue(Role::where('name', 'School Admin')->firstOrFail()->permissions()->where('name', 'view_parents')->exists());
        $this->assertTrue(Role::where('name', 'School Admin')->firstOrFail()->permissions()->where('name', 'view_all_parents')->exists());
        $this->assertTrue(Role::where('name', 'School Admin')->firstOrFail()->permissions()->where('name', 'manage_parent_accounts')->exists());
        $this->assertTrue(Role::where('name', 'Principal')->firstOrFail()->permissions()->where('name', 'view_parents')->exists());
        $this->assertFalse(Role::where('name', 'Principal')->firstOrFail()->permissions()->where('name', 'view_all_parents')->exists());
        $this->assertTrue(Role::where('name', 'Bursar')->firstOrFail()->permissions()->where('name', 'view_parents')->exists());
        $this->assertTrue(Role::where('name', 'Bursar')->firstOrFail()->permissions()->where('name', 'view_all_parents')->exists());
        $this->assertFalse(Role::where('name', 'Teacher')->firstOrFail()->permissions()->where('name', 'view_parents')->exists());
    }

    private function userWithRole(string $name, string $roleName): User
    {
        $user = User::create([
            'name' => $name,
            'email' => str($name)->slug().'@catalog.test',
            'password' => 'long-password-here',
            'status' => 'active',
        ]);
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());

        return $user;
    }
}
