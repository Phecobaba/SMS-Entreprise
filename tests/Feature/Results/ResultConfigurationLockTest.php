<?php

namespace Tests\Feature\Results;

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
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Results\Models\AssessmentComponent;
use App\Modules\Results\Models\GradeBand;
use App\Modules\Results\Models\GradingScheme;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultCard;
use App\Modules\Results\Models\ResultItem;
use App\Modules\Results\Models\ResultTemplate;
use App\Modules\Results\Services\ResultCards;
use App\Modules\Results\Services\ResultConfiguration;
use App\Modules\Results\Services\ResultWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ResultConfigurationLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_used_configuration_is_locked_and_unused_configuration_can_be_edited_and_deleted(): void
    {
        $f = $this->fixture();
        $locked = 'This is locked because 1 results use it. Create a new template for later terms.';
        $templateLocked = 'Locked because 1 results use it. Retire it between terms and create a new one.';
        $this->assertSame(1, app(ResultConfiguration::class)->templateResultCount($f['usedTemplate']));

        $this->actingAs($f['admin'])->put(route('results.templates.update', $f['usedTemplate']), $this->templatePayload($f, $f['usedTemplate'], 'Changed'))
            ->assertSessionHasErrors(['template' => $templateLocked]);
        $this->delete(route('results.templates.destroy', $f['usedTemplate']))
            ->assertSessionHasErrors(['template' => $templateLocked]);
        $this->put(route('results.bands.update', $f['usedBand']), $this->bandPayload('A+', 'Excellent'))
            ->assertSessionHasErrors(['band' => $locked]);
        $this->delete(route('results.bands.destroy', $f['usedBand']))
            ->assertSessionHasErrors(['band' => $locked]);
        $this->put(route('results.components.update', $f['usedComponent']), ['name' => 'Changed CA', 'max_score' => 40])
            ->assertSessionHasErrors(['component' => $locked]);
        $this->delete(route('results.components.destroy', $f['usedComponent']))
            ->assertSessionHasErrors(['component' => $locked]);
        $this->put(route('results.schemes.update', $f['usedScheme']), ['name' => 'Changed scale'])
            ->assertSessionHasErrors(['scheme' => $locked]);
        $this->delete(route('results.schemes.destroy', $f['usedScheme']))
            ->assertSessionHasErrors(['scheme' => $locked]);

        $this->tenant($f);
        $this->put(route('results.templates.update', $f['unusedTemplate']), $this->templatePayload($f, $f['unusedTemplate'], 'Later template'))
            ->assertSessionHasNoErrors();
        $this->delete(route('results.templates.destroy', $f['unusedTemplate']))
            ->assertRedirect(route('results.configuration'))
            ->assertSessionHas('status', 'Result template deleted.');
        $this->put(route('results.bands.update', $f['unusedBand']), $this->bandPayload('B', 'Good'))
            ->assertSessionHasNoErrors();
        $this->delete(route('results.bands.destroy', $f['unusedBand']))
            ->assertRedirect(route('results.configuration'))
            ->assertSessionHas('status', 'Grade band deleted.');
        $this->put(route('results.components.update', $f['unusedComponent']), ['name' => 'Project', 'max_score' => 25])
            ->assertSessionHasNoErrors();
        $this->delete(route('results.components.destroy', $f['unusedComponent']))
            ->assertRedirect(route('results.configuration'))
            ->assertSessionHas('status', 'Assessment component deleted.');
        $this->put(route('results.schemes.update', $f['unusedScheme']), ['name' => 'Later scale'])
            ->assertSessionHasNoErrors();
        $this->delete(route('results.schemes.destroy', $f['unusedScheme']))
            ->assertRedirect(route('results.configuration'))
            ->assertSessionHas('status', 'Grade scale deleted.');

        $this->tenant($f);
        $this->assertDatabaseHas('result_templates', ['id' => $f['usedTemplate']->id, 'name' => 'Current template']);
        $this->assertDatabaseMissing('result_templates', ['id' => $f['unusedTemplate']->id]);
        $this->assertDatabaseMissing('grade_bands', ['id' => $f['unusedBand']->id]);
        $this->assertDatabaseMissing('assessment_components', ['id' => $f['unusedComponent']->id]);
        $this->assertDatabaseMissing('grading_schemes', ['id' => $f['unusedScheme']->id]);
        $this->assertSame(8, AuditLog::whereIn('action', [
            'result_template_updated', 'result_template_deleted', 'grade_band_updated',
            'grade_band_deleted', 'assessment_component_updated', 'assessment_component_deleted',
            'grading_scheme_updated', 'grading_scheme_deleted',
        ])->count());
    }

    public function test_incomplete_scale_can_be_built_one_band_at_a_time_while_overlap_and_decimals_are_refused(): void
    {
        $f = $this->fixture();
        $scheme = GradingScheme::create(['section_id' => $f['section']->id, 'name' => 'Whole number scale']);

        $this->actingAs($f['admin'])->post(route('results.bands.store'), [
            'grading_scheme_id' => $scheme->id, 'min_score' => 10, 'max_score' => 20, 'grade' => 'D',
        ])->assertSessionHasNoErrors();
        $this->post(route('results.bands.store'), [
            'grading_scheme_id' => $scheme->id, 'min_score' => 21, 'max_score' => 30, 'grade' => 'C',
        ])->assertSessionHasNoErrors();
        $this->tenant($f);
        $middle = GradeBand::where('grading_scheme_id', $scheme->id)->where('min_score', 21)->firstOrFail();
        $this->post(route('results.bands.store'), [
            'grading_scheme_id' => $scheme->id, 'min_score' => 40, 'max_score' => 50, 'grade' => 'B',
        ])->assertSessionHasNoErrors();
        $this->tenant($f);
        $last = GradeBand::where('grading_scheme_id', $scheme->id)->where('min_score', 40)->firstOrFail();
        $this->put(route('results.bands.update', $last), [
            'min_score' => 60, 'max_score' => 70, 'grade' => 'B', 'remark' => 'Gap remains allowed',
        ])->assertSessionHasNoErrors();
        $this->post(route('results.bands.store'), [
            'grading_scheme_id' => $scheme->id, 'min_score' => 20, 'max_score' => 25, 'grade' => 'Overlap',
        ])->assertSessionHasErrors('min_score');
        $this->post(route('results.bands.store'), [
            'grading_scheme_id' => $scheme->id, 'min_score' => 31.5, 'max_score' => 40, 'grade' => 'Decimal',
        ])->assertSessionHasErrors(['min_score' => 'Grade band bounds must be whole numbers from 0 to 100.']);
        $this->post(route('results.bands.store'), [
            'grading_scheme_id' => $scheme->id, 'min_score' => 31, 'max_score' => 60.5, 'grade' => 'Decimal',
        ])->assertSessionHasErrors(['max_score' => 'Grade band bounds must be whole numbers from 0 to 100.']);
        $this->delete(route('results.bands.destroy', $middle))
            ->assertRedirect(route('results.configuration'))
            ->assertSessionHas('status', 'Grade band deleted.');
        $this->get(route('results.configuration'))->assertOk()
            ->assertSee('Incomplete:')
            ->assertSee('uncovered 0 to 9 and 21 to 59 and 71 to 100');

        $this->tenant($f);
        $roundTrip = GradingScheme::create(['section_id' => $f['section']->id, 'name' => 'Decimal storage scale']);
        GradeBand::create(['grading_scheme_id' => $roundTrip->id, 'min_score' => 0, 'max_score' => 59, 'grade' => 'B']);
        $decimalId = DB::table('grade_bands')->insertGetId([
            'school_id' => $f['school']->id,
            'grading_scheme_id' => $roundTrip->id,
            'min_score' => 60.5,
            'max_score' => 100,
            'grade' => 'A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $decimal = GradeBand::findOrFail($decimalId);
        $this->put(route('results.bands.update', $decimal), [
            'min_score' => 60, 'max_score' => 100, 'grade' => 'A', 'remark' => 'Corrected legacy decimal',
        ])->assertRedirect(route('results.configuration'))->assertSessionHas('status', 'Grade band saved.');
        $this->tenant($f);
        $this->assertEquals(60, DB::table('grade_bands')->where('id', $decimalId)->value('min_score'));
        $this->get(route('results.configuration'))->assertOk()
            ->assertSee('60–100%')->assertDontSee('60.00–100.00%');
    }

    public function test_submission_requires_complete_scale_and_half_marks_round_up_for_grade_and_live_remark(): void
    {
        $f = $this->fixture();
        $teacher = User::create(['name' => 'Scale Teacher', 'email' => 'scale-teacher@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $role = Role::create(['name' => 'Scale Teacher']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'enter_results']));
        $teacher->roles()->attach($role);
        $f['section']->heads()->attach($teacher, ['school_id' => $f['school']->id]);
        $configuration = app(ResultConfiguration::class);
        $component = $configuration->addComponent($f['section']->id, 'Rounding exam', 100);
        $workflow = app(ResultWorkflow::class);

        $incomplete = $configuration->addScheme($f['section']->id, 'Incomplete scale');
        $configuration->addBand($incomplete->id, 10, 100, 'P', 'Pass');
        [$studentOne, $subjectOne] = $this->resultContext($f, 'Incomplete', $teacher, $incomplete, $component);
        $draft = $workflow->saveDraft($studentOne->id, $subjectOne->id, $f['term']->id, [$component->id => 50], $teacher);
        try {
            $workflow->submit($draft, $teacher);
            $this->fail('Incomplete grade scale should prevent submission.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Uncovered marks: 0 to 9', $exception->errors()['grade'][0]);
        }

        $complete = $configuration->addScheme($f['section']->id, 'Rounded scale');
        $configuration->addBand($complete->id, 0, 20, 'D', 'Below target');
        $configuration->addBand($complete->id, 21, 100, 'A', 'Rounded up');
        [$studentTwo, $subjectTwo] = $this->resultContext($f, 'Rounded', $teacher, $complete, $component);
        $rounded = $workflow->saveDraft($studentTwo->id, $subjectTwo->id, $f['term']->id, [$component->id => 20.5], $teacher);
        $workflow->submit($rounded, $teacher);
        $this->assertSame('20.50', $rounded->fresh()->percentage);
        $this->assertSame('A', $rounded->fresh()->grade);

        $adminRole = $f['admin']->roles()->firstOrFail();
        $adminRole->permissions()->syncWithoutDetaching(Permission::firstOrCreate(['name' => 'approve_results']));
        $workflow->approve($rounded->fresh(), $f['admin']);
        $card = ResultCard::where('student_id', $studentTwo->id)->where('term_id', $f['term']->id)->firstOrFail();
        $detail = app(ResultCards::class)->detail($card);
        $this->assertSame('Rounded up', $detail['subjectRemarks'][$rounded->id]);
    }

    public function test_band_changes_allow_gaps_but_not_overlap_and_referenced_component_cannot_be_deleted(): void
    {
        $f = $this->fixture();
        $scheme = GradingScheme::create(['section_id' => $f['section']->id, 'name' => 'Three bands']);
        $first = GradeBand::create(['grading_scheme_id' => $scheme->id, 'min_score' => 0, 'max_score' => 39, 'grade' => 'C']);
        $middle = GradeBand::create(['grading_scheme_id' => $scheme->id, 'min_score' => 40, 'max_score' => 69, 'grade' => 'B']);
        GradeBand::create(['grading_scheme_id' => $scheme->id, 'min_score' => 70, 'max_score' => 100, 'grade' => 'A']);
        $referenced = AssessmentComponent::create(['section_id' => $f['section']->id, 'name' => 'Referenced only', 'max_score' => 10]);
        $template = app(ResultConfiguration::class)->addTemplate('Reference holder', $f['section']->id, $f['secondLevel']->id, null, $scheme->id, [$referenced->id]);

        $this->actingAs($f['admin'])->put(route('results.bands.update', $middle), [
            'min_score' => 30, 'max_score' => 69, 'grade' => 'B', 'remark' => 'Overlap',
        ])->assertSessionHasErrors('min_score');
        $this->put(route('results.bands.update', $middle), [
            'min_score' => 45, 'max_score' => 69, 'grade' => 'B', 'remark' => 'Gap',
        ])->assertSessionHasNoErrors();
        $this->delete(route('results.bands.destroy', $middle))
            ->assertRedirect(route('results.configuration'))
            ->assertSessionHas('status', 'Grade band deleted.');
        $this->delete(route('results.components.destroy', $referenced))->assertSessionHasErrors('component');

        $this->tenant($f);
        $this->assertDatabaseMissing('grade_bands', ['id' => $middle->id]);
        $this->assertDatabaseHas('assessment_components', ['id' => $referenced->id]);
        $this->assertDatabaseHas('result_templates', ['id' => $template->id]);
        $this->assertDatabaseHas('grade_bands', ['id' => $first->id]);
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $permission = Permission::firstOrCreate(['name' => 'manage_result_settings']);
        $viewAll = Permission::firstOrCreate(['name' => 'view_all_sections']);
        $role = Role::create(['name' => 'School Admin']);
        $role->permissions()->attach([$permission->id, $viewAll->id]);
        $admin = User::create(['name' => 'Admin', 'email' => 'config-admin@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $admin->roles()->attach($role);
        $studentUser = User::create(['name' => 'Student', 'email' => 'config-student@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $section = Section::create(['name' => 'Secondary', 'head_title' => 'Principal']);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Junior Secondary']);
        $secondLevel = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Senior Secondary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JS1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A']);
        $student = Student::create(['user_id' => $studentUser->id, 'class_arm_id' => $arm->id, 'admission_date' => '2026-09-01']);
        $subject = Subject::create(['section_id' => $section->id, 'name' => 'Mathematics']);
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31']);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => '2026-09-01', 'end_date' => '2026-12-18']);
        $configuration = app(ResultConfiguration::class);
        $usedScheme = $configuration->addScheme($section->id, 'Used scheme');
        $usedBand = $configuration->addBand($usedScheme->id, 0, 100, 'A', 'Excellent');
        $usedComponent = $configuration->addComponent($section->id, 'Exam', 100);
        $usedTemplate = $configuration->addTemplate('Current template', $section->id, $level->id, null, $usedScheme->id, [$usedComponent->id]);
        $result = Result::create([
            'student_id' => $student->id, 'class_arm_id' => $arm->id, 'subject_id' => $subject->id,
            'term_id' => $term->id, 'result_template_id' => $usedTemplate->id, 'status' => 'Draft',
        ]);
        ResultItem::create(['result_id' => $result->id, 'assessment_component_id' => $usedComponent->id, 'score' => 80]);
        $unusedScheme = $configuration->addScheme($section->id, 'Unused scheme');
        $unusedBand = $configuration->addBand($unusedScheme->id, 0, 100, 'C', 'Fair');
        $unusedComponent = $configuration->addComponent($section->id, 'Unused CA', 20);
        $unusedTemplate = $configuration->addTemplate('Unused template', $section->id, $level->id, $class->id, $unusedScheme->id, [$usedComponent->id]);

        return compact('school', 'admin', 'section', 'level', 'secondLevel', 'class', 'term', 'usedScheme', 'usedTemplate', 'usedBand', 'usedComponent', 'unusedTemplate', 'unusedBand', 'unusedComponent', 'unusedScheme');
    }

    private function templatePayload(array $f, ResultTemplate $template, string $name): array
    {
        return [
            'name' => $name,
            'academic_level_id' => $template->academic_level_id,
            'class_id' => $template->class_id,
            'grading_scheme_id' => $template->grading_scheme_id,
            'components' => $template->components()->pluck('assessment_component_id')->all(),
        ];
    }

    private function bandPayload(string $grade, string $remark): array
    {
        return ['min_score' => 0, 'max_score' => 100, 'grade' => $grade, 'remark' => $remark];
    }

    private function tenant(array $fixture): void
    {
        app(TenantContext::class)->set($fixture['school']->id);
    }

    private function resultContext(array $f, string $name, User $teacher, GradingScheme $scheme, AssessmentComponent $component): array
    {
        $level = AcademicLevel::create(['section_id' => $f['section']->id, 'name' => $name.' level']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => $name.' class']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A']);
        $studentUser = User::create(['name' => $name.' Student', 'email' => strtolower($name).'@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $student = Student::create(['user_id' => $studentUser->id, 'class_arm_id' => $arm->id, 'admission_date' => '2026-09-01']);
        $subject = Subject::create(['section_id' => $f['section']->id, 'name' => $name.' Subject']);
        ClassSubject::create(['class_id' => $class->id, 'subject_id' => $subject->id]);
        TeacherSubjectAssignment::create(['user_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id]);
        app(ResultConfiguration::class)->addTemplate($name.' template', $f['section']->id, $level->id, null, $scheme->id, [$component->id]);

        return [$student, $subject];
    }
}
