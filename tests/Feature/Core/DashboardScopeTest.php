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
use App\Modules\Academics\Models\TeacherSubjectAssignment;
use App\Modules\Admissions\Models\AdmissionOffer;
use App\Modules\Admissions\Models\Candidate;
use App\Modules\Admissions\Models\Student;
use App\Modules\Attendance\Models\AttendanceAlert;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\DashboardOverview;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Results\Models\GradingScheme;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_dashboard_cannot_show_another_section_including_edited_ids_or_query_strings(): void
    {
        $f = $this->fixture();

        $this->actingAs($f['head'])->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Primary School')
            ->assertSee('Primary 5 Gold')
            ->assertDontSee('JS1 Blue');
        $this->get(route('dashboard', ['section' => $f['secondary']->id]))->assertForbidden();
        $this->post(route('results.approve', $f['secondaryResult']))->assertForbidden();
        $this->get(route('attendance.alerts'))
            ->assertOk()->assertSee('Primary 5 Gold')->assertDontSee('JS1 Blue');
    }

    public function test_all_equals_the_sum_of_sections_for_additive_dashboard_figures(): void
    {
        $f = $this->fixture();
        $overview = app(DashboardOverview::class);
        $admin = $f['admin']->load('roles.permissions');
        $all = $overview->for($admin);
        $primary = $overview->for($admin, sectionId: $f['primary']->id, section: $f['primary']);
        $secondary = $overview->for($admin, sectionId: $f['secondary']->id, section: $f['secondary']);

        foreach (['students', 'approval_count', 'offers_count', 'outstanding_kobo'] as $figure) {
            $this->assertSame($all[$figure], $primary[$figure] + $secondary[$figure], $figure);
        }
        $this->assertCount(2, $all['approvals']);
        $this->assertSame(['Submitted'], collect($all['approvals'])->pluck('status')->unique()->values()->all());
        $this->assertSame(
            $all['attendance']['alerts_count'],
            $primary['attendance']['alerts_count'] + $secondary['attendance']['alerts_count'],
        );
    }

    public function test_teachers_assigned_counts_distinct_people_in_each_scope(): void
    {
        $f = $this->fixture();
        $overview = app(DashboardOverview::class);
        $admin = $f['admin']->load('roles.permissions');

        $this->assertSame(2, $overview->for($admin)['teachers']);
        $this->assertSame(1, $overview->for($admin, sectionId: $f['primary']->id, section: $f['primary'])['teachers']);
        $this->assertSame(2, $overview->for($admin, sectionId: $f['secondary']->id, section: $f['secondary'])['teachers']);
    }

    public function test_head_without_a_section_sees_only_the_not_assigned_state(): void
    {
        $f = $this->fixture();

        $this->actingAs($f['unassignedHead'])->get(route('dashboard'))
            ->assertOk()
            ->assertSee("You haven't been assigned to a section yet", false)
            ->assertDontSee('Teachers assigned')
            ->assertDontSee('Results awaiting approval');
        $this->get(route('attendance.alerts'))
            ->assertOk()
            ->assertSee("You haven't been assigned to a section yet", false)
            ->assertDontSee('Primary 5 Gold')
            ->assertDontSee('JS1 Blue');
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);

        $permissions = collect([
            'view_dashboard', 'view_all_sections', 'approve_results', 'view_attendance_alerts',
            'manage_attendance', 'manage_payments',
        ])->mapWithKeys(fn (string $name) => [$name => Permission::firstOrCreate(['name' => $name])]);
        $adminRole = Role::create(['name' => 'School Admin']);
        $adminRole->permissions()->attach($permissions->pluck('id'));
        $headRole = Role::create(['name' => 'Principal']);
        $headRole->permissions()->attach($permissions->only([
            'view_dashboard', 'approve_results', 'view_attendance_alerts', 'manage_attendance',
        ])->pluck('id'));

        $admin = $this->user('Admin');
        $head = $this->user('Primary Head');
        $unassignedHead = $this->user('Unassigned Head');
        $admin->roles()->attach($adminRole);
        $head->roles()->attach($headRole);
        $unassignedHead->roles()->attach($headRole);

        $primary = Section::create(['name' => 'Primary School', 'head_title' => 'Head Teacher']);
        $secondary = Section::create(['name' => 'Secondary School', 'head_title' => 'Principal']);
        $primary->heads()->attach($head, ['school_id' => $school->id]);
        $primaryLevel = AcademicLevel::create(['section_id' => $primary->id, 'name' => 'Primary']);
        $secondaryLevel = AcademicLevel::create(['section_id' => $secondary->id, 'name' => 'Junior Secondary']);
        $primaryClass = SchoolClass::create(['academic_level_id' => $primaryLevel->id, 'name' => 'Primary 5']);
        $secondaryClass = SchoolClass::create(['academic_level_id' => $secondaryLevel->id, 'name' => 'JS1']);
        $primaryArm = ClassArm::create(['class_id' => $primaryClass->id, 'name' => 'Gold']);
        $secondaryArm = ClassArm::create(['class_id' => $secondaryClass->id, 'name' => 'Blue']);

        $primaryStudent = $this->student('Primary Learner', $primaryArm);
        $secondaryStudent = $this->student('Secondary Learner', $secondaryArm);
        $this->student('Second Secondary Learner', $secondaryArm);
        $subject = Subject::create(['name' => 'Mathematics']);
        $rejectedSubject = Subject::create(['name' => 'English']);
        ClassSubject::create(['class_id' => $primaryClass->id, 'subject_id' => $subject->id]);
        ClassSubject::create(['class_id' => $secondaryClass->id, 'subject_id' => $subject->id]);
        $teacherOne = $this->user('Shared Teacher');
        $teacherTwo = $this->user('Secondary Teacher');
        TeacherSubjectAssignment::create(['user_id' => $teacherOne->id, 'class_id' => $primaryClass->id, 'subject_id' => $subject->id]);
        TeacherSubjectAssignment::create(['user_id' => $teacherOne->id, 'class_id' => $secondaryClass->id, 'subject_id' => $subject->id]);
        TeacherSubjectAssignment::create(['user_id' => $teacherTwo->id, 'class_id' => $secondaryClass->id, 'subject_id' => $subject->id]);

        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => today()->subMonth(), 'end_date' => today()->addMonths(8)]);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => today()->subMonth(), 'end_date' => today()->addMonths(2)]);
        $scheme = GradingScheme::create(['name' => 'Default']);
        $primaryTemplate = ResultTemplate::create(['academic_level_id' => $primaryLevel->id, 'grading_scheme_id' => $scheme->id, 'name' => 'Primary', 'scope_key' => 'level:'.$primaryLevel->id]);
        $secondaryTemplate = ResultTemplate::create(['academic_level_id' => $secondaryLevel->id, 'grading_scheme_id' => $scheme->id, 'name' => 'Secondary', 'scope_key' => 'level:'.$secondaryLevel->id]);
        Result::create(['student_id' => $primaryStudent->id, 'class_arm_id' => $primaryArm->id, 'subject_id' => $subject->id, 'term_id' => $term->id, 'result_template_id' => $primaryTemplate->id, 'status' => 'Submitted', 'submitted_by' => $teacherOne->id]);
        $secondaryResult = Result::create(['student_id' => $secondaryStudent->id, 'class_arm_id' => $secondaryArm->id, 'subject_id' => $subject->id, 'term_id' => $term->id, 'result_template_id' => $secondaryTemplate->id, 'status' => 'Submitted', 'submitted_by' => $teacherTwo->id]);
        Result::create(['student_id' => $secondaryStudent->id, 'class_arm_id' => $secondaryArm->id, 'subject_id' => $rejectedSubject->id, 'term_id' => $term->id, 'result_template_id' => $secondaryTemplate->id, 'status' => 'Rejected', 'submitted_by' => $teacherTwo->id]);

        AttendanceAlert::create(['class_arm_id' => $primaryArm->id, 'date' => today('Africa/Lagos'), 'session' => 'Morning', 'resolved' => false]);
        AttendanceAlert::create(['class_arm_id' => $secondaryArm->id, 'date' => today('Africa/Lagos'), 'session' => 'Afternoon', 'resolved' => false]);
        $this->pendingOffer('Primary Candidate', $primaryLevel, $admin);
        $this->pendingOffer('Secondary Candidate', $secondaryLevel, $admin);
        Invoice::create(['student_id' => $primaryStudent->id, 'term_id' => $term->id, 'purpose' => 'Tuition', 'amount_kobo' => 100000, 'due_date' => today(), 'status' => 'Unpaid', 'created_by' => $admin->id]);
        Invoice::create(['student_id' => $secondaryStudent->id, 'term_id' => $term->id, 'purpose' => 'Tuition', 'amount_kobo' => 200000, 'due_date' => today(), 'status' => 'Unpaid', 'created_by' => $admin->id]);

        return compact('admin', 'head', 'unassignedHead', 'primary', 'secondary', 'secondaryResult');
    }

    private function user(string $name): User
    {
        return User::create(['name' => $name, 'email' => str($name)->slug().'@dashboard.test', 'password' => 'long-password-here', 'status' => 'active']);
    }

    private function student(string $name, ClassArm $arm): Student
    {
        return Student::create(['user_id' => $this->user($name)->id, 'class_arm_id' => $arm->id, 'admission_date' => today()]);
    }

    private function pendingOffer(string $name, AcademicLevel $level, User $admin): void
    {
        $candidate = Candidate::create(['user_id' => $this->user($name)->id, 'applied_level_id' => $level->id, 'name' => $name, 'phone' => '08000000000', 'status' => 'Offer Extended']);
        AdmissionOffer::create(['candidate_id' => $candidate->id, 'offered_by' => $admin->id, 'offer_date' => today(), 'response_deadline' => today()->addWeek(), 'status' => 'Pending', 'deposit_required' => false, 'deposit_paid' => false]);
    }
}
