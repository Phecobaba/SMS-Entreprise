<?php

namespace Tests\Feature\Payments;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Admissions\Models\Student;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Models\ParentStudent;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\ManualPaymentSubmission;
use App\Modules\Payments\Services\ManualPaymentService;
use App\Modules\Payments\Services\PaymentLedger;
use App\Modules\Payments\Services\PaystackCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PaymentGatewaySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_secret_is_write_only_password_is_required_admins_are_notified_and_bursar_is_locked(): void
    {
        $f = $this->fixture();
        $secret = 'sk_test_secretvalue1234';
        Log::spy();

        $this->actingAs($f['admin'])->from(route('payments.gateway.show'))->post(route('payments.gateway.store'), [
            'public_key' => 'pk_test_publicvalue1234',
            'secret_key' => $secret,
            'current_password' => 'wrong-password',
        ])->assertSessionHasErrors('current_password')->assertDontSee($secret);
        $this->assertArrayNotHasKey('secret_key', session('_old_input', []));
        $this->assertArrayNotHasKey('current_password', session('_old_input', []));

        $this->actingAs($f['admin'])->post(route('payments.gateway.store'), [
            'public_key' => 'pk_test_publicvalue1234',
            'secret_key' => $secret,
            'current_password' => 'correct-password',
        ])->assertRedirect(route('payments.gateway.show'));

        $response = $this->actingAs($f['admin'])->get(route('payments.gateway.show'));
        $response->assertOk()->assertDontSee($secret)->assertSee('sk_test_••••1234', false)
            ->assertSee('Last changed')->assertSee($f['admin']->name);
        $this->restoreTenant($f);
        $this->assertNotSame($secret, DB::table('school_settings')->value('paystack_secret_key'));
        $this->assertStringNotContainsString($secret, SchoolSetting::firstOrFail()->toJson());
        $this->assertStringNotContainsString($secret, AuditLog::where('action', 'payment_gateway.changed')->firstOrFail()->toJson());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $f['otherAdmin']->id,
            'title' => 'Payment gateway keys changed',
        ]);
        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $level) {
            Log::shouldNotHaveReceived($level);
        }

        $this->actingAs($f['bursar'])->get(route('payments.gateway.show'))
            ->assertOk()->assertSee('Only an Admin can change the payment gateway')
            ->assertDontSee('pk_test_publicvalue1234')->assertDontSee('1234');
        $this->actingAs($f['bursar'])->post(route('payments.gateway.store'), [
            'public_key' => 'pk_live_publicvalue5678',
            'secret_key' => 'sk_live_secretvalue5678',
            'current_password' => 'correct-password',
        ])->assertForbidden();
    }

    public function test_saved_secret_verifies_webhook_signature_and_mismatched_modes_are_refused(): void
    {
        $f = $this->fixture();
        $credentials = app(PaystackCredentials::class);
        $credentials->save('pk_test_publicvalue1234', 'sk_test_secretvalue1234', 'correct-password', $f['admin']);
        $body = json_encode(['event' => 'integration.test'], JSON_THROW_ON_ERROR);

        $this->call('POST', route('payments.webhook'), [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_secretvalue1234'),
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();
        $this->call('POST', route('payments.webhook'), [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'wrong-secret'),
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertUnauthorized();

        $this->actingAs($f['admin'])->post(route('payments.gateway.store'), [
            'public_key' => 'pk_test_publicvalue9999',
            'secret_key' => 'sk_live_secretvalue9999',
            'current_password' => 'correct-password',
        ])->assertSessionHasErrors('secret_key');
        $this->actingAs($f['admin'])->post(route('payments.gateway.store'), [
            'public_key' => 'pk_live_publicvalue9999',
            'secret_key' => 'sk_test_secretvalue9999',
            'current_password' => 'correct-password',
        ])->assertSessionHasErrors('secret_key');
        $this->restoreTenant($f);
        $this->assertSame('pk_test_publicvalue1234', SchoolSetting::firstOrFail()->paystack_public_key);
    }

    public function test_no_keys_hide_all_pay_controls_refuse_part_payment_online_and_leave_manual_payment_working(): void
    {
        $f = $this->fixture();
        config()->set('services.paystack.public_key');
        config()->set('services.paystack.secret_key');
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'unsigned']], JSON_THROW_ON_ERROR);
        $this->call('POST', route('payments.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)
            ->assertUnauthorized();
        $this->call('POST', route('payments.webhook'), [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'unknown-secret'),
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertUnauthorized();
        $this->restoreTenant($f);
        $parent = $this->invoice($f, Invoice::KIND_TERM_FEE, 4500000);
        $part = Invoice::create([
            'parent_invoice_id' => $parent->id,
            'student_id' => $f['student']->id,
            'term_id' => null,
            'purpose' => 'Part payment',
            'kind' => Invoice::KIND_PART_PAYMENT,
            'creation_source' => 'part_payment',
            'amount_kobo' => 1000000,
            'due_date' => today(),
            'status' => 'Unpaid',
            'created_by' => $f['bursar']->id,
        ]);
        $oneOff = $this->invoice($f, Invoice::KIND_ONE_OFF, 250000);

        $this->actingAs($f['student']->user)->get(route('payments.mine.index'))
            ->assertOk()->assertSee("Online payment isn't set up yet", false)
            ->assertDontSee('Continue to Paystack')->assertDontSee('data-open-sheet="pay-sheet-', false);
        $this->actingAs($f['parent'])->get(route('payments.mine.index', ['student' => $f['student']->id]))
            ->assertOk()->assertSee("Online payment isn't set up yet", false)
            ->assertDontSee('Continue to Paystack')->assertDontSee('data-open-sheet="pay-sheet-', false);
        foreach ([$parent, $part, $oneOff] as $invoice) {
            $this->actingAs($f['student']->user)->from(route('payments.mine.index'))
                ->post(route('payments.mine.begin', $invoice))->assertSessionHasErrors([
                    'payment' => "Online payment isn't set up yet. Please visit the bursar with your bank teller.",
                ]);
        }
        $this->actingAs($f['bursar'])->get(route('payments.index'))
            ->assertOk()->assertSee("Online payment isn't set up yet", false);
        $this->actingAs($f['bursar'])->get(route('payments.invoices.show', $part))
            ->assertOk()->assertSee("Online payment isn't set up yet", false);

        $this->restoreTenant($f);
        $submission = app(ManualPaymentService::class)->record($part, [
            'bank' => 'GTBank', 'teller_number' => 'NO-KEYS-1',
            'paid_on' => today()->toDateString(), 'amount_kobo' => 1000000,
        ], $f['bursar'])['submission'];
        $this->assertSame(ManualPaymentSubmission::STATUS_AWAITING, $submission->status);
    }

    public function test_unreadable_saved_secret_disables_online_payments_and_gives_admin_a_generic_recovery_message(): void
    {
        $f = $this->fixture();
        DB::table('school_settings')->update([
            'paystack_public_key' => 'pk_test_publicvalue1234',
            'paystack_secret_key' => 'not-an-encrypted-value',
            'paystack_secret_last_four' => '1234',
            'paystack_mode' => 'test',
        ]);

        $this->assertFalse(app(PaystackCredentials::class)->isConfigured());
        $this->actingAs($f['admin'])->get(route('payments.gateway.show'))
            ->assertOk()
            ->assertSee("The saved keys can't be read. Please enter them again.", false)
            ->assertDontSee('not-an-encrypted-value')
            ->assertDontSee('1234');
    }

    public function test_environment_fallback_and_read_only_connection_test_work_without_saved_keys(): void
    {
        $f = $this->fixture();
        config()->set('services.paystack.public_key', 'pk_test_environment1234');
        config()->set('services.paystack.secret_key', 'sk_test_environment1234');
        Http::fake([
            'api.paystack.co/transaction?*' => Http::response(['status' => true, 'data' => []]),
            'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => [
                'authorization_url' => 'https://checkout.paystack.com/environment-test',
            ]]),
        ]);

        $credentials = app(PaystackCredentials::class);
        $this->assertTrue($credentials->isConfigured());
        $this->assertSame('environment', $credentials->state()['source']);
        $this->assertTrue($credentials->testConnection());
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.paystack.co/transaction?')
            && str_contains($request->url(), 'perPage=1') && str_contains($request->url(), 'page=1'));

        $invoice = $this->invoice($f, Invoice::KIND_ONE_OFF, 250000);
        $attempt = app(PaymentLedger::class)->beginPaystack($invoice, $f['student']->user);
        $this->assertSame('https://checkout.paystack.com/environment-test', $attempt->authorization_url);
        $this->actingAs($f['admin'])->get(route('payments.gateway.show'))
            ->assertOk()->assertSee('Using environment keys')->assertDontSee('sk_test_environment1234');
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        SchoolSetting::create([]);
        foreach (['manage_payment_gateway', 'manage_payments', 'manage_fee_schedule', 'approve_payments'] as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }
        $adminRole = Role::create(['name' => 'School Admin']);
        $adminRole->permissions()->attach(Permission::pluck('id'));
        $bursarRole = Role::create(['name' => 'Bursar']);
        $bursarRole->permissions()->attach(Permission::whereIn('name', ['manage_payments', 'manage_fee_schedule'])->pluck('id'));
        $admin = User::create(['name' => 'Admin One', 'email' => 'gateway-admin@example.test', 'password' => 'correct-password']);
        $otherAdmin = User::create(['name' => 'Admin Two', 'email' => 'gateway-admin-two@example.test', 'password' => 'correct-password']);
        $bursar = User::create(['name' => 'Bursar', 'email' => 'gateway-bursar@example.test', 'password' => 'correct-password']);
        $studentUser = User::create(['name' => 'Student', 'email' => 'gateway-student@example.test', 'password' => 'correct-password']);
        $parent = User::create(['name' => 'Parent', 'email' => 'gateway-parent@example.test', 'password' => 'correct-password']);
        $admin->roles()->attach($adminRole);
        $otherAdmin->roles()->attach($adminRole);
        $bursar->roles()->attach($bursarRole);
        $level = AcademicLevel::create(['name' => 'Secondary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JS 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A']);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'class_arm_id' => $arm->id,
            'status' => Student::STATUS_ACTIVE,
            'admission_date' => today(),
        ]);
        $parentProfile = ParentProfile::create(['user_id' => $parent->id]);
        ParentStudent::create(['parent_id' => $parentProfile->id, 'student_id' => $student->id]);

        return compact('school', 'admin', 'otherAdmin', 'bursar', 'student', 'parent');
    }

    private function invoice(array $fixture, string $kind, int $amountKobo): Invoice
    {
        return Invoice::create([
            'student_id' => $fixture['student']->id,
            'purpose' => $kind === Invoice::KIND_TERM_FEE ? 'Term fees' : 'One-off invoice',
            'kind' => $kind,
            'creation_source' => 'single',
            'amount_kobo' => $amountKobo,
            'due_date' => today(),
            'status' => 'Unpaid',
            'created_by' => $fixture['bursar']->id,
        ]);
    }

    private function restoreTenant(array $fixture): void
    {
        config()->set('tenancy.default_school_id', $fixture['school']->id);
        app(TenantContext::class)->set($fixture['school']->id);
    }
}
