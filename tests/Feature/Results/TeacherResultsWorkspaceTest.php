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
use App\Modules\Results\Models\ResultItem;
use App\Modules\Results\Models\ResultTemplateComponent;
use App\Modules\Results\Services\ResultConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TeacherResultsWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_sees_only_assigned_pairs_and_each_pair_contains_all_arms_of_its_class(): void
    {
        $f = $this->fixture();

        $this->actingAs($f['teacher'])->get(route('results.index'))
            ->assertOk()
            ->assertSee('My classes and subjects')
            ->assertSee('Mathematics · JS 1')
            ->assertSee('Basic Science · JS 2')
            ->assertDontSee('English · JS 1')
            ->assertDontSee(route('results.teacher.class', $f['armA']), false);

        $this->get(route('results.teacher.pair', [$f['classOne'], $f['math']]))
            ->assertOk()
            ->assertSee('Pupil Alpha')
            ->assertSee('Pupil Beta')
            ->assertDontSee('Pupil Other')
            ->assertSee('Mathematics · JS 1');

        $this->get(route('results.teacher.pair', [$f['classOne'], $f['english']]))->assertForbidden();
        $this->get(route('results.teacher.pair', [$f['classOne'], $f['english']]))
            ->assertSee('You are not assigned to this subject and class.');
        $this->get(route('results.teacher.entry', [$f['classOne'], $f['english'], $f['studentA']]))->assertForbidden();
        $this->post(route('results.teacher.draft', [$f['classOne'], $f['english'], $f['studentA']]), [
            'scores' => [$f['component']->id => 70],
        ])->assertForbidden();
        $csv = UploadedFile::fake()->createWithContent('legacy.csv', "student_id,subject_id,term_id,component_id,score\n1,1,1,1,50\n");
        $this->post(route('results.imports.store'), ['file' => $csv])->assertForbidden();
    }

    public function test_form_teacher_can_open_only_their_exact_arms_approved_cards(): void
    {
        $f = $this->fixture();
        $f['armA']->update(['form_teacher_user_id' => $f['teacher']->id]);
        $resultA = $this->approvedResult($f, $f['studentA'], $f['armA']);
        $resultB = $this->approvedResult($f, $f['studentB'], $f['armB']);
        $cardA = ResultCard::create(['student_id' => $f['studentA']->id, 'term_id' => $f['term']->id, 'class_arm_id' => $f['armA']->id, 'compiled_at' => now()]);
        $cardB = ResultCard::create(['student_id' => $f['studentB']->id, 'term_id' => $f['term']->id, 'class_arm_id' => $f['armB']->id, 'compiled_at' => now()]);

        $this->actingAs($f['teacher'])->get(route('results.index'))
            ->assertOk()->assertSee('My class')->assertSee('JS 1 A');
        $this->get(route('results.teacher.class', $f['armA']))
            ->assertOk()->assertSee('Pupil Alpha')->assertDontSee('Pupil Beta')
            ->assertSee(route('results.cards.show', $cardA), false);
        $this->get(route('results.teacher.class', $f['armB']))->assertForbidden();
        $this->get(route('results.cards.show', $cardB))->assertForbidden();
        $this->assertSame('Approved', $resultA->status);
        $this->assertSame('Approved', $resultB->status);
    }

    public function test_teacher_score_entry_uses_the_current_template_and_preserves_submission_locks(): void
    {
        $f = $this->fixture();
        $route = [$f['classOne']->id, $f['math']->id, $f['studentA']->id];

        $this->actingAs($f['teacher'])->get(route('results.teacher.entry', $route))
            ->assertOk()->assertSee('Exam')->assertSee('Maximum 100')->assertSee('Save draft');
        $this->post(route('results.teacher.draft', $route), ['scores' => [$f['component']->id => 81]])
            ->assertRedirect(route('results.teacher.entry', $route));
        $this->get(route('results.teacher.pair', [$f['classOne'], $f['math']]))
            ->assertOk()->assertSee('Continue scores');
        $this->get(route('results.teacher.entry', $route))
            ->assertOk()->assertSee('value="81.00"', false);
        $this->post(route('results.teacher.submit', $route), ['scores' => [$f['component']->id => 84]])
            ->assertRedirect(route('results.teacher.entry', $route));
        app(TenantContext::class)->set($f['school']->id);
        $result = Result::where('student_id', $f['studentA']->id)->where('subject_id', $f['math']->id)->firstOrFail();
        $this->assertSame('Submitted', $result->status);
        $this->assertSame('84.00', $result->total_score);
        $this->assertSame('84.00', $result->items()->firstOrFail()->score);

        $this->post(route('results.teacher.draft', $route), ['scores' => [$f['component']->id => 20]])
            ->assertSessionHasErrors('result');
        app(TenantContext::class)->set($f['school']->id);
        $this->assertSame('Submitted', $result->fresh()->status);
        $this->get(route('results.teacher.entry', $route))
            ->assertOk()->assertSee('Submitted for review')->assertDontSee('Save draft');
    }

    public function test_one_step_submit_is_atomic_when_submission_validation_fails(): void
    {
        $f = $this->fixture();
        $second = app(ResultConfiguration::class)->addComponent($f['section']->id, 'CA', 20);
        ResultTemplateComponent::create([
            'result_template_id' => $f['template']->id,
            'assessment_component_id' => $second->id,
            'position' => 2,
        ]);
        $route = [$f['classOne']->id, $f['math']->id, $f['studentA']->id];

        $this->actingAs($f['teacher'])->post(route('results.teacher.submit', $route), [
            'scores' => [$f['component']->id => 75],
        ])->assertSessionHasErrors('scores');
        $this->assertDatabaseMissing('results', [
            'student_id' => $f['studentA']->id,
            'subject_id' => $f['math']->id,
        ]);

        $this->post(route('results.teacher.submit', $route), [
            'scores' => [$f['component']->id => 75, $second->id => 15],
        ])->assertRedirect(route('results.teacher.entry', $route));
        $this->assertDatabaseHas('results', [
            'student_id' => $f['studentA']->id,
            'subject_id' => $f['math']->id,
            'status' => 'Submitted',
            'total_score' => 90,
        ]);
    }

    public function test_admin_and_head_review_but_cannot_enter_scores_while_rejected_teacher_can_resubmit(): void
    {
        $f = $this->fixture();
        $approve = Permission::firstOrCreate(['name' => 'approve_results']);
        $view = Permission::firstOrCreate(['name' => 'view_results_workspace']);
        $manage = Permission::firstOrCreate(['name' => 'manage_result_settings']);
        $allSections = Permission::firstOrCreate(['name' => 'view_all_sections']);
        $adminRole = Role::create(['name' => 'School Admin']);
        $adminRole->permissions()->attach([$approve->id, $view->id, $manage->id, $allSections->id, Permission::where('name', 'enter_results')->value('id')]);
        $headRole = Role::create(['name' => 'Principal']);
        $headRole->permissions()->attach([$approve->id, $view->id, Permission::where('name', 'enter_results')->value('id')]);
        $admin = User::create(['name' => 'Admin', 'email' => 'review-admin@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $admin->roles()->attach($adminRole);
        $head = User::create(['name' => 'Head', 'email' => 'review-head@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $head->roles()->attach($headRole);
        $f['section']->heads()->attach($head->id, ['school_id' => $f['school']->id]);

        $route = [$f['classOne']->id, $f['math']->id, $f['studentA']->id];
        foreach ([$admin, $head] as $reviewer) {
            $this->actingAs($reviewer)->get(route('results.index'))
                ->assertOk()->assertDontSee('Enter scores')->assertDontSee('Import drafts');
            $this->get(route('results.new', $f['studentA']))->assertForbidden();
            $this->post(route('results.teacher.draft', $route), ['scores' => [$f['component']->id => 75]])->assertForbidden();
            $this->post(route('results.teacher.submit', $route), ['scores' => [$f['component']->id => 75]])->assertForbidden();
            $this->post(route('results.drafts.store'), [
                'student_id' => $f['studentA']->id, 'subject_id' => $f['math']->id,
                'term_id' => $f['term']->id, 'scores' => [$f['component']->id => 75],
            ])->assertForbidden();
            $this->post(route('results.imports.store'))->assertForbidden();
        }

        $this->actingAs($f['teacher'])->post(route('results.teacher.submit', $route), [
            'scores' => [$f['component']->id => 84],
        ])->assertRedirect();
        app(TenantContext::class)->set($f['school']->id);
        $result = Result::where('student_id', $f['studentA']->id)->where('subject_id', $f['math']->id)->firstOrFail();

        $this->actingAs($head)->post(route('results.reject', $result), [])->assertSessionHasErrors('rejection_reason');
        $this->post(route('results.reject', $result), ['rejection_reason' => 'Check the examination score.'])->assertRedirect();
        app(TenantContext::class)->set($f['school']->id);
        $this->assertSame('Rejected', $result->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'result_rejected', 'actor_name' => 'Head']);

        foreach ([$admin, $head] as $reviewer) {
            $this->actingAs($reviewer)->post(route('results.drafts.update', $result), [
                'scores' => [$f['component']->id => 10],
            ])->assertForbidden();
            $this->post(route('results.submit', $result))->assertForbidden();
        }

        $this->actingAs($f['teacher'])->get(route('results.teacher.pair', [$f['classOne'], $f['math']]))
            ->assertOk()->assertSee('Correction requested: Check the examination score.');
        $this->post(route('results.teacher.submit', $route), ['scores' => [$f['component']->id => 86]])->assertRedirect();
        app(TenantContext::class)->set($f['school']->id);
        $this->assertSame('Submitted', $result->fresh()->status);

        $this->actingAs($admin)->post(route('results.approve', $result))->assertRedirect();
        app(TenantContext::class)->set($f['school']->id);
        $this->assertSame('Approved', $result->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'result_approved', 'actor_name' => 'Admin']);
    }

    public function test_pair_page_exposes_the_csv_template_preview_apply_and_cancel_actions(): void
    {
        $f = $this->fixture();

        $this->actingAs($f['teacher'])->get(route('results.teacher.pair', [$f['classOne'], $f['math']]))
            ->assertOk()
            ->assertSee('Download template')
            ->assertSee('Upload and preview');

        $download = $this->get(route('results.teacher.csv.template', [$f['classOne'], $f['math']]))->getContent();
        $response = $this->post(route('results.teacher.csv.preview', [$f['classOne'], $f['math']]), [
            'file' => UploadedFile::fake()->createWithContent('scores.csv', $this->scoreCsv($download, [
                ['SEC-0001', 'Pupil Alpha', '70'],
            ])),
        ]);

        $response->assertOk()->assertSee('Apply scores')->assertSee('Cancel');
    }

    public function test_score_writes_are_refused_with_a_visible_reason_when_there_is_no_current_term(): void
    {
        $f = $this->fixture();
        app(TenantContext::class)->set($f['school']->id);
        $f['term']->update([
            'start_date' => today('Africa/Lagos')->subMonths(2),
            'end_date' => today('Africa/Lagos')->subDay(),
        ]);
        $route = [$f['classOne']->id, $f['math']->id, $f['studentA']->id];

        $this->actingAs($f['teacher'])->get(route('results.teacher.entry', $route))
            ->assertOk()
            ->assertSee('No current term is configured');
        $this->post(route('results.teacher.draft', $route), ['scores' => [$f['component']->id => 75]])
            ->assertRedirect(route('results.teacher.entry', $route))
            ->assertSessionHasErrors(['term' => 'Score entry is unavailable because no current term is configured.']);
        $this->post(route('results.teacher.submit', $route))
            ->assertRedirect(route('results.teacher.entry', $route))
            ->assertSessionHasErrors(['term' => 'Score entry is unavailable because no current term is configured.']);
        $this->assertDatabaseCount('results', 0);
    }

    public function test_pair_csv_is_assignment_scoped_and_stage_is_bound_to_owner_pair_term_and_one_use(): void
    {
        $f = $this->fixture();
        $templateRoute = route('results.teacher.csv.template', [$f['classOne'], $f['math']]);
        $download = $this->actingAs($f['teacher'])->get($templateRoute)->assertOk();
        $this->assertStringStartsWith("\xEF\xBB\xBF# Scores for Mathematics · JS 1 · First term 2026/2027", $download->getContent());

        $this->actingAs($f['otherTeacher'])->get($templateRoute)->assertForbidden();
        $this->post(route('results.teacher.csv.preview', [$f['classOne'], $f['math']]), [
            'file' => UploadedFile::fake()->createWithContent('scores.csv', $download->getContent()),
        ])->assertForbidden();

        $csv = $this->scoreCsv($download->getContent(), [
            ['SEC-0001', 'Changed name', '71'],
        ]);
        $preview = $this->actingAs($f['teacher'])->post(route('results.teacher.csv.preview', [$f['classOne'], $f['math']]), [
            'file' => UploadedFile::fake()->createWithContent('scores.csv', $csv),
        ])->assertOk()->viewData('uploadPreview');

        $this->actingAs($f['otherTeacher'])->post(route('results.teacher.csv.apply', [$f['classOne'], $f['math']]), [
            'upload_token' => $preview['token'],
        ])->assertForbidden();
        $this->actingAs($f['teacher'])->post(route('results.teacher.csv.apply', [$f['classTwo'], $f['science']]), [
            'upload_token' => $preview['token'],
        ])->assertForbidden();
        $this->post(route('results.teacher.csv.apply', [$f['classOne'], $f['math']]), [
            'upload_token' => $preview['token'],
        ])->assertRedirect();
        $this->post(route('results.teacher.csv.apply', [$f['classOne'], $f['math']]), [
            'upload_token' => $preview['token'],
        ])->assertForbidden();
        $this->assertDatabaseHas('results', ['student_id' => $f['studentA']->id, 'status' => 'Draft']);

        app(TenantContext::class)->set($f['school']->id);
        $second = AcademicTerm::create(['session_id' => $f['session']->id, 'name' => 'Second term', 'start_date' => today('Africa/Lagos')->addDay(), 'end_date' => today('Africa/Lagos')->addMonths(2)]);
        $preview = $this->post(route('results.teacher.csv.preview', [$f['classOne'], $f['math']]), [
            'file' => UploadedFile::fake()->createWithContent('scores.csv', $csv),
        ])->assertOk()->viewData('uploadPreview');
        app(TenantContext::class)->set($f['school']->id);
        $f['term']->update(['end_date' => today('Africa/Lagos')->subDay()]);
        $second->update(['start_date' => today('Africa/Lagos')]);
        $this->post(route('results.teacher.csv.apply', [$f['classOne'], $f['math']]), [
            'upload_token' => $preview['token'],
        ])->assertForbidden();
    }

    public function test_pair_csv_validation_refuses_bad_rows_and_locked_results_without_partial_saves(): void
    {
        $f = $this->fixture();
        $inactive = $this->student('Inactive Pupil', 'inactive@example.test', 'SEC-0004', $f['armA']);
        $inactive->update(['status' => Student::STATUS_GRADUATED]);
        $download = $this->actingAs($f['teacher'])->get(route('results.teacher.csv.template', [$f['classOne'], $f['math']]))->getContent();
        $cases = [
            [['UNKNOWN', 'Unknown', '40']],
            [['SEC-0001', 'Alpha', '40'], ['SEC-0001', 'Again', '50']],
            [['SEC-0003', 'Other class', '40']],
            [['SEC-0004', 'Inactive', '40']],
            [['SEC-0001', 'Too high', '101']],
        ];
        foreach ($cases as $rows) {
            $response = $this->post(route('results.teacher.csv.preview', [$f['classOne'], $f['math']]), [
                'file' => UploadedFile::fake()->createWithContent('scores.csv', $this->scoreCsv($download, $rows)),
            ])->assertOk();
            $this->assertGreaterThan(0, $response->viewData('uploadPreview')['error_count']);
        }
        $this->assertDatabaseCount('results', 0);

        $atomicPreview = $this->post(route('results.teacher.csv.preview', [$f['classOne'], $f['math']]), [
            'file' => UploadedFile::fake()->createWithContent('scores.csv', $this->scoreCsv($download, [
                ['SEC-0001', 'Alpha', '70'], ['SEC-0002', 'Beta', '80'],
            ])),
        ])->assertOk()->viewData('uploadPreview');
        app(TenantContext::class)->set($f['school']->id);
        $changedLock = Result::create([
            'student_id' => $f['studentB']->id, 'class_arm_id' => $f['armB']->id,
            'subject_id' => $f['math']->id, 'term_id' => $f['term']->id,
            'result_template_id' => $f['template']->id, 'status' => 'Approved',
        ]);
        $this->post(route('results.teacher.csv.apply', [$f['classOne'], $f['math']]), [
            'upload_token' => $atomicPreview['token'],
        ])->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('results', ['student_id' => $f['studentA']->id]);
        app(TenantContext::class)->set($f['school']->id);
        $changedLock->delete();

        app(TenantContext::class)->set($f['school']->id);
        Result::create([
            'student_id' => $f['studentA']->id, 'class_arm_id' => $f['armA']->id,
            'subject_id' => $f['math']->id, 'term_id' => $f['term']->id,
            'result_template_id' => $f['template']->id, 'status' => 'Submitted',
        ]);
        Result::create([
            'student_id' => $f['studentB']->id, 'class_arm_id' => $f['armB']->id,
            'subject_id' => $f['math']->id, 'term_id' => $f['term']->id,
            'result_template_id' => $f['template']->id, 'status' => 'Approved',
        ]);
        $response = $this->post(route('results.teacher.csv.preview', [$f['classOne'], $f['math']]), [
            'file' => UploadedFile::fake()->createWithContent('scores.csv', $this->scoreCsv($download, [
                ['SEC-0001', 'Alpha', '70'], ['SEC-0002', 'Beta', '80'],
            ])),
        ])->assertOk();
        $this->assertSame(2, $response->viewData('uploadPreview')['error_count']);
        $this->assertDatabaseHas('results', ['student_id' => $f['studentA']->id, 'status' => 'Submitted']);
        $this->assertDatabaseHas('results', ['student_id' => $f['studentB']->id, 'status' => 'Approved']);
    }

    public function test_pair_csv_comment_blank_partial_and_name_rules_are_enforced(): void
    {
        $f = $this->fixture();
        $second = app(ResultConfiguration::class)->addComponent($f['section']->id, 'CA', 20);
        ResultTemplateComponent::create(['result_template_id' => $f['template']->id, 'assessment_component_id' => $second->id, 'position' => 2]);
        $download = $this->actingAs($f['teacher'])->get(route('results.teacher.csv.template', [$f['classOne'], $f['math']]))->getContent();

        $mismatch = preg_replace('/^.*\R/u', "# Scores for English · JS 1 · First term 2026/2027\r\n", $download, 1);
        $response = $this->post(route('results.teacher.csv.preview', [$f['classOne'], $f['math']]), [
            'file' => UploadedFile::fake()->createWithContent('scores.csv', $mismatch),
        ])->assertOk();
        $this->assertStringContainsString('different subject, class or term', $response->getContent());

        $partial = $this->scoreCsv($download, [['SEC-0001', 'Ignored name', '70', '']]);
        $response = $this->post(route('results.teacher.csv.preview', [$f['classOne'], $f['math']]), [
            'file' => UploadedFile::fake()->createWithContent('scores.csv', $partial),
        ])->assertOk();
        $this->assertStringContainsString('fill every component or leave the row blank', $response->getContent());

        $valid = $this->scoreCsv($download, [
            ['SEC-0001', 'Definitely not the pupil name', '70', '15'],
            ['SEC-0002', 'Also ignored', '', ''],
        ]);
        $preview = $this->post(route('results.teacher.csv.preview', [$f['classOne'], $f['math']]), [
            'file' => UploadedFile::fake()->createWithContent('scores.csv', $valid),
        ])->assertOk()->viewData('uploadPreview');
        $this->assertSame(1, $preview['ready_count']);
        $this->assertSame(1, $preview['skipped_count']);
        $this->post(route('results.teacher.csv.apply', [$f['classOne'], $f['math']]), ['upload_token' => $preview['token']])->assertRedirect();
        $this->assertDatabaseHas('results', ['student_id' => $f['studentA']->id, 'total_score' => 85]);
        $this->assertDatabaseMissing('results', ['student_id' => $f['studentB']->id]);
    }

    public function test_pair_csv_apply_is_atomic_audited_and_template_neutralises_formula_names(): void
    {
        $f = $this->fixture();
        app(TenantContext::class)->set($f['school']->id);
        $f['studentA']->user->update(['name' => '=2+2']);
        $download = $this->actingAs($f['teacher'])->get(route('results.teacher.csv.template', [$f['classOne'], $f['math']]))->getContent();
        $this->assertStringContainsString("'=2+2", $download);
        $csv = $this->scoreCsv($download, [['SEC-0001', 'Ignored', '60'], ['SEC-0002', 'Ignored', '70']]);
        $preview = $this->post(route('results.teacher.csv.preview', [$f['classOne'], $f['math']]), [
            'file' => UploadedFile::fake()->createWithContent('scores.csv', $csv),
        ])->assertOk()->viewData('uploadPreview');
        $this->post(route('results.teacher.csv.apply', [$f['classOne'], $f['math']]), ['upload_token' => $preview['token']])->assertRedirect();

        $this->assertDatabaseCount('results', 2);
        app(TenantContext::class)->set($f['school']->id);
        $audit = AuditLog::where('action', 'teacher_score_csv_applied')->firstOrFail();
        $this->assertSame(2, $audit->metadata['pupils_updated']);
        $this->assertSame($f['teacher']->id, $audit->user_id);
        $this->assertSame(2, ResultItem::count());
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $permission = Permission::create(['name' => 'enter_results']);
        $role = Role::create(['name' => 'Teacher']);
        $role->permissions()->attach($permission);
        $teacher = User::create(['name' => 'Teacher', 'email' => 'teacher-results@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $teacher->roles()->attach($role);
        $otherTeacher = User::create(['name' => 'Other Teacher', 'email' => 'other-teacher-results@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $otherTeacher->roles()->attach($role);

        $section = Section::create(['name' => 'Secondary School', 'head_title' => 'Principal']);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Junior Secondary']);
        $classOne = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JS 1']);
        $classTwo = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JS 2']);
        $armA = ClassArm::create(['class_id' => $classOne->id, 'name' => 'A']);
        $armB = ClassArm::create(['class_id' => $classOne->id, 'name' => 'B', 'form_teacher_user_id' => $otherTeacher->id]);
        $armOther = ClassArm::create(['class_id' => $classTwo->id, 'name' => 'Gold']);
        $math = Subject::create(['section_id' => $section->id, 'name' => 'Mathematics']);
        $science = Subject::create(['section_id' => $section->id, 'name' => 'Basic Science']);
        $english = Subject::create(['section_id' => $section->id, 'name' => 'English']);
        foreach ([[$classOne, $math], [$classOne, $english], [$classTwo, $science]] as [$class, $subject]) {
            ClassSubject::create(['class_id' => $class->id, 'subject_id' => $subject->id]);
        }
        TeacherSubjectAssignment::create(['user_id' => $teacher->id, 'class_id' => $classOne->id, 'subject_id' => $math->id]);
        TeacherSubjectAssignment::create(['user_id' => $teacher->id, 'class_id' => $classTwo->id, 'subject_id' => $science->id]);

        $studentA = $this->student('Pupil Alpha', 'alpha@example.test', 'SEC-0001', $armA);
        $studentB = $this->student('Pupil Beta', 'beta@example.test', 'SEC-0002', $armB);
        $studentOther = $this->student('Pupil Other', 'other@example.test', 'SEC-0003', $armOther);
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => today('Africa/Lagos')->subMonth(), 'end_date' => today('Africa/Lagos')->addMonths(8)]);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First term', 'start_date' => today('Africa/Lagos')->subMonth(), 'end_date' => today('Africa/Lagos')->addMonth()]);
        $configuration = app(ResultConfiguration::class);
        $component = $configuration->addComponent($section->id, 'Exam', 100);
        $scheme = $configuration->addScheme($section->id, 'Secondary scale');
        $configuration->addBand($scheme->id, 0, 100, 'A', 'Pass');
        $template = $configuration->addTemplate('Secondary template', $section->id, null, null, $scheme->id, [$component->id]);

        return compact('school', 'teacher', 'otherTeacher', 'section', 'classOne', 'classTwo', 'armA', 'armB', 'armOther', 'math', 'science', 'english', 'studentA', 'studentB', 'studentOther', 'session', 'term', 'component', 'template');
    }

    private function student(string $name, string $email, string $number, ClassArm $arm): Student
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => 'long-password-here', 'status' => 'active']);

        return Student::create(['user_id' => $user->id, 'class_arm_id' => $arm->id, 'registration_number' => $number, 'admission_date' => today()]);
    }

    private function approvedResult(array $f, Student $student, ClassArm $arm): Result
    {
        return Result::create([
            'student_id' => $student->id,
            'class_arm_id' => $arm->id,
            'subject_id' => $f['math']->id,
            'term_id' => $f['term']->id,
            'result_template_id' => $f['template']->id,
            'status' => 'Approved',
            'total_score' => 80,
            'percentage' => 80,
            'grade' => 'A',
            'locked_at' => now(),
        ]);
    }

    private function scoreCsv(string $template, array $rows): string
    {
        $lines = preg_split('/\R/', preg_replace('/^\xEF\xBB\xBF/', '', $template));
        $csv = "\xEF\xBB\xBF".$lines[0]."\r\n".$lines[1]."\r\n";
        foreach ($rows as $row) {
            $stream = fopen('php://temp', 'r+');
            fputcsv($stream, $row, ',', '"', '');
            rewind($stream);
            $csv .= rtrim(stream_get_contents($stream), "\r\n")."\r\n";
            fclose($stream);
        }

        return $csv;
    }
}
