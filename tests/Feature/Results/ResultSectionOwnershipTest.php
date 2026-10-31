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
use App\Modules\Core\Services\DefaultRoleCatalog;
use App\Modules\Core\TenantContext;
use App\Modules\Results\Models\AssessmentComponent;
use App\Modules\Results\Models\GradeBand;
use App\Modules\Results\Models\GradingScheme;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultItem;
use App\Modules\Results\Models\ResultTemplate;
use App\Modules\Results\Models\ResultTemplateComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ResultSectionOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_cannot_manage_configuration_outside_their_section_or_change_ownership(): void
    {
        $f = $this->fixture();
        $outsideScheme = GradingScheme::create(['section_id' => $f['secondary']->id, 'name' => 'Secondary scale']);
        $outsideBand = GradeBand::create(['grading_scheme_id' => $outsideScheme->id, 'min_score' => 0, 'max_score' => 100, 'grade' => 'A']);
        $outsideComponent = AssessmentComponent::create(['section_id' => $f['secondary']->id, 'name' => 'Exam', 'max_score' => 100]);
        $outsideTemplate = ResultTemplate::create(['section_id' => $f['secondary']->id, 'academic_level_id' => $f['secondaryLevel']->id, 'grading_scheme_id' => $outsideScheme->id, 'name' => 'Secondary template', 'scope_key' => 'level:'.$f['secondaryLevel']->id]);
        ResultTemplateComponent::create(['result_template_id' => $outsideTemplate->id, 'assessment_component_id' => $outsideComponent->id, 'position' => 1]);

        $this->actingAs($f['head'])->get(route('results.configuration'))->assertOk()
            ->assertSee('You see and change only Primary')->assertDontSee('Secondary template');
        $this->post(route('results.schemes.store'), ['section_id' => $f['secondary']->id, 'name' => 'Edited ID'])->assertForbidden();
        $this->post(route('results.bands.store'), ['grading_scheme_id' => $outsideScheme->id, 'min_score' => 0, 'max_score' => 100, 'grade' => 'B'])->assertForbidden();
        $this->post(route('results.components.store'), ['section_id' => $f['secondary']->id, 'name' => 'Edited ID', 'max_score' => 20])->assertForbidden();
        $this->post(route('results.templates.store'), ['section_id' => $f['secondary']->id, ...$this->templatePayload($outsideTemplate, $outsideComponent)])->assertForbidden();
        $this->put(route('results.schemes.update', $outsideScheme), ['name' => 'Changed'])->assertForbidden();
        $this->delete(route('results.schemes.destroy', $outsideScheme))->assertForbidden();
        $this->put(route('results.bands.update', $outsideBand), ['min_score' => 0, 'max_score' => 100, 'grade' => 'B'])->assertForbidden();
        $this->delete(route('results.bands.destroy', $outsideBand))->assertForbidden();
        $this->put(route('results.components.update', $outsideComponent), ['name' => 'Changed', 'max_score' => 100])->assertForbidden();
        $this->delete(route('results.components.destroy', $outsideComponent))->assertForbidden();
        $this->put(route('results.templates.update', $outsideTemplate), $this->templatePayload($outsideTemplate, $outsideComponent))->assertForbidden();
        $this->delete(route('results.templates.destroy', $outsideTemplate))->assertForbidden();

        $this->post(route('results.schemes.store'), ['name' => 'Primary scale'])->assertSessionHasNoErrors();
        $this->post(route('results.components.store'), ['name' => 'Primary Exam', 'max_score' => 100])->assertSessionHasNoErrors();
        $this->tenant($f);
        $primaryScheme = GradingScheme::where('section_id', $f['primary']->id)->where('name', 'Primary scale')->firstOrFail();
        $primaryComponent = AssessmentComponent::where('section_id', $f['primary']->id)->where('name', 'Primary Exam')->firstOrFail();
        $this->put(route('results.schemes.update', $primaryScheme), ['section_id' => $f['secondary']->id, 'name' => 'Primary scale'])->assertForbidden();
        $this->put(route('results.components.update', $primaryComponent), ['section_id' => $f['secondary']->id, 'name' => 'Primary Exam', 'max_score' => 100])->assertForbidden();

        $this->actingAs($f['admin'])->post(route('results.schemes.store'), ['section_id' => $f['secondary']->id, 'name' => 'Admin secondary'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('grading_schemes', ['section_id' => $f['secondary']->id, 'name' => 'Admin secondary']);
    }

    public function test_template_rejects_scale_or_components_from_another_section(): void
    {
        $f = $this->fixture();
        $primaryScheme = GradingScheme::create(['section_id' => $f['primary']->id, 'name' => 'Primary scale']);
        $secondaryScheme = GradingScheme::create(['section_id' => $f['secondary']->id, 'name' => 'Secondary scale']);
        $primaryComponent = AssessmentComponent::create(['section_id' => $f['primary']->id, 'name' => 'Exam', 'max_score' => 100]);
        $secondaryComponent = AssessmentComponent::create(['section_id' => $f['secondary']->id, 'name' => 'Exam', 'max_score' => 100]);

        $this->actingAs($f['head'])->post(route('results.templates.store'), [
            'name' => 'Mixed scale', 'academic_level_id' => $f['primaryLevel']->id,
            'grading_scheme_id' => $secondaryScheme->id, 'components' => [$primaryComponent->id],
        ])->assertSessionHasErrors('grading_scheme_id');
        $this->post(route('results.templates.store'), [
            'name' => 'Mixed component', 'academic_level_id' => $f['primaryLevel']->id,
            'grading_scheme_id' => $primaryScheme->id, 'components' => [$secondaryComponent->id],
        ])->assertSessionHasErrors('components');

        $this->actingAs($f['admin'])->post(route('results.templates.store'), [
            'section_id' => $f['primary']->id, 'name' => 'Edited Level',
            'academic_level_id' => $f['secondaryLevel']->id, 'grading_scheme_id' => $secondaryScheme->id,
            'components' => [$secondaryComponent->id],
        ])->assertForbidden();
    }

    public function test_principal_catalog_grants_only_section_result_settings(): void
    {
        $permissions = app(DefaultRoleCatalog::class)->definitions()['Principal'];
        $this->assertContains('manage_section_result_settings', $permissions);
        $this->assertNotContains('manage_result_settings', $permissions);
    }

    public function test_migration_splits_and_repoints_without_changing_result_values_and_refuses_a_second_run(): void
    {
        $f = $this->fixture();
        $scheme = GradingScheme::create(['section_id' => $f['primary']->id, 'name' => 'Shared scale']);
        GradeBand::create(['grading_scheme_id' => $scheme->id, 'min_score' => 0, 'max_score' => 100, 'grade' => 'A', 'remark' => 'Strong']);
        $component = AssessmentComponent::create(['section_id' => $f['primary']->id, 'name' => 'Shared exam', 'max_score' => 100]);
        $primaryTemplate = ResultTemplate::create(['section_id' => $f['primary']->id, 'academic_level_id' => $f['primaryLevel']->id, 'grading_scheme_id' => $scheme->id, 'name' => 'Primary', 'scope_key' => 'level:'.$f['primaryLevel']->id]);
        $secondaryTemplate = ResultTemplate::create(['section_id' => $f['secondary']->id, 'academic_level_id' => $f['secondaryLevel']->id, 'grading_scheme_id' => $scheme->id, 'name' => 'Secondary', 'scope_key' => 'level:'.$f['secondaryLevel']->id]);
        foreach ([$primaryTemplate, $secondaryTemplate] as $template) {
            ResultTemplateComponent::create(['result_template_id' => $template->id, 'assessment_component_id' => $component->id, 'position' => 1]);
        }
        [$primaryResult, $secondaryResult] = $this->results($f, $primaryTemplate, $secondaryTemplate);
        ResultItem::create(['result_id' => $primaryResult->id, 'assessment_component_id' => $component->id, 'score' => 20.5]);
        ResultItem::create(['result_id' => $secondaryResult->id, 'assessment_component_id' => $component->id, 'score' => 20.5]);
        $before = Result::orderBy('id')->get(['id', 'grade', 'remark', 'percentage'])->toArray();
        $counts = [GradingScheme::count(), AssessmentComponent::count(), GradeBand::count()];

        $this->assertSame(0, Artisan::call('oakfield:migrate-result-sections', ['--dry-run' => true]));
        $this->assertSame($counts, [GradingScheme::count(), AssessmentComponent::count(), GradeBand::count()]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'result_configuration_sections_migrated']);

        $this->assertSame(0, Artisan::call('oakfield:migrate-result-sections', ['--actor' => $f['admin']->id]));
        $secondaryTemplate->refresh();
        $secondaryResult->refresh();
        $this->assertSame($f['secondary']->id, $secondaryTemplate->scheme->section_id);
        $secondaryEntry = ResultTemplateComponent::where('result_template_id', $secondaryTemplate->id)->firstOrFail();
        $this->assertSame($f['secondary']->id, $secondaryEntry->component->section_id);
        $this->assertSame($secondaryEntry->assessment_component_id, $secondaryResult->items()->firstOrFail()->assessment_component_id);
        $this->assertSame($before, Result::orderBy('id')->get(['id', 'grade', 'remark', 'percentage'])->toArray());
        $this->assertSame(1, $secondaryTemplate->scheme->bands()->count());
        $afterCounts = [GradingScheme::count(), AssessmentComponent::count(), GradeBand::count()];
        $this->assertSame(1, Artisan::call('oakfield:migrate-result-sections', ['--actor' => $f['admin']->id]));
        $this->assertSame($afterCounts, [GradingScheme::count(), AssessmentComponent::count(), GradeBand::count()]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'result_configuration_sections_migrated', 'user_id' => $f['admin']->id]);
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $admin = User::create(['name' => 'Admin', 'email' => 'result-sections-admin@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $head = User::create(['name' => 'Head', 'email' => 'result-sections-head@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $adminRole = Role::create(['name' => 'School Admin']);
        $headRole = Role::create(['name' => 'Principal']);
        foreach (['manage_result_settings', 'view_all_sections'] as $name) {
            $adminRole->permissions()->attach(Permission::firstOrCreate(['name' => $name]));
        }
        $headRole->permissions()->attach(Permission::firstOrCreate(['name' => 'manage_section_result_settings']));
        $admin->roles()->attach($adminRole);
        $head->roles()->attach($headRole);
        $primary = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher']);
        $secondary = Section::create(['name' => 'Secondary', 'head_title' => 'Principal']);
        $primary->heads()->attach($head, ['school_id' => $school->id]);
        $primaryLevel = AcademicLevel::create(['section_id' => $primary->id, 'name' => 'Primary']);
        $secondaryLevel = AcademicLevel::create(['section_id' => $secondary->id, 'name' => 'Secondary']);
        $primaryClass = SchoolClass::create(['academic_level_id' => $primaryLevel->id, 'name' => 'Primary 1']);
        $secondaryClass = SchoolClass::create(['academic_level_id' => $secondaryLevel->id, 'name' => 'JS1']);
        $primaryArm = ClassArm::create(['class_id' => $primaryClass->id, 'name' => 'A']);
        $secondaryArm = ClassArm::create(['class_id' => $secondaryClass->id, 'name' => 'A']);

        return compact('school', 'admin', 'head', 'primary', 'secondary', 'primaryLevel', 'secondaryLevel', 'primaryClass', 'secondaryClass', 'primaryArm', 'secondaryArm');
    }

    private function templatePayload(ResultTemplate $template, AssessmentComponent $component): array
    {
        return ['name' => $template->name, 'academic_level_id' => $template->academic_level_id, 'grading_scheme_id' => $template->grading_scheme_id, 'components' => [$component->id]];
    }

    private function results(array $f, ResultTemplate $primaryTemplate, ResultTemplate $secondaryTemplate): array
    {
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31']);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => '2026-09-01', 'end_date' => '2026-12-18']);
        $subjectPrimary = Subject::create(['section_id' => $f['primary']->id, 'name' => 'Mathematics']);
        $subjectSecondary = Subject::create(['section_id' => $f['secondary']->id, 'name' => 'Mathematics']);
        $userPrimary = User::create(['name' => 'Primary pupil', 'email' => 'primary-result-section@example.test', 'password' => 'long-password-here']);
        $userSecondary = User::create(['name' => 'Secondary pupil', 'email' => 'secondary-result-section@example.test', 'password' => 'long-password-here']);
        $studentPrimary = Student::create(['user_id' => $userPrimary->id, 'class_arm_id' => $f['primaryArm']->id, 'admission_date' => '2026-09-01']);
        $studentSecondary = Student::create(['user_id' => $userSecondary->id, 'class_arm_id' => $f['secondaryArm']->id, 'admission_date' => '2026-09-01']);

        return [
            Result::create(['student_id' => $studentPrimary->id, 'class_arm_id' => $f['primaryArm']->id, 'subject_id' => $subjectPrimary->id, 'term_id' => $term->id, 'result_template_id' => $primaryTemplate->id, 'status' => 'Approved', 'grade' => 'A', 'remark' => 'Strong', 'percentage' => 20.5]),
            Result::create(['student_id' => $studentSecondary->id, 'class_arm_id' => $f['secondaryArm']->id, 'subject_id' => $subjectSecondary->id, 'term_id' => $term->id, 'result_template_id' => $secondaryTemplate->id, 'status' => 'Approved', 'grade' => 'A', 'remark' => 'Strong', 'percentage' => 20.5]),
        ];
    }

    private function tenant(array $fixture): void
    {
        app(TenantContext::class)->set($fixture['school']->id);
    }
}
