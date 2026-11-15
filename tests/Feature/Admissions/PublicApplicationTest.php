<?php

namespace Tests\Feature\Admissions;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Mail\ApplicationStatusMail;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\ApplicationPaymentAttempt;
use App\Modules\Admissions\Services\ApplicationWorkflow;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PublicApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_design_hides_placeholders_and_renders_settings_without_leaking_empty_lines(): void
    {
        $f = $this->fixture();
        config()->set('app.url', 'https://oakfield.example');
        $f['settings']->update([
            'contact_phone' => '0800 111 2222', 'contact_email' => 'hello@oakfield.test',
            'address' => '1 School Road', 'whatsapp_number' => '+234 800 111 2222',
            'opening_hours' => 'Monday to Friday, 8am to 4pm',
        ]);

        $home = $this->get(route('home'))->assertOk()
            ->assertSee('https://wa.me/2348001112222', false)
            ->assertSee('0800 111 2222')->assertSee('hello@oakfield.test')
            ->assertSee('https://oakfield.example/images/landing/00_HERO_owner_pick_building_exterior-1200.webp', false)
            ->assertSee('<link rel="canonical" href="https://oakfield.example/">', false)
            ->assertDontSee('Leadership')->assertSee('assets/home-');

        foreach (['[SCHOOL TO SUPPLY]', '[PLACEHOLDER', '[CONFIRM'] as $marker) {
            $home->assertDontSee($marker);
        }

        $this->restore();
        $f['settings']->update(['contact_phone' => null, 'contact_email' => null, 'address' => null, 'whatsapp_number' => null, 'opening_hours' => null]);
        $this->get(route('home'))->assertOk()->assertDontSee('wa.me')->assertDontSee('0800 111 2222')->assertDontSee('hello@oakfield.test');
    }

    public function test_public_apply_variants_fee_steps_closed_state_and_scripts_match_settings(): void
    {
        $f = $this->fixture();
        $this->get(route('applications.create'))->assertOk()->assertSee('There is no application fee.')
            ->assertSee('2 Exam day')->assertDontSee('2 Pay')->assertDontSee('assets/home-');

        $this->restore();
        $f['settings']->update(['application_fee_enabled' => true, 'application_fee_kobo' => 125050]);
        $this->get(route('applications.create'))->assertOk()->assertSee('₦1,250.50')->assertSee('2 Pay');

        $this->restore();
        $f['settings']->update(['admissions_open' => false, 'admissions_reopen_text' => 'Applications reopen in January.']);
        $this->get(route('home'))->assertOk()->assertSee('Admissions closed')->assertDontSee('href="'.route('applications.create').'"', false);
        $this->get(route('applications.create'))->assertOk()->assertSee('Admissions are closed at the moment.')
            ->assertSee('Applications reopen in January.')->assertDontSee('Submit application');
    }

    public function test_status_and_privacy_keep_behaviour_use_shared_shell_and_never_render_design_markers(): void
    {
        $f = $this->fixture();
        $this->restore();
        $f['settings']->update([
            'privacy_contact_email' => 'privacy@oakfield.test',
            'privacy_last_updated_at' => '2026-11-14',
            'non_admitted_retention_text' => 'Non-admitted records are retained for one year.',
        ]);
        $application = $this->application();
        foreach (['Awaiting payment', 'Still confirming', 'Registered', 'Failed', 'Expired'] as $state) {
            $application->update(['status' => $state === 'Still confirming' ? 'Awaiting payment' : $state]);
            if ($state === 'Still confirming') {
                ApplicationPaymentAttempt::create(['application_id' => $application->id, 'active_application_id' => $application->id, 'reference' => 'pending-design', 'amount_kobo' => 50000, 'currency' => 'NGN', 'status' => 'Pending', 'last_checked_at' => now()]);
            }
            $response = $this->withCookie(config('admissions.cookie'), 'one')->get(route('applications.status'));
            $response->assertOk()->assertDontSee('[SCHOOL TO SUPPLY]')->assertDontSee('[PLACEHOLDER')->assertDontSee('[CONFIRM')->assertDontSee('assets/home-');
            $this->restore();
            ApplicationPaymentAttempt::query()->delete();
        }
        $this->get(route('privacy'))->assertOk()->assertSee('privacy@oakfield.test')
            ->assertSee('Non-admitted records are retained for one year.')->assertDontSee('[SCHOOL TO SUPPLY]')->assertDontSee('assets/home-');
        $this->assertFileExists(public_path('robots.txt'));
        $this->assertStringContainsString('Disallow: /admin', file_get_contents(public_path('robots.txt')));
    }

    public function test_public_form_requires_consent_rejects_honeypot_and_closed_admissions(): void
    {
        $f = $this->fixture();
        $this->get(route('applications.create'))->assertOk()->assertSee('Apply to Oakfield');
        $this->post(route('applications.store'), $this->payload(['consent' => null]))->assertSessionHasErrors('consent');
        $this->post(route('applications.store'), $this->payload(['website' => 'spam']))->assertSessionHasErrors('website');
        $this->restore();
        $f['settings']->update(['admissions_open' => false]);
        $this->get(route('applications.create'))->assertOk()->assertSee('Admissions are closed')->assertDontSee('Submit application');
        $this->post(route('applications.store'), $this->payload())->assertSessionHasErrors('application');
    }

    public function test_fee_off_registers_application_without_creating_a_login_and_reserves_username(): void
    {
        $f = $this->fixture();
        Mail::fake();
        $before = User::count();
        $response = $this->post(route('applications.store'), $this->payload());
        $response->assertRedirect(route('applications.status'))->assertCookie(config('admissions.cookie'));
        $this->restore();
        $this->assertSame($before, User::count());
        $this->assertDatabaseCount('candidates', 0);
        $this->assertDatabaseHas('applications', ['username' => 'family.child', 'active_username_key' => 'family.child', 'status' => 'Registered']);
        Mail::assertSent(ApplicationStatusMail::class, 1);
        $this->post(route('applications.store'), $this->payload())->assertSessionHasErrors('username');
    }

    public function test_fee_on_saves_before_redirect_and_double_continue_reuses_one_attempt(): void
    {
        $f = $this->fixture();
        Mail::fake();
        $f['settings']->update(['application_fee_enabled' => true, 'application_fee_kobo' => 125000]);
        config()->set('services.paystack.public_key', 'pk_test_fake');
        config()->set('services.paystack.secret_key', 'sk_test_fake');
        Http::fake(['*/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://pay.example.test/start']])]);
        $response = $this->post(route('applications.store'), $this->payload());
        $response->assertRedirect('https://pay.example.test/start')->assertCookie(config('admissions.cookie'));
        $this->restore();
        $application = Application::firstOrFail();
        $this->assertSame('Awaiting payment', $application->status);
        $this->assertSame(1, $application->attempts()->count());
        Http::fake([
            '*/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'pending']]),
            '*/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://pay.example.test/other']]),
        ]);
        $this->assertSame('https://pay.example.test/start', app(ApplicationWorkflow::class)->continuePayment($application));
        $this->assertSame(1, $application->attempts()->count());
    }

    public function test_old_unpaid_attempt_is_rechecked_released_and_replaced_while_late_success_finalizes_once(): void
    {
        $this->fixture();
        Mail::fake();
        config()->set('services.paystack.public_key', 'pk_test_fake');
        config()->set('services.paystack.secret_key', 'sk_test_fake');
        config()->set('admissions.payment_attempt_reuse_minutes', 30);
        $application = $this->application();
        $old = ApplicationPaymentAttempt::create([
            'application_id' => $application->id, 'active_application_id' => $application->id,
            'reference' => 'old-application-reference', 'amount_kobo' => 50000, 'currency' => 'NGN',
            'authorization_url' => 'https://pay.example.test/old', 'status' => 'Pending',
            'last_activity_at' => now()->subMinutes(31),
        ]);
        $old->forceFill(['created_at' => now()->subMinutes(31)])->save();
        $verificationCalls = 0;
        Http::fake(function (Request $request) use (&$verificationCalls) {
            if (str_contains($request->url(), '/transaction/verify/')) {
                $verificationCalls++;
                $reference = rawurldecode(substr($request->url(), strrpos($request->url(), '/') + 1));

                return $verificationCalls === 1
                    ? Http::response(['status' => true, 'data' => ['status' => 'pending']])
                    : Http::response(['status' => true, 'data' => [
                        'status' => 'success', 'reference' => $reference, 'currency' => 'NGN', 'amount' => 50000,
                    ]]);
            }

            return Http::response(['status' => true, 'data' => ['authorization_url' => 'https://pay.example.test/new']]);
        });

        $this->assertSame('https://pay.example.test/new', app(ApplicationWorkflow::class)->continuePayment($application));
        $old->refresh();
        $new = $application->attempts()->where('id', '!=', $old->id)->firstOrFail();
        $this->assertNull($old->active_application_id);
        $this->assertSame($application->id, $new->active_application_id);

        $this->assertSame('Registered', app(ApplicationWorkflow::class)->verifyReference($old->reference));
        $this->assertSame('Registered', app(ApplicationWorkflow::class)->verifyReference($new->reference));
        $this->assertSame($old->id, $application->fresh()->confirmed_attempt_id);
        $this->assertTrue($application->fresh()->needs_attention);
        $this->assertSame(1, Application::whereKey($application->id)->where('status', 'Registered')->count());
    }

    public function test_verification_is_idempotent_and_rejects_wrong_money(): void
    {
        $f = $this->fixture();
        Mail::fake();
        config()->set('services.paystack.public_key', 'pk_test_fake');
        config()->set('services.paystack.secret_key', 'sk_test_fake');
        $application = $this->application(['fee_amount_kobo' => 50000]);
        $attempt = ApplicationPaymentAttempt::create(['application_id' => $application->id, 'active_application_id' => $application->id, 'reference' => 'app-ref-one', 'amount_kobo' => 50000, 'currency' => 'NGN', 'status' => 'Pending']);
        $checks = 0;
        Http::fake(function () use (&$checks) {
            $checks++;

            return Http::response(['status' => true, 'data' => ['status' => $checks === 1 ? 'pending' : 'success', 'reference' => 'app-ref-one', 'currency' => 'NGN', 'amount' => 50000]]);
        });
        $service = app(ApplicationWorkflow::class);
        $this->withCookie(config('admissions.cookie'), 'one')->get(route('applications.return', ['reference' => 'app-ref-one']))
            ->assertRedirect(route('applications.status'));
        $this->restore();
        $this->assertSame('Awaiting payment', $application->fresh()->status);
        $this->withCookie(config('admissions.cookie'), 'one')->post(route('applications.check'))
            ->assertRedirect(route('applications.status'));
        $this->restore();
        $this->assertSame('Registered', $service->verifyReference('app-ref-one'));
        Mail::assertSent(ApplicationStatusMail::class, 1);
        $this->assertSame('Registered', $application->fresh()->status);

        $bad = $this->application(['username' => 'second.child', 'active_username_key' => 'second.child', 'public_reference' => 'APP-SECOND', 'access_token_hash' => hash('sha256', 'second')]);
        ApplicationPaymentAttempt::create(['application_id' => $bad->id, 'active_application_id' => $bad->id, 'reference' => 'app-ref-bad', 'amount_kobo' => 50000, 'currency' => 'NGN', 'status' => 'Pending']);
        Http::fake(['*/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'reference' => 'app-ref-bad', 'currency' => 'USD', 'amount' => 50000]])]);
        $this->expectException(ValidationException::class);
        $service->verifyReference('app-ref-bad');
    }

    public function test_every_confirmation_entry_point_converges_and_late_success_is_flagged(): void
    {
        $f = $this->fixture();
        Mail::fake();
        config()->set('services.paystack.public_key', 'pk_test_fake');
        config()->set('services.paystack.secret_key', 'sk_test_fake');
        $application = $this->application();
        ApplicationPaymentAttempt::create(['application_id' => $application->id, 'active_application_id' => $application->id, 'reference' => 'all-paths', 'amount_kobo' => 50000, 'currency' => 'NGN', 'status' => 'Pending', 'created_at' => now()->subMinutes(3)]);
        Http::fake(fn (Request $request) => Http::response(['status' => true, 'data' => [
            'status' => 'success', 'reference' => str_ends_with($request->url(), 'late-success') ? 'late-success' : 'all-paths',
            'currency' => 'NGN', 'amount' => 50000,
        ]]));
        $this->get(route('applications.return', ['reference' => 'all-paths']))->assertRedirect();
        $this->restore();
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'all-paths']], JSON_THROW_ON_ERROR);
        $this->call('POST', route('payments.webhook'), [], [], [], ['HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_fake'), 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();
        $this->restore();
        $this->artisan('oakfield:recheck-pending-paystack')->assertSuccessful();
        $this->restore();
        app(ApplicationWorkflow::class)->checkAgain($application->fresh());
        app(ApplicationWorkflow::class)->markPaid($application->fresh(), $f['admin'], 'Already confirmed by the gateway.');
        Mail::assertSent(ApplicationStatusMail::class, 1);

        $late = $this->application(['username' => 'late.child', 'active_username_key' => 'late.child', 'public_reference' => 'APP-LATE', 'access_token_hash' => hash('sha256', 'late')]);
        app(ApplicationWorkflow::class)->markPaid($late, $f['admin'], 'Payment received at the school office.');
        ApplicationPaymentAttempt::create(['application_id' => $late->id, 'active_application_id' => $late->id, 'reference' => 'late-success', 'amount_kobo' => 50000, 'currency' => 'NGN', 'status' => 'Pending']);
        app(ApplicationWorkflow::class)->verifyReference('late-success');
        $this->assertTrue($late->fresh()->needs_attention);
        $this->assertTrue(ApplicationPaymentAttempt::where('reference', 'late-success')->firstOrFail()->late_success);
    }

    public function test_cookie_and_signed_status_disclose_only_first_name_and_status(): void
    {
        $this->fixture();
        $application = $this->application();
        $token = 'opaque-browser-token';
        $application->update(['access_token_hash' => hash('sha256', $token)]);
        $this->withCookie(config('admissions.cookie'), $token)->get(route('applications.status'))
            ->assertOk()->assertSee('Ada')->assertSee('Awaiting payment')
            ->assertDontSee($application->guardian_email)->assertDontSee($application->guardian_phone)->assertDontSee($application->username);
        $url = URL::temporarySignedRoute('applications.status.signed', now()->addHour(), ['application' => $application->public_reference]);
        $this->get($url)->assertOk()->assertSee('Ada')->assertDontSee('Not you?')->assertDontSee($application->guardian_email);
        $this->withCookie(config('admissions.cookie'), 'wrong-token')->get(route('applications.status'))->assertRedirect(route('applications.create'));

        app(TenantContext::class)->set($application->school_id);
        ApplicationPaymentAttempt::create(['application_id' => $application->id, 'active_application_id' => $application->id, 'reference' => 'refresh-window', 'amount_kobo' => 50000, 'currency' => 'NGN', 'status' => 'Pending']);
        $this->withCookie(config('admissions.cookie'), $token)->get(route('applications.status'))
            ->assertSee('http-equiv="refresh"', false)->assertSee('auto_refresh=1', false);
        $this->withCookie(config('admissions.cookie'), $token)->get(route('applications.status', ['auto_refresh' => 22]))
            ->assertDontSee('http-equiv="refresh"', false);
    }

    public function test_expiry_releases_username_but_keeps_unresolved_attempts(): void
    {
        $this->fixture();
        $deletable = $this->application(['expires_at' => now()->subDays(2)]);
        $kept = $this->application(['username' => 'kept.child', 'active_username_key' => 'kept.child', 'public_reference' => 'APP-KEPT', 'access_token_hash' => hash('sha256', 'kept'), 'expires_at' => now()->subDays(2)]);
        ApplicationPaymentAttempt::create(['application_id' => $kept->id, 'active_application_id' => $kept->id, 'reference' => 'pending-kept', 'amount_kobo' => 50000, 'currency' => 'NGN', 'status' => 'Pending', 'last_activity_at' => now()]);
        $this->artisan('oakfield:purge-expired-applications')->assertSuccessful();
        $this->assertModelMissing($deletable);
        $this->assertModelExists($kept);
    }

    public function test_admin_actions_are_permissioned_audited_and_bursar_sees_read_only_fee_rows(): void
    {
        $f = $this->fixture();
        Mail::fake();
        $application = $this->application();
        $this->actingAs($f['other'])->get(route('admissions.applications.index'))->assertForbidden();
        $this->actingAs($f['admin'])->post(route('admissions.applications.mark-paid', $application), ['reason' => 'Cash received at the school office.'])->assertRedirect();
        $this->restore();
        $this->assertDatabaseHas('audit_logs', ['action' => 'application_marked_paid', 'subject_id' => $application->id]);
        ApplicationPaymentAttempt::create(['application_id' => $application->id, 'reference' => 'bursar-row', 'amount_kobo' => 50000, 'currency' => 'NGN', 'status' => 'Verified']);
        $this->actingAs($f['bursar'])->get(route('payments.index'))->assertOk()->assertSee('Application fees')->assertSee('bursar-row');
    }

    public function test_submit_keeps_application_cookie_and_recovery_when_paystack_rejects_or_times_out(): void
    {
        $f = $this->fixture();
        Mail::fake();
        $f['settings']->update(['application_fee_enabled' => true, 'application_fee_kobo' => 125000]);
        config()->set('services.paystack.public_key', 'pk_test_fake');
        config()->set('services.paystack.secret_key', 'sk_test_fake');

        Http::fake(['*/transaction/initialize' => Http::response(['status' => false, 'message' => 'Rejected'], 401)]);
        $rejected = $this->post(route('applications.store'), $this->payload(['username' => 'rejected.child']));
        $rejected->assertRedirect(route('applications.status'))->assertCookie(config('admissions.cookie'));

        Http::fake(fn () => throw new ConnectionException('Connection timed out.'));
        $timeout = $this->post(route('applications.store'), $this->payload(['username' => 'timeout.child']));
        $timeout->assertRedirect(route('applications.status'))->assertCookie(config('admissions.cookie'));
        $this->restore();

        $this->assertDatabaseHas('applications', ['username' => 'rejected.child', 'status' => 'Awaiting payment']);
        $this->assertDatabaseHas('applications', ['username' => 'timeout.child', 'status' => 'Awaiting payment']);
        $this->assertDatabaseCount('application_payment_attempts', 0);
    }

    public function test_mail_failure_after_response_does_not_change_saved_application_status(): void
    {
        $this->fixture();
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp', [
            'transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1,
            'scheme' => null, 'username' => null, 'password' => null, 'timeout' => 0.1,
        ]);
        Mail::forgetMailers();

        $this->post(route('applications.store'), $this->payload())
            ->assertRedirect(route('applications.status'))->assertCookie(config('admissions.cookie'));
        $this->restore();

        $application = Application::firstOrFail();
        $this->assertSame('Registered', $application->status);
        $this->assertNotNull($application->mail_failed_at);
    }

    public function test_same_username_and_email_resumes_but_other_email_paid_and_expired_do_not(): void
    {
        $this->fixture();
        Mail::fake();
        $existing = $this->application();

        $resume = $this->post(route('applications.store'), $this->payload([
            'child_first_name' => 'Ignored',
            'guardian_email' => 'FAMILY@EXAMPLE.TEST',
        ]));
        $resume->assertRedirect(route('applications.status'))
            ->assertSessionHas('status', 'You already started this application. Continue.')
            ->assertCookie(config('admissions.cookie'));
        $this->restore();
        $this->assertDatabaseCount('applications', 1);
        $this->assertSame('Ada', $existing->fresh()->child_first_name);

        $this->post(route('applications.store'), $this->payload(['guardian_email' => 'someone@example.test']))
            ->assertSessionHasErrors('username');

        $this->application([
            'username' => 'paid.child', 'active_username_key' => 'paid.child', 'status' => 'Registered',
            'public_reference' => 'APP-PAID', 'access_token_hash' => hash('sha256', 'paid'),
        ]);
        $this->post(route('applications.store'), $this->payload(['username' => 'paid.child']))->assertSessionHasErrors('username');

        $this->application([
            'username' => 'expired.child', 'active_username_key' => 'expired.child', 'expires_at' => now()->subMinute(),
            'public_reference' => 'APP-EXPIRED', 'access_token_hash' => hash('sha256', 'expired'),
        ]);
        $this->post(route('applications.store'), $this->payload(['username' => 'expired.child']))->assertSessionHasErrors('username');
        $this->restore();
        $this->assertDatabaseCount('applications', 3);
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $settings = SchoolSetting::create(['admissions_open' => true, 'application_fee_enabled' => false, 'application_fee_kobo' => 0]);
        $section = Section::create(['name' => 'Primary', 'position' => 1, 'head_title' => 'Head Teacher']);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Primary 1', 'position' => 1]);
        foreach (['manage_admissions', 'manage_payments'] as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }
        $adminRole = Role::firstOrCreate(['name' => 'School Admin']);
        $adminRole->permissions()->syncWithoutDetaching([Permission::where('name', 'manage_admissions')->value('id')]);
        $bursarRole = Role::firstOrCreate(['name' => 'Bursar']);
        $bursarRole->permissions()->syncWithoutDetaching([Permission::where('name', 'manage_payments')->value('id')]);
        $admin = User::create(['name' => 'Admin', 'username' => 'admin.one', 'password' => 'password']);
        $admin->roles()->attach($adminRole);
        $bursar = User::create(['name' => 'Bursar', 'username' => 'bursar.one', 'password' => 'password']);
        $bursar->roles()->attach($bursarRole);
        $other = User::create(['name' => 'Other', 'username' => 'other.one', 'password' => 'password']);

        return compact('school', 'settings', 'section', 'level', 'admin', 'bursar', 'other');
    }

    private function payload(array $overrides = []): array
    {
        $this->restore();

        return array_merge(['website' => '', 'child_first_name' => 'Ada', 'child_surname' => 'Okafor', 'child_other_names' => '', 'date_of_birth' => '2017-05-10', 'gender' => 'Female', 'applied_level_id' => AcademicLevel::first()->id, 'previous_school' => '', 'guardian_name' => 'Parent Okafor', 'guardian_relationship' => 'Mother', 'guardian_phone' => '08000000000', 'guardian_email' => 'family@example.test', 'guardian_address' => 'Lagos', 'username' => 'family.child', 'consent' => '1'], $overrides);
    }

    private function restore(): void
    {
        app(TenantContext::class)->set((int) config('tenancy.default_school_id'));
    }

    private function application(array $overrides = []): Application
    {
        $this->restore();

        return Application::create(array_merge(['public_reference' => 'APP-ONE', 'child_first_name' => 'Ada', 'child_surname' => 'Okafor', 'date_of_birth' => '2017-05-10', 'gender' => 'Female', 'applied_level_id' => AcademicLevel::first()->id, 'guardian_name' => 'Parent Okafor', 'guardian_relationship' => 'Mother', 'guardian_phone' => '08000000000', 'guardian_email' => 'family@example.test', 'guardian_address' => 'Lagos', 'username' => 'family.child', 'active_username_key' => 'family.child', 'status' => 'Awaiting payment', 'fee_amount_kobo' => 50000, 'currency' => 'NGN', 'access_token_hash' => hash('sha256', 'one'), 'consented_at' => now(), 'consent_version' => 'test', 'expires_at' => now()->addDays(7)], $overrides));
    }
}
