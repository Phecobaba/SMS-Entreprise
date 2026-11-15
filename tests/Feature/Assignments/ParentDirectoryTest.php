<?php

namespace Tests\Feature\Assignments;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Models\Student;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Models\ParentStudent;
use App\Modules\Assignments\Services\ParentAccountDirectory;
use App\Modules\Assignments\Services\ParentDirectory;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\DefaultRoleCatalog;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ParentDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_head_and_bursar_access_is_permission_and_section_scoped(): void
    {
        $f = $this->fixture();

        $this->actingAs($f['admin'])->get(route('parents.index'))->assertOk()
            ->assertSee('Primary Parent')->assertSee('Secondary Parent');
        $this->get(route('parents.index', ['search' => 'Primary Child']))->assertOk()
            ->assertSee('Primary Parent')->assertDontSee('Secondary Parent');

        app(TenantContext::class)->set($f['school']->id);
        $f['head']->roles()->firstOrFail()->permissions()->syncWithoutDetaching([
            Permission::where('name', 'manage_payments')->firstOrFail()->id => ['school_id' => $f['school']->id],
        ]);
        $this->actingAs($f['head'])->get(route('parents.index'))->assertOk()
            ->assertSee('Primary Parent')->assertDontSee('Secondary Parent');
        $this->get(route('parents.show', $f['secondaryParent']))->assertNotFound();
        $this->get(route('parents.show', $f['primaryParent']))->assertOk()->assertSee('Primary Child')
            ->assertDontSee('Reset password')->assertDontSee('Unlink child');

        $this->actingAs($f['bursar'])->get(route('parents.index'))->assertOk()
            ->assertSee('Primary Parent')->assertSee('Secondary Parent');
        $this->get(route('parents.show', $f['primaryParent']))->assertOk()
            ->assertDontSee('Reset password')->assertDontSee('Unlink child');

        foreach (['teacher', 'studentUser', 'parentUser'] as $key) {
            $this->actingAs($f[$key])->get(route('parents.index'))->assertForbidden();
        }
    }

    public function test_admin_reset_email_link_and_unlink_are_audited_without_secrets(): void
    {
        $f = $this->fixture();
        app(TenantContext::class)->set($f['school']->id);
        DB::table('sessions')->insert([
            'id' => 'parent-session', 'user_id' => $f['parentUser']->id, 'ip_address' => null,
            'user_agent' => null, 'payload' => 'x', 'last_activity' => now()->timestamp,
        ]);
        $oldPassword = $f['parentUser']->password;

        $response = $this->actingAs($f['admin'])->post(route('parents.reset', $f['primaryParent']))
            ->assertOk()->assertHeader('Cache-Control');
        app(TenantContext::class)->set($f['school']->id);
        $parentUser = $f['parentUser']->fresh();
        $this->assertNotSame($oldPassword, $parentUser->password);
        $this->assertTrue($parentUser->must_change_password);
        $this->assertDatabaseMissing('sessions', ['user_id' => $parentUser->id]);
        $audit = DB::table('audit_logs')->where('action', 'parent_password_reset')->first();
        $this->assertNotNull($audit);
        $this->assertNull($audit->metadata);
        $this->assertStringContainsString('This is the only time the temporary password is shown.', $response->getContent());

        $this->put(route('parents.email.update', $f['primaryParent']), ['email' => ' Parent.New@Example.test '])
            ->assertRedirect(route('parents.show', $f['primaryParent']));
        app(TenantContext::class)->set($f['school']->id);
        $this->assertSame('parent.new@example.test', $parentUser->fresh()->email);
        $this->assertNotNull($parentUser->fresh()->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'parent_email_changed', 'actor_name' => $f['admin']->name]);

        $this->delete(route('parents.children.destroy', [$f['primaryParent'], $f['primaryStudent']]))->assertRedirect();
        app(TenantContext::class)->set($f['school']->id);
        $this->assertFalse(app(ParentAccountDirectory::class)->isWard($f['primaryStudent']->id, $parentUser->id));
        $this->assertDatabaseHas('students', ['id' => $f['primaryStudent']->id]);
        $this->assertDatabaseHas('users', ['id' => $parentUser->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'parent_child_unlinked', 'actor_name' => $f['admin']->name]);

        $this->post(route('parents.children.store', $f['primaryParent']), ['student_id' => $f['primaryStudent']->id])->assertRedirect();
        app(TenantContext::class)->set($f['school']->id);
        $this->assertTrue(app(ParentAccountDirectory::class)->isWard($f['primaryStudent']->id, $parentUser->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'parent_child_linked', 'actor_name' => $f['admin']->name]);
    }

    public function test_email_is_unique_and_fee_statuses_use_bulk_balance_logic(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->put(route('parents.email.update', $f['primaryParent']), [
            'email' => $f['secondaryParent']->user->email,
        ])->assertSessionHasErrors('email');

        $this->get(route('parents.index'))->assertOk()->assertSee('Owing');
        $this->get(route('parents.show', $f['primaryParent']))->assertOk()
            ->assertSee('Owing')->assertSee('1,000.00 outstanding');

        app(TenantContext::class)->set($f['school']->id);
        $invoice = Invoice::where('student_id', $f['primaryStudent']->id)->firstOrFail();
        Payment::create([
            'invoice_id' => $invoice->id, 'amount_kobo' => 40000, 'method' => 'Bank',
            'paid_at' => now(), 'receipt_number' => 'PARENT-001', 'confirmed_by' => $f['admin']->id,
        ]);
        $this->get(route('parents.show', $f['primaryParent']))->assertOk()
            ->assertSee('Part-paid')->assertSee('600.00 outstanding');
    }

    public function test_parent_list_query_count_does_not_grow_with_parent_count(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);
        $directory = app(ParentDirectory::class);
        $one = $this->queryCount(fn () => $directory->index($f['admin']));
        app(TenantContext::class)->set($f['school']->id);
        for ($index = 0; $index < 8; $index++) {
            $user = $this->user('Extra Parent '.$index, 'Parent');
            $parent = ParentProfile::create(['user_id' => $user->id]);
            ParentStudent::create(['parent_id' => $parent->id, 'student_id' => $f['primaryStudent']->id]);
        }
        $many = $this->queryCount(fn () => $directory->index($f['admin']));

        $this->assertLessThanOrEqual($one + 2, $many);
    }

    public function test_signed_out_recovery_copy_and_response_do_not_reveal_eligibility(): void
    {
        $f = $this->fixture();
        Mail::fake();
        config()->set('mail.default', 'array');
        config()->set('account_recovery.ip_attempts', 50);

        $this->get(route('password.request'))->assertOk()
            ->assertDontSee('Admin accounts only')->assertDontSee('eligible for email recovery')
            ->assertDontSee('ask the school office');
        $existing = $this->post(route('password.email'), ['identifier' => $f['admin']->email]);
        $missing = $this->post(route('password.email'), ['identifier' => 'nobody@example.test']);
        $this->assertSame($existing->getStatusCode(), $missing->getStatusCode());
        $this->assertSame($existing->headers->get('Location'), $missing->headers->get('Location'));
        $this->get(route('password.request.confirmation'))->assertOk()
            ->assertSee('If an account matches, a reset link has been sent.')
            ->assertDontSee('ask the school office');
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Directory School']);
        app(TenantContext::class)->set($school->id);
        app(DefaultRoleCatalog::class)->provision();
        $admin = $this->user('Directory Admin', 'School Admin');
        $head = $this->user('Primary Head', 'Principal');
        $bursar = $this->user('School Bursar', 'Bursar');
        $teacher = $this->user('Class Teacher', 'Teacher');

        [$primarySection, $primaryArm] = $this->arm('Primary School', 'Pupil', 'Primary 4', 'Primary 4', 'Gold');
        [, $secondaryArm] = $this->arm('Secondary School', 'Student', 'JS 1', 'JS 1', 'A');
        $primarySection->heads()->attach($head, ['school_id' => $school->id]);
        $studentUser = $this->user('Primary Child', 'Student');
        $primaryStudent = Student::create(['user_id' => $studentUser->id, 'class_arm_id' => $primaryArm->id, 'status' => Student::STATUS_ACTIVE, 'admission_date' => today()]);
        $secondaryStudentUser = $this->user('Secondary Child', 'Student');
        $secondaryStudent = Student::create(['user_id' => $secondaryStudentUser->id, 'class_arm_id' => $secondaryArm->id, 'status' => Student::STATUS_ACTIVE, 'admission_date' => today()]);
        $parentUser = $this->user('Primary Parent', 'Parent', 'primary.parent@example.test');
        $primaryParent = ParentProfile::create(['user_id' => $parentUser->id]);
        ParentStudent::create(['parent_id' => $primaryParent->id, 'student_id' => $primaryStudent->id]);
        $secondaryParentUser = $this->user('Secondary Parent', 'Parent', 'secondary.parent@example.test');
        $secondaryParent = ParentProfile::create(['user_id' => $secondaryParentUser->id]);
        ParentStudent::create(['parent_id' => $secondaryParent->id, 'student_id' => $secondaryStudent->id]);
        Invoice::create([
            'student_id' => $primaryStudent->id, 'purpose' => 'Tuition', 'kind' => Invoice::KIND_TERM_FEE,
            'creation_source' => 'test', 'amount_kobo' => 100000, 'due_date' => today(), 'status' => 'Unpaid', 'created_by' => $admin->id,
        ]);

        return compact('school', 'admin', 'head', 'bursar', 'teacher', 'studentUser', 'parentUser', 'primaryStudent', 'secondaryStudent', 'primaryParent', 'secondaryParent');
    }

    private function user(string $name, string $role, ?string $email = null): User
    {
        $user = User::create([
            'name' => $name, 'username' => str($name)->slug('.'), 'email' => $email ?? str($name)->slug('.').'@example.test',
            'email_verified_at' => now(), 'password' => Hash::make('long-password-here'), 'status' => User::STATUS_ACTIVE,
        ]);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());

        return $user;
    }

    private function arm(string $sectionName, string $term, string $levelName, string $className, string $armName): array
    {
        $section = Section::create(['name' => $sectionName, 'head_title' => 'Principal', 'learner_term' => $term]);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => $levelName]);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => $className]);

        return [$section, ClassArm::create(['class_id' => $class->id, 'name' => $armName])];
    }

    private function queryCount(callable $callback): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });
        $callback();

        return $count;
    }
}
