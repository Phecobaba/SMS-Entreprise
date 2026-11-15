<?php

namespace Tests\Feature\Core;

use App\Modules\Academics\Models\Section;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\DefaultRoleCatalog;
use App\Modules\Core\Services\PasswordRules;
use App\Modules\Core\TenantContext;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Tests\TestCase;

class AccountFoundationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Section $primary;

    private Section $secondary;

    private array $roleIds;

    protected function setUp(): void
    {
        parent::setUp();
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $roles = app(DefaultRoleCatalog::class)->provision();
        $this->roleIds = $roles->map->id->all();
        $this->admin = User::create(['name' => 'Admin Ada', 'username' => 'admin.ada', 'email' => 'admin@oakfield.test', 'password' => 'admin-password', 'status' => 'active']);
        $this->admin->roles()->attach($roles['School Admin']);
        $this->primary = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher']);
        $this->secondary = Section::create(['name' => 'Secondary', 'head_title' => 'Principal']);
    }

    public function test_role_rank_and_section_rules_are_enforced_on_forms_and_forged_requests(): void
    {
        $head = $this->userWithRole('Head Hana', 'head.hana', 'Head');
        $head->headSections()->attach($this->primary, ['school_id' => 1]);
        $createStaff = $this->admin->roles->first()->permissions()->where('name', 'create_staff')->firstOrFail();
        $head->roles->first()->permissions()->attach($createStaff);
        $manageAccess = $this->admin->roles->first()->permissions()->where('name', 'manage_staff_access')->firstOrFail();
        $head->roles->first()->permissions()->attach($manageAccess);
        $teacherTarget = $this->userWithRole('Teacher Target', 'teacher.target', 'Teacher');
        $teacherTarget->sections()->attach($this->primary, ['school_id' => 1]);

        $this->actingAs($head)->get(route('staff.accounts.create'))
            ->assertOk()->assertDontSee('School Admin')
            ->assertDontSee('Head (Principal)')->assertSee('Head is granted later')->assertSee('Teacher');
        $this->post(route('staff.accounts.store'), [
            'name' => 'Forged Admin', 'username' => 'forged.admin',
            'role_id' => $this->roleIds['School Admin'],
        ])->assertSessionHasErrors(['role_id' => "You can't create an account with a role above your own."]);
        $this->put(route('staff.access.update', $teacherTarget), [
            'role_id' => $this->roleIds['School Admin'],
        ])->assertSessionHasErrors(['role_id' => "You can't give a role above your own."]);
        $this->post(route('staff.access.reset', $this->admin))->assertSessionHasErrors('account');
        $this->put(route('staff.access.update', $this->admin), [
            'role_id' => $this->roleIds['Teacher'],
            'section_ids' => [$this->primary->id],
        ])->assertSessionHasErrors('account');

        $this->actingAs($this->admin)->post(route('staff.accounts.store'), [
            'name' => 'No Section Head', 'username' => 'no.section',
            'role_id' => $this->roleIds['Head'],
        ])->assertSessionHasErrors(['role_id' => 'Head is granted by a Head of assignment.']);

        app(TenantContext::class)->set(1);
        $otherSchool = School::create(['name' => 'Other']);
        app(TenantContext::class)->set($otherSchool->id);
        $outside = Section::create(['name' => 'Outside', 'head_title' => 'Head']);
        app(TenantContext::class)->set(1);
        $this->actingAs($this->admin)->post(route('staff.accounts.store'), [
            'name' => 'Outside Head', 'username' => 'outside.head',
            'role_id' => $this->roleIds['Head'],
            'section_ids' => [$outside->id],
        ])->assertSessionHasErrors('role_id');
    }

    public function test_one_time_credentials_force_password_change_and_leave_no_plaintext_trace(): void
    {
        $response = $this->actingAs($this->admin)->post(route('staff.accounts.store'), [
            'name' => 'Teacher Tola', 'username' => 'teacher.tola', 'email' => 'tola@oakfield.test',
            'role_id' => $this->roleIds['Teacher'],
            'section_ids' => [$this->primary->id],
        ])->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        preg_match('/id="temporary-password"[^>]*>([^<]+)</', $response->getContent(), $matches);
        $temporary = html_entity_decode($matches[1] ?? '');
        $this->assertGreaterThanOrEqual(10, strlen($temporary));
        $this->assertStringNotContainsString('wire:snapshot', $response->getContent());
        $response->assertSee('data-copy-credentials', false)
            ->assertSee('data-credential-username', false)
            ->assertSee('data-credential-password', false)
            ->assertSee('aria-live="polite"', false);
        $copyScript = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('Username: ${username}', $copyScript);
        $this->assertStringContainsString('Password: ${password}', $copyScript);
        $this->assertStringContainsString("confirm('Copied')", $copyScript);
        $this->assertStringContainsString("status.textContent = '';", $copyScript);
        app(TenantContext::class)->set(1);
        $teacher = User::where('username', 'teacher.tola')->firstOrFail();
        $this->assertTrue(Hash::check($temporary, $teacher->password));
        $this->assertTrue($teacher->must_change_password);
        $this->assertStringNotContainsString($temporary, json_encode(session()->all()));
        $this->assertDatabaseMissing('audit_logs', ['metadata' => $temporary]);
        $this->assertStringNotContainsString($temporary, DB::table('audit_logs')->get()->toJson());
        $this->assertStringNotContainsString($temporary, $this->storageFileContents());
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff_account_created', 'user_id' => $this->admin->id, 'actor_name' => 'Admin Ada', 'actor_role' => 'School Admin']);
        $this->assertSame(0, DB::table('sessions')->where('payload', 'like', '%'.$temporary.'%')->count());
        $this->assertSame(0, DB::table('jobs')->where('payload', 'like', '%'.$temporary.'%')->count());

        $this->get(route('staff.accounts.create'))->assertOk()->assertDontSee($temporary);
        auth()->logout();
        $this->post(route('login.store'), ['identifier' => 'TEACHER.TOLA', 'password' => $temporary])
            ->assertRedirect(route('password.set.show'));
        app(TenantContext::class)->set(1);
        $this->assertTrue(Hash::check($temporary, $teacher->fresh()->password));
        $this->get(route('dashboard'))->assertRedirect(route('password.set.show'));
        $this->post(route('notifications.read-all'))->assertRedirect(route('password.set.show'));
        $samePassword = $this->post(route('password.set.store'), ['password' => $temporary, 'password_confirmation' => $temporary]);
        $samePassword->assertSessionHasErrors('password');
        $this->post(route('password.set.store'), ['password' => 'chosen-password-88', 'password_confirmation' => 'chosen-password-88'])
            ->assertRedirect(route('dashboard'));
        $this->assertFalse($teacher->fresh()->must_change_password);
    }

    public function test_reset_revokes_sessions_and_access_changes_obey_rank_and_audit_snapshots(): void
    {
        $teacher = $this->userWithRole('Teacher Timi', 'teacher.timi', 'Teacher');
        $teacher->sections()->attach($this->primary, ['school_id' => 1]);
        $teacher->forceFill(['remember_token' => 'remember-me'])->save();
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $teacher->id, 'payload' => 'safe', 'last_activity' => time()]);

        $response = $this->actingAs($this->admin)->post(route('staff.access.reset', $teacher))->assertOk();
        preg_match('/id="temporary-password"[^>]*>([^<]+)</', $response->getContent(), $matches);
        $temporary = html_entity_decode($matches[1] ?? '');
        app(TenantContext::class)->set(1);
        $this->assertFalse(Hash::check('long-password-here', $teacher->fresh()->password));
        $this->assertTrue(Hash::check($temporary, $teacher->fresh()->password));
        $this->assertNull($teacher->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff_password_reset', 'actor_name' => 'Admin Ada']);
        $this->assertStringNotContainsString($temporary, DB::table('audit_logs')->get()->toJson());

        auth()->logout();
        $this->post(route('login.store'), ['identifier' => 'teacher.timi', 'password' => 'long-password-here'])
            ->assertSessionHasErrors('identifier');
        $this->post(route('login.store'), ['identifier' => 'teacher.timi', 'password' => $temporary])
            ->assertRedirect(route('password.set.show'));
        auth()->logout();
        app(TenantContext::class)->set(1);

        $this->actingAs($this->admin)->put(route('staff.access.update', $teacher), [
            'role_id' => $this->roleIds['Bursar'],
        ])->assertRedirect(route('staff.access.show', $teacher));
        app(TenantContext::class)->set(1);
        $this->assertTrue($teacher->fresh()->roles()->where('name', 'Bursar')->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff_access_changed', 'actor_name' => 'Admin Ada']);
        $this->post(route('staff.access.reset', $this->admin))->assertSessionHasErrors('account');
    }

    public function test_username_login_is_case_insensitive_school_wide_and_failure_copy_is_generic(): void
    {
        $this->actingAs($this->admin)->post(route('staff.accounts.store'), [
            'name' => 'Amara Eze', 'username' => 'amara.eze',
            'role_id' => $this->roleIds['Teacher'],
            'section_ids' => [$this->secondary->id],
        ])->assertOk();
        $this->post(route('staff.accounts.store'), [
            'name' => 'Duplicate', 'username' => 'AMARA.EZE',
            'role_id' => $this->roleIds['Bursar'],
        ])->assertSessionHasErrors('username');
        $this->post(route('staff.accounts.store'), [
            'name' => 'Bad Username', 'username' => 'bad@name',
            'role_id' => $this->roleIds['Teacher'],
            'section_ids' => [$this->primary->id],
        ])->assertSessionHasErrors('username');

        auth()->logout();
        $this->post(route('login.store'), ['identifier' => 'AMARA.EZE', 'password' => 'wrong'])
            ->assertSessionHasErrors(['identifier' => 'Check your email or username and your password, and try again.']);
        $this->post(route('login.store'), ['identifier' => 'missing.user', 'password' => 'wrong'])
            ->assertSessionHasErrors(['identifier' => 'Check your email or username and your password, and try again.']);
    }

    public function test_account_password_requires_current_password_and_new_permissions_are_admin_only(): void
    {
        foreach (['Head', 'Bursar', 'Teacher'] as $role) {
            $permissions = app(DefaultRoleCatalog::class)->definitions()[$role];
            $this->assertEmpty(array_intersect(['create_staff', 'manage_staff_access', 'view_sign_in_rules'], $permissions));
        }
        $this->actingAs($this->admin)->post(route('account.password'), [
            'current_password' => 'wrong', 'password' => 'new-password-88', 'password_confirmation' => 'new-password-88',
        ])->assertSessionHasErrors('current_password');
        $this->post(route('account.password'), [
            'current_password' => 'admin-password', 'password' => 'admin.ada', 'password_confirmation' => 'admin.ada',
        ])->assertSessionHasErrors('password');
        $this->post(route('account.password'), [
            'current_password' => 'admin-password', 'password' => 'new-password-88', 'password_confirmation' => 'new-password-88',
        ])->assertRedirect(route('account.show'));
        app(TenantContext::class)->set(1);
        $this->assertTrue(Hash::check('new-password-88', $this->admin->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'password_changed', 'actor_name' => 'Admin Ada']);
        $throttled = false;
        for ($attempt = 0; $attempt < 6; $attempt++) {
            if ($this->post(route('account.password'), [
                'current_password' => 'wrong', 'password' => 'another-password-99', 'password_confirmation' => 'another-password-99',
            ])->status() === 429) {
                $throttled = true;
                break;
            }
        }
        $this->assertTrue($throttled, 'Wrong current-password attempts were not throttled.');
    }

    public function test_shared_password_rules_reject_every_forbidden_case_and_accept_a_valid_eight_character_password(): void
    {
        $rules = app(PasswordRules::class);
        $hash = Hash::make('temporary-pass');
        $cases = [
            'short',
            'admin.ada',
            'Oakfield',
            '1234567890',
            'password1',
            'temporary-pass',
        ];

        foreach ($cases as $password) {
            try {
                $rules->validate($password, 'admin.ada', 'Oakfield', $hash, true);
                $this->fail("Password [{$password}] should have been refused.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('password', $exception->errors());
            }
        }

        $rules->validate('River8Go', 'admin.ada', 'Oakfield', $hash, true);
        $this->assertTrue(true);
    }

    public function test_last_active_admin_cannot_be_demoted_and_access_changes_revoke_sessions(): void
    {
        $secondAdmin = $this->userWithRole('Admin Ben', 'admin.ben', 'School Admin');
        $this->admin->update(['status' => 'inactive']);

        $this->actingAs($this->admin)->put(route('staff.access.update', $secondAdmin), [
            'role_id' => $this->roleIds['Teacher'],
            'section_ids' => [$this->primary->id],
        ])->assertSessionHasErrors([
            'role_id' => 'The last active Admin cannot be demoted. Add or re-enable another Admin first.',
        ]);
        app(TenantContext::class)->set(1);
        $this->assertTrue($secondAdmin->fresh()->roles()->where('name', 'School Admin')->exists());

        $this->admin->update(['status' => 'active']);
        $secondAdmin->forceFill(['remember_token' => 'remember-access'])->save();
        DB::table('sessions')->insert([
            'id' => 'access-session', 'user_id' => $secondAdmin->id,
            'payload' => 'safe', 'last_activity' => time(),
        ]);
        $this->put(route('staff.access.update', $secondAdmin), [
            'role_id' => $this->roleIds['Teacher'],
            'section_ids' => [$this->primary->id],
        ])->assertRedirect(route('staff.access.show', $secondAdmin));
        $this->assertDatabaseMissing('sessions', ['id' => 'access-session']);
        $this->assertNull($secondAdmin->fresh()->remember_token);
    }

    public function test_flagged_user_is_blocked_on_a_real_livewire_update_request(): void
    {
        Route::get('/livewire-password-test', LivewirePasswordTestComponent::class)
            ->middleware(['web', 'auth']);
        $teacher = $this->userWithRole('Teacher Live', 'teacher.live', 'Teacher');
        $this->actingAs($teacher);
        $component = Livewire::test(LivewirePasswordTestComponent::class);
        $snapshot = $component->snapshot;
        $snapshot['memo']['path'] = 'livewire-password-test';
        $snapshot['memo']['method'] = 'GET';

        $teacher->forceFill(['must_change_password' => true])->save();
        auth()->setUser($teacher->fresh());

        $this->withHeaders(['X-Livewire' => 'true'])->postJson(EndpointResolver::updatePath(), [
            'components' => [[
                'calls' => [['path' => '', 'method' => 'refresh', 'params' => []]],
                'updates' => [],
                'snapshot' => json_encode($snapshot),
            ]],
        ])->assertRedirect(route('password.set.show'));
    }

    public function test_all_demo_accounts_still_sign_in_to_their_expected_destination(): void
    {
        $originalEnvironment = app()->environment();
        app()->instance('env', 'local');
        $accounts = [
            'admin' => ['dashboard', 'DEMO_ADMIN_PASSWORD'],
            'teacher' => ['dashboard', 'DEMO_TEACHER_PASSWORD'],
            'principal' => ['dashboard', 'DEMO_PRINCIPAL_PASSWORD'],
            'bursar' => ['dashboard', 'DEMO_BURSAR_PASSWORD'],
            'student' => ['dashboard', 'DEMO_STUDENT_PASSWORD'],
            'parent' => ['dashboard', 'DEMO_PARENT_PASSWORD'],
            'candidate' => ['admissions.mine', 'DEMO_CANDIDATE_PASSWORD'],
        ];

        try {
            $this->seed(DemoDataSeeder::class);
        } finally {
            app()->instance('env', $originalEnvironment);
        }

        foreach ($accounts as $role => [$destination, $passwordKey]) {
            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.'.(array_search($role, array_keys($accounts), true) + 1)])
                ->post(route('login.store'), [
                    'identifier' => 'demo-'.$role.'@oakfield.test',
                    'password' => env($passwordKey),
                ])->assertRedirect(route($destination));
            auth()->logout();
        }
    }

    private function userWithRole(string $name, string $username, string $role): User
    {
        $user = User::create(['name' => $name, 'username' => $username, 'email' => $username.'@oakfield.test', 'password' => 'long-password-here', 'status' => 'active']);
        $user->roles()->attach($this->roleIds[$role]);

        return $user;
    }

    private function storageFileContents(): string
    {
        $contents = '';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(storage_path(), \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getSize() <= 10 * 1024 * 1024) {
                $contents .= (string) file_get_contents($file->getPathname());
            }
        }

        return $contents;
    }
}

class LivewirePasswordTestComponent extends Component
{
    public int $refreshes = 0;

    public function refresh(): void
    {
        $this->refreshes++;
    }

    public function render(): string
    {
        return '<div>Livewire password test</div>';
    }
}
