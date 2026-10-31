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
            if (DB::table('schools')->where('id', 1)->exists()) {
                DB::table('schools')->where('id', 1)->update([
                    'name' => 'Oakfield International Schools',
                    'updated_at' => $now,
                ]);
            } else {
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

            $users = collect(self::ACCOUNTS)->mapWithKeys(function (array $account, string $key) use ($passwords, $roles): array {
                $user = User::firstOrNew(['email' => $account[1]]);
                if (! $user->exists) {
                    $user->password = $passwords[$key];
                }
                $user->name = 'Demo '.$account[0];
                $user->status = 'active';
                $user->save();
                $user->roles()->syncWithPivotValues([$roles[$account[0]]->id], ['school_id' => 1]);

                return [$key => $user];
            });

            $primary = Section::updateOrCreate(
                ['name' => 'Primary School'],
                ['head_title' => 'Head Teacher', 'classes_arranged_by' => $users['admin']->id, 'classes_arranged_at' => $now],
            );
            $secondary = Section::updateOrCreate(
                ['name' => 'Secondary School'],
                ['head_title' => 'Principal', 'classes_arranged_by' => $users['admin']->id, 'classes_arranged_at' => $now],
            );
            $secondary->heads()->syncWithoutDetaching([
                $users['principal']->id => ['school_id' => 1],
            ]);

            $lowerPrimary = AcademicLevel::updateOrCreate(['name' => 'Lower Primary'], ['section_id' => $primary->id]);
            $upperPrimary = AcademicLevel::updateOrCreate(['name' => 'Upper Primary'], ['section_id' => $primary->id]);
            $juniorSecondary = AcademicLevel::updateOrCreate(['name' => 'Junior Secondary'], ['section_id' => $secondary->id]);
            $seniorSecondary = AcademicLevel::updateOrCreate(['name' => 'Senior Secondary'], ['section_id' => $secondary->id]);

            $primaryOne = SchoolClass::updateOrCreate(['academic_level_id' => $lowerPrimary->id, 'name' => 'Primary 1'], ['position' => 1]);
            $primaryFour = SchoolClass::updateOrCreate(['academic_level_id' => $upperPrimary->id, 'name' => 'Primary 4'], ['position' => 2]);
            $primarySix = SchoolClass::updateOrCreate(['academic_level_id' => $upperPrimary->id, 'name' => 'Primary 6'], ['position' => 3]);
            $jsOne = SchoolClass::updateOrCreate(['academic_level_id' => $juniorSecondary->id, 'name' => 'JS 1'], ['position' => 4]);
            $ssOne = SchoolClass::updateOrCreate(['academic_level_id' => $seniorSecondary->id, 'name' => 'SS 1'], ['position' => 5]);

            $arms = collect([
                'primary_one' => [$primaryOne, 'A'],
                'primary_four' => [$primaryFour, 'A'],
                'primary_six' => [$primarySix, 'A'],
                'js_one' => [$jsOne, 'A'],
                'ss_one' => [$ssOne, 'A'],
            ])->mapWithKeys(fn (array $entry, string $key): array => [
                $key => ClassArm::updateOrCreate(['class_id' => $entry[0]->id, 'name' => $entry[1]]),
            ]);
            $arms['js_one']->update(['form_teacher_user_id' => $users['teacher']->id]);

            $primaryMath = Subject::updateOrCreate(['section_id' => $primary->id, 'name' => 'Mathematics']);
            $primaryEnglish = Subject::updateOrCreate(['section_id' => $primary->id, 'name' => 'English Language']);
            $secondaryMath = Subject::updateOrCreate(['section_id' => $secondary->id, 'name' => 'Mathematics']);
            $secondaryEnglish = Subject::updateOrCreate(['section_id' => $secondary->id, 'name' => 'English Language']);
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
            $session = AcademicSession::updateOrCreate(
                ['name' => $today->year.'/'.($today->year + 1)],
                ['start_date' => $today->copy()->subMonth()->toDateString(), 'end_date' => $today->copy()->addMonths(9)->toDateString()],
            );
            $term = AcademicTerm::updateOrCreate(
                ['session_id' => $session->id, 'name' => 'First Term'],
                ['start_date' => $today->copy()->subMonth()->toDateString(), 'end_date' => $today->copy()->addMonths(2)->toDateString()],
            );

            $student = Student::updateOrCreate(
                ['user_id' => $users['student']->id],
                ['class_arm_id' => $arms['primary_four']->id, 'status' => Student::STATUS_ACTIVE, 'admission_date' => $today->copy()->subYear()->toDateString()],
            );
            app(RegistrationNumbers::class)->assignAutomatic($student, $users['admin']);
            $parent = ParentProfile::firstOrCreate(['user_id' => $users['parent']->id]);
            ParentStudent::firstOrCreate(['parent_id' => $parent->id, 'student_id' => $student->id]);
            Candidate::updateOrCreate(
                ['user_id' => $users['candidate']->id],
                ['applied_level_id' => $juniorSecondary->id, 'name' => $users['candidate']->name, 'phone' => '08000000000', 'status' => 'Application Submitted'],
            );

            $tuition = FeeItem::firstOrCreate(['name' => 'Tuition']);
            $schedule = FeeScheduleLine::updateOrCreate(
                ['term_id' => $term->id, 'scope_type' => 'level', 'academic_level_id' => $upperPrimary->id, 'class_id' => null, 'department_id' => null, 'fee_item_id' => $tuition->id],
                ['amount_kobo' => 4500000, 'is_required' => true],
            );
            $invoice = Invoice::updateOrCreate(
                ['term_id' => $term->id, 'term_fee_student_id' => $student->id],
                ['student_id' => $student->id, 'purpose' => 'First Term fees', 'kind' => Invoice::KIND_TERM_FEE,
                    'creation_source' => 'demo', 'amount_kobo' => $schedule->amount_kobo,
                    'due_date' => $today->copy()->addMonth()->toDateString(), 'status' => 'Unpaid', 'created_by' => $users['admin']->id],
            );
            InvoiceLine::updateOrCreate(
                ['invoice_id' => $invoice->id, 'fee_item_id' => $tuition->id],
                ['description' => 'Tuition', 'amount_kobo' => $schedule->amount_kobo, 'is_required' => true],
            );
        });
    }
}
