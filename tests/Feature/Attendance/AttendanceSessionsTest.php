<?php

namespace Tests\Feature\Attendance;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Models\Student;
use App\Modules\Attendance\Models\AttendanceAlert;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Attendance\Models\AttendanceSessionNotHeld;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceSessionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_posted_session_is_saved_not_the_clock_and_each_session_has_one_record(): void
    {
        $f = $this->fixture();
        CarbonImmutable::setTestNow('2026-10-19 13:00:00 Africa/Lagos');
        $statuses = [$f['students'][0]->id => 'Present', $f['students'][1]->id => 'Absent'];

        $this->actingAs($f['teacher'])->get(route('attendance.day', $f['armA']->id))
            ->assertOk()->assertSee('Afternoon');
        $this->post(route('attendance.mark', $f['armA']->id), ['date' => '2026-10-19', 'session' => 'Morning', 'statuses' => $statuses])
            ->assertSessionHasNoErrors();
        $this->post(route('attendance.mark', $f['armA']->id), ['date' => '2026-10-19', 'session' => 'Morning', 'statuses' => $statuses])
            ->assertSessionHasNoErrors();
        $this->post(route('attendance.mark', $f['armA']->id), ['date' => '2026-10-19', 'session' => 'Afternoon', 'statuses' => $statuses])
            ->assertSessionHasNoErrors();

        $this->tenant($f);
        $this->assertSame(2, AttendanceRecord::where('student_id', $f['students'][0]->id)->count());
        $this->assertSame(['Afternoon', 'Morning'], AttendanceRecord::where('student_id', $f['students'][0]->id)->orderBy('session')->pluck('session')->all());
    }

    public function test_lock_is_per_session(): void
    {
        $f = $this->fixture();
        $date = today('Africa/Lagos')->toDateString();
        $statuses = [$f['students'][0]->id => 'Present', $f['students'][1]->id => 'Absent'];
        $service = app(AttendanceService::class);
        $service->markDay($f['armA']->id, $date, 'Morning', $statuses, $f['teacher']);
        $service->markDay($f['armA']->id, $date, 'Afternoon', $statuses, $f['teacher']);
        $service->lockDay($f['armA']->id, $date, 'Morning', $f['admin']);

        $this->actingAs($f['teacher'])->post(route('attendance.mark', $f['armA']->id), [
            'date' => $date, 'session' => 'Morning', 'statuses' => array_fill_keys(array_keys($statuses), 'Late'),
        ])->assertSessionHasErrors('attendance');
        $this->post(route('attendance.mark', $f['armA']->id), [
            'date' => $date, 'session' => 'Afternoon', 'statuses' => array_fill_keys(array_keys($statuses), 'Late'),
        ])->assertSessionHasNoErrors();
        $this->tenant($f);
        $this->assertTrue(AttendanceRecord::where('session', 'Morning')->get()->every->locked);
        $this->assertTrue(AttendanceRecord::where('session', 'Afternoon')->get()->every(fn ($row) => ! $row->locked));
    }

    public function test_legacy_rows_count_twice_and_exclusions_produce_the_session_percentage(): void
    {
        $f = $this->fixture();
        SchoolSetting::create(['result_access_payment_rule' => 'None']);
        $student = $f['students'][0];
        $this->record($student, $f['armA'], '2026-10-12', null, 'Present', $f['teacher']);
        $summary = app(AttendanceService::class)->portalSummary($student->id, $student->user);
        $this->assertSame(2, $summary['present_sessions']);
        $this->assertSame(2, $summary['counted_sessions']);

        AttendanceRecord::query()->delete();
        $this->record($student, $f['armA'], '2026-10-13', 'Morning', 'Present', $f['teacher']);
        $this->record($student, $f['armA'], '2026-10-13', 'Afternoon', 'Excused', $f['teacher']);
        $this->record($student, $f['armA'], '2026-10-14', 'Morning', 'Absent', $f['teacher']);
        $this->record($student, $f['armA'], '2026-10-15', 'Morning', 'Present', $f['teacher']);
        $this->record($student, $f['armA'], '2026-10-15', 'Afternoon', 'Present', $f['teacher']);
        AttendanceSessionNotHeld::create(['section_id' => $f['sectionA']->id, 'date' => '2026-10-15', 'session' => 'Afternoon', 'marked_by' => $f['admin']->id, 'created_at' => now()]);
        $this->record($student, $f['armA'], '2026-10-16', 'Morning', 'Present', $f['teacher']);
        $this->record($student, $f['armA'], '2026-10-16', 'Afternoon', 'Present', $f['teacher']);
        foreach (AttendanceService::SESSIONS as $session) {
            AttendanceSessionNotHeld::create(['section_id' => $f['sectionA']->id, 'date' => '2026-10-16', 'session' => $session, 'marked_by' => $f['admin']->id, 'created_at' => now()]);
        }
        $summary = app(AttendanceService::class)->portalSummary($student->id, $student->user);

        $this->assertSame(67, $summary['percentage']);
        $this->assertSame(3, $summary['counted_sessions']);
        $this->assertSame(1, $summary['absent_sessions']);
        $this->assertSame('Not held', $summary['days']->first()['sessions']['Afternoon']);
        $this->assertSame('Not marked', $summary['days'][1]['sessions']['Afternoon']);
        $this->assertNotContains('2026-10-16', $summary['days']->pluck('date')->map->toDateString()->all());
    }

    public function test_head_is_limited_to_own_section_admin_can_manage_any_and_every_change_is_audited(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['head'])->put(route('attendance.settings.update', $f['sectionA']->id), [
            'morning_due_at' => '08:15', 'afternoon_due_at' => '14:15',
        ])->assertSessionHasNoErrors();
        $this->put(route('attendance.settings.update', $f['sectionB']->id), [
            'morning_due_at' => '08:15', 'afternoon_due_at' => '14:15',
        ])->assertForbidden();
        $this->actingAs($f['admin'])->put(route('attendance.settings.update', $f['sectionB']->id), [
            'morning_due_at' => '08:30', 'afternoon_due_at' => '14:30',
        ])->assertSessionHasNoErrors();
        $this->tenant($f);
        $this->assertSame(2, AuditLog::where('action', 'attendance.alert_times_updated')->count());

        $this->put(route('attendance.settings.update', $f['sectionB']->id), [
            'morning_due_at' => '15:00', 'afternoon_due_at' => '14:00',
        ])->assertSessionHasErrors('afternoon_due_at');

        $date = today('Africa/Lagos')->toDateString();
        $this->actingAs($f['head'])->post(route('attendance.not-held.store', $f['sectionA']->id), ['date' => $date, 'session' => 'Morning'])
            ->assertSessionHasNoErrors();
        $this->post(route('attendance.not-held.store', $f['sectionB']->id), ['date' => $date, 'session' => 'Morning'])
            ->assertForbidden();
        $this->actingAs($f['admin'])->post(route('attendance.not-held.store', $f['sectionB']->id), ['date' => $date, 'session' => 'Morning'])
            ->assertSessionHasNoErrors();
        $this->tenant($f);
        $this->assertSame(2, AuditLog::where('action', 'attendance.session_not_held_added')->count());
        $entry = AttendanceSessionNotHeld::where('section_id', $f['sectionB']->id)->firstOrFail();
        $this->delete(route('attendance.not-held.destroy', $entry->id))->assertSessionHasNoErrors();
        $this->tenant($f);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.session_not_held_added']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.session_not_held_removed']);
    }

    public function test_both_not_held_suppresses_and_resolves_alerts_duplicate_is_refused_and_past_cannot_be_removed(): void
    {
        $f = $this->fixture();
        $today = today('Africa/Lagos')->toDateString();
        foreach (AttendanceService::SESSIONS as $session) {
            AttendanceAlert::create(['class_arm_id' => $f['armA']->id, 'date' => $today, 'session' => $session, 'resolved' => false]);
        }
        $this->record($f['students'][0], $f['armA'], $today, 'Morning', 'Present', $f['teacher']);
        $this->record($f['students'][0], $f['armA'], $today, 'Afternoon', 'Present', $f['teacher']);
        $this->record($f['students'][1], $f['armA'], $today, 'Afternoon', 'Late', $f['teacher']);
        $preview = app(AttendanceService::class)->previewNotHeld($f['sectionA']->id, $today, 'Both', $f['admin']);
        $this->assertSame(2, $preview['open_alerts']);
        $this->assertSame(1, $preview['sessions']['Morning']['marked_students']);
        $this->assertSame(2, $preview['sessions']['Afternoon']['marked_students']);
        $this->actingAs($f['admin'])->post(route('attendance.not-held.store', $f['sectionA']->id), ['date' => $today, 'session' => 'Both'])
            ->assertSessionHasNoErrors()->assertSessionHas('notHeldReport', $preview);
        $this->tenant($f);
        $this->assertSame(2, AttendanceAlert::where('resolved', true)->count());
        $this->post(route('attendance.not-held.store', $f['sectionA']->id), ['date' => $today, 'session' => 'Morning'])
            ->assertSessionHasErrors('session');
        $this->tenant($f);
        $now = CarbonImmutable::parse($today.' 16:00', 'Africa/Lagos');
        $this->assertSame(0, app(AttendanceService::class)->generateMissingAlerts($today, 'Morning', $now));
        $this->assertSame(0, app(AttendanceService::class)->generateMissingAlerts($today, 'Afternoon', $now));

        $past = AttendanceSessionNotHeld::create(['section_id' => $f['sectionB']->id, 'date' => today('Africa/Lagos')->subDay(), 'session' => 'Morning', 'marked_by' => $f['admin']->id, 'created_at' => now()]);
        $this->delete(route('attendance.not-held.destroy', $past->id))->assertSessionHasErrors('session');
        $this->assertDatabaseHas('attendance_sessions_not_held', ['id' => $past->id]);
    }

    public function test_portal_summary_is_payment_gated_and_limited_to_thirty_weekday_school_days(): void
    {
        $f = $this->fixture();
        $student = $f['students'][0];
        $this->expectException(AuthorizationException::class);
        app(AttendanceService::class)->portalSummary($student->id, $student->user);
    }

    public function test_portal_summary_uses_only_the_latest_thirty_school_days(): void
    {
        $f = $this->fixture();
        SchoolSetting::create(['result_access_payment_rule' => 'None']);
        $student = $f['students'][0];
        $date = CarbonImmutable::parse('2026-10-16');
        $created = [];
        while (count($created) < 31) {
            if (! $date->isWeekend()) {
                $created[] = $date->toDateString();
                $this->record($student, $f['armA'], $date->toDateString(), 'Morning', 'Present', $f['teacher']);
            }
            $date = $date->subDay();
        }
        $summary = app(AttendanceService::class)->portalSummary($student->id, $student->user);
        $this->assertCount(30, $summary['days']);
        $this->assertSame($created[0], $summary['days']->first()['date']->toDateString());
        $this->assertNotContains($created[30], $summary['days']->pluck('date')->map->toDateString()->all());
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $mark = Permission::firstOrCreate(['name' => 'mark_attendance']);
        $manage = Permission::firstOrCreate(['name' => 'manage_attendance']);
        $alerts = Permission::firstOrCreate(['name' => 'view_attendance_alerts']);
        $all = Permission::firstOrCreate(['name' => 'view_all_sections']);
        $teacher = $this->user('Form Teacher', 'form@example.test');
        $head = $this->user('Head', 'head@example.test');
        $admin = $this->user('Admin', 'admin@example.test');
        $teacherRole = Role::create(['name' => 'Teacher']);
        $headRole = Role::create(['name' => 'Head']);
        $adminRole = Role::create(['name' => 'School Admin']);
        $teacherRole->permissions()->attach($mark);
        $headRole->permissions()->attach([$mark->id, $manage->id, $alerts->id]);
        $adminRole->permissions()->attach([$mark->id, $manage->id, $alerts->id, $all->id]);
        $teacher->roles()->attach($teacherRole);
        $head->roles()->attach($headRole);
        $admin->roles()->attach($adminRole);
        $sectionA = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher']);
        $sectionB = Section::create(['name' => 'Secondary', 'head_title' => 'Principal']);
        $sectionA->heads()->attach($head->id, ['school_id' => $school->id]);
        $levelA = AcademicLevel::create(['section_id' => $sectionA->id, 'name' => 'Primary']);
        $levelB = AcademicLevel::create(['section_id' => $sectionB->id, 'name' => 'Secondary']);
        $classA = SchoolClass::create(['academic_level_id' => $levelA->id, 'name' => 'Primary 1']);
        $classB = SchoolClass::create(['academic_level_id' => $levelB->id, 'name' => 'JSS 1']);
        $armA = ClassArm::create(['class_id' => $classA->id, 'name' => 'A', 'form_teacher_user_id' => $teacher->id]);
        $armB = ClassArm::create(['class_id' => $classB->id, 'name' => 'A']);
        $students = collect(['First Pupil', 'Second Pupil'])->map(function (string $name, int $index) use ($armA): Student {
            $user = $this->user($name, 'pupil'.$index.'@example.test');

            return Student::create(['user_id' => $user->id, 'class_arm_id' => $armA->id, 'admission_date' => '2026-01-01'])->setRelation('user', $user);
        })->all();

        return compact('school', 'teacher', 'head', 'admin', 'sectionA', 'sectionB', 'armA', 'armB', 'students');
    }

    private function user(string $name, string $email): User
    {
        return User::create(['name' => $name, 'email' => $email, 'password' => 'long-password-here', 'status' => 'active']);
    }

    private function tenant(array $fixture): void
    {
        app(TenantContext::class)->set($fixture['school']->id);
    }

    private function record(Student $student, ClassArm $arm, string $date, ?string $session, string $status, User $marker): AttendanceRecord
    {
        return AttendanceRecord::create([
            'student_id' => $student->id, 'class_arm_id' => $arm->id, 'date' => $date,
            'session' => $session, 'status' => $status, 'marked_by' => $marker->id,
        ]);
    }
}
