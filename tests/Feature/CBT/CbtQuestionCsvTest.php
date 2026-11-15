<?php

namespace Tests\Feature\CBT;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Admissions\Models\Student;
use App\Modules\CBT\Models\Exam;
use App\Modules\CBT\Models\ExamSubmission;
use App\Modules\CBT\Models\ExamType;
use App\Modules\CBT\Models\Question;
use App\Modules\CBT\Models\QuestionOption;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CbtQuestionCsvTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $head;

    private User $teacher;

    private SchoolClass $ownClass;

    private SchoolClass $otherClass;

    private Subject $ownSubject;

    private Subject $otherSubject;

    private ExamType $type;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $school = $this->school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $this->admin = $this->user('Admin', ['manage_cbt', 'view_all_sections']);
        $this->head = $this->user('Head', ['manage_cbt', 'approve_results']);
        $this->teacher = $this->user('Teacher', []);
        $own = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher']);
        $other = Section::create(['name' => 'Secondary', 'head_title' => 'Principal']);
        $own->heads()->attach($this->head, ['school_id' => $school->id]);
        [$this->ownClass, $this->ownSubject] = $this->pair($own, 'Primary 6', 'Mathematics');
        [$this->otherClass, $this->otherSubject] = $this->pair($other, 'JS 1', 'English');
        $this->type = ExamType::create(['name' => 'Internal']);
    }

    public function test_valid_rows_append_single_and_multiple_answers_as_plain_text_and_write_one_audit(): void
    {
        $exam = $this->exam($this->ownClass, $this->ownSubject);
        $existing = $this->question($exam, 'Existing', 1);
        $rows = [
            ['=Two plus two?', 'Four', '2', 'Five', '', '', '', '', '', '', '', '', ''],
            ['+Choose vowels', 'A', '1', 'B', '', 'E', '3', 'C', '', '', '', '', ''],
            ['-Leading minus stays text', 'Yes', '1', 'No', '', '', '', '', '', '', '', '', ''],
            ['@Leading at stays text', 'Yes', '1', 'No', '', '', '', '', '', '', '', '', ''],
        ];
        $template = $this->actingAs($this->admin)->get(route('cbt.staff.questions.template', $exam));
        $template->assertOk();
        $this->assertStringStartsWith("\xEF\xBB\xBF#", $template->getContent());
        $this->assertStringContainsString('Question,"Option A","Option B","Option C","Option D","Option E","Option F","Correct Answer"', $template->getContent());
        $this->assertSame(3, substr_count($template->getContent(), 'EXAMPLE — delete:'));
        $headers = collect([
            'Question', 'Option A', 'Marks for A', 'Option B', 'Marks for B', 'Option C', 'Marks for C',
            'Option D', 'Marks for D', 'Option E', 'Marks for E', 'Option F', 'Marks for F',
        ])->map(fn (string $header): string => ' '.mb_strtolower($header).' ')->all();

        $preview = $this->preview($this->admin, $exam, $this->csv($rows, $headers));
        $preview->assertOk()->assertSee('Single answer')->assertSee('Multiple answer')->assertSee('8 total marks');
        $data = $preview->viewData('questionUploadPreview');
        $this->assertSame(4, $data['questions_to_add']);
        $this->assertSame(8, $data['total_marks']);
        $this->assertSame(5, $data['resulting_question_count']);
        $stage = $this->stage();

        $this->post(route('cbt.staff.questions.apply', $exam), ['upload_token' => $stage['token']])
            ->assertRedirect(route('cbt.staff.show', $exam));

        app(TenantContext::class)->set($this->school->id);
        $questions = Question::where('exam_id', $exam->id)->with('options')->orderBy('position')->get();
        $this->assertSame([$existing->id, 2, 3, 4, 5], [$questions[0]->id, $questions[1]->position, $questions[2]->position, $questions[3]->position, $questions[4]->position]);
        $this->assertSame('=Two plus two?', $questions[1]->text);
        $this->assertSame('+Choose vowels', $questions[2]->text);
        $this->assertSame('-Leading minus stays text', $questions[3]->text);
        $this->assertSame('@Leading at stays text', $questions[4]->text);
        $this->assertSame([2, 0], $questions[1]->options->pluck('marks')->all());
        $this->assertSame([1, 0, 3, 0], $questions[2]->options->pluck('marks')->all());
        $this->assertSame(1, AuditLog::where('action', 'cbt_question_csv_applied')->count());
        $audit = AuditLog::where('action', 'cbt_question_csv_applied')->firstOrFail();
        $this->assertSame(4, $audit->metadata['questions_added']);
        $this->assertSame(8, $audit->metadata['total_marks_added']);
        Storage::disk('local')->assertMissing($stage['path']);
    }

    public function test_new_layout_parses_configured_override_and_partial_marks(): void
    {
        $exam = $this->exam($this->ownClass, $this->ownSubject);
        $exam->update(['default_question_marks' => 4]);
        $preview = $this->preview($this->admin, $exam, $this->newCsv([
            ['Configured mark', 'Wrong', 'Right', '', '', '', '', ' b '],
            ['Override', 'Right', 'Wrong', '', '', '', '', 'a=3'],
            ['Partial', 'Right one', 'Wrong', 'Right two', 'Wrong two', '', '', 'a=2; c=1'],
        ]));

        $preview->assertOk()->assertSee('3 questions to add')->assertSee('10 total marks');
        $stage = $this->stage();
        $this->post(route('cbt.staff.questions.apply', $exam), ['upload_token' => $stage['token']])->assertRedirect();

        app(TenantContext::class)->set($this->school->id);
        $marks = Question::where('exam_id', $exam->id)->with('options')->orderBy('position')->get()
            ->map(fn (Question $question): array => $question->options->pluck('marks')->all())->all();
        $this->assertSame([[0, 4], [3, 0], [2, 0, 1, 0]], $marks);
    }

    public function test_new_layout_reports_each_correct_answer_rule_and_saves_nothing(): void
    {
        $cases = [
            'multiple without marks' => [['Q', 'A', 'B', 'C', '', '', '', 'A, C'], 'Row 2, Correct Answer: give a mark for each correct option, for example A=2, C=1.'],
            'bad letter' => [['Q', 'A', 'B', '', '', '', '', 'G'], 'Use option letters A to F'],
            'duplicate letter' => [['Q', 'A', 'B', 'C', '', '', '', 'A=2, A=1'], 'Do not repeat option A'],
            'zero mark' => [['Q', 'A', 'B', '', '', '', '', 'A=0'], 'whole numbers of at least 1'],
            'decimal mark' => [['Q', 'A', 'B', '', '', '', '', 'A=1.5'], 'whole numbers of at least 1'],
            'text mark' => [['Q', 'A', 'B', '', '', '', '', 'A=yes'], 'whole numbers of at least 1'],
            'empty option' => [['Q', 'A', 'B', '', '', '', '', 'C'], 'Option C has no text'],
            'no correct answer' => [['Q', 'A', 'B', '', '', '', '', ''], 'At least one correct option is required'],
        ];

        foreach ($cases as $label => [$row, $message]) {
            $exam = $this->exam($this->ownClass, $this->ownSubject, 'New layout '.$label);
            $report = $this->preview($this->admin, $exam, $this->newCsv([$row]))->viewData('questionUploadPreview');
            $this->assertGreaterThan(0, $report['error_count'], $label);
            $this->assertStringContainsString($message, collect($report['errors'])->pluck('message')->implode(' '), $label);
            $this->assertDatabaseMissing('questions', ['exam_id' => $exam->id]);
        }
    }

    public function test_both_old_layouts_still_parse_and_mixed_headings_are_refused(): void
    {
        $marksExam = $this->exam($this->ownClass, $this->ownSubject, 'Old marks layout');
        $marksCsv = $this->csv([
            ['Old marks', 'Right', '2', 'Wrong', '', '', '', '', '', '', '', '', ''],
        ]);
        $marksReport = $this->preview($this->admin, $marksExam, $marksCsv)->viewData('questionUploadPreview');
        $this->assertSame(1, $marksReport['questions_to_add']);

        $scoresExam = $this->exam($this->ownClass, $this->ownSubject, 'Old score layout');
        $scoreHeaders = [
            'Question', 'Option A', 'Option A Score', 'Option B', 'Option B Score', 'Option C', 'Option C Score',
            'Option D', 'Option D Score', 'Option E', 'Option E Score', 'Option F', 'Option F Score',
        ];
        $scoresCsv = $this->csv([
            ['Old scores', 'Right', '3', 'Wrong', '', '', '', '', '', '', '', '', ''],
        ], $scoreHeaders);
        $scoresReport = $this->preview($this->admin, $scoresExam, $scoresCsv)->viewData('questionUploadPreview');
        $this->assertSame(1, $scoresReport['questions_to_add']);

        $mixedExam = $this->exam($this->ownClass, $this->ownSubject, 'Mixed layout');
        $mixed = ['Question', 'Option A', 'Marks for A', 'Option B', 'Option B Score', 'Option C', 'Marks for C', 'Option D', 'Marks for D', 'Option E', 'Marks for E', 'Option F', 'Marks for F'];
        $report = $this->preview($this->admin, $mixedExam, $this->csv([
            ['Mixed', 'Right', '1', 'Wrong', '', '', '', '', '', '', '', '', ''],
        ], $mixed))->viewData('questionUploadPreview');
        $this->assertStringContainsString('Do not mix CBT question CSV layouts', $report['errors'][0]['message']);
        $this->assertDatabaseMissing('questions', ['exam_id' => $mixedExam->id]);
    }

    public function test_csv_rule_violations_report_row_and_column_and_save_nothing(): void
    {
        $cases = [
            'missing heading' => $this->csv([['Question']], ['Wrong heading']),
            'no marked option' => $this->csv([['Q', 'A', '', 'B', '', '', '', '', '', '', '', '', '']]),
            'every option marked' => $this->csv([['Q', 'A', '1', 'B', '1', '', '', '', '', '', '', '', '']]),
            'mark on empty' => $this->csv([['Q', 'A', '1', '', '2', 'B', '', '', '', '', '', '', '']]),
            'decimal mark' => $this->csv([['Q', 'A', '1.5', 'B', '', '', '', '', '', '', '', '', '']]),
            'zero mark' => $this->csv([['Q', 'A', '0', 'B', '', '', '', '', '', '', '', '', '']]),
            'negative mark' => $this->csv([['Q', 'A', '-1', 'B', '', '', '', '', '', '', '', '', '']]),
            'text mark' => $this->csv([['Q', 'A', 'yes', 'B', '', '', '', '', '', '', '', '', '']]),
            'fewer options' => $this->csv([['Q', 'A', '1', '', '', '', '', '', '', '', '', '', '']]),
            'question too long' => $this->csv([[str_repeat('Q', 1001), 'A', '1', 'B', '', '', '', '', '', '', '', '', '']]),
            'option too long' => $this->csv([['Q', str_repeat('A', 301), '1', 'B', '', '', '', '', '', '', '', '', '']]),
        ];
        foreach ($cases as $label => $csv) {
            $exam = $this->exam($this->ownClass, $this->ownSubject, 'Draft '.$label);
            $response = $this->preview($this->admin, $exam, $csv);
            $report = $response->viewData('questionUploadPreview');
            $this->assertGreaterThan(0, $report['error_count'], $label);
            $this->assertStringContainsString('Row ', $report['errors'][0]['message'], $label);
            $this->assertNotSame('', $report['errors'][0]['column'], $label);
            $this->assertDatabaseMissing('questions', ['exam_id' => $exam->id]);
        }

        $exam = $this->exam($this->ownClass, $this->ownSubject, 'Too many rows');
        $report = $this->preview($this->admin, $exam, $this->csv(array_fill(0, 201, ['Q', 'A', '1', 'B', '', '', '', '', '', '', '', '', ''])))
            ->viewData('questionUploadPreview');
        $this->assertGreaterThan(0, $report['error_count']);
        $this->assertStringContainsString('at most 200 data rows', collect($report['errors'])->pluck('message')->implode(' '));

        $full = $this->exam($this->ownClass, $this->ownSubject, 'Nearly full');
        for ($position = 1; $position <= 200; $position++) {
            $this->question($full, 'Existing '.$position, $position);
        }
        $report = $this->preview($this->admin, $full, $this->csv([['Extra', 'A', '1', 'B', '', '', '', '', '', '', '', '', '']]))
            ->viewData('questionUploadPreview');
        $this->assertStringContainsString('above 200 questions', $report['errors'][0]['message']);
    }

    public function test_two_hundred_row_preview_has_a_bounded_query_count(): void
    {
        $exam = $this->exam($this->ownClass, $this->ownSubject, 'Two hundred row preview');
        $rows = [];
        for ($number = 1; $number <= 200; $number++) {
            $rows[] = ["Question {$number}", 'Correct', '1', 'Wrong', '', '', '', '', '', '', '', '', ''];
        }
        $csvContents = $this->csv($rows);

        $queries = 0;
        $databaseMilliseconds = 0.0;
        $patterns = [];
        DB::listen(function ($query) use (&$queries, &$databaseMilliseconds, &$patterns): void {
            $queries++;
            $databaseMilliseconds += $query->time;
            $sql = preg_replace('/\s+/', ' ', $query->sql) ?? $query->sql;
            $patterns[$sql] = ($patterns[$sql] ?? 0) + 1;
        });
        $started = microtime(true);
        $response = $this->preview($this->admin, $exam, $csvContents);
        $elapsed = microtime(true) - $started;

        $response->assertOk();
        $this->assertSame(200, $response->viewData('questionUploadPreview')['questions_to_add']);
        arsort($patterns);
        $repeated = collect($patterns)->take(8)->map(fn (int $count, string $sql): string => "{$count}x {$sql}")->implode("\n");
        $this->assertLessThanOrEqual(30, $queries, "Preview used {$queries} queries ({$databaseMilliseconds} ms) in {$elapsed} seconds.\n{$repeated}");
    }

    public function test_one_bad_row_blocks_all_rows_and_form_and_upload_share_limits(): void
    {
        $exam = $this->exam($this->ownClass, $this->ownSubject);
        $csv = $this->csv([
            ['Good', 'A', '1', 'B', '', '', '', '', '', '', '', '', ''],
            ['Bad', 'A', '1.5', 'B', '', '', '', '', '', '', '', '', ''],
        ]);
        $report = $this->preview($this->admin, $exam, $csv)->viewData('questionUploadPreview');
        $this->assertSame([], $report['rows']);
        $this->assertDatabaseMissing('questions', ['exam_id' => $exam->id]);

        $overlong = str_repeat('Q', 1001);
        $this->post(route('cbt.staff.questions.store', $exam), [
            'text' => $overlong, 'options' => ['A', 'B'], 'correct_options' => [0], 'option_marks' => [0 => 1],
        ])->assertSessionHasErrors('text');
        $upload = $this->preview($this->admin, $exam, $this->csv([[$overlong, 'A', '1', 'B', '', '', '', '', '', '', '', '', '']]))
            ->viewData('questionUploadPreview');
        $this->assertStringContainsString('1,000', $upload['errors'][0]['message']);
    }

    public function test_scope_and_exam_state_are_enforced_for_preview_and_apply(): void
    {
        $own = $this->exam($this->ownClass, $this->ownSubject);
        $other = $this->exam($this->otherClass, $this->otherSubject);
        $file = UploadedFile::fake()->createWithContent('questions.csv', $this->csv([['Q', 'A', '1', 'B', '', '', '', '', '', '', '', '', '']]));
        $this->actingAs($this->admin)->get(route('cbt.staff.show', $own))->assertOk()->assertSee('How to use');
        $this->actingAs($this->head)->get(route('cbt.staff.show', $own))->assertOk()->assertSee('How to use');
        $this->actingAs($this->teacher)->get(route('cbt.staff.show', $own))->assertForbidden()->assertDontSee('How to use');
        $this->actingAs($this->teacher)->get(route('cbt.staff.questions.template', $own))->assertForbidden();
        $this->post(route('cbt.staff.questions.preview', $own), ['file' => $file])->assertForbidden();
        $this->actingAs($this->head)->get(route('cbt.staff.questions.template', $other))->assertNotFound();
        $this->post(route('cbt.staff.questions.preview', $other), ['file' => $file])->assertNotFound();

        $published = $this->exam($this->ownClass, $this->ownSubject, 'Published', 'Published');
        $this->actingAs($this->admin)->post(route('cbt.staff.questions.preview', $published), ['file' => $file])->assertSessionHasErrors('file');

        $preview = $this->preview($this->admin, $own, $this->csv([['Q', 'A', '1', 'B', '', '', '', '', '', '', '', '', '']]));
        $preview->assertOk();
        $stage = $this->stage();
        $student = $this->student($this->ownClass);
        ExamSubmission::create(['exam_id' => $own->id, 'student_id' => $student->id, 'started_at' => now(), 'status' => 'In Progress']);
        $this->post(route('cbt.staff.questions.apply', $own), ['upload_token' => $stage['token']])->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('questions', ['exam_id' => $own->id]);
        Storage::disk('local')->assertMissing($stage['path']);
    }

    public function test_stage_is_bound_single_use_expires_replaces_and_cancel_removes_files(): void
    {
        Carbon::setTestNow('2026-11-07 10:00:00');
        try {
            $exam = $this->exam($this->ownClass, $this->ownSubject);
            $otherExam = $this->exam($this->ownClass, $this->ownSubject, 'Other');
            $csv = $this->csv([['Q', 'A', '1', 'B', '', '', '', '', '', '', '', '', '']]);
            $this->preview($this->admin, $exam, $csv);
            $first = $this->stage();
            Storage::disk('local')->assertExists($first['path']);

            $this->preview($this->admin, $exam, $csv);
            $replacement = $this->stage();
            Storage::disk('local')->assertMissing($first['path']);
            $this->actingAs($this->head)->post(route('cbt.staff.questions.apply', $exam), ['upload_token' => $replacement['token']])->assertForbidden();
            $this->actingAs($this->admin)->post(route('cbt.staff.questions.apply', $otherExam), ['upload_token' => $replacement['token']])->assertForbidden();
            Storage::disk('local')->assertExists($replacement['path']);

            $this->post(route('cbt.staff.questions.cancel', $exam), ['upload_token' => $replacement['token']])->assertRedirect();
            Storage::disk('local')->assertMissing($replacement['path']);

            $this->preview($this->admin, $exam, $csv);
            $expired = $this->stage();
            Carbon::setTestNow(now()->addMinutes(31));
            $this->post(route('cbt.staff.questions.apply', $exam), ['upload_token' => $expired['token']])->assertSessionHasErrors('file');
            Storage::disk('local')->assertMissing($expired['path']);

            Carbon::setTestNow('2026-11-07 12:00:00');
            $this->preview($this->admin, $exam, $csv);
            $applied = $this->stage();
            $this->post(route('cbt.staff.questions.apply', $exam), ['upload_token' => $applied['token']])->assertRedirect();
            $this->post(route('cbt.staff.questions.apply', $exam), ['upload_token' => $applied['token']])->assertForbidden();

            Storage::disk('local')->put('cbt-question-imports/1/abandoned.csv', 'old');
            touch(Storage::disk('local')->path('cbt-question-imports/1/abandoned.csv'), now()->subMinutes(31)->timestamp);
            $this->artisan('oakfield:cleanup-cbt-question-uploads')->assertSuccessful();
            Storage::disk('local')->assertMissing('cbt-question-imports/1/abandoned.csv');
        } finally {
            Carbon::setTestNow();
        }
    }

    private function preview(User $actor, Exam $exam, string $csv)
    {
        return $this->actingAs($actor)->post(route('cbt.staff.questions.preview', $exam), [
            'file' => UploadedFile::fake()->createWithContent('questions.csv', $csv),
        ]);
    }

    private function stage(): array
    {
        $stages = session('cbt_question_csv', []);
        $token = array_key_last($stages);

        return ['token' => $token, ...$stages[$token]];
    }

    private function csv(array $rows, ?array $headers = null): string
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF# Instructions\r\n");
        fputcsv($stream, $headers ?? [
            'Question', 'Option A', 'Marks for A', 'Option B', 'Marks for B', 'Option C', 'Marks for C',
            'Option D', 'Marks for D', 'Option E', 'Marks for E', 'Option F', 'Marks for F',
        ], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_pad($row, 13, ''), ',', '"', '');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return $contents;
    }

    private function newCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['Question', 'Option A', 'Option B', 'Option C', 'Option D', 'Option E', 'Option F', 'Correct Answer'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_pad($row, 8, ''), ',', '"', '');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return $contents;
    }

    private function user(string $name, array $permissions): User
    {
        $user = User::create(['name' => $name, 'email' => str($name)->slug().uniqid().'@test.test', 'password' => 'long-password-here', 'status' => 'active']);
        $role = Role::create(['name' => $name.uniqid()]);
        $role->permissions()->attach(collect($permissions)->map(fn ($name) => Permission::firstOrCreate(['name' => $name])->id));
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

    private function exam(SchoolClass $class, Subject $subject, string $title = 'Upload draft', string $status = 'Draft'): Exam
    {
        app(TenantContext::class)->set($this->school->id);

        return Exam::create([
            'exam_type_id' => $this->type->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'created_by' => $this->admin->id, 'title' => $title, 'duration' => 30, 'status' => $status,
            'result_release_type' => 'Manual',
        ]);
    }

    private function question(Exam $exam, string $text, int $position): Question
    {
        app(TenantContext::class)->set($this->school->id);
        $question = Question::create(['exam_id' => $exam->id, 'text' => $text, 'marks' => 1, 'position' => $position]);
        QuestionOption::create(['question_id' => $question->id, 'text' => 'Yes', 'is_correct' => true, 'marks' => 1]);
        QuestionOption::create(['question_id' => $question->id, 'text' => 'No', 'is_correct' => false, 'marks' => 0]);

        return $question;
    }

    private function student(SchoolClass $class): Student
    {
        app(TenantContext::class)->set($this->school->id);
        $user = User::create(['name' => 'Pupil', 'email' => uniqid().'@pupil.test', 'password' => 'long-password-here', 'status' => 'active']);

        return Student::create(['user_id' => $user->id, 'class_arm_id' => $class->arms()->firstOrFail()->id, 'status' => Student::STATUS_ACTIVE, 'admission_date' => today()]);
    }
}
