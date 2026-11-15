<?php

namespace Tests\Feature\CBT;

use App\Modules\Academics\Models\AcademicLevel;
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
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CbtScopeAndResultsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private User $head;

    private User $teacher;

    private Section $primary;

    private Section $secondary;

    private SchoolClass $primaryClass;

    private SchoolClass $secondaryClass;

    private Subject $primarySubject;

    private Subject $secondarySubject;

    private ExamType $internalType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $this->school->id);
        app(TenantContext::class)->set($this->school->id);

        $this->admin = $this->user('Admin', ['manage_cbt', 'view_cbt_results', 'view_all_sections']);
        $this->head = $this->user('Head', ['manage_cbt', 'view_cbt_results']);
        $this->teacher = $this->user('Teacher', ['view_cbt_results']);

        $this->primary = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher']);
        $this->secondary = Section::create(['name' => 'Secondary', 'head_title' => 'Principal']);
        $this->primary->heads()->attach($this->head, ['school_id' => $this->school->id]);
        [$this->primaryClass, $this->primarySubject] = $this->pair($this->primary, 'Primary 5', 'Mathematics');
        [$this->secondaryClass, $this->secondarySubject] = $this->pair($this->secondary, 'JS 1', 'English');
        $this->internalType = ExamType::create(['name' => 'Internal']);
    }

    public function test_head_management_is_scoped_for_every_exam_action_and_handles_two_or_no_sections(): void
    {
        $own = $this->exam($this->primaryClass, $this->primarySubject, 'Own draft');
        $other = $this->exam($this->secondaryClass, $this->secondarySubject, 'Other draft');
        $otherQuestion = $this->question($other);

        $this->actingAs($this->head)->post(route('cbt.staff.exams.store'), $this->examPayload($this->primaryClass, $this->primarySubject, 'Created in scope'))->assertRedirect();
        $this->post(route('cbt.staff.exams.store'), $this->examPayload($this->secondaryClass, $this->secondarySubject, 'Forged create'))->assertNotFound();
        $this->actingAs($this->head)->get(route('cbt.staff.index'))->assertOk()->assertSee('Own draft')->assertSee('Primary 5')->assertDontSee('Other draft')->assertDontSee('JS 1');
        $this->get(route('cbt.staff.show', $own))->assertOk();
        $this->get(route('cbt.staff.edit', $own))->assertOk();
        $this->put(route('cbt.staff.update', $own), $this->examPayload($this->primaryClass, $this->primarySubject, 'Own saved'))->assertSessionHasNoErrors();
        $this->post(route('cbt.staff.questions.store', $own), $this->questionPayload())->assertSessionHasNoErrors();
        $this->post(route('cbt.staff.publish', $own))->assertSessionHasNoErrors();
        $this->post(route('cbt.staff.release', $own))->assertSessionHasNoErrors();
        $this->post(route('cbt.staff.close', $own))->assertSessionHasNoErrors();
        $this->get(route('cbt.staff.attempts', $own))->assertOk();

        $checks = [
            ['get', route('cbt.staff.show', $other), []],
            ['get', route('cbt.staff.edit', $other), []],
            ['put', route('cbt.staff.update', $other), $this->examPayload($this->secondaryClass, $this->secondarySubject, 'Forged')],
            ['post', route('cbt.staff.questions.store', $other), $this->questionPayload()],
            ['put', route('cbt.staff.questions.update', [$other, $otherQuestion]), $this->questionPayload()],
            ['delete', route('cbt.staff.questions.destroy', [$other, $otherQuestion]), []],
            ['post', route('cbt.staff.publish', $other), []],
            ['post', route('cbt.staff.release', $other), []],
            ['post', route('cbt.staff.close', $other), []],
            ['delete', route('cbt.staff.destroy', $other), []],
            ['get', route('cbt.staff.attempts', $other), []],
        ];
        foreach ($checks as [$method, $url, $data]) {
            $this->{$method}($url, $data)->assertNotFound();
        }

        app(TenantContext::class)->set($this->school->id);
        $secondHead = $this->user('Two Sections', ['manage_cbt', 'view_cbt_results']);
        $secondHead->sections()->attach([
            $this->primary->id => ['school_id' => $this->school->id],
            $this->secondary->id => ['school_id' => $this->school->id],
        ]);
        $this->actingAs($secondHead)->get(route('cbt.staff.index'))->assertOk()->assertSee('Other draft');
        app(TenantContext::class)->set($this->school->id);
        $noSection = $this->user('No Section', ['manage_cbt', 'view_cbt_results']);
        $this->actingAs($noSection)->get(route('cbt.staff.index'))->assertOk()->assertDontSee('Other draft');
    }

    public function test_teacher_has_no_management_access_and_entrance_cbt_is_admin_only(): void
    {
        $exam = $this->exam($this->primaryClass, $this->primarySubject, 'Protected draft');
        $question = $this->question($exam);
        foreach ([
            ['get', route('cbt.staff.index')],
            ['post', route('cbt.staff.types.store')],
            ['post', route('cbt.staff.exams.store')],
            ['get', route('cbt.staff.show', $exam)],
            ['get', route('cbt.staff.edit', $exam)],
            ['put', route('cbt.staff.update', $exam)],
            ['post', route('cbt.staff.questions.store', $exam)],
            ['put', route('cbt.staff.questions.update', [$exam, $question])],
            ['delete', route('cbt.staff.questions.destroy', [$exam, $question])],
            ['post', route('cbt.staff.publish', $exam)],
            ['post', route('cbt.staff.release', $exam)],
            ['post', route('cbt.staff.close', $exam)],
            ['delete', route('cbt.staff.destroy', $exam)],
            ['get', route('cbt.staff.attempts', $exam)],
        ] as [$method, $url]) {
            $this->actingAs($this->teacher)->{$method}($url)->assertForbidden();
        }

        app(TenantContext::class)->set($this->school->id);
        $entrance = ExamType::create(['name' => 'Entrance']);
        $payload = [
            'exam_type_id' => $entrance->id, 'title' => 'Entrance', 'duration' => 30,
            'pass_mark' => 50, 'result_release_type' => 'Immediate',
        ];
        $this->actingAs($this->head)->post(route('cbt.staff.exams.store'), $payload)->assertForbidden();
        $this->actingAs($this->admin)->post(route('cbt.staff.exams.store'), $payload)->assertRedirect();
        $this->assertDatabaseHas('exams', ['title' => 'Entrance', 'class_id' => null]);
    }

    public function test_exam_subject_must_be_assigned_to_the_selected_scoped_class(): void
    {
        $unassigned = Subject::create(['section_id' => $this->primary->id, 'name' => 'Unassigned']);
        $payload = $this->examPayload($this->primaryClass, $unassigned, 'Forged pair');
        $this->actingAs($this->head)->post(route('cbt.staff.exams.store'), $payload)
            ->assertSessionHasErrors(['subject_id' => 'Assign this subject to the class before creating an exam.']);
        $this->post(route('cbt.staff.exams.store'), $this->examPayload($this->primaryClass, $this->primarySubject, 'Assigned pair'))
            ->assertSessionHasNoErrors();

        app(TenantContext::class)->set($this->school->id);
        $emptyLevel = AcademicLevel::create(['section_id' => $this->primary->id, 'name' => 'Empty Level']);
        SchoolClass::create(['academic_level_id' => $emptyLevel->id, 'name' => 'Empty Class']);
        $this->get(route('cbt.staff.index'))->assertOk()->assertSee('No subjects are assigned to this class.');
        $this->post(route('cbt.staff.exams.store'), $this->examPayload($this->secondaryClass, $this->secondarySubject, 'Outside section'))
            ->assertNotFound();
    }

    public function test_delete_allows_only_a_draft_without_attempts(): void
    {
        $deletable = $this->exam($this->primaryClass, $this->primarySubject, 'Delete me');
        $this->actingAs($this->head)->delete(route('cbt.staff.destroy', $deletable))->assertRedirect(route('cbt.staff.index'));
        $this->assertDatabaseMissing('exams', ['id' => $deletable->id]);

        app(TenantContext::class)->set($this->school->id);
        $withAttempt = $this->exam($this->primaryClass, $this->primarySubject, 'Has attempt');
        $student = $this->student($this->primaryClass);
        ExamSubmission::create(['exam_id' => $withAttempt->id, 'student_id' => $student->id, 'started_at' => now(), 'status' => 'In Progress']);
        $this->delete(route('cbt.staff.destroy', $withAttempt))->assertSessionHasErrors('exam');

        foreach (['Published', 'Closed'] as $status) {
            app(TenantContext::class)->set($this->school->id);
            $record = $this->exam($this->primaryClass, $this->primarySubject, $status, $status);
            $this->delete(route('cbt.staff.destroy', $record))->assertSessionHasErrors('exam');
            $this->assertDatabaseHas('exams', ['id' => $record->id]);
        }
    }

    public function test_teacher_results_are_closed_assigned_pairs_only_with_read_only_answer_detail(): void
    {
        TeacherSubjectAssignment::create(['user_id' => $this->teacher->id, 'class_id' => $this->primaryClass->id, 'subject_id' => $this->primarySubject->id]);
        $closed = $this->exam($this->primaryClass, $this->primarySubject, 'Closed Mathematics', 'Closed');
        $open = $this->exam($this->primaryClass, $this->primarySubject, 'Open Mathematics', 'Published');
        $other = $this->exam($this->secondaryClass, $this->secondarySubject, 'Other English', 'Closed');
        [$submission, $question, $correct, $wrong] = $this->answeredAttempt($closed);

        $this->actingAs($this->teacher)->get(route('cbt.results.index'))->assertOk()
            ->assertSee('Closed Mathematics')->assertDontSee('Open Mathematics')->assertDontSee('Other English');
        $this->get(route('cbt.results.show', $open))->assertNotFound();
        $this->get(route('cbt.results.show', $other))->assertNotFound();
        $this->get(route('cbt.results.pupil', [$closed, $submission]))->assertOk()
            ->assertSee($question->text)->assertSee($wrong->text)->assertSee($correct->text)->assertSee('Wrong')->assertSee('Right');
    }

    public function test_head_results_are_section_wide_and_admin_results_are_school_wide(): void
    {
        $own = $this->exam($this->primaryClass, $this->primarySubject, 'Primary result', 'Published');
        $other = $this->exam($this->secondaryClass, $this->secondarySubject, 'Secondary result', 'Closed');

        $this->actingAs($this->head)->get(route('cbt.results.index'))->assertOk()->assertSee('Primary result')->assertDontSee('Secondary result');
        $this->get(route('cbt.results.show', $own))->assertOk();
        $this->get(route('cbt.results.show', $other))->assertNotFound();
        $this->actingAs($this->admin)->get(route('cbt.results.index'))->assertOk()->assertSee('Primary result')->assertSee('Secondary result');
        $this->get(route('cbt.results.show', $other))->assertOk();
    }

    public function test_permission_migration_adds_head_and_teacher_access_without_removing_custom_permissions(): void
    {
        $principal = Role::create(['name' => 'Principal']);
        $teacher = Role::create(['name' => 'Teacher']);
        $bursar = Role::create(['name' => 'Bursar']);
        $custom = Permission::firstOrCreate(['name' => 'custom_school_permission']);
        $principal->permissions()->attach($custom);

        $migration = require app_path('Modules/Core/Database/Migrations/2026_11_04_000500_grant_cbt_scope_permissions.php');
        $migration->up();

        $this->assertTrue($principal->permissions()->where('name', 'manage_cbt')->exists());
        $this->assertTrue($principal->permissions()->where('name', 'view_cbt_results')->exists());
        $this->assertTrue($principal->permissions()->where('name', 'custom_school_permission')->exists());
        $this->assertTrue($teacher->permissions()->where('name', 'view_cbt_results')->exists());
        $this->assertFalse($teacher->permissions()->where('name', 'manage_cbt')->exists());
        $this->assertFalse($bursar->permissions()->where('name', 'manage_cbt')->exists());
        $this->assertFalse($bursar->permissions()->where('name', 'view_cbt_results')->exists());
    }

    private function user(string $name, array $permissions): User
    {
        $user = User::create(['name' => $name, 'email' => str($name)->slug().'-'.uniqid().'@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $role = Role::create(['name' => $name.' '.uniqid()]);
        $role->permissions()->attach(collect($permissions)->map(fn (string $permission) => Permission::firstOrCreate(['name' => $permission])->id));
        $user->roles()->attach($role);

        return $user;
    }

    private function pair(Section $section, string $className, string $subjectName): array
    {
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => $className.' Level']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => $className]);
        ClassArm::create(['class_id' => $class->id, 'name' => 'A']);
        $subject = Subject::create(['section_id' => $section->id, 'name' => $subjectName]);
        ClassSubject::create(['class_id' => $class->id, 'subject_id' => $subject->id]);

        return [$class, $subject];
    }

    private function exam(SchoolClass $class, Subject $subject, string $title, string $status = 'Draft'): Exam
    {
        return Exam::create([
            'exam_type_id' => $this->internalType->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'created_by' => $this->admin->id, 'title' => $title, 'duration' => 30, 'status' => $status,
            'result_release_type' => 'Manual', 'show_score' => true,
        ]);
    }

    private function question(Exam $exam): Question
    {
        $question = Question::create(['exam_id' => $exam->id, 'text' => 'Question '.$exam->id, 'marks' => 1, 'position' => 1]);
        QuestionOption::create(['question_id' => $question->id, 'text' => 'Yes', 'is_correct' => true]);
        QuestionOption::create(['question_id' => $question->id, 'text' => 'No', 'is_correct' => false]);

        return $question;
    }

    private function student(SchoolClass $class): Student
    {
        $user = User::create(['name' => 'Pupil '.uniqid(), 'email' => uniqid().'@pupil.test', 'password' => 'long-password-here', 'status' => 'active']);

        return Student::create(['user_id' => $user->id, 'class_arm_id' => $class->arms()->firstOrFail()->id, 'status' => Student::STATUS_ACTIVE, 'admission_date' => today()]);
    }

    private function answeredAttempt(Exam $exam): array
    {
        $question = $this->question($exam);
        $correct = $question->options()->where('is_correct', true)->firstOrFail();
        $wrong = $question->options()->where('is_correct', false)->firstOrFail();
        $student = $this->student($exam->schoolClass);
        $submission = ExamSubmission::create(['exam_id' => $exam->id, 'student_id' => $student->id, 'started_at' => now()->subMinute(), 'submitted_at' => now(), 'raw_score' => 0, 'max_score' => 1, 'status' => 'Submitted']);
        ExamAnswer::create(['exam_submission_id' => $submission->id, 'question_id' => $question->id, 'selected_option_id' => $wrong->id]);
        $rightQuestion = Question::create(['exam_id' => $exam->id, 'text' => 'Second question', 'marks' => 1, 'position' => 2]);
        $rightOption = QuestionOption::create(['question_id' => $rightQuestion->id, 'text' => 'Right choice', 'is_correct' => true]);
        QuestionOption::create(['question_id' => $rightQuestion->id, 'text' => 'Other choice', 'is_correct' => false]);
        ExamAnswer::create(['exam_submission_id' => $submission->id, 'question_id' => $rightQuestion->id, 'selected_option_id' => $rightOption->id]);

        return [$submission, $question, $correct, $wrong];
    }

    private function examPayload(SchoolClass $class, Subject $subject, string $title): array
    {
        return ['exam_type_id' => $this->internalType->id, 'class_id' => $class->id, 'subject_id' => $subject->id, 'title' => $title, 'duration' => 30, 'result_release_type' => 'Manual'];
    }

    private function questionPayload(): array
    {
        return ['text' => 'Scoped question', 'marks' => 1, 'options' => ['Yes', 'No'], 'correct_index' => 0];
    }
}
