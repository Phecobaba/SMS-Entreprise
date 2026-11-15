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
use App\Modules\Academics\Models\TeacherSubjectAssignment;
use App\Modules\Admissions\Models\Student;
use App\Modules\CBT\Models\Exam;
use App\Modules\CBT\Models\ExamAnswer;
use App\Modules\CBT\Models\ExamSubmission;
use App\Modules\CBT\Models\ExamType;
use App\Modules\CBT\Models\Question;
use App\Modules\CBT\Models\QuestionOption;
use App\Modules\CBT\Services\CbtResultConversion;
use App\Modules\CBT\Services\ExamAttemptLifecycle;
use App\Modules\CBT\Services\ExamResitGrants;
use App\Modules\CBT\Services\ExamWorkflow;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultItem;
use App\Modules\Results\Services\ResultConfiguration;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CbtResitTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_exam_grant_is_one_pupil_only_and_preserves_the_original_attempt(): void
    {
        $f = $this->fixture();
        $exam = $this->exam($f, ['status' => 'Closed']);
        [$question, $correct, $wrong] = $this->question($exam);
        $original = $this->submitted($exam, $f['student'], 1, $question, $wrong);

        $this->actingAs($f['head'])->post(route('cbt.staff.resits.store', [$exam, $f['student']]), [
            'reason' => 'Technical fault', 'opens_until' => now('Africa/Lagos')->addHour()->format('Y-m-d H:i:s'),
        ])->assertRedirect()->assertSessionHas('status', 'Another attempt was granted.');
        $this->actingAs($f['otherLearner'])->post(route('cbt.start', $exam))->assertSessionHasErrors('exam');
        $this->actingAs($f['learner'])->get(route('cbt.mine'))->assertOk()->assertSee('Resit')->assertSee('Subject: Mathematics');
        $this->post(route('cbt.start', $exam))->assertRedirect();

        app(TenantContext::class)->set($f['school']->id);
        $resit = ExamSubmission::where('exam_id', $exam->id)->where('student_id', $f['student']->id)->where('attempt_number', 2)->firstOrFail();
        $this->assertSame('In Progress', $resit->status);
        $this->assertDatabaseHas('exam_answers', ['exam_submission_id' => $original->id, 'question_id' => $question->id, 'selected_option_id' => $wrong->id]);
        $this->assertSame(2, ExamSubmission::where('exam_id', $exam->id)->where('student_id', $f['student']->id)->count());

        $this->actingAs($f['learner'])->post(route('cbt.start', $exam))->assertRedirect(route('cbt.sit', $resit));
        $this->post(route('cbt.submit', $resit), ['answers' => [$question->id => $correct->id]])->assertRedirect();
        $this->get(route('cbt.mine'))->assertOk()->assertSee('Waiting for results');
        $this->post(route('cbt.start', $exam))->assertSessionHasErrors('exam');
        app(TenantContext::class)->set($f['school']->id);
        $this->assertSame(2, ExamSubmission::where('exam_id', $exam->id)->where('student_id', $f['student']->id)->count());
    }

    public function test_grant_validation_and_scope_are_enforced_server_side(): void
    {
        $f = $this->fixture();
        $published = $this->exam($f);
        $draft = $this->exam($f, ['title' => 'Draft', 'status' => 'Draft']);
        $entrance = Exam::create([
            'exam_type_id' => $f['entranceType']->id, 'created_by' => $f['admin']->id, 'title' => 'Entrance',
            'duration' => 30, 'status' => 'Published', 'pass_mark' => 50, 'result_release_type' => 'Manual',
        ]);

        $this->actingAs($f['teacher'])->post(route('cbt.staff.resits.store', [$published, $f['student']]), [])->assertForbidden();
        $this->actingAs($f['head'])->post(route('cbt.staff.resits.store', [$f['otherExam'], $f['student']]), [
            'reason' => 'Illness', 'opens_until' => now('Africa/Lagos')->addHour(),
        ])->assertNotFound();
        $this->actingAs($f['admin'])->post(route('cbt.staff.resits.store', [$published, $f['student']]), [
            'reason' => '', 'opens_until' => now('Africa/Lagos')->addHour(),
        ])->assertSessionHasErrors('reason');
        $this->post(route('cbt.staff.resits.store', [$published, $f['student']]), [
            'reason' => 'Late arrival', 'opens_until' => now('Africa/Lagos')->subMinute(),
        ])->assertSessionHasErrors('opens_until');
        $this->post(route('cbt.staff.resits.store', [$draft, $f['student']]), [
            'reason' => 'Illness', 'opens_until' => now('Africa/Lagos')->addHour(),
        ])->assertSessionHasErrors('exam');
        $this->post(route('cbt.staff.resits.store', [$entrance, $f['student']]), [
            'reason' => 'Illness', 'opens_until' => now('Africa/Lagos')->addHour(),
        ])->assertSessionHasErrors('exam');
        $this->post(route('cbt.staff.resits.store', [$published, $f['student']]), [
            'reason' => 'Illness', 'opens_until' => now('Africa/Lagos')->addHour(),
        ])->assertRedirect();
        $this->assertDatabaseHas('exam_resit_grants', ['exam_id' => $published->id, 'student_id' => $f['student']->id, 'reason' => 'Illness']);
    }

    public function test_resit_timer_is_capped_by_duration_and_grant_in_both_closing_modes(): void
    {
        $f = $this->fixture();
        Carbon::setTestNow('2026-11-05 10:00:00');
        foreach ([true, false] as $automatic) {
            $grantCapped = $this->exam($f, ['title' => 'Grant cap '.(int) $automatic, 'status' => 'Closed', 'duration' => 30, 'closes_automatically' => $automatic, 'closes_at' => now()->subHour()]);
            app(ExamResitGrants::class)->grant($grantCapped, $f['student']->id, 'Extra access', now()->addMinutes(10), $f['admin']);
            $attempt = app(ExamWorkflow::class)->start($grantCapped, $f['learner']);
            $this->assertSame(now()->addMinutes(10)->timestamp, app(ExamAttemptLifecycle::class)->effectiveEnd($attempt)->timestamp);

            $durationCapped = $this->exam($f, ['title' => 'Duration cap '.(int) $automatic, 'status' => 'Closed', 'duration' => 30, 'closes_automatically' => $automatic, 'closes_at' => now()->subHour()]);
            app(ExamResitGrants::class)->grant($durationCapped, $f['student']->id, 'Extra access', now()->addHour(), $f['admin']);
            $attempt = app(ExamWorkflow::class)->start($durationCapped, $f['learner']);
            $this->assertSame(now()->addMinutes(30)->timestamp, app(ExamAttemptLifecycle::class)->effectiveEnd($attempt)->timestamp);
        }
        Carbon::setTestNow();
    }

    public function test_latest_submitted_attempt_counts_for_results_and_conversion(): void
    {
        $f = $this->fixture(true);
        $exam = $this->exam($f, ['status' => 'Closed', 'term_id' => $f['term']->id, 'assessment_component_id' => $f['component']->id]);
        [$question, $correct, $wrong] = $this->question($exam);
        $first = $this->submitted($exam, $f['student'], 1, $question, $wrong);
        app(ExamResitGrants::class)->grant($exam, $f['student']->id, 'Technical fault', now()->addHour(), $f['admin']);
        $second = app(ExamWorkflow::class)->start($exam, $f['learner']);

        $preview = app(CbtResultConversion::class)->preview($exam, $f['admin']);
        $this->assertSame(0.0, (float) $preview['writes'][0]['score']);
        app(ExamWorkflow::class)->submit($second, [$question->id => $correct->id], $f['learner']);
        $preview = app(CbtResultConversion::class)->preview($exam, $f['admin']);
        $this->assertSame(70.0, (float) $preview['writes'][0]['score']);
        app(CbtResultConversion::class)->apply($exam, $f['admin']);
        $result = Result::where('student_id', $f['student']->id)->firstOrFail();
        $this->assertSame('70.00', ResultItem::where('result_id', $result->id)->firstOrFail()->score);

        $this->actingAs($f['head'])->get(route('cbt.results.show', $exam))->assertOk()
            ->assertSee('Latest submitted: Attempt 2')->assertSee('Attempt 1')->assertSee('Attempt 2');
        $this->actingAs($f['teacher'])->get(route('cbt.results.pupil', [$exam, $first]))->assertOk()->assertSee('Attempt 1')->assertSee('Wrong');
        $this->get(route('cbt.results.pupil', [$exam, $second]))->assertOk()->assertSee('Attempt 2')->assertSee('Right');

        app(TenantContext::class)->set($f['school']->id);
        $result->update(['status' => 'Submitted']);
        app(ExamResitGrants::class)->grant($exam, $f['student']->id, 'Repeat fault', now()->addHour(), $f['admin']);
        $third = app(ExamWorkflow::class)->start($exam, $f['learner']);
        app(ExamWorkflow::class)->submit($third, [$question->id => $wrong->id], $f['learner']);
        $summary = app(CbtResultConversion::class)->preview($exam, $f['admin']);
        $this->assertSame(1, $summary['skip_reasons']['submitted']);
        $this->assertSame('70.00', ResultItem::where('result_id', $result->id)->firstOrFail()->score);
    }

    public function test_withdrawal_and_grant_are_audited_and_a_used_grant_cannot_be_withdrawn(): void
    {
        $f = $this->fixture();
        $exam = $this->exam($f);
        $grant = app(ExamResitGrants::class)->grant($exam, $f['student']->id, 'Illness', now()->addHour(), $f['head']);
        $this->actingAs($f['head'])->delete(route('cbt.staff.resits.destroy', [$exam, $grant]))->assertRedirect();
        app(TenantContext::class)->set($f['school']->id);
        $this->assertNotNull($grant->fresh()->withdrawn_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cbt_resit_granted', 'actor_name' => 'Head']);
        $withdrawAudit = AuditLog::where('action', 'cbt_resit_withdrawn')->firstOrFail();
        $this->assertSame('Illness', $withdrawAudit->metadata['reason']);
        $this->assertSame('Head role', $withdrawAudit->actor_role);

        $used = app(ExamResitGrants::class)->grant($exam, $f['student']->id, 'Late arrival', now()->addHour(), $f['head']);
        app(ExamWorkflow::class)->start($exam, $f['learner']);
        $this->actingAs($f['head'])->delete(route('cbt.staff.resits.destroy', [$exam, $used]))->assertSessionHasErrors('grant');
    }

    public function test_absent_is_derived_and_progress_filters_latest_attempt_status(): void
    {
        $f = $this->fixture();
        $exam = $this->exam($f, ['status' => 'Closed']);
        $this->submitted($exam, $f['student'], 1);
        app(ExamResitGrants::class)->grant($exam, $f['student']->id, 'Connection failed', now()->addHour(), $f['admin']);
        app(ExamWorkflow::class)->start($exam, $f['learner']);
        $thirdUser = $this->user('Third Learner', []);
        $third = Student::create(['user_id' => $thirdUser->id, 'class_arm_id' => $f['arm']->id, 'status' => Student::STATUS_ACTIVE, 'admission_date' => '2026-09-01']);
        $this->submitted($exam, $third, 1);

        $this->actingAs($f['head'])->get(route('cbt.staff.progress', [$exam, 'status' => 'absent']))->assertOk()
            ->assertSee('Absent')->assertSee('Other Learner')->assertDontSee('<strong>Learner</strong>', false);
        $this->get(route('cbt.staff.progress', [$exam, 'status' => 'submitted']))->assertOk()
            ->assertSee('Third Learner')->assertDontSee('Other Learner');
        $this->get(route('cbt.staff.progress', [$exam, 'status' => 'writing']))->assertOk()
            ->assertSee('Learner')->assertDontSee('Third Learner');
        $this->getJson(route('cbt.staff.progress.refresh', [$exam, 'status' => 'submitted']))
            ->assertOk()->assertJsonPath('updated_at', now('Africa/Lagos')->format('H:i:s'))
            ->assertJson(fn ($json) => $json->whereType('html', 'string')->etc())
            ->assertJsonPath('html', fn (string $html): bool => str_contains($html, '>1</strong>'));
        $this->actingAs($f['otherHead'])->getJson(route('cbt.staff.progress.refresh', $exam))->assertNotFound();
        $this->actingAs($f['teacher'])->getJson(route('cbt.staff.progress.refresh', $exam))->assertForbidden();
    }

    public function test_expired_resit_is_finalized_before_conversion_but_exam_end_does_not_cut_it_short(): void
    {
        $f = $this->fixture(true);
        Carbon::setTestNow('2026-11-05 10:00:00');
        $exam = $this->exam($f, ['closes_automatically' => false, 'term_id' => $f['term']->id, 'assessment_component_id' => $f['component']->id]);
        [$question, $correct] = $this->question($exam);
        app(ExamResitGrants::class)->grant($exam, $f['student']->id, 'Late arrival', now()->addMinutes(10), $f['admin']);
        $attempt = app(ExamWorkflow::class)->start($exam, $f['learner']);
        ExamAnswer::create(['exam_submission_id' => $attempt->id, 'question_id' => $question->id, 'selected_option_id' => $correct->id]);
        app(ExamWorkflow::class)->close($exam, $f['admin']);
        $this->assertSame('In Progress', $attempt->fresh()->status);
        Carbon::setTestNow('2026-11-05 10:11:00');
        app(CbtResultConversion::class)->preview($exam->refresh(), $f['admin']);
        $this->assertSame('Submitted', $attempt->fresh()->status);
        Carbon::setTestNow();
    }

    private function fixture(bool $results = false): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $admin = $this->user('Admin', ['manage_cbt', 'view_cbt_results', 'view_all_sections']);
        $head = $this->user('Head', ['manage_cbt', 'view_cbt_results']);
        $otherHead = $this->user('Other Head', ['manage_cbt', 'view_cbt_results']);
        $teacher = $this->user('Teacher', ['view_cbt_results']);
        $learner = $this->user('Learner', []);
        $otherLearner = $this->user('Other Learner', []);
        $section = Section::create(['name' => 'Secondary', 'head_title' => 'Principal']);
        $section->heads()->attach($head, ['school_id' => $school->id]);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'JSS']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JSS 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'Gold']);
        $subject = Subject::create(['section_id' => $section->id, 'name' => 'Mathematics']);
        ClassSubject::create(['class_id' => $class->id, 'subject_id' => $subject->id]);
        TeacherSubjectAssignment::create(['user_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id]);
        $student = Student::create(['user_id' => $learner->id, 'class_arm_id' => $arm->id, 'status' => Student::STATUS_ACTIVE, 'admission_date' => '2026-09-01']);
        $otherStudent = Student::create(['user_id' => $otherLearner->id, 'class_arm_id' => $arm->id, 'status' => Student::STATUS_ACTIVE, 'admission_date' => '2026-09-01']);
        $schoolType = ExamType::create(['name' => 'School exam', 'feeds_results' => true, 'active' => true]);
        $entranceType = ExamType::create(['name' => 'Entrance', 'feeds_results' => false, 'active' => true]);

        $otherSection = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher']);
        $otherSection->heads()->attach($otherHead, ['school_id' => $school->id]);
        $otherLevel = AcademicLevel::create(['section_id' => $otherSection->id, 'name' => 'Primary']);
        $otherClass = SchoolClass::create(['academic_level_id' => $otherLevel->id, 'name' => 'Primary 5']);
        $otherSubject = Subject::create(['section_id' => $otherSection->id, 'name' => 'English']);
        $otherExam = Exam::create(['exam_type_id' => $schoolType->id, 'class_id' => $otherClass->id, 'subject_id' => $otherSubject->id, 'created_by' => $admin->id, 'title' => 'Other exam', 'duration' => 30, 'status' => 'Published', 'result_release_type' => 'Manual']);

        $data = compact('school', 'admin', 'head', 'otherHead', 'teacher', 'learner', 'otherLearner', 'section', 'level', 'class', 'arm', 'subject', 'student', 'otherStudent', 'schoolType', 'entranceType', 'otherExam');
        if ($results) {
            $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-08-31']);
            $data['term'] = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => '2026-09-01', 'end_date' => '2026-12-20']);
            $configuration = app(ResultConfiguration::class);
            $data['component'] = $configuration->addComponent($section->id, 'Exam', 70);
            $scheme = $configuration->addScheme($section->id, 'Scale');
            $configuration->addBand($scheme->id, 0, 100, 'A', 'Recorded');
            $data['template'] = $configuration->addTemplate('JSS', $section->id, $level->id, null, $scheme->id, [$data['component']->id]);
        }

        return $data;
    }

    private function user(string $name, array $permissions): User
    {
        $user = User::create(['name' => $name, 'email' => str($name)->slug().uniqid().'@example.test', 'password' => 'password-12345', 'status' => 'active']);
        $role = Role::create(['name' => $name.' role']);
        foreach ($permissions as $permission) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $permission]));
        }
        $user->roles()->attach($role);

        return $user;
    }

    private function exam(array $f, array $overrides = []): Exam
    {
        return Exam::create([
            'exam_type_id' => $f['schoolType']->id, 'class_id' => $f['class']->id, 'subject_id' => $f['subject']->id,
            'created_by' => $f['admin']->id, 'title' => 'CBT exam '.uniqid(), 'duration' => 30,
            'status' => 'Published', 'closes_automatically' => true, 'result_release_type' => 'Manual',
            ...$overrides,
        ]);
    }

    private function question(Exam $exam): array
    {
        $question = Question::create(['exam_id' => $exam->id, 'text' => 'Two plus two?', 'marks' => 5, 'position' => 1]);
        $correct = QuestionOption::create(['question_id' => $question->id, 'text' => 'Four', 'is_correct' => true]);
        $wrong = QuestionOption::create(['question_id' => $question->id, 'text' => 'Five', 'is_correct' => false]);

        return [$question, $correct, $wrong];
    }

    private function submitted(Exam $exam, Student $student, int $number, ?Question $question = null, ?QuestionOption $option = null): ExamSubmission
    {
        $attempt = ExamSubmission::create([
            'exam_id' => $exam->id, 'student_id' => $student->id, 'attempt_number' => $number,
            'started_at' => now()->subMinute(), 'submitted_at' => now(), 'raw_score' => $option?->is_correct ? 5 : 0,
            'max_score' => $question ? 5 : 1, 'status' => 'Submitted',
        ]);
        if ($question && $option) {
            ExamAnswer::create(['exam_submission_id' => $attempt->id, 'question_id' => $question->id, 'selected_option_id' => $option->id]);
        }

        return $attempt;
    }
}
