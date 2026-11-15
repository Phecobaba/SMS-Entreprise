<?php

namespace Tests\Feature\Payments;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\ApplicationPaymentAttempt;
use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Services\ApplicationWorkflow;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Models\ParentStudent;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Models\Receipt;
use App\Modules\Payments\Models\ReceiptCounter;
use App\Modules\Payments\Services\InvoiceLifecycle;
use App\Modules\Payments\Services\ManualPaymentService;
use App\Modules\Payments\Services\PaymentLedger;
use App\Modules\Payments\Services\PaystackCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ReceiptIssuanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_online_finalization_issues_one_number_and_snapshot_with_scoped_pdf_access(): void
    {
        $f = $this->fixture();
        Mail::fake();
        $attempt = $this->onlineAttempt($f);
        Http::fake(['*/transaction/verify/*' => Http::response(['status' => true, 'data' => [
            'status' => 'success', 'reference' => $attempt->reference, 'currency' => 'NGN', 'amount' => 100000,
        ]])]);

        $ledger = app(PaymentLedger::class);
        $first = $ledger->verifyPaystackResult($attempt->reference)['payment'];
        $second = $ledger->verifyPaystackResult($attempt->reference)['payment'];

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('receipts', 1);
        $this->assertSame(2, ReceiptCounter::firstOrFail()->next_number);
        $receipt = Receipt::firstOrFail();
        $this->assertMatchesRegularExpression('/^OAK-\d{4}-000001$/', $receipt->number);
        $snapshot = [$receipt->child_name, $receipt->items, $receipt->amount_kobo, $receipt->school_name];

        $f['studentUser']->update(['name' => 'Changed pupil']);
        $f['invoice']->update(['purpose' => 'Changed invoice']);
        $this->assertSame($snapshot, [
            $receipt->fresh()->child_name, $receipt->fresh()->items,
            $receipt->fresh()->amount_kobo, $receipt->fresh()->school_name,
        ]);

        $own = $this->actingAs($f['parent'])->get(route('receipts.download', $receipt));
        $own->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringNotContainsString('card_number', serialize($receipt->getAttributes()));
        $this->assertStringNotContainsString('4111111111111111', $own->getContent());
        $this->actingAs($f['otherParent'])->get(route('receipts.download', $receipt))->assertForbidden();
        $this->actingAs($f['bursar'])->get(route('receipts.download', $receipt))->assertOk();
    }

    public function test_partial_manual_payment_snapshots_amount_balance_and_receiver(): void
    {
        $f = $this->fixture();
        Mail::fake();
        $part = app(InvoiceLifecycle::class)->createPart($f['invoice'], 40000, today()->toDateString(), null, $f['bursar']);
        $recorded = app(ManualPaymentService::class)->record($part, [
            'bank' => 'School bank', 'teller_number' => 'TELLER-1', 'paid_on' => today()->toDateString(), 'amount_kobo' => 40000,
        ], $f['bursar']);
        app(ManualPaymentService::class)->approve($recorded['submission'], $f['bursar']);

        $receipt = Receipt::whereHas('payment', fn ($query) => $query->where('invoice_id', $part->id))->firstOrFail();
        $this->assertSame(40000, $receipt->amount_kobo);
        $this->assertSame(60000, $receipt->balance_after_kobo);
        $this->assertSame('Bursar', $receipt->received_by_name);
        $this->assertSame('Manual bank payment', $receipt->method);
    }

    public function test_application_receipt_requires_owner_cookie_or_signed_link(): void
    {
        $f = $this->fixture();
        Mail::fake();
        $token = 'application-owner-token';
        $application = Application::create([
            'public_reference' => 'APP-RECEIPT-1', 'child_first_name' => 'Ada', 'child_surname' => 'Pupil',
            'date_of_birth' => '2015-01-01', 'gender' => 'Female', 'applied_level_id' => $f['level']->id,
            'guardian_name' => 'Guardian', 'guardian_relationship' => 'Parent', 'guardian_phone' => '08000000000',
            'guardian_email' => 'guardian@example.test', 'guardian_address' => 'Address', 'username' => 'ada.pupil',
            'active_username_key' => 'ada.pupil', 'status' => ApplicationWorkflow::AWAITING,
            'fee_amount_kobo' => 250000, 'currency' => 'NGN', 'access_token_hash' => hash('sha256', $token),
            'consented_at' => now(), 'consent_version' => '1', 'expires_at' => now()->addDays(7),
        ]);
        $attempt = ApplicationPaymentAttempt::create([
            'application_id' => $application->id, 'active_application_id' => $application->id,
            'reference' => 'application-reference', 'amount_kobo' => 250000, 'currency' => 'NGN',
            'status' => 'Pending', 'last_activity_at' => now(),
        ]);
        app(ApplicationWorkflow::class)->finalize($attempt);
        $receipt = Receipt::where('application_payment_attempt_id', $attempt->id)->firstOrFail();

        $this->withCookie(config('admissions.cookie'), $token)
            ->get(route('applications.receipt', $application->public_reference))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->withCookie(config('admissions.cookie'), 'wrong-token')
            ->get(route('applications.receipt', $application->public_reference))->assertNotFound();
        $signed = URL::temporarySignedRoute('applications.receipt', now()->addMinute(), ['application' => $application->public_reference]);
        $this->get($signed)->assertOk();
        $this->assertSame('Application fee', $receipt->items[0]['description']);
    }

    public function test_receipt_mail_failure_does_not_reverse_payment(): void
    {
        $f = $this->fixture();
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp', [
            'transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'scheme' => null,
            'username' => null, 'password' => null, 'timeout' => 0.1,
        ]);
        Mail::forgetMailers();
        $attempt = $this->onlineAttempt($f);
        Http::fake(['*/transaction/verify/*' => Http::response(['status' => true, 'data' => [
            'status' => 'success', 'reference' => $attempt->reference, 'currency' => 'NGN', 'amount' => 100000,
        ]])]);

        $this->actingAs($f['parent'])->get(route('payments.verify', ['reference' => $attempt->reference]))->assertRedirect();

        $this->assertSame('Paid', $f['invoice']->fresh()->status);
        $this->assertNotNull(Receipt::firstOrFail()->mail_failed_at);
    }

    public function test_manually_confirmed_application_fee_gets_one_audited_receipt(): void
    {
        $f = $this->fixture();
        Mail::fake();
        $application = Application::create([
            'public_reference' => 'APP-MANUAL-1', 'child_first_name' => 'Manual', 'child_surname' => 'Applicant',
            'date_of_birth' => '2015-01-01', 'gender' => 'Female', 'applied_level_id' => $f['level']->id,
            'guardian_name' => 'Guardian', 'guardian_relationship' => 'Parent', 'guardian_phone' => '08000000000',
            'guardian_email' => 'manual.guardian@example.test', 'guardian_address' => 'Address', 'username' => 'manual.applicant',
            'active_username_key' => 'manual.applicant', 'status' => ApplicationWorkflow::AWAITING,
            'fee_amount_kobo' => 250000, 'currency' => 'NGN', 'access_token_hash' => hash('sha256', 'manual-token'),
            'consented_at' => now(), 'consent_version' => '1', 'expires_at' => now()->addDays(7),
        ]);

        app(ApplicationWorkflow::class)->markPaid($application, $f['bursar'], 'Paid at school');
        app(ApplicationWorkflow::class)->markPaid($application->fresh(), $f['bursar'], 'Repeated click');

        $receipt = Receipt::where('application_id', $application->id)->sole();
        $this->assertSame('Manual payment', $receipt->method);
        $this->assertSame('Bursar', $receipt->received_by_name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'receipt.issued_manually', 'subject_id' => $receipt->id]);
        $this->assertDatabaseCount('receipts', 1);
    }

    private function onlineAttempt(array $f)
    {
        $this->app->instance(PaystackCredentials::class, new class extends PaystackCredentials
        {
            public function secretKeyOrFail(): string
            {
                return 'fake-credential';
            }
        });
        Http::fake(['*/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.test/receipt']], 200)]);

        return app(PaymentLedger::class)->beginPaystack($f['invoice'], $f['parent']);
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $bursar = User::create(['name' => 'Bursar', 'email' => 'bursar@example.test', 'password' => 'password']);
        $role = Role::create(['name' => 'Bursar']);
        foreach (['manage_payments', 'manage_fee_schedule', 'approve_payments'] as $permission) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $permission]));
        }
        $bursar->roles()->attach($role);
        $parent = User::create(['name' => 'Parent', 'email' => 'parent@example.test', 'password' => 'password']);
        $otherParent = User::create(['name' => 'Other parent', 'email' => 'other@example.test', 'password' => 'password']);
        $studentUser = User::create(['name' => 'Pupil', 'email' => 'pupil@example.test', 'password' => 'password']);
        $level = AcademicLevel::create(['name' => 'JS 1']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JS1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'Gold']);
        $student = Student::create(['user_id' => $studentUser->id, 'class_arm_id' => $arm->id, 'admission_date' => today()]);
        $profile = ParentProfile::create(['user_id' => $parent->id]);
        ParentStudent::create(['parent_id' => $profile->id, 'student_id' => $student->id]);
        ParentProfile::create(['user_id' => $otherParent->id]);
        $invoice = app(PaymentLedger::class)->invoice([
            'student_id' => $student->id, 'term_id' => null, 'purpose' => 'Tuition',
            'amount_kobo' => 100000, 'due_date' => today()->toDateString(),
        ], $bursar);

        return compact('school', 'bursar', 'parent', 'otherParent', 'studentUser', 'student', 'level', 'invoice');
    }
}
