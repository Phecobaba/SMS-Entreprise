<?php

namespace Tests\Feature\Admissions;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Models\RegistrationNumberSequence;
use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Services\RegistrationNumbers;
use App\Modules\Admissions\Services\StudentAccounts;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Models\ParentStudent;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\DefaultRoleCatalog;
use App\Modules\Core\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StudentRegistrationNumbersTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private User $head;

    private Section $primary;

    private Section $secondary;

    private AcademicLevel $level;

    private ClassArm $arm;

    private ClassArm $otherArm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->school = School::create(['name' => 'Oakfield International Schools']);
        config()->set('tenancy.default_school_id', $this->school->id);
        app(TenantContext::class)->set($this->school->id);
        $roles = app(DefaultRoleCatalog::class)->provision();
        $this->admin = $this->user('Admin', 'admin');
        $this->admin->roles()->attach($roles['School Admin'], ['school_id' => $this->school->id]);
        $this->head = $this->user('Primary Head', 'head');
        $this->head->roles()->attach($roles['Principal'], ['school_id' => $this->school->id]);
        $this->primary = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher', 'registration_prefix' => 'PRI']);
        $this->secondary = Section::create(['name' => 'Secondary', 'head_title' => 'Principal', 'registration_prefix' => 'SEC']);
        $this->primary->heads()->attach($this->head, ['school_id' => $this->school->id]);
        $this->level = AcademicLevel::create(['section_id' => $this->primary->id, 'name' => 'Primary 1', 'registration_prefix' => 'P1']);
        $otherLevel = AcademicLevel::create(['section_id' => $this->secondary->id, 'name' => 'JSS 1']);
        $class = SchoolClass::create(['academic_level_id' => $this->level->id, 'name' => 'Primary One']);
        $otherClass = SchoolClass::create(['academic_level_id' => $otherLevel->id, 'name' => 'Junior One']);
        $this->arm = ClassArm::create(['class_id' => $class->id, 'name' => 'Gold']);
        $this->otherArm = ClassArm::create(['class_id' => $otherClass->id, 'name' => 'Blue']);
    }

    public function test_two_allocation_saves_never_share_a_number_and_prefixes_resolve_level_section_school(): void
    {
        $numbers = app(RegistrationNumbers::class);
        $first = $this->student('First Pupil', $this->arm);
        $second = $this->student('Second Pupil', $this->arm);
        $numbers->assignAutomatic($first, $this->admin);
        $numbers->assignAutomatic($second, $this->admin);
        $this->assertSame('P1-0001', $first->refresh()->registration_number);
        $this->assertSame('P1-0002', $second->refresh()->registration_number);
        $this->assertNotSame($first->registration_number, $second->registration_number);
        $this->assertStringNotContainsString('PRIMARY', $first->registration_number);
        $this->assertStringNotContainsString('GOLD', $first->registration_number);
        try {
            $second->update(['registration_number' => $first->registration_number]);
            $this->fail('The database allowed two pupils to share one registration number.');
        } catch (QueryException) {
            $this->assertSame('P1-0002', $second->refresh()->registration_number);
        }

        $this->level->update(['registration_prefix' => null]);
        $third = $this->student('Third Pupil', $this->arm);
        $numbers->assignAutomatic($third, $this->admin);
        $this->assertSame('PRI-0003', $third->refresh()->registration_number);
        $this->primary->update(['registration_prefix' => null]);
        $numbers->settings()->update(['registration_prefix' => 'SCH']);
        $fourth = $this->student('Fourth Pupil', $this->arm);
        $numbers->assignAutomatic($fourth, $this->admin);
        $this->assertSame('SCH-0004', $fourth->refresh()->registration_number);
    }

    public function test_number_length_is_minimum_padding_and_the_ten_thousandth_number_stays_unique(): void
    {
        $numbers = app(RegistrationNumbers::class);
        $numbers->settings()->update(['registration_number_length' => 4]);
        RegistrationNumberSequence::create(['sequence_key' => 'school', 'next_number' => 10000]);

        $tenThousandth = $this->student('Ten Thousand', $this->arm);
        $next = $this->student('Ten Thousand One', $this->arm);
        $numbers->assignAutomatic($tenThousandth, $this->admin);
        $numbers->assignAutomatic($next, $this->admin);

        $this->assertSame('P1-10000', $tenThousandth->refresh()->registration_number);
        $this->assertSame('P1-10001', $next->refresh()->registration_number);
        $this->assertNotSame($tenThousandth->registration_number, $next->registration_number);
    }

    public function test_prefixes_are_validated_and_typed_numbers_need_the_number_management_permission(): void
    {
        $numbers = app(RegistrationNumbers::class);
        try {
            $numbers->saveSettings([
                'registration_prefix' => 'BAD PREFIX!', 'registration_number_length' => 4,
                'registration_include_year' => false,
                'registration_move_policy' => RegistrationNumbers::KEEP_DIGITS_CHANGE_PREFIX,
                'section_prefixes' => [], 'level_prefixes' => [], 'next_numbers' => [],
            ], $this->admin);
            $this->fail('An invalid school prefix was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('registration_prefix', $exception->errors());
        }

        $clerk = $this->user('Admissions Clerk', 'admissions.clerk');
        $role = Role::create(['name' => 'Admissions Clerk']);
        $role->permissions()->attach(Permission::where('name', 'create_students')->firstOrFail());
        $clerk->roles()->attach($role, ['school_id' => $this->school->id]);
        $beforeStudents = Student::count();
        try {
            app(StudentAccounts::class)->create([
                'name' => 'Typed Pupil', 'class_arm_id' => $this->arm->id,
                'registration_mode' => 'typed', 'registration_number' => 'PRI-9000',
                'username' => 'typed.pupil', 'email' => null, 'parent_mode' => 'none',
            ], $clerk);
            $this->fail('Typed registration-number entry did not require its separate permission.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame($beforeStudents, Student::count());
    }

    public function test_student_identity_edit_is_audited_and_can_set_a_missing_username(): void
    {
        $student = $this->student('Unnamed Pupil', $this->arm);
        $student->user->update(['username' => null]);

        app(StudentAccounts::class)->update($student, [
            'name' => 'Named Pupil', 'username' => 'named.pupil', 'email' => 'Named.Pupil@example.test',
        ], $this->admin);

        $this->assertSame('Named Pupil', $student->user->refresh()->name);
        $this->assertSame('named.pupil', $student->user->username);
        $this->assertSame('named.pupil@example.test', $student->user->email);
        $audit = AuditLog::where('action', 'student_identity_changed')->where('subject_id', $student->id)->firstOrFail();
        $this->assertSame('named.pupil', data_get($audit->metadata, 'after.username'));
        $this->assertNull(data_get($audit->metadata, 'before.username'));
    }

    public function test_duplicates_and_history_are_reserved_format_changes_do_not_rewrite_and_settings_lock(): void
    {
        $numbers = app(RegistrationNumbers::class);
        $first = $this->student('First Pupil', $this->arm);
        $second = $this->student('Second Pupil', $this->arm);
        $numbers->change($first, 'CUSTOM-001', $this->admin);
        try {
            $numbers->change($second, 'custom-001', $this->admin);
            $this->fail('A duplicate current number was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('registration_number', $exception->errors());
        }
        $numbers->change($first, 'CUSTOM-002', $this->admin);
        try {
            $numbers->change($second, 'CUSTOM-001', $this->admin);
            $this->fail('A historical number was reused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('registration_number', $exception->errors());
        }

        $issued = $first->refresh()->registration_number;
        $automatic = $this->student('Automatic Pupil', $this->arm);
        $numbers->assignAutomatic($automatic, $this->admin);
        try {
            $numbers->saveSettings([
                'registration_prefix' => 'P1', 'registration_number_length' => 4,
                'registration_include_year' => false,
                'registration_move_policy' => RegistrationNumbers::KEEP_DIGITS_CHANGE_PREFIX,
                'section_prefixes' => [], 'level_prefixes' => [], 'next_numbers' => ['school' => 1],
            ], $this->admin);
            $this->fail('The next number moved below an issued sequence value.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('next_numbers', $exception->errors());
        }
        $numbers->saveSettings([
            'registration_prefix' => 'NEW', 'registration_number_length' => 6,
            'registration_include_year' => true,
            'registration_move_policy' => RegistrationNumbers::KEEP_DIGITS_CHANGE_PREFIX,
            'section_prefixes' => [], 'level_prefixes' => [], 'next_numbers' => [],
        ], $this->admin);
        $this->assertSame($issued, $first->refresh()->registration_number);
        $this->assertNotNull($numbers->settings()->registration_move_policy_locked_at);
        $this->expectException(ValidationException::class);
        $numbers->saveSettings([
            'registration_prefix' => 'NEW', 'registration_number_length' => 6,
            'registration_include_year' => true,
            'registration_move_policy' => RegistrationNumbers::NEW_PREFIX_SEQUENCE,
        ], $this->admin);
    }

    public function test_number_changes_are_section_scoped_awaiting_is_admin_only_and_every_change_is_logged(): void
    {
        $numbers = app(RegistrationNumbers::class);
        $own = $this->student('Own Pupil', $this->arm);
        $outside = $this->student('Outside Pupil', $this->otherArm);
        $numbers->change($own, 'PRI-101', $this->head);
        $this->assertDatabaseHas('student_registration_number_history', [
            'student_id' => $own->id, 'new_number' => 'PRI-101', 'changed_by' => $this->head->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student_registration_number_changed', 'subject_id' => $own->id, 'user_id' => $this->head->id,
        ]);
        try {
            $numbers->change($outside, 'SEC-101', $this->head);
            $this->fail('The Head changed another Section.');
        } catch (AuthorizationException) {
            $this->assertNull($outside->refresh()->registration_number);
        }
        $own->update(['status' => Student::STATUS_AWAITING]);
        try {
            $numbers->change($own, 'PRI-102', $this->head);
            $this->fail('The Head changed an Awaiting-admission number.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $numbers->change($own, 'PRI-102', $this->admin);
        $this->assertSame('PRI-102', $own->refresh()->registration_number);
    }

    public function test_old_number_search_finds_the_student_and_head_cannot_forge_the_change_route(): void
    {
        $student = $this->student('Searchable Pupil', $this->arm);
        app(RegistrationNumbers::class)->change($student, 'OLD-100', $this->admin);
        app(RegistrationNumbers::class)->change($student, 'NEW-100', $this->admin);
        $this->actingAs($this->admin)->get(route('students.index', ['search' => 'OLD-100']))
            ->assertOk()->assertSee('Searchable Pupil');
        app(TenantContext::class)->set($this->school->id);
        $outside = $this->student('Outside Pupil', $this->otherArm);
        $this->actingAs($this->head)->post(route('students.registration-number.update', $outside), [
            'registration_number' => 'SEC-900',
        ])->assertForbidden();
    }

    public function test_student_and_parent_creation_is_atomic_existing_parent_is_reused_and_resets_keep_links(): void
    {
        $accounts = app(StudentAccounts::class);
        $created = $accounts->create([
            'name' => 'New Student', 'class_arm_id' => $this->arm->id,
            'registration_mode' => 'automatic', 'username' => 'new.student', 'email' => null,
            'parent_mode' => 'new', 'parent_name' => 'New Parent', 'parent_username' => 'new.parent', 'parent_email' => null,
        ], $this->admin);
        $this->assertTrue($created['student_user']->must_change_password);
        $this->assertTrue($created['parent_user']->must_change_password);
        $credentialSlip = view('students.credentials', $created)->render();
        $this->assertStringContainsString('Registration number', $credentialSlip);
        $this->assertStringContainsString($created['student']->registration_number, $credentialSlip);
        $this->actingAs($created['student_user'])->get(route('dashboard'))
            ->assertRedirect(route('password.set.show'));
        $this->actingAs($created['parent_user'])->get(route('dashboard'))
            ->assertRedirect(route('password.set.show'));
        app(TenantContext::class)->set($this->school->id);
        $parent = ParentProfile::where('user_id', $created['parent_user']->id)->firstOrFail();
        $this->assertDatabaseHas('parent_student', ['parent_id' => $parent->id, 'student_id' => $created['student']->id]);
        $userCount = User::count();
        $parentCount = ParentProfile::count();
        $linked = $accounts->create([
            'name' => 'Sibling', 'class_arm_id' => $this->arm->id,
            'registration_mode' => 'automatic', 'username' => 'new.sibling', 'email' => null,
            'parent_mode' => 'existing', 'parent_id' => $parent->id,
        ], $this->admin);
        $this->assertNull($linked['parent_password']);
        $this->assertSame($parentCount, ParentProfile::count());
        $this->assertSame($userCount + 1, User::count());

        DB::table('sessions')->insert(['id' => 'parent-session', 'user_id' => $parent->user_id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'test', 'payload' => 'test', 'last_activity' => time()]);
        $reset = $accounts->resetParent($parent->load('user'), $this->admin);
        $this->assertTrue($reset['user']->must_change_password);
        $this->actingAs($reset['user'])->get(route('dashboard'))
            ->assertRedirect(route('password.set.show'));
        app(TenantContext::class)->set($this->school->id);
        $this->assertDatabaseMissing('sessions', ['id' => 'parent-session']);
        $this->assertSame(2, ParentStudent::where('parent_id', $parent->id)->count());
        $this->assertStringNotContainsString($reset['temporary_password'], (string) AuditLog::where('action', 'parent_password_reset')->value('metadata'));
        $studentReset = $accounts->resetStudent($created['student']->load('user'), $this->admin);
        $this->assertTrue($studentReset['user']->must_change_password);
        $this->actingAs($studentReset['user'])->get(route('dashboard'))
            ->assertRedirect(route('password.set.show'));
        app(TenantContext::class)->set($this->school->id);
        $secrets = [$created['student_password'], $created['parent_password'], $reset['temporary_password'], $studentReset['temporary_password']];
        $stored = User::pluck('password')->join(' ').AuditLog::pluck('metadata')->join(' ')
            .(Schema::hasTable('sessions') ? DB::table('sessions')->pluck('payload')->join(' ') : '')
            .(Schema::hasTable('jobs') ? DB::table('jobs')->pluck('payload')->join(' ') : '');
        $log = is_file(storage_path('logs/laravel.log')) ? file_get_contents(storage_path('logs/laravel.log')) : '';
        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $stored);
            $this->assertStringNotContainsString($secret, $log);
        }

        $beforeStudents = Student::count();
        $beforeUsers = User::count();
        try {
            $accounts->create([
                'name' => 'Rolled Back', 'class_arm_id' => $this->arm->id,
                'registration_mode' => 'automatic', 'username' => 'rolled.back', 'email' => null,
                'parent_mode' => 'existing', 'parent_id' => 999999,
            ], $this->admin);
            $this->fail('The invalid parent did not roll the transaction back.');
        } catch (ModelNotFoundException) {
            $this->assertSame($beforeStudents, Student::count());
            $this->assertSame($beforeUsers, User::count());
        }
    }

    public function test_new_permissions_are_admin_only_except_section_scoped_number_change(): void
    {
        $adminRole = Role::where('name', 'School Admin')->firstOrFail();
        $headRole = Role::where('name', 'Principal')->firstOrFail();
        foreach (['view_students', 'create_students', 'manage_student_accounts', 'manage_registration_numbers', 'change_registration_numbers'] as $permission) {
            $this->assertTrue($adminRole->permissions()->where('name', $permission)->exists(), $permission);
        }
        $this->assertTrue($headRole->permissions()->where('name', 'change_registration_numbers')->exists());
        $this->assertFalse($headRole->permissions()->whereIn('name', ['view_students', 'create_students', 'manage_student_accounts', 'manage_registration_numbers'])->exists());
    }

    private function user(string $name, string $slug): User
    {
        return User::create(['name' => $name, 'username' => $slug, 'email' => $slug.'@example.test', 'password' => 'ValidPass88', 'status' => 'active']);
    }

    private function student(string $name, ClassArm $arm): Student
    {
        $slug = strtolower(str_replace(' ', '.', $name)).'.'.random_int(10, 9999);
        $user = $this->user($name, $slug);
        $role = Role::where('name', 'Student')->firstOrFail();
        $user->roles()->attach($role, ['school_id' => $this->school->id]);

        return Student::create(['user_id' => $user->id, 'class_arm_id' => $arm->id, 'status' => Student::STATUS_ACTIVE, 'admission_date' => today()]);
    }
}
