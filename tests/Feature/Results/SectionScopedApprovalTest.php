<?php

namespace Tests\Feature\Results;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Admissions\Models\Student;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Results\Models\GradingScheme;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionScopedApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_cannot_see_or_approve_another_sections_result(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);

        $primaryHead = User::create(['name' => 'Primary Head', 'email' => 'primary-head@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $secondaryHead = User::create(['name' => 'Secondary Head', 'email' => 'secondary-head@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $teacher = User::create(['name' => 'Teacher', 'email' => 'teacher-section@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $primaryLearner = User::create(['name' => 'Primary Learner', 'email' => 'primary-learner@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $secondaryLearner = User::create(['name' => 'Secondary Learner', 'email' => 'secondary-learner@example.test', 'password' => 'long-password-here', 'status' => 'active']);

        $approve = Permission::firstOrCreate(['name' => 'approve_results']);
        $viewAll = Permission::firstOrCreate(['name' => 'view_all_sections']);
        $manageAcademics = Permission::firstOrCreate(['name' => 'manage_academics']);
        $headRole = Role::create(['name' => 'Principal']);
        $headRole->permissions()->attach($approve);
        $adminRole = Role::create(['name' => 'School Admin']);
        $adminRole->permissions()->attach([$approve->id, $viewAll->id, $manageAcademics->id]);
        $primaryHead->roles()->attach($headRole);
        $secondaryHead->roles()->attach($headRole);
        $admin->roles()->attach($adminRole);

        $primary = Section::create(['name' => 'Primary School', 'head_title' => 'Head Teacher']);
        $primary->heads()->attach($primaryHead, ['school_id' => $school->id]);
        $secondary = Section::create(['name' => 'Secondary School', 'head_title' => 'Principal']);
        $secondary->heads()->attach($secondaryHead, ['school_id' => $school->id]);
        $primaryLevel = AcademicLevel::create(['section_id' => $primary->id, 'name' => 'Primary']);
        $secondaryLevel = AcademicLevel::create(['section_id' => $secondary->id, 'name' => 'Junior Secondary']);
        $primaryClass = SchoolClass::create(['academic_level_id' => $primaryLevel->id, 'name' => 'Primary 6']);
        $secondaryClass = SchoolClass::create(['academic_level_id' => $secondaryLevel->id, 'name' => 'JS1']);
        $primaryArm = ClassArm::create(['class_id' => $primaryClass->id, 'name' => 'Gold']);
        $secondaryArm = ClassArm::create(['class_id' => $secondaryClass->id, 'name' => 'Blue']);
        $primaryStudent = Student::create(['user_id' => $primaryLearner->id, 'class_arm_id' => $primaryArm->id, 'admission_date' => '2026-09-01']);
        $secondaryStudent = Student::create(['user_id' => $secondaryLearner->id, 'class_arm_id' => $secondaryArm->id, 'admission_date' => '2026-09-01']);
        $subject = Subject::create(['name' => 'Mathematics']);
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31']);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => '2026-09-01', 'end_date' => '2026-12-18']);
        $scheme = GradingScheme::create(['name' => 'Default']);
        $primaryTemplate = ResultTemplate::create(['academic_level_id' => $primaryLevel->id, 'grading_scheme_id' => $scheme->id, 'name' => 'Primary', 'scope_key' => 'level:'.$primaryLevel->id]);
        $secondaryTemplate = ResultTemplate::create(['academic_level_id' => $secondaryLevel->id, 'grading_scheme_id' => $scheme->id, 'name' => 'Secondary', 'scope_key' => 'level:'.$secondaryLevel->id]);

        $primaryResult = Result::create(['student_id' => $primaryStudent->id, 'class_arm_id' => $primaryArm->id, 'subject_id' => $subject->id, 'term_id' => $term->id, 'result_template_id' => $primaryTemplate->id, 'status' => 'Submitted', 'submitted_by' => $teacher->id]);
        $secondaryResult = Result::create(['student_id' => $secondaryStudent->id, 'class_arm_id' => $secondaryArm->id, 'subject_id' => $subject->id, 'term_id' => $term->id, 'result_template_id' => $secondaryTemplate->id, 'status' => 'Submitted', 'submitted_by' => $teacher->id]);

        $this->actingAs($primaryHead)->get(route('results.index'))
            ->assertOk()->assertSee('Primary Learner')->assertDontSee('Secondary Learner');
        $this->get(route('academics.classes.index'))
            ->assertOk()->assertSee('Primary 6')->assertDontSee('JS1')->assertDontSee('Add a class');
        $this->get(route('academics.classes.index', ['section' => $secondary->id]))->assertForbidden();
        $this->get(route('academics.index'))->assertForbidden();
        $this->post(route('results.approve', $secondaryResult))->assertForbidden();
        $this->post(route('results.approve', $primaryResult))->assertSessionHasNoErrors();
        $this->assertSame('Approved', $primaryResult->fresh()->status);
        $this->assertSame('Submitted', $secondaryResult->fresh()->status);

        $this->actingAs($admin)->get(route('results.index'))
            ->assertOk()->assertSee('Primary Learner')->assertSee('Secondary Learner');
        $this->get(route('academics.classes.index'))
            ->assertOk()->assertSee('Primary 6')->assertSee('JS1')->assertSee('Add a class');
        $this->post(route('results.approve', $secondaryResult))->assertSessionHasNoErrors();
        $this->assertSame('Approved', $secondaryResult->fresh()->status);
    }
}
