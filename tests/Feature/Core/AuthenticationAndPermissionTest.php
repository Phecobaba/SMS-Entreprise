<?php

namespace Tests\Feature\Core;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeacherSubjectAssignment;
use App\Modules\Academics\Services\ResultTeachingAssignments;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AppNavigation;
use App\Modules\Core\Services\DashboardOverview;
use App\Modules\Core\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthenticationAndPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_in_removes_extra_account_help_and_keeps_failures_non_enumerating(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        User::create(['name' => 'Known User', 'email' => 'known@example.test', 'password' => 'correct-password']);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Students and parents: use the username on your slip.')
            ->assertDontSee('Forgot your username or have no email?');

        $known = $this->from(route('login'))->post(route('login.store'), [
            'identifier' => 'known@example.test', 'password' => 'wrong-password',
        ]);
        $knownMessage = $known->getSession()->get('errors')->first('identifier');
        $unknown = $this->from(route('login'))->post(route('login.store'), [
            'identifier' => 'unknown@example.test', 'password' => 'wrong-password',
        ]);
        $this->assertSame($knownMessage, $unknown->getSession()->get('errors')->first('identifier'));
    }

    public function test_admin_navigation_has_attendance_and_alerts_while_teacher_keeps_attendance(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);

        $permissions = collect([
            'view_dashboard', 'view_all_sections', 'mark_attendance', 'view_attendance_alerts', 'enter_results',
            'manage_academics', 'manage_result_settings', 'manage_promotions',
            'manage_section_classes', 'manage_section_levels', 'manage_section_subjects',
            'manage_section_teacher_assignments', 'manage_staff_records', 'view_section_staff', 'approve_results',
            'manage_payments', 'manage_fee_schedule', 'manage_payment_gateway',
        ])->mapWithKeys(fn (string $name) => [$name => Permission::firstOrCreate(['name' => $name])]);
        $adminRole = Role::create(['name' => 'School Admin']);
        $adminRole->permissions()->attach($permissions->only([
            'view_all_sections', 'mark_attendance', 'view_attendance_alerts',
            'manage_academics', 'manage_result_settings', 'manage_promotions', 'manage_payments',
        ])->pluck('id'));
        $teacherRole = Role::create(['name' => 'Teacher']);
        $teacherRole->permissions()->attach($permissions->only([
            'view_dashboard', 'mark_attendance', 'enter_results',
        ])->pluck('id'));
        $headRole = Role::create(['name' => 'Head']);
        $headRole->permissions()->attach($permissions->only([
            'approve_results', 'manage_promotions', 'manage_section_classes', 'manage_section_levels',
            'manage_section_subjects', 'manage_section_teacher_assignments', 'view_section_staff',
        ])->pluck('id'));

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password']);
        $teacher = User::create(['name' => 'Teacher', 'email' => 'teacher@example.test', 'password' => 'password']);
        $head = User::create(['name' => 'Head', 'email' => 'head@example.test', 'password' => 'password']);
        $admin->roles()->attach($adminRole);
        $teacher->roles()->attach($teacherRole);
        $head->roles()->attach($headRole);
        $section = Section::create(['name' => 'Secondary', 'head_title' => 'Principal']);
        $section->heads()->attach($head, ['school_id' => $school->id]);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Junior Secondary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JS 1']);
        ClassArm::create(['class_id' => $class->id, 'name' => 'Gold', 'form_teacher_user_id' => $teacher->id]);

        $teacherWithoutArm = User::create([
            'name' => 'Teacher Without Arm', 'email' => 'teacher-no-arm@example.test', 'password' => 'password',
        ]);
        $teacherWithoutArm->roles()->attach($teacherRole);

        $navigation = app(AppNavigation::class);
        $adminItems = collect($navigation->items($admin));
        $teacherItems = collect($navigation->items($teacher));
        $teacherWithoutArmItems = collect($navigation->items($teacherWithoutArm));
        $headItems = collect($navigation->items($head));

        $this->assertSame(route('attendance.index'), $adminItems->firstWhere('label', 'Attendance')['url']);
        $this->assertSame(route('attendance.alerts'), $adminItems->firstWhere('label', 'Alerts')['url']);
        $this->assertSame(route('attendance.index'), $teacherItems->firstWhere('label', 'Attendance')['url']);
        $this->assertNull($teacherWithoutArmItems->firstWhere('label', 'Attendance'));
        $this->actingAs($teacherWithoutArm)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Mark attendance');
        $paymentChildren = collect($adminItems->firstWhere('label', 'Payments')['children']);
        $this->assertSame(['Record payment', 'Awaiting approval', 'Receipts'], $paymentChildren->pluck('label')->all());
        $this->assertFalse($paymentChildren->pluck('label')->contains('Fee schedule'));
        $this->assertFalse($paymentChildren->pluck('label')->contains('Payment gateway'));
        foreach (['Academics', 'Bulk subjects', 'Teacher assignments', 'Promotion'] as $label) {
            $this->assertSame('Admin', $adminItems->firstWhere('label', $label)['group']);
        }
        $this->assertSame(
            ['Classes', 'Levels', 'Subjects', 'Bulk subjects', 'Teacher assignments', 'Promotion', 'Staff', 'Results'],
            $headItems->where('group', 'Head')->pluck('label')->values()->all(),
        );
    }

    public function test_login_is_scoped_to_the_school_and_dashboard_requires_a_permission(): void
    {
        $firstSchool = School::create(['name' => 'First School']);
        $secondSchool = School::create(['name' => 'Second School']);
        config()->set('tenancy.default_school_id', $firstSchool->id);
        $context = app(TenantContext::class);

        $context->set($firstSchool->id);
        $user = User::create([
            'name' => 'First User',
            'email' => 'shared@example.test',
            'password' => 'first-password',
        ]);

        $context->set($secondSchool->id);
        User::create([
            'name' => 'Second User',
            'email' => 'shared@example.test',
            'password' => 'second-password',
        ]);
        $context->clear();

        $this->post('/login', ['email' => 'shared@example.test', 'password' => 'second-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'shared@example.test', 'password' => 'first-password'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertForbidden();

        $context->set($firstSchool->id);
        $role = Role::create(['name' => 'School Admin']);
        $permission = Permission::create(['name' => 'view_dashboard']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        $this->assertSame(1, DB::table('role_permissions')->count());
        $this->assertSame(1, DB::table('user_roles')->count());
        $this->assertSame($firstSchool->id, DB::table('role_permissions')->value('school_id'));
        $this->assertSame($firstSchool->id, DB::table('user_roles')->value('school_id'));
        $this->assertTrue($user->roles()->exists());
        $this->assertTrue($role->permissions()->exists());
        $this->assertTrue($user->hasPermission('view_dashboard'));
        $context->clear();

        $this->get('/dashboard')->assertOk()->assertSee('Good')->assertSee('First User')->assertSee('Welcome to your Oakfield workspace');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_multi_role_navigation_and_dashboard_blocks_are_composed_without_a_role_switch(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $permissions = collect(['view_dashboard', 'enter_results', 'approve_results', 'view_all_sections', 'manage_section_classes'])
            ->mapWithKeys(fn (string $name) => [$name => Permission::firstOrCreate(['name' => $name])]);
        $teacherRole = Role::create(['name' => 'Teacher']);
        $teacherRole->permissions()->attach($permissions->only(['view_dashboard', 'enter_results'])->pluck('id'));
        $headRole = Role::create(['name' => 'Head']);
        $headRole->permissions()->attach($permissions->only(['view_dashboard', 'approve_results', 'manage_section_classes'])->pluck('id'));
        $adminRole = Role::create(['name' => 'School Admin']);
        $adminRole->permissions()->attach($permissions->only(['view_dashboard', 'approve_results', 'view_all_sections'])->pluck('id'));
        $section = Section::create(['name' => 'Secondary', 'head_title' => 'Principal']);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Junior Secondary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JS 1']);
        $subject = Subject::create(['section_id' => $section->id, 'name' => 'Mathematics']);
        ClassSubject::create(['class_id' => $class->id, 'subject_id' => $subject->id]);

        $teacher = User::create(['name' => 'Teacher only', 'email' => 'teacher-only@example.test', 'password' => 'password', 'status' => 'active']);
        $teacherHead = User::create(['name' => 'Teacher Head', 'email' => 'teacher-head@example.test', 'password' => 'password', 'status' => 'active']);
        $adminTeacher = User::create(['name' => 'Admin Teacher', 'email' => 'admin-teacher@example.test', 'password' => 'password', 'status' => 'active']);
        $allThree = User::create(['name' => 'All three', 'email' => 'all-three@example.test', 'password' => 'password', 'status' => 'active']);
        $teacher->roles()->attach($teacherRole);
        $teacherHead->roles()->attach([$teacherRole->id, $headRole->id]);
        $adminTeacher->roles()->attach([$teacherRole->id, $adminRole->id]);
        $allThree->roles()->attach([$teacherRole->id, $headRole->id, $adminRole->id]);
        $section->heads()->attach([$teacherHead->id, $allThree->id], ['school_id' => $school->id]);
        foreach ([$teacher, $teacherHead, $adminTeacher, $allThree] as $user) {
            TeacherSubjectAssignment::create(['user_id' => $user->id, 'class_id' => $class->id, 'subject_id' => $subject->id]);
        }

        $navigation = app(AppNavigation::class);
        $this->assertSame(['teaching'], $navigation->staffGroups($teacher));
        $this->assertSame(['teaching', 'head'], $navigation->staffGroups($teacherHead));
        $this->assertSame(['teaching', 'admin'], $navigation->staffGroups($adminTeacher));
        $this->assertSame(['teaching', 'head', 'admin'], $navigation->staffGroups($allThree));
        $this->assertSame(['Teaching'], collect($navigation->items($teacher))->pluck('group')->filter()->unique()->values()->all());
        $this->assertSame(['Teaching', 'Head'], collect($navigation->items($teacherHead))->pluck('group')->filter()->unique()->values()->all());
        $this->assertSame(['Teaching', 'Admin'], collect($navigation->items($adminTeacher))->pluck('group')->filter()->unique()->values()->all());

        $overview = app(DashboardOverview::class)->for($teacherHead, sectionId: $section->id, section: $section, headSection: $section);
        $this->assertSame('staff_composed', $overview['kind']);
        $this->assertSame(['teaching', 'head'], array_keys($overview['blocks']));

        $otherSection = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher']);
        $otherLevel = AcademicLevel::create(['section_id' => $otherSection->id, 'name' => 'Primary 4']);
        $otherClass = SchoolClass::create(['academic_level_id' => $otherLevel->id, 'name' => 'Primary 4']);
        $otherSubject = Subject::create(['section_id' => $otherSection->id, 'name' => 'English']);
        ClassSubject::create(['class_id' => $otherClass->id, 'subject_id' => $otherSubject->id]);
        TeacherSubjectAssignment::create(['user_id' => $teacherHead->id, 'class_id' => $otherClass->id, 'subject_id' => $otherSubject->id]);

        app(ResultTeachingAssignments::class)->assertMayEnterClass($teacherHead, $otherClass->id, $otherSubject->id);
        $this->actingAs($teacherHead)->get(route('academics.classes.index', ['section' => $otherSection->id]))->assertForbidden();
    }
}
