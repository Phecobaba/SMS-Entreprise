<?php

namespace Tests\Feature\Attendance;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Admissions\Models\Student;
use App\Modules\Attendance\Models\AttendanceAlert;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_class_marking_is_same_day_only_and_an_admin_lock_blocks_direct_edits(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);

        $teacher = User::create(['name' => 'Form Teacher', 'email' => 'form@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $admin = User::create(['name' => 'Administrator', 'email' => 'admin@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $principal = User::create(['name' => 'Principal', 'email' => 'principal-attendance@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $mark = Permission::create(['name' => 'mark_attendance']);
        $manage = Permission::create(['name' => 'manage_attendance']);
        $alerts = Permission::create(['name' => 'view_attendance_alerts']);
        $teacherRole = Role::create(['name' => 'Teacher']);
        $adminRole = Role::create(['name' => 'School Admin']);
        $principalRole = Role::create(['name' => 'Principal']);
        $teacherRole->permissions()->attach($mark);
        $adminRole->permissions()->attach([$mark->id, $manage->id, $alerts->id]);
        $principalRole->permissions()->attach($alerts);
        $teacher->roles()->attach($teacherRole);
        $admin->roles()->attach($adminRole);
        $principal->roles()->attach($principalRole);

        $level = AcademicLevel::create(['name' => 'Primary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'Primary 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A', 'form_teacher_user_id' => $teacher->id]);
        $otherArm = ClassArm::create(['class_id' => $class->id, 'name' => 'B']);
        $firstUser = User::create(['name' => 'First Pupil', 'email' => 'first@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $secondUser = User::create(['name' => 'Second Pupil', 'email' => 'second@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $first = Student::create(['user_id' => $firstUser->id, 'class_arm_id' => $arm->id, 'admission_date' => '2026-01-01']);
        $second = Student::create(['user_id' => $secondUser->id, 'class_arm_id' => $arm->id, 'admission_date' => '2026-01-01']);

        $today = today('Africa/Lagos')->toDateString();
        $statuses = [$first->id => 'Present', $second->id => 'Absent'];
        $this->actingAs($teacher)->get(route('attendance.day', $arm->id))->assertOk()->assertSee('First Pupil')->assertSee('Second Pupil');
        $this->post(route('attendance.mark', $arm->id), ['date' => $today, 'statuses' => [$first->id => 'Present']])
            ->assertSessionHasErrors('attendance');
        $this->post(route('attendance.mark', $otherArm->id), ['date' => $today, 'statuses' => $statuses])->assertSessionHasErrors('class_arm_id');
        $this->post(route('attendance.mark', $arm->id), ['date' => $today, 'statuses' => $statuses])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($school->id);
        $this->assertSame(2, AttendanceRecord::where('class_arm_id', $arm->id)->count());
        $this->assertSame('Absent', AttendanceRecord::where('student_id', $second->id)->firstOrFail()->status);

        $this->actingAs($admin)->post(route('attendance.lock', $arm->id), ['date' => $today])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($school->id);
        $this->assertSame(2, AttendanceRecord::where('class_arm_id', $arm->id)->where('locked', true)->count());
        $this->actingAs($teacher)->post(route('attendance.mark', $arm->id), ['date' => $today, 'statuses' => [$first->id => 'Absent', $second->id => 'Present']])
            ->assertSessionHasErrors('attendance');
        app(TenantContext::class)->set($school->id);
        $this->assertSame('Absent', AttendanceRecord::where('student_id', $second->id)->firstOrFail()->status);

        $this->post(route('attendance.mark', $arm->id), ['date' => today('Africa/Lagos')->subDay()->toDateString(), 'statuses' => $statuses])
            ->assertSessionHasErrors('date');
        app(TenantContext::class)->set($school->id);

        $thirdUser = User::create(['name' => 'Third Pupil', 'email' => 'third@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        Student::create(['user_id' => $thirdUser->id, 'class_arm_id' => $otherArm->id, 'admission_date' => '2026-01-01']);
        $weekday = now('Africa/Lagos')->nextWeekday()->toDateString();
        $this->assertSame(2, app(AttendanceService::class)->generateMissingAlerts($weekday));
        $this->assertSame(1, AttendanceAlert::where('class_arm_id', $otherArm->id)->where('resolved', false)->count());
        $this->actingAs($admin)->get(route('attendance.alerts'))->assertOk()->assertSee('Primary 1');
        $this->actingAs($principal)->get(route('attendance.alerts'))->assertOk()->assertSee('Primary 1');
        $this->get(route('attendance.index'))->assertForbidden();
    }
}
