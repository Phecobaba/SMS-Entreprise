<?php

namespace Tests\Feature\Admissions;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Models\RegistrationNumberSequence;
use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Models\StudentRegistrationNumberHistory;
use App\Modules\Admissions\Services\RegistrationNumbers;
use App\Modules\Admissions\Services\StudentAccounts;
use App\Modules\Admissions\Services\StudentImportService;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Models\ParentStudent;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\DefaultRoleCatalog;
use App\Modules\Core\Services\TemporaryCredentials;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Models\FeeItem;
use App\Modules\Payments\Models\FeeScheduleLine;
use App\Modules\Payments\Models\Invoice;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private ClassArm $arm;

    private AcademicLevel $level;

    private string $classLabel;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->school = School::create(['name' => 'Oakfield International Schools']);
        config()->set('tenancy.default_school_id', $this->school->id);
        app(TenantContext::class)->set($this->school->id);
        $roles = app(DefaultRoleCatalog::class)->provision();
        $this->admin = User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@example.test', 'password' => 'password']);
        $this->admin->roles()->attach($roles['School Admin'], ['school_id' => $this->school->id]);
        $section = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher', 'registration_prefix' => 'PRI', 'registration_code' => 'PRI']);
        $this->level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Primary 1', 'registration_prefix' => 'P1', 'registration_code' => 'P1']);
        $class = SchoolClass::create(['academic_level_id' => $this->level->id, 'name' => 'Primary One']);
        $this->arm = ClassArm::create(['class_id' => $class->id, 'name' => 'Gold']);
        $this->classLabel = 'Primary One';
        app(RegistrationNumbers::class)->settings()->update([
            'registration_include_school' => false, 'registration_include_level' => true,
            'registration_school_code' => 'OAK', 'registration_separator' => '-',
        ]);
    }

    public function test_valid_file_creates_active_students_in_file_order_reuses_parent_and_uses_add_student_invoice_path(): void
    {
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => '2026-09-01', 'end_date' => '2026-12-15']);
        $item = FeeItem::create(['name' => 'Tuition']);
        FeeScheduleLine::create(['term_id' => $term->id, 'scope_type' => 'level', 'academic_level_id' => $this->level->id,
            'fee_item_id' => $item->id, 'amount_kobo' => 5000000, 'is_required' => true]);
        $csv = $this->csv([
            ['Ada One', $this->classLabel, 'Gold', '', '', 'ada@example.test', 'Pat One', 'pat.one', 'pat@example.test'],
            ['Bola Two', $this->classLabel, 'Gold', '', '', 'bola@example.test', 'Pat One', 'pat.one', 'pat@example.test'],
        ]);
        $import = app(StudentImportService::class)->check($this->upload($csv), $this->admin);
        $this->assertSame(2, $import->ready_count);
        $this->assertSame(0, Student::count(), 'Check must not create students.');
        Log::spy();
        $result = app(StudentImportService::class)->confirm($import, $this->admin);

        $students = Student::with('user')->orderBy('id')->get();
        $this->assertSame(['Ada One', 'Bola Two'], $students->pluck('user.name')->all());
        $this->assertSame(['P1-0001', 'P1-0002'], $students->pluck('registration_number')->all());
        $this->assertTrue($students->every(fn (Student $student) => $student->status === Student::STATUS_ACTIVE && $student->user->must_change_password));
        $this->assertSame(1, ParentProfile::count());
        $this->assertSame(2, ParentStudent::count());
        $this->assertSame(2, Invoice::where('kind', Invoice::KIND_TERM_FEE)->count());
        $this->assertCount(2, $result['credentials']);
        $this->assertNotSame($result['credentials'][0]['student_password'], $result['credentials'][1]['student_password']);
        $this->assertNotEmpty($result['credentials'][0]['parent_password']);
        $this->assertNull($result['credentials'][1]['parent_password']);
        $this->assertSame(['Ada One', 'Bola Two'], $result['credentials'][0]['parent_children']);
        $this->assertSame('Primary One', $result['credentials'][0]['class']);
        $this->assertSame('Gold', $result['credentials'][0]['arm']);
        $this->assertNull($import->refresh()->stored_path);
        $this->assertStringNotContainsString($result['credentials'][0]['student_password'], json_encode($import->report));
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertFalse(AuditLog::get()->contains(fn ($log) => str_contains(json_encode($log->metadata), $result['credentials'][0]['student_password'])));
        $this->assertStringNotContainsString($result['credentials'][0]['student_password'], json_encode(session()->all()));
        if (is_file(storage_path('logs/laravel.log'))) {
            $this->assertStringNotContainsString($result['credentials'][0]['student_password'], file_get_contents(storage_path('logs/laravel.log')));
        }
        Log::shouldNotHaveReceived('info', fn (...$arguments) => str_contains(json_encode($arguments), $result['credentials'][0]['student_password']));
    }

    public function test_all_validation_errors_are_reported_and_no_students_are_created(): void
    {
        User::create(['name' => 'Taken', 'username' => 'taken.user', 'email' => 'taken@example.test', 'password' => 'password']);
        $csv = $this->csv([
            ['', 'Missing class', '', 'BAD NUMBER!', 'Same.User', 'bad', 'Parent', 'same.parent', 'bad-parent'],
            ['Second', $this->classLabel, 'Gold', 'DUP-1', 'same.user', 'same@example.test', 'Parent A', 'same.parent', 'parent@example.test'],
            ['Third', $this->classLabel, 'Gold', 'DUP-1', 'third', 'same@example.test', 'Different Parent', 'same.parent', 'different@example.test'],
            ['Fourth', $this->classLabel, 'Gold', '', 'taken.user', 'taken@example.test', '', '', ''],
        ]);
        $import = app(StudentImportService::class)->check($this->upload($csv), $this->admin);

        $this->assertSame(4, $import->error_count);
        $this->assertSame(0, Student::count());
        $messages = collect($import->report['errors']);
        $this->assertTrue($messages->contains(fn ($error) => $error['column'] === 'registration_number' && str_contains($error['message'], 'row')));
        $this->assertTrue($messages->contains(fn ($error) => $error['column'] === 'username'));
        $this->assertTrue($messages->contains(fn ($error) => $error['column'] === 'parent_username'));
        $this->assertTrue($messages->contains(fn ($error) => $error['column'] === 'email' && str_contains($error['message'], 'already in use')));
    }

    public function test_class_and_arm_resolution_accepts_a_single_blank_arm_and_lists_every_ambiguous_path(): void
    {
        $singleArm = app(StudentImportService::class)->check($this->upload($this->csv([
            ['Single Arm', 'Primary One', '', '', 'single.arm', '', '', '', ''],
        ])), $this->admin);
        $this->assertSame(0, $singleArm->error_count);
        $this->assertSame('Gold', $singleArm->report['preview'][0]['arm']);

        $otherSection = Section::create(['name' => 'Annex', 'head_title' => 'Head Teacher']);
        $otherLevel = AcademicLevel::create(['section_id' => $otherSection->id, 'name' => 'Foundation']);
        $otherClass = SchoolClass::create(['academic_level_id' => $otherLevel->id, 'name' => 'Primary One']);
        ClassArm::create(['class_id' => $otherClass->id, 'name' => 'Blue']);

        $ambiguous = app(StudentImportService::class)->check($this->upload($this->csv([
            ['Ambiguous', 'Primary One', 'Gold', '', 'ambiguous', '', '', '', ''],
        ])), $this->admin);
        $message = collect($ambiguous->report['errors'])->firstWhere('column', 'class')['message'];
        $this->assertStringContainsString('Primary / Primary 1 / Primary One', $message);
        $this->assertStringContainsString('Annex / Foundation / Primary One', $message);

        $resolved = app(StudentImportService::class)->check($this->upload($this->csv([
            ['Resolved', 'Primary / Primary 1 / Primary One', 'Gold', '', 'resolved', '', '', '', ''],
        ])), $this->admin);
        $this->assertSame(0, $resolved->error_count);
        app(StudentImportService::class)->confirm($resolved, $this->admin);
        $this->assertSame($this->arm->id, Student::whereHas('user', fn ($query) => $query->where('username', 'resolved'))->value('class_arm_id'));
    }

    public function test_on_page_class_list_is_school_scoped_and_permission_guarded(): void
    {
        $plain = User::create(['name' => 'No permission', 'username' => 'no-class-list', 'password' => 'password']);
        $this->actingAs($plain)->get(route('students.imports.create'))->assertForbidden();

        $otherSchool = School::create(['name' => 'Other School']);
        config()->set('tenancy.default_school_id', $otherSchool->id);
        app(TenantContext::class)->set($otherSchool->id);
        $otherRoles = app(DefaultRoleCatalog::class)->provision();
        $otherAdmin = User::create(['name' => 'Other Admin', 'username' => 'other-class-admin', 'password' => 'password']);
        $otherAdmin->roles()->attach($otherRoles['School Admin'], ['school_id' => $otherSchool->id]);
        $section = Section::create(['name' => 'Other Section', 'head_title' => 'Head']);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Other Level']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'Other Class']);
        ClassArm::create(['class_id' => $class->id, 'name' => 'Other Arm']);

        $this->actingAs($otherAdmin)->get(route('students.imports.create'))
            ->assertOk()->assertSee('Other Class')->assertDontSee('Primary One');
    }

    public function test_confirm_response_contains_complete_slips_and_credentials_leave_no_durable_copy(): void
    {
        $import = app(StudentImportService::class)->check($this->upload($this->csv([
            ['Slip Pupil', 'Primary One', 'Gold', '', 'slip.pupil', 'pupil@example.test', 'Slip Parent', 'slip.parent', 'parent-slip@example.test'],
        ])), $this->admin);

        $response = $this->actingAs($this->admin)->post(route('students.imports.confirm', $import));
        $response->assertOk()->assertSee('Oakfield International Schools')->assertSee('Slip Pupil')
            ->assertSee('Primary One Gold')->assertSee('P1-0001')->assertSee('slip.pupil')
            ->assertSee('Slip Parent')->assertSee('slip.parent')->assertSee('Sign in')
            ->assertSee('You will be asked to choose a new password the first time you sign in');

        preg_match_all('/<code[^>]*data-secret[^>]*>([^<]+)<\/code>/', $response->getContent(), $matches);
        $this->assertCount(2, $matches[1]);
        foreach ($matches[1] as $secret) {
            $this->assertFalse(DB::table('audit_logs')->get()->contains(fn ($row) => str_contains(json_encode($row), $secret)));
            $this->assertFalse(DB::table('jobs')->get()->contains(fn ($row) => str_contains((string) $row->payload, $secret)));
            $this->assertStringNotContainsString($secret, serialize(session()->all()));
            $this->assertStringNotContainsString($secret, json_encode($import->fresh()->report));
            if (is_file(storage_path('logs/laravel.log'))) {
                $this->assertStringNotContainsString($secret, file_get_contents(storage_path('logs/laravel.log')));
            }
        }
    }

    public function test_current_and_historical_numbers_are_refused_exact_duplicate_is_skipped_and_parent_email_is_reused(): void
    {
        $existingUser = User::create(['name' => 'Existing Pupil', 'username' => 'existing', 'email' => null, 'password' => 'password']);
        $existing = Student::create(['user_id' => $existingUser->id, 'class_arm_id' => $this->arm->id, 'registration_number' => 'P1-0900', 'status' => Student::STATUS_ACTIVE, 'admission_date' => today()]);
        StudentRegistrationNumberHistory::create(['student_id' => $existing->id, 'old_number' => null, 'new_number' => 'OLD-0900', 'changed_by' => $this->admin->id, 'changed_at' => now()]);
        $parentUser = User::create(['name' => 'Existing Parent', 'username' => 'existing.parent', 'email' => 'family@example.test', 'password' => 'password']);
        $parent = ParentProfile::create(['user_id' => $parentUser->id]);

        $duplicate = app(StudentImportService::class)->check($this->upload($this->csv([
            ['Existing Pupil', $this->classLabel, 'Gold', 'P1-0900', 'existing', '', '', '', ''],
            ['New Child', $this->classLabel, 'Gold', '', 'new.child', '', 'Existing Parent', '', 'family@example.test'],
        ])), $this->admin);
        $this->assertSame(1, $duplicate->skipped_count);
        app(StudentImportService::class)->confirm($duplicate, $this->admin);
        $this->assertSame(2, Student::count());
        $this->assertSame(1, ParentProfile::count());
        $this->assertDatabaseHas('parent_student', ['parent_id' => $parent->id, 'student_id' => Student::whereHas('user', fn ($q) => $q->where('username', 'new.child'))->value('id')]);

        $history = app(StudentImportService::class)->check($this->upload($this->csv([
            ['History Clash', $this->classLabel, 'Gold', 'OLD-0900', 'history.clash', '', '', '', ''],
        ])), $this->admin);
        $this->assertSame(1, $history->error_count);

        $suggested = app(StudentImportService::class)->check($this->upload($this->csv([
            ['Sibling One', $this->classLabel, 'Gold', '', 'sibling.one', '', 'New Family', '', 'new-family@example.test'],
            ['Sibling Two', $this->classLabel, 'Gold', '', 'sibling.two', '', 'New Family', '', 'new-family@example.test'],
        ])), $this->admin);
        app(StudentImportService::class)->confirm($suggested, $this->admin);
        $newParent = ParentProfile::whereHas('user', fn ($q) => $q->where('email', 'new-family@example.test'))->with('user')->firstOrFail();
        $this->assertNotEmpty($newParent->user->username);
        $this->assertSame(2, $newParent->students()->count());
    }

    public function test_cap_format_template_permission_owner_scope_and_repeat_confirmation_are_enforced(): void
    {
        config()->set('student_imports.row_cap', 2);
        $tooMany = app(StudentImportService::class)->check($this->upload($this->csv([
            ['One', $this->classLabel, 'Gold', '', 'one', '', '', '', ''], ['Two', $this->classLabel, 'Gold', '', 'two', '', '', '', ''], ['Three', $this->classLabel, 'Gold', '', 'three', '', '', '', ''],
        ])), $this->admin);
        $this->assertSame(1, $tooMany->error_count);
        $this->assertStringContainsString('current limit is 2', $tooMany->report['errors'][0]['message']);
        $this->actingAs($this->admin)->get(route('students.imports.template'))->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $template = $this->actingAs($this->admin)->get(route('students.imports.template'))->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $template);
        $this->assertStringContainsString("\r\n", $template);
        $this->assertStringContainsString(implode(',', StudentImportService::HEADERS), $template);
        $this->assertStringContainsString('"Primary One",Gold', $template);
        $this->actingAs($this->admin)->get(route('students.imports.create'))->assertOk()
            ->assertSee('Classes and arms')->assertSee('Primary One')->assertSee('Gold')->assertSee('Copy');

        app(TenantContext::class)->set($this->school->id);
        config()->set('student_imports.max_kilobytes', 1);
        $this->actingAs($this->admin)->post(route('students.imports.check'), [
            'file' => UploadedFile::fake()->create('too-large.csv', 2, 'text/csv'),
        ])->assertSessionHasErrors('file');
        app(TenantContext::class)->set($this->school->id);
        $badEncoding = app(StudentImportService::class)->check($this->upload("\xFF\xFEbroken"), $this->admin);
        $this->assertSame('Use a UTF-8 CSV file.', $badEncoding->report['errors'][0]['message']);

        app(TenantContext::class)->set($this->school->id);
        config()->set('student_imports.row_cap', 50);
        $import = app(StudentImportService::class)->check($this->upload($this->csv([['Only', $this->classLabel, 'Gold', '', 'only', '', '', '', '']])), $this->admin);
        $first = app(StudentImportService::class)->confirm($import, $this->admin);
        $second = app(StudentImportService::class)->confirm($import->refresh(), $this->admin);
        $this->assertCount(1, $first['credentials']);
        $this->assertCount(0, $second['credentials']);
        $this->assertSame(1, Student::count());

        $plain = User::create(['name' => 'No permission', 'username' => 'plain', 'email' => 'plain@example.test', 'password' => 'password']);
        $this->actingAs($plain)->get(route('students.imports.create'))->assertForbidden();
        $this->actingAs($plain)->post(route('students.imports.confirm', $import))->assertForbidden();
        app(TenantContext::class)->set($this->school->id);
        $viewer = User::create(['name' => 'Student viewer', 'username' => 'viewer', 'email' => 'viewer@example.test', 'password' => 'password']);
        $viewerRole = Role::create(['name' => 'Student viewer']);
        $viewerRole->permissions()->attach(Permission::where('name', 'view_students')->firstOrFail());
        $viewer->roles()->attach($viewerRole, ['school_id' => $this->school->id]);
        $this->actingAs($this->admin)->get(route('students.index'))->assertSee(route('students.imports.create'));
        $this->actingAs($viewer)->get(route('students.index'))->assertDontSee(route('students.imports.create'));

        $otherSchool = School::create(['name' => 'Other School']);
        config()->set('tenancy.default_school_id', $otherSchool->id);
        app(TenantContext::class)->set($otherSchool->id);
        $otherRoles = app(DefaultRoleCatalog::class)->provision();
        $otherAdmin = User::create(['name' => 'Other Admin', 'username' => 'other.admin', 'email' => 'other@example.test', 'password' => 'password']);
        $otherAdmin->roles()->attach($otherRoles['School Admin'], ['school_id' => $otherSchool->id]);
        $this->actingAs($otherAdmin)->get('/students/import/'.$import->id)->assertNotFound();
    }

    public function test_forced_failure_on_later_row_rolls_back_accounts_numbers_invoices_and_sequence(): void
    {
        $real = app(StudentAccounts::class);
        $failing = new class($real) extends StudentAccounts
        {
            private int $calls = 0;

            public function __construct(private StudentAccounts $real) {}

            public function create(array $data, User $actor): array
            {
                $this->calls++;
                if ($this->calls === 2) {
                    throw new RuntimeException('Forced later-row failure.');
                }

                return $this->real->create($data, $actor);
            }
        };
        $this->app->instance(StudentAccounts::class, $failing);
        $this->app->forgetInstance(StudentImportService::class);
        $import = app(StudentImportService::class)->check($this->upload($this->csv([
            ['First', $this->classLabel, 'Gold', '', 'first', '', '', '', ''], ['Second', $this->classLabel, 'Gold', '', 'second', '', '', '', ''],
        ])), $this->admin);
        try {
            app(StudentImportService::class)->confirm($import, $this->admin);
            $this->fail('The forced failure was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced later-row failure.', $exception->getMessage());
        }
        $this->assertSame(0, Student::count());
        $this->assertSame(0, StudentRegistrationNumberHistory::count());
        $this->assertSame(0, RegistrationNumberSequence::count());
        $this->assertSame(0, Invoice::count());

        $this->app->instance(StudentAccounts::class, $real);
        $this->app->forgetInstance(StudentImportService::class);
        $retry = app(StudentImportService::class)->check($this->upload($this->csv([
            ['Retry', $this->classLabel, 'Gold', '', 'retry', '', '', '', ''],
        ])), $this->admin);
        app(StudentImportService::class)->confirm($retry, $this->admin);
        $this->assertSame('P1-0001', Student::firstOrFail()->registration_number);
    }

    public function test_fifty_row_cap_completes_synchronously_with_unique_credentials_and_numbers(): void
    {
        config()->set('student_imports.row_cap', 50);
        $rows = [];
        for ($number = 1; $number <= 50; $number++) {
            $rows[] = ["Cap Student {$number}", $this->classLabel, 'Gold', '', "cap.student.{$number}", '', '', '', ''];
        }
        $import = app(StudentImportService::class)->check($this->upload($this->csv($rows)), $this->admin);
        $started = microtime(true);
        $result = app(StudentImportService::class)->confirm($import, $this->admin);
        $elapsed = microtime(true) - $started;

        $this->assertSame(50, Student::count());
        $this->assertSame(50, Student::pluck('registration_number')->unique()->count());
        $this->assertSame(50, collect($result['credentials'])->pluck('student_password')->unique()->count());
        $this->assertLessThan(120, $elapsed, 'The configured 50-row synchronous cap exceeded two minutes on the test machine.');
    }

    public function test_confirmed_file_hash_is_refused_unconfirmed_hash_is_allowed_and_hashes_do_not_leak_across_schools(): void
    {
        $csv = $this->csv([
            ['Duplicate Gap', $this->classLabel, 'Gold', '', '', '', '', '', ''],
        ]);
        $first = app(StudentImportService::class)->check($this->upload($csv), $this->admin);
        app(StudentImportService::class)->confirm($first, $this->admin);

        try {
            app(StudentImportService::class)->check($this->upload($csv), $this->admin);
            $this->fail('A confirmed file hash was accepted twice.');
        } catch (ValidationException $exception) {
            $this->assertStringStartsWith('This file was already imported on ', $exception->errors()['file'][0]);
        }

        $correctable = $this->csv([['Correctable', $this->classLabel, 'Gold', '', '', '', '', '', '']]);
        $this->assertSame('checked', app(StudentImportService::class)->check($this->upload($correctable), $this->admin)->status);
        $this->assertSame('checked', app(StudentImportService::class)->check($this->upload($correctable), $this->admin)->status);

        $otherSchool = School::create(['name' => 'Another School']);
        config()->set('tenancy.default_school_id', $otherSchool->id);
        app(TenantContext::class)->set($otherSchool->id);
        $roles = app(DefaultRoleCatalog::class)->provision();
        $otherAdmin = User::create(['name' => 'Other Admin', 'username' => 'other-admin', 'password' => 'password']);
        $otherAdmin->roles()->attach($roles['School Admin'], ['school_id' => $otherSchool->id]);
        $section = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher']);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Primary 1']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'Primary One']);
        ClassArm::create(['class_id' => $class->id, 'name' => 'Gold']);

        $this->assertSame('checked', app(StudentImportService::class)->check($this->upload($csv), $otherAdmin)->status);
    }

    public function test_name_and_class_warning_blocks_confirm_until_create_anyway_is_acknowledged(): void
    {
        $existingUser = User::create(['name' => '  Ada   Pupil ', 'username' => 'ada.existing', 'password' => 'password']);
        Student::create(['user_id' => $existingUser->id, 'class_arm_id' => $this->arm->id,
            'registration_number' => 'P1-0999', 'status' => Student::STATUS_ACTIVE, 'admission_date' => today()]);
        $import = app(StudentImportService::class)->check($this->upload($this->csv([
            ['Ada Pupil', $this->classLabel, 'Gold', '', 'ada.new', '', '', '', ''],
        ])), $this->admin);

        $this->assertSame([[
            'row' => 2, 'matching_name' => '  Ada   Pupil ', 'class' => 'Primary / Primary 1 / Primary One / Gold',
            'registration_number' => 'P1-0999',
        ]], $import->report['warnings']);
        $this->actingAs($this->admin)->post(route('students.imports.confirm', $import))
            ->assertSessionHasErrors('create_anyway');
        app(TenantContext::class)->set($this->school->id);
        $this->assertSame(1, Student::count());

        $this->actingAs($this->admin)->post(route('students.imports.confirm', $import), ['create_anyway' => '1'])
            ->assertOk();
        app(TenantContext::class)->set($this->school->id);
        $this->assertSame(2, Student::count());
    }

    public function test_effective_cap_uses_half_the_web_execution_limit_and_configured_seconds_per_row(): void
    {
        $imports = app(StudentImportService::class);
        config()->set('student_imports.row_cap', 50);
        config()->set('student_imports.seconds_per_row', 0.5);
        $this->assertSame(50, $imports->effectiveRowCap(0));
        $this->assertSame(50, $imports->effectiveRowCap(600));
        $this->assertSame(30, $imports->effectiveRowCap(30));
        $this->assertSame(9, $imports->effectiveRowCap(9));
        config()->set('student_imports.seconds_per_row', 1.0);
        $this->assertSame(15, $imports->effectiveRowCap(30));
    }

    public function test_expired_upload_cleanup_command_deletes_private_file_and_is_scheduled(): void
    {
        $import = app(StudentImportService::class)->check($this->upload($this->csv([
            ['Expired Import', $this->classLabel, 'Gold', '', '', '', '', '', ''],
        ])), $this->admin);
        $path = $import->stored_path;
        $import->update(['expires_at' => now()->subMinute()]);

        $this->artisan('oakfield:cleanup-student-imports')->assertSuccessful();

        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertSame('expired', $import->fresh()->status);
        $this->assertTrue(collect(app(Schedule::class)->events())
            ->contains(fn ($event): bool => str_contains((string) $event->command, 'oakfield:cleanup-student-imports')));
    }

    public function test_real_temporary_password_cost_ten_is_measured_once(): void
    {
        config()->set('accounts.temporary_bcrypt_rounds', 10);
        $credentials = app(TemporaryCredentials::class);
        $password = $credentials->generate();
        $started = microtime(true);
        $hash = $credentials->hash($password);
        $elapsed = microtime(true) - $started;

        $this->assertSame(12, strlen($password));
        $this->assertSame('bcrypt', Hash::info($hash)['algoName']);
        $this->assertSame(10, Hash::info($hash)['options']['cost']);
        $this->assertTrue(Hash::check($password, $hash));
        $this->assertLessThan(10, $elapsed);
    }

    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, StudentImportService::HEADERS, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }
        rewind($stream);
        $csv = str_replace("\n", "\r\n", stream_get_contents($stream));
        fclose($stream);

        return $csv;
    }

    private function upload(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('students.csv', $contents);
    }
}
