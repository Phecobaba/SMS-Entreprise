<?php

namespace Tests\Feature\Core;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Models\Candidate;
use App\Modules\Admissions\Models\Student;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Models\FeeScheduleLine;
use App\Modules\Payments\Models\Invoice;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoDataSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD_KEYS = [
        'DEMO_ADMIN_PASSWORD', 'DEMO_TEACHER_PASSWORD', 'DEMO_PRINCIPAL_PASSWORD',
        'DEMO_BURSAR_PASSWORD', 'DEMO_STUDENT_PASSWORD', 'DEMO_PARENT_PASSWORD',
        'DEMO_CANDIDATE_PASSWORD',
    ];

    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::PASSWORD_KEYS as $index => $key) {
            $this->originalEnvironment[$key] = getenv($key);
            $value = str_repeat((string) ($index + 1), 12);
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                putenv("{$key}={$value}");
                $_ENV[$key] = $_SERVER[$key] = $value;
            }
        }
        parent::tearDown();
    }

    public function test_second_demo_seed_changes_nothing(): void
    {
        $this->runSeeder();
        $before = $this->snapshot();

        $this->runSeeder();

        $this->assertSame($before, $this->snapshot());
        $this->assertDatabaseHas('users', ['email' => 'demo-student@oakfield.test']);
        $this->assertDatabaseCount('students', 1);
    }

    public function test_existing_custom_values_are_never_overwritten(): void
    {
        $this->runSeeder();
        app(TenantContext::class)->set(1);

        School::findOrFail(1)->update(['name' => 'Owner School Name']);
        SchoolSetting::firstOrFail()->update([
            'logo_path' => 'branding/owner-logo.png',
            'primary_color' => '#112233',
            'secondary_color' => '#445566',
        ]);
        $section = Section::where('name', 'Primary School')->firstOrFail();
        $section->update([
            'head_title' => 'Owner Head Title',
            'classes_arranged_by' => null,
            'classes_arranged_at' => '2025-01-02 03:04:05',
        ]);
        $term = AcademicTerm::where('name', 'First Term')->firstOrFail();
        $term->update(['start_date' => '2025-09-15']);
        $teacher = User::where('email', 'demo-teacher@oakfield.test')->firstOrFail();
        $teacher->update(['name' => 'Owner Teacher Name', 'status' => 'inactive']);
        $teacher->roles()->syncWithPivotValues(
            [Role::where('name', 'Bursar')->firstOrFail()->id],
            ['school_id' => 1],
        );
        $student = Student::firstOrFail();
        $student->update(['status' => Student::STATUS_GRADUATED]);
        $candidate = Candidate::firstOrFail();
        $candidate->update(['name' => 'Owner Candidate Name', 'status' => 'Owner review']);
        $schedule = FeeScheduleLine::firstOrFail();
        $schedule->update(['amount_kobo' => 1234500, 'is_required' => false]);
        $invoice = Invoice::firstOrFail();
        $invoice->update(['amount_kobo' => 1234500, 'status' => 'Paid', 'purpose' => 'Owner purpose']);

        $this->runSeeder();

        $this->assertSame('Owner School Name', School::findOrFail(1)->name);
        $settings = SchoolSetting::firstOrFail();
        $this->assertSame('branding/owner-logo.png', $settings->logo_path);
        $this->assertSame('#112233', $settings->primary_color);
        $this->assertSame('#445566', $settings->secondary_color);
        $this->assertSame('Owner Head Title', $section->fresh()->head_title);
        $this->assertNull($section->fresh()->classes_arranged_by);
        $this->assertSame('2025-01-02 03:04:05', $section->fresh()->classes_arranged_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2025-09-15', $term->fresh()->start_date->format('Y-m-d'));
        $this->assertSame('Owner Teacher Name', $teacher->fresh()->name);
        $this->assertSame('inactive', $teacher->fresh()->status);
        $this->assertSame(['Bursar'], $teacher->fresh()->roles()->pluck('name')->all());
        $this->assertSame(Student::STATUS_GRADUATED, $student->fresh()->status);
        $this->assertSame('Owner Candidate Name', $candidate->fresh()->name);
        $this->assertSame('Owner review', $candidate->fresh()->status);
        $this->assertSame(1234500, $schedule->fresh()->amount_kobo);
        $this->assertFalse($schedule->fresh()->is_required);
        $this->assertSame(1234500, $invoice->fresh()->amount_kobo);
        $this->assertSame('Paid', $invoice->fresh()->status);
        $this->assertSame('Owner purpose', $invoice->fresh()->purpose);
    }

    public function test_demo_student_is_created_only_when_there_are_no_pupils(): void
    {
        $school = School::create(['name' => 'Existing School']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $section = Section::create(['name' => 'Existing Section', 'head_title' => 'Head']);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Existing Level']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'Existing Class']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A']);
        $user = User::create([
            'name' => 'Existing Pupil',
            'email' => 'existing-pupil@oakfield.test',
            'password' => str_repeat('8', 12),
            'status' => 'active',
        ]);
        Student::create([
            'user_id' => $user->id,
            'class_arm_id' => $arm->id,
            'admission_date' => today(),
        ]);

        $this->runSeeder();

        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseMissing('users', ['email' => 'demo-student@oakfield.test']);
        $this->assertDatabaseMissing('invoices', ['creation_source' => 'demo']);
    }

    private function runSeeder(): void
    {
        $environment = app()->environment();
        app()->instance('env', 'local');
        try {
            $this->seed(DemoDataSeeder::class);
        } finally {
            app()->instance('env', $environment);
        }
    }

    private function snapshot(): array
    {
        return collect([
            'schools', 'school_settings', 'roles', 'permissions', 'role_permissions',
            'users', 'user_roles', 'sections', 'section_user', 'academic_levels',
            'classes', 'class_arms', 'subjects', 'class_subjects',
            'teacher_subject_assignments', 'academic_sessions', 'terms', 'students',
            'parents', 'parent_student', 'candidates', 'fee_items',
            'fee_schedule_lines', 'invoices', 'invoice_lines',
            'student_registration_number_history', 'audit_logs',
        ])->mapWithKeys(fn (string $table): array => [
            $table => DB::table($table)->orderByRaw('rowid')->get()->toJson(),
        ])->all();
    }
}
