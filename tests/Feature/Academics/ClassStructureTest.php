<?php

namespace Tests\Feature\Academics;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\Department;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Services\ClassOrderingService;
use App\Modules\Admissions\Models\Student;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Results\Models\GradingScheme;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_scope_position_rules_and_admin_override_are_enforced(): void
    {
        $f = $this->fixture();
        $science = Department::create(['name' => 'Science']);
        $arts = Department::create(['name' => 'Arts']);
        $primaryA = SchoolClass::create(['academic_level_id' => $f['primaryLevel']->id, 'department_id' => $science->id, 'name' => 'Primary 1 Science', 'position' => 1]);
        $primaryB = SchoolClass::create(['academic_level_id' => $f['primaryLevel']->id, 'department_id' => $arts->id, 'name' => 'Primary 1 Arts', 'position' => 1]);
        $primaryC = SchoolClass::create(['academic_level_id' => $f['primaryLevel']->id, 'department_id' => $science->id, 'name' => 'Primary 2', 'position' => 2]);
        $secondary = SchoolClass::create(['academic_level_id' => $f['secondaryLevel']->id, 'department_id' => $science->id, 'name' => 'JS1', 'position' => 3]);
        $f['primary']->update(['classes_arranged_by' => $f['admin']->id, 'classes_arranged_at' => now()]);
        $f['secondary']->update(['classes_arranged_by' => $f['admin']->id, 'classes_arranged_at' => now()]);

        $this->actingAs($f['head'])->get(route('academics.classes.index'))
            ->assertOk()->assertSee('Primary 1 Science')->assertDontSee('JS1')->assertSee('Add a class');
        $this->actingAs($f['admin'])->get(route('academics.classes.index'))
            ->assertOk()->assertSee('All sections')->assertSee('Primary School')->assertSee('Secondary School');
        $this->actingAs($f['head']);
        app(TenantContext::class)->set($f['school']->id);
        $options = app(ClassOrderingService::class)->comesAfterOptions($f['head'], $f['primaryLevel']);
        $this->assertEqualsCanonicalizing([$primaryA->id, $primaryB->id, $primaryC->id], $options->pluck('id')->all());

        $this->post(route('academics.classes.store'), [
            'academic_level_id' => $f['secondaryLevel']->id, 'name' => 'Edited id', 'department_id' => $arts->id,
            'comes_after_id' => $secondary->id, 'placement' => 'new',
        ])->assertForbidden();
        $this->put(route('academics.classes.update', $secondary), [
            'academic_level_id' => $f['secondaryLevel']->id, 'name' => 'Changed', 'department_id' => $science->id,
            'comes_after_id' => null, 'placement' => 'new',
        ])->assertForbidden();
        $this->put(route('academics.classes.update', $primaryC), [
            'academic_level_id' => $f['secondaryLevel']->id, 'name' => $primaryC->name, 'department_id' => $science->id,
            'comes_after_id' => $secondary->id, 'placement' => 'new',
        ])->assertForbidden();
        $this->post(route('academics.classes.move', $secondary), ['direction' => 'up'])->assertForbidden();
        $this->delete(route('academics.classes.destroy', $secondary))->assertForbidden();

        $this->post(route('academics.classes.store'), [
            'academic_level_id' => $f['primaryLevel']->id, 'name' => 'Duplicate step', 'department_id' => $science->id,
            'comes_after_id' => $primaryA->id, 'placement' => 'same',
        ])->assertSessionHasErrors('department_id');
        app(TenantContext::class)->set($f['school']->id);
        $this->assertSame([1, 1, 2, 3], SchoolClass::orderBy('id')->pluck('position')->all());

        $this->post(route('academics.classes.move', $primaryC), ['direction' => 'up'])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($f['school']->id);
        $this->assertSame(2, $primaryA->fresh()->position);
        $this->assertSame(2, $primaryB->fresh()->position);
        $this->assertSame(1, $primaryC->fresh()->position);
        $this->assertSame(3, $secondary->fresh()->position);
        $this->assertSame($science->id, $primaryA->fresh()->department_id);
        $this->assertSame($arts->id, $primaryB->fresh()->department_id);

        $this->actingAs($f['admin'])->post(route('academics.classes.store'), [
            'academic_level_id' => $f['secondaryLevel']->id, 'name' => 'JS2', 'department_id' => $arts->id,
            'comes_after_id' => $secondary->id, 'placement' => 'new',
        ])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($f['school']->id);
        $js2 = SchoolClass::where('name', 'JS2')->firstOrFail();
        $this->assertSame(4, $js2->position);

        $this->put(route('academics.classes.update', $js2), [
            'academic_level_id' => $f['primaryLevel']->id, 'name' => 'Primary 3', 'department_id' => $arts->id,
            'comes_after_id' => $primaryA->id, 'placement' => 'new',
        ])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($f['school']->id);
        $this->assertSame($f['primaryLevel']->id, $js2->fresh()->academic_level_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'class_level_changed', 'subject_id' => $js2->id]);
        $this->post(route('academics.classes.move', $js2), ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->delete(route('academics.classes.destroy', $js2))->assertSessionHasNoErrors();
        app(TenantContext::class)->set($f['school']->id);
        $this->assertDatabaseMissing('classes', ['id' => $js2->id]);
    }

    public function test_arrangement_guess_is_not_written_and_head_cannot_save_another_section(): void
    {
        $f = $this->fixture();
        $primary10 = SchoolClass::create(['academic_level_id' => $f['primaryLevel']->id, 'name' => 'Primary 10']);
        $primary2 = SchoolClass::create(['academic_level_id' => $f['primaryLevel']->id, 'name' => 'Primary 2']);
        $secondary = SchoolClass::create(['academic_level_id' => $f['secondaryLevel']->id, 'name' => 'JS1']);

        $this->actingAs($f['head'])->get(route('academics.classes.arrange'))
            ->assertOk()->assertSeeInOrder(['Primary 2', 'Primary 10'])->assertDontSee('JS1');
        $this->assertNull($primary2->fresh()->position);
        $this->assertNull($primary10->fresh()->position);
        $this->assertNull($f['primary']->fresh()->classes_arranged_at);

        $this->post(route('academics.classes.arrange.store'), [
            'section_ids' => [$f['secondary']->id],
            'steps' => [$f['secondary']->id => [(string) $secondary->id]],
        ])->assertForbidden();
        $this->assertNull($secondary->fresh()->position);

        $this->post(route('academics.classes.arrange.store'), [
            'section_ids' => [$f['primary']->id],
            'steps' => [$f['primary']->id => [(string) $primary2->id, (string) $primary10->id]],
        ])->assertRedirect(route('academics.classes.arrange'));
        $this->assertSame(1, $primary2->fresh()->position);
        $this->assertSame(2, $primary10->fresh()->position);
        $this->assertNotNull($f['primary']->fresh()->classes_arranged_at);
        $this->assertNull($secondary->fresh()->position);
    }

    public function test_deletion_is_blocked_by_each_history_type_and_empty_class_is_deleted(): void
    {
        $f = $this->fixture();
        $subject = Subject::create(['name' => 'Mathematics']);
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31']);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => '2026-09-01', 'end_date' => '2026-12-18']);
        $scheme = GradingScheme::create(['name' => 'Default']);
        $template = ResultTemplate::create(['academic_level_id' => $f['primaryLevel']->id, 'grading_scheme_id' => $scheme->id, 'name' => 'Primary', 'scope_key' => 'level:'.$f['primaryLevel']->id]);
        $protected = SchoolClass::create(['academic_level_id' => $f['primaryLevel']->id, 'name' => 'Protected', 'position' => 1]);
        $arm = ClassArm::create(['class_id' => $protected->id, 'name' => 'Gold']);
        $studentUser = User::create(['name' => 'Learner', 'email' => 'history-learner@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $student = Student::create(['user_id' => $studentUser->id, 'class_arm_id' => $arm->id, 'admission_date' => '2026-09-01']);
        Result::create(['student_id' => $student->id, 'class_arm_id' => $arm->id, 'subject_id' => $subject->id, 'term_id' => $term->id, 'result_template_id' => $template->id, 'status' => 'Draft']);
        AttendanceRecord::create(['student_id' => $student->id, 'class_arm_id' => $arm->id, 'date' => '2026-10-18', 'session' => 'Morning', 'status' => 'Present', 'marked_by' => $f['head']->id]);
        Invoice::create(['student_id' => $student->id, 'term_id' => $term->id, 'purpose' => 'Tuition', 'amount_kobo' => 100000, 'due_date' => '2026-10-31', 'status' => 'Unpaid', 'created_by' => $f['admin']->id]);
        $empty = SchoolClass::create(['academic_level_id' => $f['primaryLevel']->id, 'name' => 'Empty', 'position' => 2]);

        $this->actingAs($f['head'])->delete(route('academics.classes.destroy', $protected))
            ->assertSessionHasErrors('class');
        $message = session('errors')->first('class');
        $this->assertStringContainsString('students', $message);
        $this->assertStringContainsString('results', $message);
        $this->assertStringContainsString('attendance', $message);
        $this->assertStringContainsString('invoices', $message);
        $this->assertDatabaseHas('classes', ['id' => $protected->id]);

        $this->delete(route('academics.classes.destroy', $empty))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('classes', ['id' => $empty->id]);
        $this->assertDatabaseHas('results', ['student_id' => $student->id]);
        $this->assertDatabaseHas('attendance_records', ['student_id' => $student->id]);
        $this->assertDatabaseHas('invoices', ['student_id' => $student->id]);
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $head = User::create(['name' => 'Primary Head', 'email' => 'class-head@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'class-admin@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $manageSection = Permission::firstOrCreate(['name' => 'manage_section_classes']);
        $manageAcademics = Permission::firstOrCreate(['name' => 'manage_academics']);
        $viewAll = Permission::firstOrCreate(['name' => 'view_all_sections']);
        $headRole = Role::create(['name' => 'Principal']);
        $headRole->permissions()->attach($manageSection);
        $adminRole = Role::create(['name' => 'School Admin']);
        $adminRole->permissions()->attach([$manageAcademics->id, $viewAll->id]);
        $head->roles()->attach($headRole);
        $admin->roles()->attach($adminRole);
        $primary = Section::create(['name' => 'Primary School', 'head_title' => 'Head Teacher']);
        $secondary = Section::create(['name' => 'Secondary School', 'head_title' => 'Principal']);
        $primary->heads()->attach($head, ['school_id' => $school->id]);
        $primaryLevel = AcademicLevel::create(['section_id' => $primary->id, 'name' => 'Primary']);
        $secondaryLevel = AcademicLevel::create(['section_id' => $secondary->id, 'name' => 'Junior Secondary']);

        return compact('school', 'head', 'admin', 'primary', 'secondary', 'primaryLevel', 'secondaryLevel');
    }
}
