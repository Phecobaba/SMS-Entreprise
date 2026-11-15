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
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultCard;
use App\Modules\Results\Services\ResultCards;
use App\Modules\Results\Services\ResultConfiguration;
use App\Modules\Results\Services\ResultTemplateResolver;
use App\Modules\Results\Services\ResultWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultTemplateScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_most_specific_template_wins_and_section_template_is_the_fallback(): void
    {
        $f = $this->fixture();
        $configuration = app(ResultConfiguration::class);
        $section = $configuration->addTemplate('Secondary default', $f['secondary']->id, null, null, $f['scheme']->id, [$f['component']->id]);
        $level = $configuration->addTemplate('JSS 1 default', $f['secondary']->id, $f['levelOne']->id, null, $f['scheme']->id, [$f['component']->id]);
        $class = $configuration->addTemplate('JSS 1 Gold', $f['secondary']->id, $f['levelOne']->id, $f['classGold']->id, $f['scheme']->id, [$f['component']->id]);

        $resolver = app(ResultTemplateResolver::class);
        $this->assertSame($class->id, $resolver->forClassArm($f['armGold']->id)?->id);
        $this->assertSame($level->id, $resolver->forClassArm($f['armSilver']->id)?->id);
        $this->assertSame($section->id, $resolver->forClassArm($f['armJssTwo']->id)?->id);
    }

    public function test_a_more_specific_template_does_not_change_an_existing_result_or_its_card(): void
    {
        $f = $this->fixture();
        $configuration = app(ResultConfiguration::class);
        $section = $configuration->addTemplate('Secondary default', $f['secondary']->id, null, null, $f['scheme']->id, [$f['component']->id]);
        $result = app(ResultWorkflow::class)->saveDraft(
            $f['student']->id,
            $f['subject']->id,
            $f['term']->id,
            [$f['component']->id => 80],
            $f['teacher'],
        );

        $warning = $configuration->broaderCurrentTermWarning($f['secondary']->id, $f['levelOne']->id, null);
        $this->assertSame(['pupils' => 1, 'template' => 'Secondary default'], $warning);
        $level = $configuration->addTemplate('JSS 1 default', $f['secondary']->id, $f['levelOne']->id, null, $f['scheme']->id, [$f['component']->id]);
        $this->assertSame($level->id, app(ResultTemplateResolver::class)->forClassArm($f['armGold']->id)?->id);
        $this->assertSame($section->id, $result->fresh()->result_template_id);

        $result->update(['status' => 'Approved', 'percentage' => 80, 'grade' => 'A', 'remark' => 'Strong']);
        $card = ResultCard::create([
            'student_id' => $f['student']->id,
            'term_id' => $f['term']->id,
            'class_arm_id' => $f['armGold']->id,
            'compiled_at' => now(),
        ]);
        $cardResult = app(ResultCards::class)->detail($card)['results']->firstOrFail();
        $this->assertSame($section->id, $cardResult->result_template_id);
        $this->assertSame('Secondary default', $cardResult->template->name);
    }

    public function test_duplicate_scope_is_refused_and_a_head_cannot_create_for_another_section(): void
    {
        $f = $this->fixture();
        app(ResultConfiguration::class)->addTemplate('Secondary default', $f['secondary']->id, null, null, $f['scheme']->id, [$f['component']->id]);

        $this->actingAs($f['admin'])->post(route('results.templates.store'), [
            'section_id' => $f['secondary']->id,
            'name' => 'Duplicate',
            'academic_level_id' => '',
            'class_id' => '',
            'grading_scheme_id' => $f['scheme']->id,
            'components' => [$f['component']->id],
        ])->assertSessionHasErrors(['class_id' => 'A result template already exists for this scope.']);

        $this->actingAs($f['head'])->post(route('results.templates.store'), [
            'section_id' => $f['secondary']->id,
            'name' => 'Edited Section',
            'academic_level_id' => '',
            'class_id' => '',
            'grading_scheme_id' => $f['scheme']->id,
            'components' => [$f['component']->id],
        ])->assertForbidden();
    }

    public function test_used_template_scope_is_locked_and_missing_template_has_a_clear_entry_message(): void
    {
        $f = $this->fixture();
        $template = app(ResultConfiguration::class)->addTemplate('Secondary default', $f['secondary']->id, null, null, $f['scheme']->id, [$f['component']->id]);
        Result::create([
            'student_id' => $f['student']->id,
            'class_arm_id' => $f['armGold']->id,
            'subject_id' => $f['subject']->id,
            'term_id' => $f['term']->id,
            'result_template_id' => $template->id,
            'status' => 'Draft',
        ]);

        $this->actingAs($f['admin'])->put(route('results.templates.update', $template), [
            'name' => $template->name,
            'academic_level_id' => $f['levelOne']->id,
            'class_id' => '',
            'grading_scheme_id' => $f['scheme']->id,
            'components' => [$f['component']->id],
        ])->assertSessionHasErrors('template');

        app(TenantContext::class)->set($f['school']->id);
        $studentWithoutTemplate = Student::create([
            'user_id' => User::create(['name' => 'Primary pupil', 'email' => 'primary-template@example.test', 'password' => 'long-password-here'])->id,
            'class_arm_id' => $f['primaryArm']->id,
            'admission_date' => '2026-09-01',
        ]);
        $this->actingAs($f['teacher'])->get(route('results.teacher.entry', [$f['primaryClass'], $f['primarySubject'], $studentWithoutTemplate]))->assertOk()
            ->assertSee('No result template applies to Primary 1. Ask your head or the school office to set one up.')
            ->assertDontSee('Save draft');
    }

    public function test_retiring_between_terms_frees_the_scope_and_writes_an_audit_entry(): void
    {
        $f = $this->fixture();
        $template = app(ResultConfiguration::class)->addTemplate('Old default', $f['secondary']->id, null, null, $f['scheme']->id, [$f['component']->id]);
        $pastTerm = AcademicTerm::create([
            'session_id' => $f['session']->id,
            'name' => 'Past Term',
            'start_date' => today('Africa/Lagos')->subMonths(4),
            'end_date' => today('Africa/Lagos')->subMonths(2),
        ]);
        Result::create([
            'student_id' => $f['student']->id,
            'class_arm_id' => $f['armGold']->id,
            'subject_id' => $f['subject']->id,
            'term_id' => $pastTerm->id,
            'result_template_id' => $template->id,
            'status' => 'Approved',
        ]);

        $this->actingAs($f['admin'])->post(route('results.templates.retire', $template))
            ->assertRedirect(route('results.configuration'));
        app(TenantContext::class)->set($f['school']->id);
        $template->refresh();
        $this->assertNotNull($template->retired_at);
        $this->assertNull($template->active_scope_key);
        $replacement = app(ResultConfiguration::class)->addTemplate('New default', $f['secondary']->id, null, null, $f['scheme']->id, [$f['component']->id]);
        $this->assertSame('section:'.$f['secondary']->id, $replacement->active_scope_key);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $f['admin']->id,
            'action' => 'result_template_retired',
            'subject_id' => $template->id,
        ]);
        $this->assertSame(1, AuditLog::where('action', 'result_template_retired')->firstOrFail()->metadata['result_count']);
    }

    public function test_current_term_use_blocks_retirement_and_head_cannot_retire_another_section(): void
    {
        $f = $this->fixture();
        $template = app(ResultConfiguration::class)->addTemplate('Current default', $f['secondary']->id, null, null, $f['scheme']->id, [$f['component']->id]);
        $unused = app(ResultConfiguration::class)->addTemplate('Unused JSS 2', $f['secondary']->id, $f['levelTwo']->id, null, $f['scheme']->id, [$f['component']->id]);
        Result::create([
            'student_id' => $f['student']->id,
            'class_arm_id' => $f['armGold']->id,
            'subject_id' => $f['subject']->id,
            'term_id' => $f['term']->id,
            'result_template_id' => $template->id,
            'status' => 'Draft',
        ]);

        $this->actingAs($f['admin'])->post(route('results.templates.retire', $unused))
            ->assertSessionHasErrors(['template' => 'No results use this template. Delete it instead.']);
        $this->actingAs($f['admin'])->post(route('results.templates.retire', $template))
            ->assertSessionHasErrors(['template' => '1 results in the current term use this template. Retire it between terms.']);
        $this->actingAs($f['head'])->post(route('results.templates.retire', $template))->assertForbidden();
        $this->assertNull($template->fresh()->retired_at);
    }

    public function test_retired_template_is_read_only_and_existing_results_keep_it_while_new_results_do_not(): void
    {
        $f = $this->fixture();
        $configuration = app(ResultConfiguration::class);
        $template = $configuration->addTemplate('Old default', $f['secondary']->id, null, null, $f['scheme']->id, [$f['component']->id]);
        $pastTerm = AcademicTerm::create([
            'session_id' => $f['session']->id,
            'name' => 'Past Term',
            'start_date' => today('Africa/Lagos')->subMonths(4),
            'end_date' => today('Africa/Lagos')->subMonths(2),
        ]);
        $result = Result::create([
            'student_id' => $f['student']->id,
            'class_arm_id' => $f['armGold']->id,
            'subject_id' => $f['subject']->id,
            'term_id' => $pastTerm->id,
            'result_template_id' => $template->id,
            'status' => 'Approved',
            'percentage' => 80,
            'grade' => 'A',
            'remark' => 'Strong',
        ]);
        $configuration->retireTemplate($template, $f['admin']);

        $this->assertNull(app(ResultTemplateResolver::class)->forClassArm($f['armGold']->id));
        $this->actingAs($f['admin'])->put(route('results.templates.update', $template), [
            'name' => 'Changed',
            'academic_level_id' => '',
            'class_id' => '',
            'grading_scheme_id' => $f['scheme']->id,
            'components' => [$f['component']->id],
        ])->assertSessionHasErrors('template');

        app(TenantContext::class)->set($f['school']->id);
        $replacement = $configuration->addTemplate('New default', $f['secondary']->id, null, null, $f['scheme']->id, [$f['component']->id]);
        $newResult = app(ResultWorkflow::class)->saveDraft(
            $f['student']->id,
            $f['subject']->id,
            $f['term']->id,
            [$f['component']->id => 70],
            $f['teacher'],
        );
        $this->assertSame($replacement->id, $newResult->result_template_id);
        $this->assertSame($template->id, $result->fresh()->result_template_id);

        $card = ResultCard::create([
            'student_id' => $f['student']->id,
            'term_id' => $pastTerm->id,
            'class_arm_id' => $f['armGold']->id,
            'compiled_at' => now(),
        ]);
        $this->assertSame($template->id, app(ResultCards::class)->detail($card)['results']->firstOrFail()->result_template_id);
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);

        $admin = User::create(['name' => 'Admin', 'email' => 'template-admin@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $head = User::create(['name' => 'Primary Head', 'email' => 'template-head@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $teacher = User::create(['name' => 'Teacher', 'email' => 'template-teacher@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $adminRole = Role::create(['name' => 'School Admin']);
        $headRole = Role::create(['name' => 'Head']);
        $teacherRole = Role::create(['name' => 'Teacher']);
        foreach (['manage_result_settings', 'view_all_sections', 'view_results_workspace', 'enter_results'] as $permission) {
            $adminRole->permissions()->attach(Permission::firstOrCreate(['name' => $permission]));
        }
        foreach (['manage_section_result_settings', 'view_results_workspace'] as $permission) {
            $headRole->permissions()->attach(Permission::firstOrCreate(['name' => $permission]));
        }
        foreach (['view_results_workspace', 'enter_results'] as $permission) {
            $teacherRole->permissions()->attach(Permission::firstOrCreate(['name' => $permission]));
        }
        $admin->roles()->attach($adminRole);
        $head->roles()->attach($headRole);
        $teacher->roles()->attach($teacherRole);

        $primary = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher']);
        $secondary = Section::create(['name' => 'Secondary', 'head_title' => 'Principal']);
        $primary->heads()->attach($head, ['school_id' => $school->id]);
        $primaryLevel = AcademicLevel::create(['section_id' => $primary->id, 'name' => 'Primary']);
        $levelOne = AcademicLevel::create(['section_id' => $secondary->id, 'name' => 'JSS 1']);
        $levelTwo = AcademicLevel::create(['section_id' => $secondary->id, 'name' => 'JSS 2']);
        $primaryClass = SchoolClass::create(['academic_level_id' => $primaryLevel->id, 'name' => 'Primary 1']);
        $classGold = SchoolClass::create(['academic_level_id' => $levelOne->id, 'name' => 'Gold']);
        $classSilver = SchoolClass::create(['academic_level_id' => $levelOne->id, 'name' => 'Silver']);
        $classJssTwo = SchoolClass::create(['academic_level_id' => $levelTwo->id, 'name' => 'Blue']);
        $primaryArm = ClassArm::create(['class_id' => $primaryClass->id, 'name' => 'A']);
        $armGold = ClassArm::create(['class_id' => $classGold->id, 'name' => 'A']);
        $armSilver = ClassArm::create(['class_id' => $classSilver->id, 'name' => 'A']);
        $armJssTwo = ClassArm::create(['class_id' => $classJssTwo->id, 'name' => 'A']);

        $subject = Subject::create(['section_id' => $secondary->id, 'name' => 'Mathematics']);
        $primarySubject = Subject::create(['section_id' => $primary->id, 'name' => 'Mathematics']);
        ClassSubject::create(['class_id' => $primaryClass->id, 'subject_id' => $primarySubject->id]);
        foreach ([$classGold, $classSilver, $classJssTwo] as $class) {
            ClassSubject::create(['class_id' => $class->id, 'subject_id' => $subject->id]);
        }
        TeacherSubjectAssignment::create(['user_id' => $teacher->id, 'class_id' => $classGold->id, 'subject_id' => $subject->id]);
        TeacherSubjectAssignment::create(['user_id' => $teacher->id, 'class_id' => $primaryClass->id, 'subject_id' => $primarySubject->id]);
        $studentUser = User::create(['name' => 'Learner', 'email' => 'template-student@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $student = Student::create(['user_id' => $studentUser->id, 'class_arm_id' => $armGold->id, 'admission_date' => '2026-09-01']);
        $session = AcademicSession::create(['name' => 'Current Session', 'start_date' => today('Africa/Lagos')->subYear(), 'end_date' => today('Africa/Lagos')->addYear()]);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'Current Term', 'start_date' => today('Africa/Lagos')->subMonth(), 'end_date' => today('Africa/Lagos')->addMonth()]);

        $configuration = app(ResultConfiguration::class);
        $scheme = $configuration->addScheme($secondary->id, 'Secondary scale');
        $configuration->addBand($scheme->id, 0, 100, 'A', 'Strong');
        $component = $configuration->addComponent($secondary->id, 'Exam', 100);

        return compact(
            'school', 'admin', 'head', 'teacher', 'primary', 'secondary', 'primaryLevel', 'levelOne', 'levelTwo',
            'primaryClass', 'classGold', 'classSilver', 'classJssTwo', 'primaryArm', 'armGold', 'armSilver',
            'armJssTwo', 'subject', 'primarySubject', 'student', 'session', 'term', 'scheme', 'component',
        );
    }
}
