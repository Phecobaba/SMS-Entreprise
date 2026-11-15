<?php

namespace Database\Seeders;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeacherSubjectAssignment;
use App\Modules\Academics\Services\ClassOrderingService;
use App\Modules\Admissions\Models\Candidate;
use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Services\RegistrationNumbers;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Models\ParentStudent;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\DefaultRoleCatalog;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Models\FeeItem;
use App\Modules\Payments\Models\FeeScheduleLine;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\InvoiceLine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoDataSeeder extends Seeder
{
    private const ACCOUNTS = [
        'admin' => ['School Admin', 'demo-admin@oakfield.test', 'DEMO_ADMIN_PASSWORD'],
        'teacher' => ['Teacher', 'demo-teacher@oakfield.test', 'DEMO_TEACHER_PASSWORD'],
        'principal' => ['Principal', 'demo-principal@oakfield.test', 'DEMO_PRINCIPAL_PASSWORD'],
        'bursar' => ['Bursar', 'demo-bursar@oakfield.test', 'DEMO_BURSAR_PASSWORD'],
        'student' => ['Student', 'demo-student@oakfield.test', 'DEMO_STUDENT_PASSWORD'],
        'parent' => ['Parent', 'demo-parent@oakfield.test', 'DEMO_PARENT_PASSWORD'],
        'candidate' => ['Candidate', 'demo-candidate@oakfield.test', 'DEMO_CANDIDATE_PASSWORD'],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoDataSeeder is restricted to APP_ENV=local.');
        }

        $passwords = collect(self::ACCOUNTS)->mapWithKeys(function (array $account, string $key): array {
            $password = env($account[2]);
            if (! is_string($password) || strlen($password) < 12) {
                throw new RuntimeException("Set {$account[2]} in the local .env to at least 12 characters before seeding.");
            }

            return [$key => $password];
        });

        DB::transaction(function () use ($passwords): void {
            $now = now();
            if (! DB::table('schools')->where('id', 1)->exists()) {
                DB::table('schools')->insert([
                    'id' => 1,
                    'name' => 'Oakfield International Schools',
                    'domain' => null,
                    'logo_path' => null,
                    'motto' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            config()->set('tenancy.default_school_id', 1);
            app(TenantContext::class)->set(1);
            $this->call(OakfieldBrandingSeeder::class);

            $roles = app(DefaultRoleCatalog::class)->provision();
            $roles['Candidate'] = Role::firstOrCreate(['name' => 'Candidate']);

            $createDemoStudent = Student::query()->doesntExist();
            $accounts = collect(self::ACCOUNTS)
                ->when(! $createDemoStudent, fn ($items) => $items->except('student'));

            $users = $accounts->mapWithKeys(function (array $account, string $key) use ($passwords, $roles): array {
                $user = User::firstOrCreate(['email' => $account[1]], [
                    'name' => 'Demo '.$account[0],
                    'email_verified_at' => now(),
                    'password' => $passwords[$key],
                    'status' => 'active',
                ]);
                if ($user->wasRecentlyCreated) {
                    $user->roles()->attach($roles[$account[0]]->id, ['school_id' => 1]);
                }

                return [$key => $user];
            });

            $primary = Section::firstOrCreate(
                ['name' => 'Primary School'],
                ['head_title' => 'Head Teacher', 'classes_arranged_by' => $users['admin']->id, 'classes_arranged_at' => $now],
            );
            $secondary = Section::firstOrCreate(
                ['name' => 'Secondary School'],
                ['head_title' => 'Principal', 'classes_arranged_by' => $users['admin']->id, 'classes_arranged_at' => $now],
            );
            if (! $secondary->heads()->whereKey($users['principal']->id)->exists()) {
                $secondary->heads()->attach($users['principal']->id, ['school_id' => 1]);
            }

            $lowerPrimary = AcademicLevel::firstOrCreate(['name' => 'Lower Primary'], ['section_id' => $primary->id]);
            $upperPrimary = AcademicLevel::firstOrCreate(['name' => 'Upper Primary'], ['section_id' => $primary->id]);
            $juniorSecondary = AcademicLevel::firstOrCreate(['name' => 'Junior Secondary'], ['section_id' => $secondary->id]);
            $seniorSecondary = AcademicLevel::firstOrCreate(['name' => 'Senior Secondary'], ['section_id' => $secondary->id]);

            $upsertClass = function (AcademicLevel $level, string $name, int $position): SchoolClass {
                $existing = SchoolClass::where('academic_level_id', $level->id)
                    ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])->first();
                if ($existing) {
                    return $existing;
                }
                $name = app(ClassOrderingService::class)->assertSchoolWideNameIsUnique($name);
                $class = SchoolClass::create([
                    'academic_level_id' => $level->id,
                    'name' => $name,
                    'position' => $position,
                ]);

                return $class;
            };
            $primaryOne = $upsertClass($lowerPrimary, 'Primary 1', 1);
            $primaryFour = $upsertClass($upperPrimary, 'Primary 4', 2);
            $primarySix = $upsertClass($upperPrimary, 'Primary 6', 3);
            $jsOne = $upsertClass($juniorSecondary, 'JS 1', 4);
            $ssOne = $upsertClass($seniorSecondary, 'SS 1', 5);

            $arms = collect([
                'primary_one' => [$primaryOne, 'A'],
                'primary_four' => [$primaryFour, 'A'],
                'primary_six' => [$primarySix, 'A'],
                'js_one' => [$jsOne, 'A'],
                'ss_one' => [$ssOne, 'A'],
            ])->mapWithKeys(fn (array $entry, string $key): array => [
                $key => ClassArm::firstOrCreate(
                    ['class_id' => $entry[0]->id, 'name' => $entry[1]],
                    $key === 'js_one' ? ['form_teacher_user_id' => $users['teacher']->id] : [],
                ),
            ]);

            $primaryMath = Subject::firstOrCreate(['section_id' => $primary->id, 'name' => 'Mathematics']);
            $primaryEnglish = Subject::firstOrCreate(['section_id' => $primary->id, 'name' => 'English Language']);
            $secondaryMath = Subject::firstOrCreate(['section_id' => $secondary->id, 'name' => 'Mathematics']);
            $secondaryEnglish = Subject::firstOrCreate(['section_id' => $secondary->id, 'name' => 'English Language']);
            foreach ([
                [$primaryOne, $primaryMath], [$primaryOne, $primaryEnglish],
                [$primaryFour, $primaryMath], [$primaryFour, $primaryEnglish],
                [$primarySix, $primaryMath], [$primarySix, $primaryEnglish],
                [$jsOne, $secondaryMath], [$jsOne, $secondaryEnglish],
                [$ssOne, $secondaryMath], [$ssOne, $secondaryEnglish],
            ] as [$class, $subject]) {
                ClassSubject::firstOrCreate(['class_id' => $class->id, 'subject_id' => $subject->id]);
            }
            TeacherSubjectAssignment::firstOrCreate([
                'user_id' => $users['teacher']->id,
                'class_id' => $jsOne->id,
                'subject_id' => $secondaryMath->id,
            ]);

            $today = Carbon::now('Africa/Lagos')->startOfDay();
            $session = AcademicSession::firstOrCreate(
                ['name' => $today->year.'/'.($today->year + 1)],
                ['start_date' => $today->copy()->subMonth()->toDateString(), 'end_date' => $today->copy()->addMonths(9)->toDateString()],
            );
            $term = AcademicTerm::firstOrCreate(
                ['session_id' => $session->id, 'name' => 'First Term'],
                ['start_date' => $today->copy()->subMonth()->toDateString(), 'end_date' => $today->copy()->addMonths(2)->toDateString()],
            );

            Candidate::firstOrCreate(
                ['user_id' => $users['candidate']->id],
                ['applied_level_id' => $juniorSecondary->id, 'name' => $users['candidate']->name, 'phone' => '08000000000', 'status' => 'Application Submitted'],
            );

            $tuition = FeeItem::firstOrCreate(['name' => 'Tuition']);
            $schedule = FeeScheduleLine::firstOrCreate(
                ['term_id' => $term->id, 'scope_type' => 'level', 'academic_level_id' => $upperPrimary->id, 'class_id' => null, 'department_id' => null, 'fee_item_id' => $tuition->id],
                ['amount_kobo' => 4500000, 'is_required' => true],
            );

            if ($createDemoStudent) {
                $student = Student::firstOrCreate(
                    ['user_id' => $users['student']->id],
                    ['class_arm_id' => $arms['primary_four']->id, 'status' => Student::STATUS_ACTIVE, 'admission_date' => $today->copy()->subYear()->toDateString()],
                );
                if ($student->wasRecentlyCreated) {
                    app(RegistrationNumbers::class)->assignAutomatic($student, $users['admin']);
                }
                $parent = ParentProfile::firstOrCreate(['user_id' => $users['parent']->id]);
                ParentStudent::firstOrCreate(['parent_id' => $parent->id, 'student_id' => $student->id]);
                $invoice = Invoice::firstOrCreate(
                    ['term_id' => $term->id, 'term_fee_student_id' => $student->id],
                    ['student_id' => $student->id, 'purpose' => 'First Term fees', 'kind' => Invoice::KIND_TERM_FEE,
                        'creation_source' => 'demo', 'amount_kobo' => $schedule->amount_kobo,
                        'due_date' => $today->copy()->addMonth()->toDateString(), 'status' => 'Unpaid', 'created_by' => $users['admin']->id],
                );
                InvoiceLine::firstOrCreate(
                    ['invoice_id' => $invoice->id, 'fee_item_id' => $tuition->id],
                    ['description' => 'Tuition', 'amount_kobo' => $schedule->amount_kobo, 'is_required' => true],
                );
            }
        });
    }
}
