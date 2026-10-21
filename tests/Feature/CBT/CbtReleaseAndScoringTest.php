<?php

namespace Tests\Feature\CBT;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Admissions\Models\Candidate;
use App\Modules\Admissions\Models\CandidateStatusHistory;
use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Services\AdmissionPipeline;
use App\Modules\CBT\Models\Exam;
use App\Modules\CBT\Models\ExamSubmission;
use App\Modules\CBT\Models\Question;
use App\Modules\CBT\Services\ExamWorkflow;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Services\PaymentLedger;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultItem;
use App\Modules\Results\Services\ResultConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CbtReleaseAndScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_score_converts_and_unreleased_result_is_denied_by_direct_request(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-cbt@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $learner = User::create(['name' => 'Learner', 'email' => 'learner-cbt@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $role = Role::create(['name' => 'CBT Admin']);
        $permissions = [];
        foreach (['manage_cbt', 'approve_cbt_results', 'enter_results', 'manage_result_settings'] as $name) {
            $permissions[] = Permission::create(['name' => $name])->id;
        }
        $role->permissions()->attach($permissions);
        $admin->roles()->attach($role);
        $level = AcademicLevel::create(['name' => 'Secondary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JSS 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A']);
        $subject = Subject::create(['name' => 'Mathematics']);
        ClassSubject::create(['class_id' => $class->id, 'subject_id' => $subject->id]);
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31']);
        $student = Student::create(['user_id' => $learner->id, 'class_arm_id' => $arm->id, 'admission_date' => '2026-01-01']);
        $config = app(ResultConfiguration::class);
        $component = $config->addComponent('Exam', 70);
        $scheme = $config->addScheme('Secondary');
        $config->addBand($scheme->id, 0, 100, 'A', 'Recorded');
        $config->addTemplate('Secondary', $level->id, null, $scheme->id, [$component->id]);

        $workflow = app(ExamWorkflow::class);
        $type = $workflow->createType('Internal', $admin);
        $this->actingAs($admin)->get(route('cbt.staff.index'))->assertOk()->assertSee('Create exam');
        app(TenantContext::class)->set($school->id);
        $this->post(route('cbt.staff.exams.store'), [
            'exam_type_id' => $type->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'term_id' => $term->id, 'assessment_component_id' => $component->id,
            'title' => 'Mathematics CBT', 'duration' => 45, 'result_release_type' => 'Manual',
            'show_score' => 1, 'requires_approval' => 1,
        ])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($school->id);
        $exam = Exam::firstOrFail();
        $this->post(route('cbt.staff.questions.store', $exam), [
            'text' => 'First question', 'marks' => 40, 'explanation' => 'Forty marks',
            'options' => ['Correct', 'Wrong'], 'correct_index' => 0,
        ])->assertSessionHasNoErrors();
        $this->post(route('cbt.staff.questions.store', $exam), [
            'text' => 'Second question', 'marks' => 10,
            'options' => ['Correct', 'Wrong'], 'correct_index' => 0,
        ])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($school->id);
        $first = Question::where('exam_id', $exam->id)->orderBy('position')->firstOrFail();
        $second = Question::where('exam_id', $exam->id)->orderByDesc('position')->firstOrFail();
        $this->post(route('cbt.staff.publish', $exam))->assertSessionHasNoErrors();
        $this->get(route('cbt.staff.show', $exam))->assertOk()->assertSee('First question');

        $this->actingAs($learner)->get(route('cbt.mine'))->assertOk()->assertSee('Mathematics CBT');
        $this->post(route('cbt.start', $exam))->assertRedirect();
        app(TenantContext::class)->set($school->id);
        $attempt = ExamSubmission::where('student_id', $student->id)->firstOrFail();
        $this->get(route('cbt.sit', $attempt))->assertOk()->assertDontSee('Forty marks');
        app(TenantContext::class)->set($school->id);
        $this->post(route('cbt.submit', $attempt), ['answers' => [
            $first->id => $first->options()->where('is_correct', true)->firstOrFail()->id,
            $second->id => $second->options()->where('is_correct', false)->firstOrFail()->id,
        ]])->assertRedirect(route('cbt.mine'));
        app(TenantContext::class)->set($school->id);
        $attempt->refresh();
        $this->assertSame('40.00', $attempt->raw_score);
        $this->assertSame('50.00', $attempt->max_score);
        $this->assertSame('56.00', $attempt->converted_score);
        $result = Result::where('student_id', $student->id)->firstOrFail();
        $this->assertSame('Draft', $result->status);
        $this->assertSame('56.00', ResultItem::where('result_id', $result->id)->firstOrFail()->score);

        $this->get(route('cbt.result', $attempt))->assertOk()->assertSee('Result not released yet')
            ->assertDontSee('40.00')->assertDontSee('First question')->assertDontSee('Correct answer');
        $this->post(route('cbt.staff.approve', $exam))->assertForbidden();
        $this->actingAs($admin)->post(route('cbt.staff.release', $exam))->assertSessionHasNoErrors();
        $this->actingAs($learner)->get(route('cbt.result', $attempt))->assertOk()->assertSee('Result not released yet')->assertDontSee('40.00');
        $this->actingAs($admin)->post(route('cbt.staff.approve', $exam))->assertSessionHasNoErrors();
        $this->actingAs($learner)->get(route('cbt.result', $attempt))->assertOk()->assertSee('40.00');
        app(TenantContext::class)->set($school->id);
        $exam->refresh()->update(['result_release_type' => 'Scheduled', 'result_release_at' => now()->addDay()]);
        $this->get(route('cbt.result', $attempt))->assertOk()->assertSee('Result not released yet')->assertDontSee('40.00');
        app(TenantContext::class)->set($school->id);
        $exam->refresh()->update(['result_release_at' => now()->subMinute(), 'show_score' => false, 'show_correct_answers' => true]);
        $this->get(route('cbt.result', $attempt))->assertOk()->assertDontSee('Score:')->assertSee('Correct answer: Correct');
    }

    public function test_released_entrance_cbt_records_verified_admission_outcome(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $admin = User::create(['name' => 'Admin', 'email' => 'admissions-cbt@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $role = Role::create(['name' => 'CBT Admin']);
        $permission = Permission::create(['name' => 'manage_cbt']);
        $paymentsPermission = Permission::firstOrCreate(['name' => 'manage_payments']);
        $role->permissions()->attach($permission);
        $role->permissions()->attach($paymentsPermission);
        $admin->roles()->attach($role);
        $section = Section::create(['name' => 'Primary School', 'head_title' => 'Head Teacher']);
        $section->heads()->attach($admin, ['school_id' => $school->id]);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Primary']);
        $pipeline = app(AdmissionPipeline::class);
        $candidate = $pipeline->register(['name' => 'Candidate', 'email' => 'candidate-cbt@example.test', 'phone' => '08000000000', 'password' => 'long-password-here', 'applied_level_id' => $level->id]);
        $invoice = app(PaymentLedger::class)->invoice(['candidate_id' => $candidate->id, 'purpose' => 'Registration', 'amount_kobo' => 10000, 'due_date' => today()->toDateString()], $admin);
        app(PaymentLedger::class)->manualPayment($invoice, $admin);
        $candidateUser = User::findOrFail($candidate->user_id);
        $workflow = app(ExamWorkflow::class);
        $type = $workflow->createType('Entrance', $admin);
        $exam = $workflow->createExam([
            'exam_type_id' => $type->id, 'title' => 'Entrance CBT', 'duration' => 30,
            'pass_mark' => 60, 'result_release_type' => 'Immediate', 'show_score' => false,
        ], $admin);
        $question = $workflow->addQuestion($exam, 'Entrance question', 10, null, ['Yes', 'No'], 0, $admin);
        $workflow->publish($exam, $admin);

        $this->actingAs($candidateUser)->post(route('cbt.start', $exam))->assertRedirect();
        app(TenantContext::class)->set($school->id);
        $this->assertSame('Exam Taken', $candidate->fresh()->status);
        $attempt = ExamSubmission::where('candidate_id', $candidate->id)->firstOrFail();
        $this->post(route('cbt.submit', $attempt), ['answers' => [
            $question->id => $question->options()->where('is_correct', true)->firstOrFail()->id,
        ]])->assertRedirect(route('cbt.result', $attempt));
        app(TenantContext::class)->set($school->id);
        $this->assertSame('Passed', Candidate::findOrFail($candidate->id)->status);
        $this->assertSame('Passed', CandidateStatusHistory::where('candidate_id', $candidate->id)->orderByDesc('id')->firstOrFail()->to_status);
        $this->get(route('cbt.result', $attempt))->assertOk()->assertDontSee('10.00');
    }
}
