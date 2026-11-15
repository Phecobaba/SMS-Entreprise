<?php

namespace Tests\Feature\CBT;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Admissions\Models\Student;
use App\Modules\CBT\Models\Exam;
use App\Modules\CBT\Models\ExamAnswer;
use App\Modules\CBT\Models\ExamAnswerSelection;
use App\Modules\CBT\Models\ExamSubmission;
use App\Modules\CBT\Models\ExamType;
use App\Modules\CBT\Models\Question;
use App\Modules\CBT\Models\QuestionOption;
use App\Modules\CBT\Services\ExamAttemptLifecycle;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CbtMultipleAnswerTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_credit_sums_only_selected_correct_option_marks_and_reviews_every_option(): void
    {
        $f = $this->fixture();
        [$multi, $first, $second, $wrong] = $this->multipleQuestion($f['exam']);
        [$single, $singleCorrect] = $this->singleQuestion($f['exam']);
        $attempt = $this->attempt($f);

        app(ExamAttemptLifecycle::class)->submit($attempt, [
            $multi->id => [$first->id, $wrong->id],
            $single->id => $singleCorrect->id,
        ]);

        $this->assertSame('6.00', $attempt->fresh()->raw_score);
        $this->assertSame('9.00', $attempt->fresh()->max_score);
        $this->actingAs($f['admin'])->get(route('cbt.results.pupil', [$f['exam'], $attempt]))
            ->assertOk()->assertSee('Selected')->assertSee('Correct')->assertSee('2 / 5 marks earned');
        $this->actingAs($f['learner'])->get(route('cbt.result', $attempt))
            ->assertOk()->assertSee('2 / 5 marks')->assertSee($first->text)->assertSee($second->text);
    }

    public function test_autosave_replaces_selection_set_restores_it_and_rejects_too_many(): void
    {
        $f = $this->fixture();
        [$question, $first, $second, $wrong] = $this->multipleQuestion($f['exam']);
        $attempt = $this->attempt($f);

        $this->actingAs($f['learner'])->postJson(route('cbt.answers.store', $attempt), [
            'question_id' => $question->id, 'option_ids' => [$first->id, $second->id], 'flagged' => false,
        ])->assertNoContent();
        app(TenantContext::class)->set($f['school']->id);
        $this->assertSame(2, ExamAnswerSelection::count());

        $this->postJson(route('cbt.answers.store', $attempt), [
            'question_id' => $question->id, 'option_ids' => [$second->id], 'flagged' => true,
        ])->assertNoContent();
        app(TenantContext::class)->set($f['school']->id);
        $this->assertSame([$second->id], ExamAnswerSelection::pluck('question_option_id')->all());
        $this->get(route('cbt.sit', $attempt))->assertOk()
            ->assertSee('Choose up to 2 answers.')->assertSee('value="'.$second->id.'" checked', false);

        $this->postJson(route('cbt.answers.store', $attempt), [
            'question_id' => $question->id, 'option_ids' => [$first->id, $second->id, $wrong->id], 'flagged' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('answers');
        app(TenantContext::class)->set($f['school']->id);
        $this->assertSame([$second->id], ExamAnswerSelection::pluck('question_option_id')->all());
    }

    public function test_question_rules_require_whole_marks_correct_and_wrong_options_and_lock_after_start(): void
    {
        $f = $this->fixture(['status' => 'Draft']);
        $this->actingAs($f['admin'])->post(route('cbt.staff.questions.store', $f['exam']), [
            'text' => 'Valid multiple answer', 'options' => ['A', 'B', 'C'],
            'correct_options' => [0, 1], 'option_marks' => [0 => 2, 1 => 3],
        ])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($f['school']->id);
        $question = Question::firstOrFail();
        $this->assertSame(5, (int) $question->marks);

        $this->post(route('cbt.staff.questions.store', $f['exam']), [
            'text' => 'Fractional', 'options' => ['A', 'B'],
            'correct_options' => [0], 'option_marks' => [0 => 1.5],
        ])->assertSessionHasErrors('option_marks');
        $this->post(route('cbt.staff.questions.store', $f['exam']), [
            'text' => 'All correct', 'options' => ['A', 'B'],
            'correct_options' => [0, 1], 'option_marks' => [0 => 1, 1 => 1],
        ])->assertSessionHasErrors('question');

        app(TenantContext::class)->set($f['school']->id);
        $this->attempt($f);
        $this->put(route('cbt.staff.questions.update', [$f['exam'], $question]), [
            'text' => 'Changed', 'options' => ['A', 'B', 'C'],
            'correct_options' => [0], 'option_marks' => [0 => 8],
        ])->assertSessionHasErrors('question');
        app(TenantContext::class)->set($f['school']->id);
        $this->assertSame('Valid multiple answer', $question->fresh()->text);
        $this->assertSame(5, (int) $question->fresh()->marks);
    }

    public function test_publish_rechecks_multiple_answer_shape(): void
    {
        $f = $this->fixture(['status' => 'Draft']);
        $question = Question::create(['exam_id' => $f['exam']->id, 'text' => 'Invalid', 'marks' => 2, 'position' => 1]);
        $first = QuestionOption::create(['question_id' => $question->id, 'text' => 'A', 'is_correct' => true, 'marks' => 1]);
        $second = QuestionOption::create(['question_id' => $question->id, 'text' => 'B', 'is_correct' => true, 'marks' => 1]);

        $this->actingAs($f['admin'])->post(route('cbt.staff.publish', $f['exam']))->assertSessionHasErrors('questions');
        app(TenantContext::class)->set($f['school']->id);
        $second->update(['is_correct' => false, 'marks' => 0]);
        $this->post(route('cbt.staff.publish', $f['exam']))->assertSessionHasNoErrors();
        $this->assertSame('Published', $f['exam']->fresh()->status);
        $this->assertSame(1, $first->fresh()->marks);
    }

    public function test_legacy_weight_and_selected_option_score_are_preserved(): void
    {
        $f = $this->fixture();
        $question = Question::create(['exam_id' => $f['exam']->id, 'text' => 'Legacy', 'marks' => 7, 'position' => 1]);
        $correct = QuestionOption::create(['question_id' => $question->id, 'text' => 'Yes', 'is_correct' => true]);
        QuestionOption::create(['question_id' => $question->id, 'text' => 'No', 'is_correct' => false]);
        $attempt = $this->attempt($f);
        ExamAnswer::create([
            'exam_submission_id' => $attempt->id, 'question_id' => $question->id,
            'selected_option_id' => $correct->id,
        ]);

        app(ExamAttemptLifecycle::class)->submit($attempt, []);

        $this->assertSame(7, $correct->fresh()->marks);
        $this->assertSame('7.00', $attempt->fresh()->raw_score);
        $this->assertSame('7.00', $attempt->fresh()->max_score);
    }

    private function fixture(array $examOverrides = []): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $admin = User::create(['name' => 'Admin', 'email' => 'multi-admin@example.test', 'password' => 'password-12345', 'status' => 'active']);
        $learner = User::create(['name' => 'Learner', 'email' => 'multi-learner@example.test', 'password' => 'password-12345', 'status' => 'active']);
        $role = Role::create(['name' => 'CBT Admin']);
        $role->permissions()->attach(collect(['manage_cbt', 'view_cbt_results', 'view_all_sections'])->map(
            fn (string $name) => Permission::firstOrCreate(['name' => $name])->id,
        ));
        $admin->roles()->attach($role);
        $section = Section::create(['name' => 'Secondary School', 'head_title' => 'Principal']);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Secondary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JSS 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'Gold']);
        $subject = Subject::create(['section_id' => $section->id, 'name' => 'Mathematics']);
        $student = Student::create(['user_id' => $learner->id, 'class_arm_id' => $arm->id, 'admission_date' => today()]);
        $type = ExamType::create(['name' => 'Internal']);
        $exam = Exam::create(array_merge([
            'exam_type_id' => $type->id, 'subject_id' => $subject->id, 'class_id' => $class->id,
            'created_by' => $admin->id, 'title' => 'Multiple answers', 'duration' => 30,
            'status' => 'Published', 'result_release_type' => 'Immediate',
            'show_score' => true, 'show_correct_answers' => true,
        ], $examOverrides));

        return compact('school', 'admin', 'learner', 'student', 'exam');
    }

    private function multipleQuestion(Exam $exam): array
    {
        $question = Question::create(['exam_id' => $exam->id, 'text' => 'Choose two', 'marks' => 5, 'position' => 1]);
        $first = QuestionOption::create(['question_id' => $question->id, 'text' => 'First', 'is_correct' => true, 'marks' => 2]);
        $second = QuestionOption::create(['question_id' => $question->id, 'text' => 'Second', 'is_correct' => true, 'marks' => 3]);
        $wrong = QuestionOption::create(['question_id' => $question->id, 'text' => 'Wrong', 'is_correct' => false, 'marks' => 0]);

        return [$question->load('options'), $first, $second, $wrong];
    }

    private function singleQuestion(Exam $exam): array
    {
        $question = Question::create(['exam_id' => $exam->id, 'text' => 'Choose one', 'marks' => 4, 'position' => 2]);
        $correct = QuestionOption::create(['question_id' => $question->id, 'text' => 'Yes', 'is_correct' => true, 'marks' => 4]);
        QuestionOption::create(['question_id' => $question->id, 'text' => 'No', 'is_correct' => false, 'marks' => 0]);

        return [$question->load('options'), $correct];
    }

    private function attempt(array $fixture): ExamSubmission
    {
        return ExamSubmission::create([
            'exam_id' => $fixture['exam']->id, 'student_id' => $fixture['student']->id,
            'started_at' => now(), 'status' => 'In Progress',
        ]);
    }
}
