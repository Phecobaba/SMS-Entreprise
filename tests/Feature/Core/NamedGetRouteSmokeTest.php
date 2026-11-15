<?php

namespace Tests\Feature\Core;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Admissions\Models\Candidate;
use App\Modules\Admissions\Models\Student;
use App\Modules\Assignments\Models\Assignment;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Models\ParentStudent;
use App\Modules\CBT\Models\Exam;
use App\Modules\CBT\Models\ExamSubmission;
use App\Modules\CBT\Models\ExamType;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\DefaultRoleCatalog;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\ManualPaymentSubmission;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultCard;
use App\Modules\Results\Services\ResultConfiguration;
use App\Modules\StaffHR\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NamedGetRouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_named_get_routes_without_parameters_never_fail_for_each_core_role(): void
    {
        $users = $this->fixtures();
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (IlluminateRoute $route): bool => in_array('GET', $route->methods(), true))
            ->filter(fn (IlluminateRoute $route): bool => filled($route->getName()))
            ->filter(fn (IlluminateRoute $route): bool => ! str_contains($route->uri(), '{'))
            ->values();

        foreach ($users as $role => $user) {
            foreach ($routes as $route) {
                $response = $this->actingAs($user)->get(route($route->getName()));

                $this->assertLessThan(
                    500,
                    $response->getStatusCode(),
                    "{$role} received {$response->getStatusCode()} from [{$route->getName()}] {$route->uri()}.",
                );
            }
        }
    }

    public function test_named_get_routes_with_domain_objects_never_fail(): void
    {
        $f = $this->fixtures(true);
        Storage::fake('local');
        Storage::disk('local')->put('payment-slips/smoke.txt', 'smoke');

        $routes = [
            ['payments.manual-submissions.slip', $f['bursar'], $f['manualSubmission']],
            ['payments.invoices.show', $f['admin'], $f['invoice']],
            ['password.reset', $f['admin'], 'smoke-token'],
            ['results.cards.show', $f['admin'], $f['card']],
            ['results.cards.pdf', $f['admin'], $f['card']],
            ['results.mine.show', $f['studentUser'], $f['card']],
            ['results.mine.pdf', $f['studentUser'], $f['card']],
            ['results.new', $f['admin'], $f['student']],
            ['results.show', $f['admin'], $f['result']],
            ['staff.show', $f['admin'], $f['staff']],
            ['staff.edit', $f['admin'], $f['staff']],
            ['staff.salary', $f['admin'], $f['staff']],
            ['staff.discipline', $f['admin'], $f['staff']],
            ['staff.access.show', $f['admin'], $f['teacher']],
            ['admissions.show', $f['admin'], $f['candidate']],
            ['assignments.show', $f['teacher'], $f['assignment']],
            ['students.show', $f['admin'], $f['student']],
            ['attendance.day', $f['teacher'], $f['arm']],
            ['cbt.staff.show', $f['admin'], $f['exam']],
            ['cbt.sit', $f['studentUser'], $f['activeSubmission']],
            ['cbt.result', $f['studentUser'], $f['submittedSubmission']],
        ];

        foreach ($routes as [$name, $actor, $parameter]) {
            $response = $this->actingAs($actor)->get(route($name, $parameter));

            $this->assertLessThan(500, $response->getStatusCode(), "[{$name}] returned {$response->getStatusCode()}.");
        }
    }

    /** @return array<string, User> */
    private function fixtures(bool $withDomainObjects = false): array
    {
        $school = School::create(['name' => 'Route Smoke School']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        SchoolSetting::create([]);
        $roles = app(DefaultRoleCatalog::class)->provision();

        $users = [];
        foreach (['School Admin' => 'Admin', 'Head' => 'Head', 'Teacher' => 'Teacher', 'Bursar' => 'Bursar', 'Student' => 'Student', 'Parent' => 'Parent'] as $role => $label) {
            $user = User::create([
                'name' => "{$label} Route Smoke",
                'email' => strtolower($label).'@route-smoke.test',
                'username' => strtolower($label).'-route-smoke',
                'password' => 'long-password-here',
            ]);
            $user->roles()->attach($roles[$role]);
            $users[$label] = $user;
        }

        $primary = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher']);
        DB::table('section_user')->insert([
            'school_id' => $school->id,
            'section_id' => $primary->id,
            'user_id' => $users['Head']->id,
            'is_head' => true,
        ]);
        $level = AcademicLevel::create(['section_id' => $primary->id, 'name' => 'Primary 1']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'Primary 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'Gold', 'form_teacher_user_id' => $users['Teacher']->id]);
        $student = Student::create([
            'user_id' => $users['Student']->id,
            'class_arm_id' => $arm->id,
            'status' => Student::STATUS_ACTIVE,
            'admission_date' => today(),
        ]);
        $parent = ParentProfile::create(['user_id' => $users['Parent']->id]);
        ParentStudent::create(['parent_id' => $parent->id, 'student_id' => $student->id]);

        if (! $withDomainObjects) {
            return $users;
        }

        $session = AcademicSession::create([
            'name' => '2026/2027', 'start_date' => today()->subYear(), 'end_date' => today()->addYear(),
        ]);
        $term = AcademicTerm::create([
            'session_id' => $session->id, 'name' => 'Current Term',
            'start_date' => today()->subMonth(), 'end_date' => today()->addMonth(),
        ]);
        $subject = Subject::create(['section_id' => $primary->id, 'name' => 'Mathematics']);
        ClassSubject::create(['class_id' => $class->id, 'subject_id' => $subject->id]);
        $configuration = app(ResultConfiguration::class);
        $scheme = $configuration->addScheme($primary->id, 'Smoke scale');
        $configuration->addBand($scheme->id, 0, 100, 'A', 'Strong');
        $component = $configuration->addComponent($primary->id, 'Exam', 100);
        $template = $configuration->addTemplate('Smoke template', $primary->id, $level->id, null, $scheme->id, [$component->id]);
        $result = Result::create([
            'student_id' => $student->id, 'class_arm_id' => $arm->id, 'subject_id' => $subject->id,
            'term_id' => $term->id, 'result_template_id' => $template->id, 'status' => 'Approved',
            'total_score' => 75, 'percentage' => 75, 'grade' => 'A', 'remark' => 'Strong',
        ]);
        $card = ResultCard::create([
            'student_id' => $student->id, 'term_id' => $term->id,
            'class_arm_id' => $arm->id, 'compiled_at' => now(),
        ]);
        $candidateUser = User::create([
            'name' => 'Candidate Route Smoke', 'email' => 'candidate@route-smoke.test',
            'username' => 'candidate-route-smoke', 'password' => 'long-password-here',
        ]);
        $candidate = Candidate::create([
            'user_id' => $candidateUser->id, 'applied_level_id' => $level->id,
            'name' => $candidateUser->name, 'phone' => '08000000000', 'status' => 'Registered',
        ]);
        $assignment = Assignment::create([
            'subject_id' => $subject->id, 'class_arm_id' => $arm->id, 'title' => 'Smoke assignment',
            'description' => 'Route smoke fixture', 'due_date' => today()->addDay(), 'created_by' => $users['Teacher']->id,
        ]);
        $staff = Staff::create([
            'user_id' => $users['Teacher']->id, 'role_title' => 'Teacher',
            'employment_date' => today()->subYear(), 'contract_type' => 'Permanent', 'status' => 'Active',
        ]);
        $invoice = Invoice::create([
            'student_id' => $student->id, 'term_id' => $term->id, 'purpose' => 'Smoke invoice',
            'kind' => Invoice::KIND_ONE_OFF, 'creation_source' => 'single', 'amount_kobo' => 100000,
            'due_date' => today(), 'status' => 'Unpaid', 'created_by' => $users['Bursar']->id,
        ]);
        $manualSubmission = ManualPaymentSubmission::create([
            'invoice_id' => $invoice->id, 'bank' => 'Smoke Bank', 'teller_number' => 'SMOKE-1',
            'active_teller_key' => 'SMOKE-1', 'paid_on' => today(), 'amount_kobo' => 100000,
            'photo_path' => 'payment-slips/smoke.txt', 'recorded_by' => $users['Bursar']->id,
            'status' => ManualPaymentSubmission::STATUS_AWAITING,
        ]);
        $examType = ExamType::create(['name' => 'Smoke type']);
        $exam = Exam::create([
            'exam_type_id' => $examType->id, 'subject_id' => $subject->id, 'class_id' => $class->id,
            'term_id' => $term->id, 'created_by' => $users['Admin']->id, 'title' => 'Smoke exam',
            'duration' => 30, 'status' => 'Published', 'result_release_type' => 'Immediate',
            'is_released' => true, 'show_score' => true,
        ]);
        $activeSubmission = ExamSubmission::create([
            'exam_id' => $exam->id, 'student_id' => $student->id,
            'started_at' => now(), 'status' => 'In Progress',
        ]);
        $resultExam = Exam::create([
            'exam_type_id' => $examType->id, 'subject_id' => $subject->id, 'class_id' => $class->id,
            'term_id' => $term->id, 'created_by' => $users['Admin']->id, 'title' => 'Completed smoke exam',
            'duration' => 30, 'status' => 'Published', 'result_release_type' => 'Immediate',
            'is_released' => true, 'show_score' => true,
        ]);
        $submittedSubmission = ExamSubmission::create([
            'exam_id' => $resultExam->id, 'student_id' => $student->id,
            'started_at' => now()->subMinute(), 'submitted_at' => now(),
            'raw_score' => 0, 'max_score' => 0, 'converted_score' => 0, 'status' => 'Submitted',
        ]);

        return $users + compact(
            'student', 'arm', 'result', 'card', 'candidate', 'assignment', 'staff',
            'invoice', 'manualSubmission', 'exam', 'activeSubmission', 'submittedSubmission',
        ) + ['studentUser' => $users['Student'], 'teacher' => $users['Teacher'], 'bursar' => $users['Bursar'], 'admin' => $users['Admin']];
    }
}
