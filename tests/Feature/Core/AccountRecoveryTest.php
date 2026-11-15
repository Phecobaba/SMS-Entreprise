<?php

namespace Tests\Feature\Core;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Models\Student;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Models\ParentStudent;
use App\Modules\Core\Mail\AdminPasswordChangedMail;
use App\Modules\Core\Mail\ConfirmAccountEmailMail;
use App\Modules\Core\Mail\PasswordChangedMail;
use App\Modules\Core\Mail\PasswordResetLinkMail;
use App\Modules\Core\Models\AccountPasswordResetToken;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AccountRecovery;
use App\Modules\Core\Services\DefaultRoleCatalog;
use App\Modules\Core\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Tests\TestCase;

class AccountRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_response_is_generic_and_every_active_email_backed_role_receives_mail(): void
    {
        $f = $this->fixture();
        Mail::fake();
        config()->set('mail.default', 'array');
        $lines = [];
        Log::listen(function (MessageLogged $event) use (&$lines): void {
            if (str_starts_with($event->message, 'Password reset request outcome=')) {
                $lines[] = $event->message;
            }
        });
        config()->set('account_recovery.ip_attempts', 50);

        $baseline = null;
        $cases = collect(['admin', 'teacher', 'principal', 'bursar', 'student', 'parent', 'candidate'])
            ->mapWithKeys(fn (string $key): array => [$f[$key]->email => 'queued_for_send'])
            ->merge([
                'missing@example.test' => 'no_matching_account',
                $f['emailLess']->username => 'account_has_no_email',
                $f['disabled']->email => 'not_eligible',
            ]);
        foreach ($cases as $identifier => $outcome) {
            $before = count($lines);
            $response = $this->post(route('password.email'), ['identifier' => $identifier])
                ->assertRedirect(route('password.request.confirmation'))
                ->assertSessionHas('recovery_message', AccountRecovery::GENERIC_MESSAGE)
                ->assertSessionMissing('retry_after');
            $signature = [
                $response->getStatusCode(),
                $response->headers->get('Location'),
                session('recovery_message'),
                session()->has('retry_after'),
            ];
            $baseline ??= $signature;
            $this->assertSame($baseline, $signature);
            $this->assertCount($before + 1, $lines);
            $this->assertSame('Password reset request outcome='.$outcome.'.', $lines[$before]);
        }

        Mail::assertSent(PasswordResetLinkMail::class, function (PasswordResetLinkMail $mail): bool {
            $html = $mail->render();

            return str_contains($html, '60 minutes') && str_contains($html, '/reset-password/');
        });
        Mail::assertSent(PasswordResetLinkMail::class, 7);
        $this->assertDatabaseCount('account_password_reset_tokens', 7);
        $this->assertDatabaseCount('jobs', 0);
        $logged = implode("\n", $lines);
        foreach ([$f['admin']->email, $f['admin']->username, 'missing@example.test', $f['emailLess']->username, $f['teacher']->email, $f['teacher']->username, $f['student']->email, $f['parent']->email, '/reset-password/'] as $secret) {
            $this->assertStringNotContainsString($secret, $logged);
        }
    }

    public function test_guest_request_resolves_the_school_and_deferred_array_delivery_runs_after_the_response(): void
    {
        $fixture = $this->fixture();
        config()->set('mail.default', 'array');
        app('mail.manager')->forgetMailers();
        Log::spy();

        $this->post(route('password.email'), ['identifier' => $fixture['admin']->email])
            ->assertRedirect(route('password.request.confirmation'))
            ->assertSessionHas('recovery_message', AccountRecovery::GENERIC_MESSAGE);

        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $this->assertCount(1, $transport->messages());
        $this->assertDatabaseHas('account_password_reset_tokens', ['user_id' => $fixture['admin']->id]);
        $this->assertDatabaseCount('jobs', 0);
        Log::shouldHaveReceived('info')->once()->with('Password reset request outcome=sent.');
    }

    public function test_recovery_pages_are_private_and_never_send_a_referrer(): void
    {
        $f = $this->fixture();
        Mail::fake();
        config()->set('mail.default', 'array');

        foreach ([
            $this->get(route('password.request')),
            $this->get(route('password.request.confirmation')),
            $this->get(route('password.reset', $this->requestToken($f['admin']->email))),
            $this->get(route('password.reset.success')),
        ] as $response) {
            $response->assertHeader('Referrer-Policy', 'no-referrer');
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        }
    }

    public function test_failed_deferred_send_keeps_generic_response_and_logs_only_scrubbed_transport_detail(): void
    {
        $f = $this->fixture();
        config()->set('mail.default', 'array');
        Log::spy();
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException(
            'SMTP failed for https://school.test/reset-password/leaked-token username=admin-recovery password=plain-secret to admin-recovery@example.test',
        ));

        $this->post(route('password.email'), ['identifier' => $f['admin']->email])
            ->assertRedirect(route('password.request.confirmation'))
            ->assertSessionHas('recovery_message', AccountRecovery::GENERIC_MESSAGE);

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $line): bool {
            $this->assertStringStartsWith('Password reset request outcome=send_failed.', $line);
            $this->assertStringContainsString(RuntimeException::class, $line);
            $this->assertStringContainsString('SMTP failed', $line);
            foreach (['leaked-token', 'admin-recovery', 'plain-secret', 'admin-recovery@example.test', 'https://'] as $secret) {
                $this->assertStringNotContainsString($secret, $line);
            }

            return true;
        });
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_token_is_hashed_single_use_expires_and_a_new_request_invalidates_the_old_one(): void
    {
        $f = $this->fixture();
        Mail::fake();
        config()->set('mail.default', 'array');

        $old = $this->requestToken($f['admin']->email);
        app(TenantContext::class)->set($f['school']->id);
        $this->assertDatabaseMissing('account_password_reset_tokens', ['token_hash' => $old]);
        $this->assertDatabaseHas('account_password_reset_tokens', ['token_hash' => hash('sha256', $old), 'used_at' => null]);
        $stored = AccountPasswordResetToken::where('token_hash', hash('sha256', $old))->firstOrFail();
        $this->assertTrue($stored->expires_at->between(now()->addMinutes(59), now()->addMinutes(60)->addSecond()));
        $this->requestToken($f['admin']->username);
        $this->get(route('password.reset', $old))->assertOk()->assertSee('expired or has already been used');

        $token = $this->latestRawToken();
        app(TenantContext::class)->set($f['school']->id);
        DB::table('sessions')->insert([
            'id' => 'admin-session', 'user_id' => $f['admin']->id, 'ip_address' => null,
            'user_agent' => null, 'payload' => 'x', 'last_activity' => now()->timestamp,
        ]);
        $f['admin']->forceFill(['remember_token' => 'remember-me', 'must_change_password' => true])->save();
        $newPassword = 'Fresh-River-8492';
        $this->post(route('password.update', $token), [
            'password' => $newPassword, 'password_confirmation' => $newPassword,
        ])->assertRedirect(route('password.reset.success'));

        app(TenantContext::class)->set($f['school']->id);
        $this->assertTrue(Hash::check($newPassword, $f['admin']->fresh()->password));
        $this->assertFalse($f['admin']->fresh()->must_change_password);
        $this->assertNull($f['admin']->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['user_id' => $f['admin']->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'password_self_service_reset', 'user_id' => $f['admin']->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $f['otherAdmin']->id, 'title' => 'Admin password changed']);
        $this->post(route('password.update', $token), [
            'password' => 'Another-River-8492', 'password_confirmation' => 'Another-River-8492',
        ])->assertSessionHasErrors('token');

        app(TenantContext::class)->set($f['school']->id);
        $expired = AccountPasswordResetToken::create([
            'user_id' => $f['admin']->id,
            'token_hash' => hash('sha256', 'expired-token'),
            'expires_at' => now()->subSecond(),
        ]);
        $this->assertFalse($expired->usable());
        $this->get(route('password.reset', 'expired-token'))->assertSee('expired or has already been used');
    }

    public function test_reset_uses_shared_password_rules_and_follow_up_emails_contain_no_token_or_password(): void
    {
        $f = $this->fixture();
        Mail::fake();
        config()->set('mail.default', 'array');
        $token = $this->requestToken($f['admin']->email);

        $this->post(route('password.update', $token), [
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');
        app(TenantContext::class)->set($f['school']->id);
        $this->assertNull(AccountPasswordResetToken::where('token_hash', hash('sha256', $token))->firstOrFail()->used_at);

        $password = 'Secure-Cloud-9381';
        $this->post(route('password.update', $token), [
            'password' => $password, 'password_confirmation' => $password,
        ])->assertRedirect(route('password.reset.success'));

        app(TenantContext::class)->set($f['school']->id);
        Mail::assertSent(PasswordChangedMail::class, function (PasswordChangedMail $mail) use ($token, $password): bool {
            $html = $mail->render();

            return ! str_contains($html, $token) && ! str_contains($html, $password);
        });
        Mail::assertSent(AdminPasswordChangedMail::class, function (AdminPasswordChangedMail $mail) use ($token, $password): bool {
            $html = $mail->render();

            return ! str_contains($html, $token) && ! str_contains($html, $password);
        });
        $this->assertDatabaseMissing('audit_logs', ['metadata' => $token]);
        $this->assertDatabaseMissing('notifications', ['body' => $token]);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertStringNotContainsString($token, serialize(session()->all()));
        $this->assertFalse(DB::table('audit_logs')->get()->contains(
            fn (object $row): bool => str_contains(json_encode($row, JSON_THROW_ON_ERROR), $token),
        ));
        $this->assertFalse(DB::table('notifications')->get()->contains(
            fn (object $row): bool => str_contains(json_encode($row, JSON_THROW_ON_ERROR), $token),
        ));
        $this->assertFalse(DB::table('jobs')->get()->contains(
            fn (object $row): bool => str_contains((string) $row->payload, $token),
        ));
    }

    public function test_per_identifier_and_ip_throttling_keep_the_generic_state(): void
    {
        $f = $this->fixture();
        Mail::fake();
        config()->set('mail.default', 'array');
        config()->set('account_recovery.identifier_attempts', 1);
        Log::spy();

        $first = $this->post(route('password.email'), ['identifier' => $f['admin']->email]);
        $response = $this->post(route('password.email'), ['identifier' => $f['admin']->email]);

        $response->assertRedirect(route('password.request.confirmation'))
            ->assertSessionHas('recovery_message', AccountRecovery::GENERIC_MESSAGE)
            ->assertSessionMissing('retry_after');
        $this->assertSame($first->getStatusCode(), $response->getStatusCode());
        $this->assertSame($first->headers->get('Location'), $response->headers->get('Location'));
        Log::shouldHaveReceived('info')->with('Password reset request outcome=queued_for_send.')->once();
        Log::shouldHaveReceived('info')->with('Password reset request outcome=throttled.')->once();

        config()->set('account_recovery.identifier_attempts', 100);
        config()->set('account_recovery.ip_attempts', 1);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.19']);
        $ipFirst = $this->post(route('password.email'), ['identifier' => 'first-missing@example.test']);
        $ipThrottled = $this->post(route('password.email'), ['identifier' => 'second-missing@example.test']);
        $ipThrottled->assertRedirect(route('password.request.confirmation'))
            ->assertSessionHas('recovery_message', AccountRecovery::GENERIC_MESSAGE)
            ->assertSessionMissing('retry_after');
        $this->assertSame($ipFirst->getStatusCode(), $ipThrottled->getStatusCode());
        $this->assertSame($ipFirst->headers->get('Location'), $ipThrottled->headers->get('Location'));
        Log::shouldHaveReceived('info')->with('Password reset request outcome=no_matching_account.')->once();
        Log::shouldHaveReceived('info')->with('Password reset request outcome=throttled.')->twice();
    }

    public function test_log_mailer_never_receives_the_reset_link(): void
    {
        $f = $this->fixture();
        Mail::fake();
        Log::spy();
        config()->set('mail.default', 'log');

        $this->post(route('password.email'), ['identifier' => $f['admin']->email])->assertRedirect();

        Mail::assertNothingSent();
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Password reset request outcome=send_failed. ['.RuntimeException::class.']: The configured mailer cannot deliver password reset email.');
        app(TenantContext::class)->set($f['school']->id);
        $this->assertSame(64, strlen(AccountPasswordResetToken::firstOrFail()->token_hash));
    }

    public function test_student_and_parent_account_pages_are_scoped_and_change_own_password(): void
    {
        $f = $this->fixture(withPortal: true);

        $this->actingAs($f['studentUser'])->get(route('account.show'))
            ->assertOk()->assertSee('Primary 1 Gold')->assertSee($f['studentUser']->username);
        $this->actingAs($f['parentUser'])->get(route('account.show'))
            ->assertOk()->assertSee($f['studentUser']->name)->assertDontSee('Other School Child');
        $this->post(route('account.password'), [
            'current_password' => 'wrong-password',
            'password' => 'Student-New-8492',
            'password_confirmation' => 'Student-New-8492',
        ])->assertSessionHasErrors('current_password');
        $this->post(route('account.password'), [
            'current_password' => 'long-password-here',
            'password' => 'Parent-New-8492',
            'password_confirmation' => 'Parent-New-8492',
        ])->assertRedirect(route('account.show'));
        app(TenantContext::class)->set($f['school']->id);
        $this->assertTrue(Hash::check('Parent-New-8492', $f['parentUser']->fresh()->password));
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $throttled = $this->post(route('account.password'), [
                'current_password' => 'wrong-password',
                'password' => 'Another-Parent-8492',
                'password_confirmation' => 'Another-Parent-8492',
            ]);
        }
        $throttled->assertTooManyRequests();
    }

    public function test_break_glass_resets_only_admin_prints_once_flags_and_audits(): void
    {
        $f = $this->fixture();
        $this->artisan('oakfield:reset-admin-password', ['identifier' => $f['teacher']->email])
            ->expectsOutput('The account is not an Admin account for this school.')
            ->assertFailed();

        $before = $f['admin']->password;
        $this->artisan('oakfield:reset-admin-password', ['identifier' => $f['admin']->username])
            ->expectsOutputToContain('Temporary password (shown once): ')
            ->expectsOutput('The Admin must choose a new password at the next sign-in.')
            ->assertSuccessful();
        $admin = User::withoutGlobalScopes()->findOrFail($f['admin']->id);
        $this->assertNotSame($before, $admin->password);
        $this->assertTrue($admin->must_change_password);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin_password_break_glass_reset',
            'actor_name' => 'Console break-glass',
            'actor_role' => 'Console',
            'subject_id' => $admin->id,
        ]);
    }

    public function test_mail_test_refuses_log_transport_and_never_prints_credentials(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp', [
            'transport' => 'log',
            'host' => 'smtp.example.test',
            'port' => 587,
            'scheme' => 'tls',
            'username' => 'private-user',
            'password' => 'private-password',
        ]);

        $this->artisan('oakfield:mail-test', ['address' => 'deliver@example.test'])
            ->expectsOutput('Mailer: smtp')
            ->expectsOutput('Host: smtp.example.test')
            ->expectsOutput('Port: 587')
            ->expectsOutput('Scheme: tls')
            ->expectsOutput('Mail test refused: the configured mailer is missing, invalid, or includes the log transport. Configure a real delivery mailer first.')
            ->doesntExpectOutputToContain('private-user')
            ->doesntExpectOutputToContain('private-password')
            ->assertFailed();
    }

    public function test_parent_email_change_requires_password_and_confirmation_before_recovery(): void
    {
        $f = $this->fixture(true);
        Mail::fake();

        $this->actingAs($f['parentUser'])->post(route('account.email.update'), [
            'email' => 'parent-new@example.test', 'current_password' => 'wrong-password',
        ])->assertSessionHasErrors('current_password');
        $this->assertNull($f['parentUser']->fresh()->email);

        $this->post(route('account.email.update'), [
            'email' => ' Parent-New@Example.test ', 'current_password' => 'long-password-here',
        ])->assertRedirect(route('account.show'));
        $parent = $f['parentUser']->fresh();
        $this->assertSame('parent-new@example.test', $parent->email);
        $this->assertNull($parent->email_verified_at);
        $url = '';
        Mail::assertSent(ConfirmAccountEmailMail::class, function (ConfirmAccountEmailMail $mail) use (&$url): bool {
            $url = $mail->confirmationUrl;

            return true;
        });

        Mail::fake();
        auth()->logout();
        $this->post(route('password.email'), ['identifier' => $parent->email])
            ->assertSessionHas('recovery_message', AccountRecovery::GENERIC_MESSAGE);
        Mail::assertNotSent(PasswordResetLinkMail::class);
        $this->assertDatabaseMissing('account_password_reset_tokens', ['user_id' => $parent->id]);

        $this->actingAs($f['admin'])->get($url)->assertForbidden();
        $expiredUrl = URL::temporarySignedRoute('account.email.confirm', now()->subMinute(), [
            'user' => $parent->id, 'email' => hash('sha256', $parent->email),
        ]);
        $this->actingAs($parent)->get($expiredUrl)->assertForbidden();
        $this->actingAs($parent)->get($url)->assertRedirect(route('account.show'));
        $this->assertNotNull($parent->fresh()->email_verified_at);
        $this->get($url)->assertStatus(410);

        Mail::fake();
        auth()->logout();
        $this->post(route('password.email'), ['identifier' => $parent->email]);
        Mail::assertSent(PasswordResetLinkMail::class);
    }

    public function test_existing_email_backfill_marks_addresses_confirmed(): void
    {
        $f = $this->fixture();
        $f['teacher']->forceFill(['email_verified_at' => null])->save();
        $migration = require base_path('app/Modules/Core/Database/Migrations/2026_11_09_000200_confirm_existing_user_emails.php');

        $migration->up();

        $this->assertNotNull($f['teacher']->fresh()->email_verified_at);
        $this->assertNull($f['emailLess']->fresh()->email_verified_at);
    }

    private function fixture(bool $withPortal = false): array
    {
        $school = School::create(['name' => 'Oakfield International Schools']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        SchoolSetting::create([]);
        $roles = app(DefaultRoleCatalog::class)->provision();
        $roles['Candidate'] = Role::firstOrCreate(['name' => 'Candidate']);

        $users = [];
        foreach ([
            'admin' => ['Recovery Admin', 'admin-recovery@example.test', 'admin-recovery', 'School Admin'],
            'otherAdmin' => ['Other Admin', 'other-admin@example.test', 'other-admin', 'School Admin'],
            'teacher' => ['Recovery Teacher', 'teacher-recovery@example.test', 'teacher-recovery', 'Teacher'],
            'principal' => ['Recovery Head', 'principal-recovery@example.test', 'principal-recovery', 'Head'],
            'bursar' => ['Recovery Bursar', 'bursar-recovery@example.test', 'bursar-recovery', 'Bursar'],
            'student' => ['Recovery Student', 'student-recovery@example.test', 'student-recovery', 'Student'],
            'parent' => ['Recovery Parent', 'parent-recovery@example.test', 'parent-recovery', 'Parent'],
            'candidate' => ['Recovery Candidate', 'candidate-recovery@example.test', 'candidate-recovery', 'Candidate'],
            'emailLess' => ['No Email Admin', null, 'no-email-admin', 'School Admin'],
            'disabled' => ['Disabled Teacher', 'disabled-recovery@example.test', 'disabled-recovery', 'Teacher'],
        ] as $key => [$name, $email, $username, $role]) {
            $user = User::create(compact('name', 'email', 'username') + [
                'email_verified_at' => $email ? now() : null,
                'password' => 'long-password-here',
                'status' => $key === 'disabled' ? 'disabled' : 'active',
            ]);
            $user->roles()->attach($roles[$role]);
            $users[$key] = $user;
        }

        if ($withPortal) {
            $section = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher']);
            $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Primary 1']);
            $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'Primary 1']);
            $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'Gold']);
            $studentUser = User::create(['name' => 'Linked Child', 'username' => 'linked-child', 'password' => 'long-password-here']);
            $studentUser->roles()->attach($roles['Student']);
            $student = Student::create(['user_id' => $studentUser->id, 'class_arm_id' => $arm->id, 'status' => Student::STATUS_ACTIVE, 'admission_date' => today()]);
            $parentUser = User::create(['name' => 'Linked Parent', 'username' => 'linked-parent', 'password' => 'long-password-here']);
            $parentUser->roles()->attach($roles['Parent']);
            $parent = ParentProfile::create(['user_id' => $parentUser->id]);
            ParentStudent::create(['parent_id' => $parent->id, 'student_id' => $student->id]);
            $unlinkedUser = User::create(['name' => 'Other School Child', 'username' => 'unlinked-child', 'password' => 'long-password-here']);
            $unlinkedUser->roles()->attach($roles['Student']);
            $users += compact('studentUser', 'student', 'parentUser');
        }

        return ['school' => $school] + $users;
    }

    private function requestToken(string $identifier): string
    {
        Mail::fake();
        $this->post(route('password.email'), compact('identifier'))->assertRedirect();

        return $this->latestRawToken();
    }

    private function latestRawToken(): string
    {
        $token = null;
        Mail::assertSent(PasswordResetLinkMail::class, function (PasswordResetLinkMail $mail) use (&$token): bool {
            $token = basename(parse_url($mail->resetUrl, PHP_URL_PATH));

            return true;
        });

        return (string) $token;
    }
}
