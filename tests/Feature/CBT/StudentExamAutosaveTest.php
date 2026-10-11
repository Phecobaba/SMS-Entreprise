<?php

namespace Tests\Feature\CBT;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Subject;
use App\Modules\Admissions\Models\Student;
use App\Modules\CBT\Models\Exam;
use App\Modules\CBT\Models\ExamAnswer;
use App\Modules\CBT\Models\ExamSubmission;
use App\Modules\CBT\Models\ExamType;
use App\Modules\CBT\Models\Question;
use App\Modules\CBT\Models\QuestionOption;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentExamAutosaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_autosave_rejects_an_answer_after_the_grace_window(): void
    {
        $fixture = $this->fixture();
        $attempt = $this->attempt($fixture['exam'], $fixture['student'], now()->subMinutes(2)->subSeconds(31));

        $this->actingAs($fixture['learner'])->postJson(route('cbt.answers.store', $attempt), [
            'question_id' => $fixture['question']->id,
            'option_id' => $fixture['correct']->id,
            'flagged' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('submission');
    }

    public function test_autosave_rejects_another_students_attempt(): void
    {
        $fixture = $this->fixture();
        $attempt = $this->attempt($fixture['exam'], $fixture['student'], now());

        $this->actingAs($fixture['otherLearner'])->postJson(route('cbt.answers.store', $attempt), [
            'question_id' => $fixture['question']->id,
            'option_id' => $fixture['correct']->id,
            'flagged' => false,
        ])->assertForbidden();
    }

    public function test_start_rejects_an_exam_after_its_closing_time(): void
    {
        $fixture = $this->fixture(['closes_at' => now()->subSecond()]);

        $this->actingAs($fixture['learner'])->from(route('cbt.mine'))
            ->post(route('cbt.start', $fixture['exam']))
            ->assertRedirect(route('cbt.mine'))
            ->assertSessionHasErrors('exam');

        $this->assertDatabaseMissing('exam_submissions', ['exam_id' => $fixture['exam']->id]);
    }

    public function test_late_submit_scores_only_answers_saved_inside_the_grace_window(): void
    {
        $fixture = $this->fixture();
        $attempt = $this->attempt($fixture['exam'], $fixture['student'], now());
        ExamAnswer::create([
            'exam_submission_id' => $attempt->id,
            'question_id' => $fixture['question']->id,
            'selected_option_id' => $fixture['correct']->id,
            'flagged' => true,
        ]);
        $attempt->update(['started_at' => now()->subMinutes(2)->subSeconds(31)]);

        $this->actingAs($fixture['learner'])->post(route('cbt.submit', $attempt), [
            'answers' => [$fixture['question']->id => $fixture['wrong']->id],
        ])->assertRedirect();

        $this->assertSame('5.00', $attempt->fresh()->raw_score);
    }

    public function test_autosave_rejects_an_option_from_another_exam(): void
    {
        $fixture = $this->fixture();
        $attempt = $this->attempt($fixture['exam'], $fixture['student'], now());
        $otherExam = $this->exam($fixture, ['title' => 'Other exam']);
        $otherQuestion = Question::create(['exam_id' => $otherExam->id, 'text' => 'Other question', 'marks' => 1, 'position' => 1]);
        $otherOption = QuestionOption::create(['question_id' => $otherQuestion->id, 'text' => 'Other option', 'is_correct' => true]);

        $this->actingAs($fixture['learner'])->postJson(route('cbt.answers.store', $attempt), [
            'question_id' => $fixture['question']->id,
            'option_id' => $otherOption->id,
            'flagged' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('answer');
    }

    private function fixture(array $examOverrides = []): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $admin = User::create(['name' => 'Admin', 'email' => 'autosave-admin@example.test', 'password' => 'password-12345', 'status' => 'active']);
        $learner = User::create(['name' => 'Learner', 'email' => 'autosave-learner@example.test', 'password' => 'password-12345', 'status' => 'active']);
        $otherLearner = User::create(['name' => 'Other learner', 'email' => 'autosave-other@example.test', 'password' => 'password-12345', 'status' => 'active']);
        $level = AcademicLevel::create(['name' => 'Secondary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JSS 2']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'Gold']);
        $subject = Subject::create(['name' => 'Mathematics']);
        $student = Student::create(['user_id' => $learner->id, 'class_arm_id' => $arm->id, 'admission_date' => today()]);
        Student::create(['user_id' => $otherLearner->id, 'class_arm_id' => $arm->id, 'admission_date' => today()]);
        $type = ExamType::create(['name' => 'Internal']);
        $fixture = compact('admin', 'learner', 'otherLearner', 'class', 'subject', 'student', 'type');
        $exam = $this->exam($fixture, $examOverrides);
        $question = Question::create(['exam_id' => $exam->id, 'text' => 'Two plus two?', 'marks' => 5, 'position' => 1]);
        $correct = QuestionOption::create(['question_id' => $question->id, 'text' => 'Four', 'is_correct' => true]);
        $wrong = QuestionOption::create(['question_id' => $question->id, 'text' => 'Five', 'is_correct' => false]);

        return [...$fixture, ...compact('exam', 'question', 'correct', 'wrong')];
    }

    private function exam(array $fixture, array $overrides = []): Exam
    {
        return Exam::create(array_merge([
            'exam_type_id' => $fixture['type']->id,
            'subject_id' => $fixture['subject']->id,
            'class_id' => $fixture['class']->id,
            'created_by' => $fixture['admin']->id,
            'title' => 'Student exam',
            'duration' => 2,
            'status' => 'Published',
            'result_release_type' => 'Manual',
        ], $overrides));
    }

    private function attempt(Exam $exam, Student $student, $startedAt): ExamSubmission
    {
        return ExamSubmission::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'started_at' => $startedAt,
            'status' => 'In Progress',
        ]);
    }
}
