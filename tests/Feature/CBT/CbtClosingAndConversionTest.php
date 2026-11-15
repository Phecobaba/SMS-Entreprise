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
use App\Modules\CBT\Services\ExamAttemptLifecycle;
use App\Modules\CBT\Services\ExamClosing;
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

class CbtClosingAndConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_effective_timer_is_capped_for_automatic_close_but_not_stay_open(): void
    {
        $f = $this->fixture();
        Carbon::setTestNow('2026-11-05 10:00:00');
        $automatic = $this->exam($f, $f['schoolType'], ['duration' => 30, 'closes_at' => now()->addMinutes(5), 'closes_automatically' => true]);
        $stayOpen = $this->exam($f, $f['schoolType'], ['title' => 'Stay open', 'duration' => 30, 'closes_at' => now()->addMinutes(5), 'closes_automatically' => false]);
        $student = Student::where('user_id', $f['learner']->id)->firstOrFail();
        $capped = ExamSubmission::create(['exam_id' => $automatic->id, 'student_id' => $student->id, 'started_at' => now(), 'status' => 'In Progress']);
        $uncapped = ExamSubmission::create(['exam_id' => $stayOpen->id, 'student_id' => $student->id, 'started_at' => now(), 'status' => 'In Progress']);
        [$question, $correct] = $this->question($automatic);
        ExamAnswer::create(['exam_submission_id' => $capped->id, 'question_id' => $question->id, 'selected_option_id' => $correct->id]);

        $lifecycle = app(ExamAttemptLifecycle::class);
        $this->assertSame($automatic->closes_at->timestamp, $lifecycle->effectiveEnd($capped)->timestamp);
        $this->assertSame(now()->addMinutes(30)->timestamp, $lifecycle->effectiveEnd($uncapped)->timestamp);
        Carbon::setTestNow('2026-11-05 10:05:00');
        $this->assertTrue(app(ExamClosing::class)->countsAsClosed($automatic));
        $this->actingAs($f['head'])->get(route('cbt.staff.show', $automatic))->assertOk();
        app(TenantContext::class)->set($f['school']->id);
        $this->assertSame('Submitted', $capped->fresh()->status);
        $this->assertSame('In Progress', $uncapped->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_ending_exam_submits_saved_answers_once_and_audits_count(): void
    {
        $f = $this->fixture();
        $exam = $this->exam($f, $f['schoolType'], ['closes_automatically' => false]);
        [$question, $correct] = $this->question($exam);
        $student = Student::where('user_id', $f['learner']->id)->firstOrFail();
        $attempt = ExamSubmission::create(['exam_id' => $exam->id, 'student_id' => $student->id, 'started_at' => now(), 'status' => 'In Progress']);
        ExamAnswer::create(['exam_submission_id' => $attempt->id, 'question_id' => $question->id, 'selected_option_id' => $correct->id]);

        $this->actingAs($f['head'])->post(route('cbt.staff.close', $exam))->assertRedirect()->assertSessionHas('status', 'Exam ended. 1 active attempt submitted.');
        app(TenantContext::class)->set($f['school']->id);
        $this->assertDatabaseHas('exam_submissions', ['id' => $attempt->id, 'status' => 'Submitted', 'raw_score' => 5]);
        $this->assertSame(0, app(ExamAttemptLifecycle::class)->submitExpiredForExam($exam->refresh(), true));
        $audit = AuditLog::where('action', 'cbt_exam_ended')->firstOrFail();
        $this->assertSame(1, $audit->metadata['submitted_attempts']);
    }

    public function test_personal_expiry_is_submitted_on_next_load_without_a_job(): void
    {
        $f = $this->fixture();
        $exam = $this->exam($f, $f['schoolType'], ['duration' => 1, 'closes_automatically' => false]);
        [$question, $correct] = $this->question($exam);
        $student = Student::where('user_id', $f['learner']->id)->firstOrFail();
        $attempt = ExamSubmission::create(['exam_id' => $exam->id, 'student_id' => $student->id, 'started_at' => now()->subMinutes(2), 'status' => 'In Progress']);
        ExamAnswer::create(['exam_submission_id' => $attempt->id, 'question_id' => $question->id, 'selected_option_id' => $correct->id]);

        $this->actingAs($f['learner'])->get(route('cbt.sit', $attempt))->assertRedirect();
        $this->assertDatabaseHas('exam_submissions', ['id' => $attempt->id, 'status' => 'Submitted', 'raw_score' => 5]);
    }

    public function test_live_progress_is_scoped_and_reports_all_eligible_active_pupils(): void
    {
        $f = $this->fixture();
        $exam = $this->exam($f, $f['schoolType']);
        $writing = Student::where('user_id', $f['learner']->id)->firstOrFail();
        $submitted = $this->pupil($f, 'Submitted child');
        $notStarted = $this->pupil($f, 'Not started child');
        $inactive = $this->pupil($f, 'Inactive child');
        $inactive->update(['status' => Student::STATUS_GRADUATED]);
        ExamSubmission::create(['exam_id' => $exam->id, 'student_id' => $writing->id, 'started_at' => now(), 'status' => 'In Progress']);
        ExamSubmission::create(['exam_id' => $exam->id, 'student_id' => $submitted->id, 'started_at' => now(), 'submitted_at' => now(), 'raw_score' => 0, 'max_score' => 1, 'status' => 'Submitted']);

        $this->actingAs($f['head'])->get(route('cbt.staff.progress', $exam))->assertOk()
            ->assertSee('Not started child')->assertSee('Writing')->assertSee('Submitted child')->assertDontSee('Inactive child');
        $this->actingAs($f['teacher'])->get(route('cbt.staff.progress', $exam))->assertForbidden();
        $this->actingAs($f['admin'])->get(route('cbt.staff.progress', $exam))->assertOk();

        app(TenantContext::class)->set($f['school']->id);
        $otherSection = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher']);
        $otherLevel = AcademicLevel::create(['section_id' => $otherSection->id, 'name' => 'Primary']);
        $otherClass = SchoolClass::create(['academic_level_id' => $otherLevel->id, 'name' => 'Primary 1']);
        $otherSubject = Subject::create(['section_id' => $otherSection->id, 'name' => 'English']);
        $otherExam = Exam::create(['exam_type_id' => $f['schoolType']->id, 'class_id' => $otherClass->id, 'subject_id' => $otherSubject->id, 'created_by' => $f['admin']->id, 'title' => 'Other', 'duration' => 30, 'status' => 'Published', 'result_release_type' => 'Manual']);
        $this->actingAs($f['head'])->get(route('cbt.staff.progress', $otherExam))->assertNotFound();
    }

    public function test_exam_type_result_feed_is_admin_only_and_used_types_cannot_be_deleted(): void
    {
        $f = $this->fixture(true);
        $this->actingAs($f['head'])->get(route('cbt.staff.types.index'))->assertForbidden();
        $this->put(route('cbt.staff.types.update', $f['mockType']), ['name' => 'Mock', 'feeds_results' => 1, 'active' => 1])->assertForbidden();
        $this->actingAs($f['admin'])->put(route('cbt.staff.types.update', $f['mockType']), ['name' => 'Mock', 'feeds_results' => 1, 'active' => 1])->assertRedirect();
        app(TenantContext::class)->set($f['school']->id);
        $this->assertTrue($f['mockType']->fresh()->feeds_results);
        $exam = $this->exam($f, $f['mockType']);
        $this->delete(route('cbt.staff.types.destroy', $f['mockType']))->assertSessionHasErrors('type');
        $this->assertDatabaseHas('exam_types', ['id' => $f['mockType']->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cbt_exam_type_updated', 'subject_id' => $f['mockType']->id]);
    }

    public function test_conversion_submits_an_expired_attempt_before_building_results(): void
    {
        $f = $this->fixture(true);
        $exam = $this->exam($f, $f['schoolType'], [
            'duration' => 1, 'closes_automatically' => false,
            'term_id' => $f['term']->id, 'assessment_component_id' => $f['component']->id,
        ]);
        [$question, $correct] = $this->question($exam);
        $student = Student::where('user_id', $f['learner']->id)->firstOrFail();
        $attempt = ExamSubmission::create(['exam_id' => $exam->id, 'student_id' => $student->id, 'started_at' => now()->subMinutes(2), 'status' => 'In Progress']);
        ExamAnswer::create(['exam_submission_id' => $attempt->id, 'question_id' => $question->id, 'selected_option_id' => $correct->id]);

        $this->actingAs($f['head'])->post(route('cbt.staff.convert', $exam))->assertOk()->assertSee('Conversion complete');
        app(TenantContext::class)->set($f['school']->id);
        $this->assertSame('Submitted', $attempt->fresh()->status);
        $result = Result::where('student_id', $student->id)->firstOrFail();
        $this->assertSame('70.00', ResultItem::where('result_id', $result->id)->firstOrFail()->score);
    }

    public function test_automatic_and_manual_closing_are_time_aware_without_a_job(): void
    {
        $f = $this->fixture();
        Carbon::setTestNow('2026-11-04 10:00:00');
        $automatic = $this->exam($f, $f['schoolType'], ['closes_at' => now()->addMinute(), 'closes_automatically' => true]);
        $manual = $this->exam($f, $f['schoolType'], ['title' => 'Stay open', 'closes_at' => now()->subMinute(), 'closes_automatically' => false]);

        $this->assertFalse(app(ExamClosing::class)->countsAsClosed($automatic));
        Carbon::setTestNow('2026-11-04 10:02:00');
        $this->assertTrue(app(ExamClosing::class)->countsAsClosed($automatic));
        $this->assertFalse(app(ExamClosing::class)->countsAsClosed($manual));

        $this->actingAs($f['learner'])->post(route('cbt.start', $automatic))->assertSessionHasErrors('exam');
        $this->assertDatabaseMissing('exam_submissions', ['exam_id' => $automatic->id]);
        $this->actingAs($f['head'])->post(route('cbt.staff.close', $manual))->assertRedirect();
        app(TenantContext::class)->set($f['school']->id);
        $this->assertTrue(app(ExamClosing::class)->countsAsClosed($manual->refresh()));
        $this->assertDatabaseHas('audit_logs', ['action' => 'cbt_exam_ended', 'subject_id' => $manual->id, 'actor_name' => 'Head']);
        Carbon::setTestNow();
    }

    public function test_head_end_scope_teacher_denial_and_teacher_results_follow_effective_closing(): void
    {
        $f = $this->fixture();
        $otherSection = Section::create(['name' => 'Other', 'head_title' => 'Principal']);
        $otherLevel = AcademicLevel::create(['section_id' => $otherSection->id, 'name' => 'Other level']);
        $otherClass = SchoolClass::create(['academic_level_id' => $otherLevel->id, 'name' => 'Other class']);
        $otherSubject = Subject::create(['section_id' => $otherSection->id, 'name' => 'Other subject']);
        ClassSubject::create(['class_id' => $otherClass->id, 'subject_id' => $otherSubject->id]);
        $other = Exam::create([
            'exam_type_id' => $f['schoolType']->id, 'class_id' => $otherClass->id, 'subject_id' => $otherSubject->id,
            'created_by' => $f['admin']->id, 'title' => 'Other exam', 'duration' => 30, 'status' => 'Published',
            'closes_automatically' => false, 'result_release_type' => 'Manual',
        ]);
        $automatic = $this->exam($f, $f['schoolType'], ['title' => 'Automatic result', 'closes_at' => now()->subMinute(), 'closes_automatically' => true]);
        $manual = $this->exam($f, $f['schoolType'], ['title' => 'Manual result', 'closes_at' => now()->subMinute(), 'closes_automatically' => false]);

        $this->actingAs($f['teacher'])->get(route('cbt.results.index'))->assertOk()
            ->assertSee('Automatic result')->assertDontSee('Manual result');
        $this->post(route('cbt.staff.close', $manual))->assertForbidden();
        $this->actingAs($f['head'])->post(route('cbt.staff.close', $other))->assertNotFound();
        $this->post(route('cbt.staff.close', $manual))->assertRedirect();
        $this->actingAs($f['teacher'])->get(route('cbt.results.index'))->assertOk()->assertSee('Manual result');
    }

    public function test_school_exam_conversion_replaces_only_drafts_and_audits_one_summary(): void
    {
        $f = $this->fixture(true);
        $exam = $this->exam($f, $f['schoolType'], [
            'term_id' => $f['term']->id, 'assessment_component_id' => $f['component']->id,
        ]);
        $draft = $this->pupil($f, 'Draft pupil');
        $submitted = $this->pupil($f, 'Submitted pupil');
        $approved = $this->pupil($f, 'Approved pupil');
        $absent = $this->pupil($f, 'No attempt pupil');

        foreach ([[$draft, 'Draft', 12], [$submitted, 'Submitted', 13], [$approved, 'Approved', 14]] as [$student, $status, $score]) {
            $result = Result::create([
                'student_id' => $student->id, 'class_arm_id' => $f['arm']->id, 'subject_id' => $f['subject']->id,
                'term_id' => $f['term']->id, 'result_template_id' => $f['template']->id, 'status' => $status,
            ]);
            ResultItem::create(['result_id' => $result->id, 'assessment_component_id' => $f['component']->id, 'score' => $score]);
            ExamSubmission::create([
                'exam_id' => $exam->id, 'student_id' => $student->id, 'started_at' => now()->subHour(),
                'submitted_at' => now(), 'raw_score' => 40, 'max_score' => 50, 'status' => 'Submitted',
            ]);
        }

        $this->actingAs($f['head'])->get(route('cbt.staff.conversion', $exam))->assertOk()
            ->assertSee('Scores written')->assertSee('Existing scores replaced')->assertSee('Pupils skipped')
            ->assertSee('Result is Submitted')->assertSee('Result is Approved')->assertSee('No submitted attempt');
        $this->post(route('cbt.staff.convert', $exam))->assertOk()->assertSee('Conversion complete');

        app(TenantContext::class)->set($f['school']->id);
        $draftResult = Result::where('student_id', $draft->id)->firstOrFail();
        $this->assertSame('56.00', ResultItem::where('result_id', $draftResult->id)->where('assessment_component_id', $f['component']->id)->firstOrFail()->score);
        $this->assertSame('13.00', ResultItem::where('result_id', Result::where('student_id', $submitted->id)->value('id'))->firstOrFail()->score);
        $this->assertSame('14.00', ResultItem::where('result_id', Result::where('student_id', $approved->id)->value('id'))->firstOrFail()->score);
        $this->assertDatabaseMissing('results', ['student_id' => $absent->id]);

        $audits = AuditLog::where('action', 'cbt_results_converted')->get();
        $this->assertCount(1, $audits);
        $this->assertSame('Head', $audits->first()->actor_name);
        $this->assertSame(1, $audits->first()->metadata['written']);
        $this->assertSame(1, $audits->first()->metadata['replaced']);
        $this->assertSame(4, $audits->first()->metadata['skipped']);
        $this->assertSame('12.00', $audits->first()->metadata['previous_values'][0]['previous_score']);
    }

    public function test_non_school_exam_conversion_is_refused_and_changes_nothing(): void
    {
        $f = $this->fixture(true);
        $exam = $this->exam($f, $f['mockType'], ['term_id' => $f['term']->id, 'assessment_component_id' => $f['component']->id]);
        $student = $this->pupil($f, 'Mock pupil');
        $result = Result::create([
            'student_id' => $student->id, 'class_arm_id' => $f['arm']->id, 'subject_id' => $f['subject']->id,
            'term_id' => $f['term']->id, 'result_template_id' => $f['template']->id, 'status' => 'Draft',
        ]);
        ResultItem::create(['result_id' => $result->id, 'assessment_component_id' => $f['component']->id, 'score' => 22]);

        $this->actingAs($f['head'])->get(route('cbt.staff.show', $exam))->assertOk()->assertDontSee('Convert scores into Results');
        $this->get(route('cbt.staff.conversion', $exam))->assertSessionHasErrors('conversion');
        $this->post(route('cbt.staff.convert', $exam))->assertSessionHasErrors('conversion');
        app(TenantContext::class)->set($f['school']->id);
        $this->assertSame('22.00', ResultItem::firstOrFail()->score);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'cbt_results_converted']);
    }

    private function fixture(bool $results = false): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $admin = $this->user('Admin', ['manage_cbt', 'view_cbt_results', 'view_all_sections']);
        $head = $this->user('Head', ['manage_cbt', 'view_cbt_results']);
        $teacher = $this->user('Teacher', ['view_cbt_results']);
        $learner = $this->user('Learner', []);
        $section = Section::create(['name' => 'Secondary', 'head_title' => 'Principal']);
        $section->heads()->attach($head, ['school_id' => $school->id]);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'JSS']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JSS 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'Gold']);
        $subject = Subject::create(['section_id' => $section->id, 'name' => 'Mathematics']);
        ClassSubject::create(['class_id' => $class->id, 'subject_id' => $subject->id]);
        TeacherSubjectAssignment::create(['user_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id]);
        Student::create(['user_id' => $learner->id, 'class_arm_id' => $arm->id, 'status' => Student::STATUS_ACTIVE, 'admission_date' => '2026-09-01']);
        $data = compact('school', 'admin', 'head', 'teacher', 'learner', 'section', 'level', 'class', 'arm', 'subject');
        $data['schoolType'] = ExamType::create(['name' => 'School exam', 'feeds_results' => true, 'active' => true]);
        $data['mockType'] = ExamType::create(['name' => 'Mock', 'feeds_results' => false, 'active' => true]);

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
        $user = User::create(['name' => $name, 'email' => strtolower(str_replace(' ', '-', $name)).'@example.test', 'password' => 'password-12345', 'status' => 'active']);
        $role = Role::create(['name' => $name.' role']);
        foreach ($permissions as $permission) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $permission]));
        }
        $user->roles()->attach($role);

        return $user;
    }

    private function exam(array $f, ExamType $type, array $overrides = []): Exam
    {
        return Exam::create([
            'exam_type_id' => $type->id, 'class_id' => $f['class']->id, 'subject_id' => $f['subject']->id,
            'created_by' => $f['admin']->id, 'title' => 'CBT exam', 'duration' => 30, 'status' => 'Published',
            'closes_automatically' => true, 'result_release_type' => 'Manual', ...$overrides,
        ]);
    }

    private function pupil(array $f, string $name): Student
    {
        $user = $this->user($name, []);

        return Student::create(['user_id' => $user->id, 'class_arm_id' => $f['arm']->id, 'status' => Student::STATUS_ACTIVE, 'admission_date' => '2026-09-01']);
    }

    private function question(Exam $exam): array
    {
        $question = Question::create(['exam_id' => $exam->id, 'text' => 'Two plus two?', 'marks' => 5, 'position' => 1]);
        $correct = QuestionOption::create(['question_id' => $question->id, 'text' => 'Four', 'is_correct' => true]);
        QuestionOption::create(['question_id' => $question->id, 'text' => 'Five', 'is_correct' => false]);

        return [$question, $correct];
    }
}
