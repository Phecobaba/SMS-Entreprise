<?php

namespace Tests\Feature\Academics;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Admissions\Models\Student;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Models\FeeItem;
use App\Modules\Payments\Models\FeeScheduleLine;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Results\Models\GradingScheme;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicSetupCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_guards_list_usage_and_allow_only_empty_records(): void
    {
        $f = $this->fixture();
        $class = SchoolClass::create(['academic_level_id' => $f['primaryLevel']->id, 'name' => 'Primary 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A']);
        $studentUser = User::create(['name' => 'Pupil', 'email' => 'setup-pupil@example.test', 'password' => 'long-password-here']);
        Student::create(['user_id' => $studentUser->id, 'class_arm_id' => $arm->id, 'admission_date' => today()]);

        $this->actingAs($f['admin'])->from(route('academics.levels.index'))->delete(route('academics.levels.destroy', $f['primaryLevel']))
            ->assertSessionHasErrors('level');
        $this->assertStringContainsString('1 classes', session('errors')->first('level'));
        $this->delete(route('academics.arms.destroy', $arm))->assertSessionHasErrors('arm');
        $this->assertStringContainsString('1 students', session('errors')->first('arm'));

        app(TenantContext::class)->set($f['school']->id);
        $emptyArm = ClassArm::create(['class_id' => $class->id, 'name' => 'B']);
        $emptyLevel = AcademicLevel::create(['section_id' => $f['primary']->id, 'name' => 'Nursery']);
        $scheduleLevel = AcademicLevel::create(['section_id' => $f['primary']->id, 'name' => 'Pre-Nursery']);
        $session = AcademicSession::create(['name' => '2023/2024', 'start_date' => '2023-01-01', 'end_date' => '2023-12-31']);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => '2023-01-01', 'end_date' => '2023-03-31']);
        $item = FeeItem::create(['name' => 'Tuition']);
        FeeScheduleLine::create(['term_id' => $term->id, 'scope_type' => 'level', 'academic_level_id' => $scheduleLevel->id, 'fee_item_id' => $item->id, 'amount_kobo' => 10000, 'is_required' => true]);
        $this->delete(route('academics.levels.destroy', $scheduleLevel))->assertSessionHasErrors('level');
        $this->assertStringContainsString('1 fee schedule lines', session('errors')->first('level'));
        $this->delete(route('academics.arms.destroy', $emptyArm))->assertSessionHasNoErrors();
        $this->delete(route('academics.levels.destroy', $emptyLevel))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('class_arms', ['id' => $emptyArm->id]);
        $this->assertDatabaseMissing('academic_levels', ['id' => $emptyLevel->id]);
    }

    public function test_head_is_scoped_for_level_and_arm_and_cannot_move_a_level(): void
    {
        $f = $this->fixture();
        $primaryClass = SchoolClass::create(['academic_level_id' => $f['primaryLevel']->id, 'name' => 'Primary 1']);
        $secondaryClass = SchoolClass::create(['academic_level_id' => $f['secondaryLevel']->id, 'name' => 'JS1']);
        $primaryArm = ClassArm::create(['class_id' => $primaryClass->id, 'name' => 'A']);
        $secondaryArm = ClassArm::create(['class_id' => $secondaryClass->id, 'name' => 'A']);

        $this->actingAs($f['head'])->get(route('academics.levels.index'))->assertOk()->assertSee('Primary School · 1 class')->assertDontSee('Secondary School · 1 class');
        $this->put(route('academics.levels.update', $f['secondaryLevel']), ['name' => 'Edited'])->assertForbidden();
        $this->delete(route('academics.levels.destroy', $f['secondaryLevel']))->assertForbidden();
        $this->put(route('academics.arms.update', $secondaryArm), ['name' => 'Gold'])->assertForbidden();
        $this->delete(route('academics.arms.destroy', $secondaryArm))->assertForbidden();
        $this->put(route('academics.levels.update', $f['primaryLevel']), ['name' => 'Primary', 'section_id' => $f['secondary']->id])->assertForbidden();

        $this->put(route('academics.arms.update', $primaryArm), ['name' => 'Gold'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('class_arms', ['id' => $primaryArm->id, 'name' => 'Gold']);

        app(TenantContext::class)->set($f['school']->id);
        $unassigned = User::create(['name' => 'Unassigned Head', 'email' => 'unassigned-setup@example.test', 'password' => 'long-password-here']);
        $unassigned->roles()->attach($f['head']->roles()->firstOrFail());
        $this->actingAs($unassigned)->get(route('academics.levels.index'))->assertOk()->assertSee('No section assigned')->assertDontSee('Primary School · 1 class');
    }

    public function test_current_dates_history_preservation_and_session_cascade_rules(): void
    {
        $f = $this->fixture();
        $currentSession = AcademicSession::create(['name' => 'Current', 'start_date' => today()->subMonth(), 'end_date' => today()->addMonth()]);
        $currentTerm = AcademicTerm::create(['session_id' => $currentSession->id, 'name' => 'Current Term', 'start_date' => today()->subWeek(), 'end_date' => today()->addWeek()]);
        $overlappingTerm = AcademicTerm::create(['session_id' => $currentSession->id, 'name' => 'Overlapping Term', 'start_date' => today()->subDays(2), 'end_date' => today()->addWeeks(2)]);
        $this->actingAs($f['admin'])->delete(route('academics.terms.destroy', $currentTerm))->assertSessionHasErrors('term');
        $this->delete(route('academics.terms.destroy', $overlappingTerm))->assertSessionHasErrors('term');
        $this->delete(route('academics.sessions.destroy', $currentSession))->assertSessionHasErrors('session');

        app(TenantContext::class)->set($f['school']->id);
        $pastSession = AcademicSession::create(['name' => 'Past', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
        $pastTerm = AcademicTerm::create(['session_id' => $pastSession->id, 'name' => 'First Term', 'start_date' => '2024-01-01', 'end_date' => '2024-03-31']);
        $class = SchoolClass::create(['academic_level_id' => $f['primaryLevel']->id, 'name' => 'Primary 2']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A']);
        $studentUser = User::create(['name' => 'History Pupil', 'email' => 'history-setup@example.test', 'password' => 'long-password-here']);
        $student = Student::create(['user_id' => $studentUser->id, 'class_arm_id' => $arm->id, 'admission_date' => '2024-01-01']);
        $subject = Subject::create(['name' => 'Mathematics']);
        $scheme = GradingScheme::create(['name' => 'Default']);
        $template = ResultTemplate::create(['academic_level_id' => $f['primaryLevel']->id, 'grading_scheme_id' => $scheme->id, 'name' => 'Primary', 'scope_key' => 'level:'.$f['primaryLevel']->id]);
        $result = Result::create(['student_id' => $student->id, 'class_arm_id' => $arm->id, 'subject_id' => $subject->id, 'term_id' => $pastTerm->id, 'result_template_id' => $template->id, 'status' => 'Draft']);
        $attendance = AttendanceRecord::create(['student_id' => $student->id, 'class_arm_id' => $arm->id, 'date' => '2024-02-01', 'session' => 'Morning', 'status' => 'Present', 'marked_by' => $f['admin']->id]);
        $invoice = Invoice::create(['student_id' => $student->id, 'term_id' => $pastTerm->id, 'purpose' => 'Tuition', 'amount_kobo' => 100000, 'due_date' => '2024-02-01', 'status' => 'Unpaid', 'created_by' => $f['admin']->id]);

        $this->put(route('academics.arms.update', $arm), ['name' => 'Gold'])->assertSessionHasNoErrors();
        $this->put(route('academics.sessions.update', $pastSession), ['name' => '2024 Academic Year', 'start_date' => '2023-12-01', 'end_date' => '2025-01-01'])->assertSessionHasNoErrors();
        $this->put(route('academics.terms.update', $pastTerm), ['session_id' => $pastSession->id, 'name' => 'Term One', 'start_date' => '2024-01-02', 'end_date' => '2024-04-01'])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($f['school']->id);
        $this->assertSame($arm->id, $result->fresh()->class_arm_id);
        $this->assertSame('2024-02-01', $attendance->fresh()->date->toDateString());
        $this->assertSame(100000, $invoice->fresh()->amount_kobo);

        $this->delete(route('academics.arms.destroy', $arm))->assertSessionHasErrors('arm');
        $armMessage = session('errors')->first('arm');
        $this->assertStringContainsString('results', $armMessage);
        $this->assertStringContainsString('attendance records', $armMessage);
        $this->assertStringContainsString('invoices', $armMessage);
        $this->delete(route('academics.terms.destroy', $pastTerm))->assertSessionHasErrors('term');
        $termMessage = session('errors')->first('term');
        $this->assertStringContainsString('results', $termMessage);
        $this->assertStringContainsString('attendance records', $termMessage);
        $this->assertStringContainsString('invoices', $termMessage);
        $this->delete(route('academics.sessions.destroy', $pastSession))->assertSessionHasErrors('session');
        app(TenantContext::class)->set($f['school']->id);
        $emptySession = AcademicSession::create(['name' => 'Unused', 'start_date' => '2022-01-01', 'end_date' => '2022-12-31']);
        $emptyTerm = AcademicTerm::create(['session_id' => $emptySession->id, 'name' => 'Unused Term', 'start_date' => '2022-01-01', 'end_date' => '2022-03-31']);
        $this->delete(route('academics.sessions.destroy', $emptySession))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('academic_sessions', ['id' => $emptySession->id]);
        $this->assertDatabaseMissing('terms', ['id' => $emptyTerm->id]);
        $this->assertDatabaseHas('results', ['id' => $result->id]);
        $this->assertDatabaseHas('attendance_records', ['id' => $attendance->id]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $admin = User::create(['name' => 'Admin', 'email' => 'setup-admin@example.test', 'password' => 'long-password-here']);
        $head = User::create(['name' => 'Head', 'email' => 'setup-head@example.test', 'password' => 'long-password-here']);
        $adminRole = Role::create(['name' => 'School Admin']);
        $adminRole->permissions()->attach(collect(['manage_academics', 'view_all_sections'])->map(fn ($name) => Permission::firstOrCreate(['name' => $name])->id));
        $headRole = Role::create(['name' => 'Principal']);
        $headRole->permissions()->attach(collect(['manage_section_levels', 'manage_section_arms', 'approve_results'])->map(fn ($name) => Permission::firstOrCreate(['name' => $name])->id));
        $admin->roles()->attach($adminRole);
        $head->roles()->attach($headRole);
        $primary = Section::create(['name' => 'Primary School', 'head_title' => 'Head Teacher']);
        $secondary = Section::create(['name' => 'Secondary School', 'head_title' => 'Principal']);
        $primary->heads()->attach($head, ['school_id' => $school->id]);
        $primaryLevel = AcademicLevel::create(['section_id' => $primary->id, 'name' => 'Primary']);
        $secondaryLevel = AcademicLevel::create(['section_id' => $secondary->id, 'name' => 'Junior Secondary']);

        return compact('school', 'admin', 'head', 'primary', 'secondary', 'primaryLevel', 'secondaryLevel');
    }
}
