<?php

namespace Tests\Feature\Payments;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Services\BillingRecipients;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Models\ParentStudent;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Payments\Services\PaymentLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_parent_can_pay_only_their_own_wards_invoice(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $parentUser = User::create(['name' => 'Parent', 'email' => 'parent-pay@example.test', 'password' => 'long-password-here']);
        $ownUser = User::create(['name' => 'Own child', 'email' => 'own-child@example.test', 'password' => 'long-password-here']);
        $otherUser = User::create(['name' => 'Other child', 'email' => 'other-child@example.test', 'password' => 'long-password-here']);
        $level = AcademicLevel::create(['name' => 'Secondary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JSS 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A']);
        $ownChild = Student::create(['user_id' => $ownUser->id, 'class_arm_id' => $arm->id, 'admission_date' => today()]);
        $otherChild = Student::create(['user_id' => $otherUser->id, 'class_arm_id' => $arm->id, 'admission_date' => today()]);
        $parent = ParentProfile::create(['user_id' => $parentUser->id]);
        ParentStudent::create(['parent_id' => $parent->id, 'student_id' => $ownChild->id]);

        $recipients = app(BillingRecipients::class);
        $this->assertTrue($recipients->owns(null, $ownChild->id, $parentUser));
        $this->assertFalse($recipients->owns(null, $otherChild->id, $parentUser));
        $this->assertTrue($recipients->owns(null, $ownChild->id, $ownUser));
        $this->assertFalse($recipients->owns(null, $ownChild->id, $otherUser));
    }

    public function test_signed_paystack_webhook_verifies_and_reconciles_once_for_bursar(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        config()->set('services.paystack.secret_key', 'sk_test_local_fake_only');
        app(TenantContext::class)->set($school->id);
        $bursar = User::create(['name' => 'Bursar', 'email' => 'bursar@example.test', 'password' => 'long-password-here']);
        $learner = User::create(['name' => 'Learner', 'email' => 'learner-pay@example.test', 'password' => 'long-password-here']);
        $role = Role::create(['name' => 'Bursar']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'manage_payments']));
        $bursar->roles()->attach($role);
        $level = AcademicLevel::create(['name' => 'Secondary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JSS 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A']);
        $student = Student::create(['user_id' => $learner->id, 'class_arm_id' => $arm->id, 'admission_date' => today()]);
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31']);
        $ledger = app(PaymentLedger::class);
        $invoice = $ledger->invoice(['student_id' => $student->id, 'term_id' => $term->id, 'purpose' => 'Tuition', 'amount_kobo' => 1250000, 'due_date' => today()->toDateString()], $bursar);
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/fake']], 200)]);
        $attempt = $ledger->beginPaystack($invoice, $bursar);
        $this->assertSame('Pending', $attempt->status);
        $this->assertSame('Unpaid', $invoice->fresh()->status);
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::sequence()
            ->push(['status' => true, 'data' => ['status' => 'success', 'reference' => $attempt->reference, 'currency' => 'NGN', 'amount' => 1249999]])
            ->push(['status' => true, 'data' => ['status' => 'success', 'reference' => $attempt->reference, 'currency' => 'NGN', 'amount' => 1250000]])
            ->push(['status' => true, 'data' => ['status' => 'success', 'reference' => $attempt->reference, 'currency' => 'NGN', 'amount' => 1250000]])]);
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $attempt->reference]], JSON_THROW_ON_ERROR);
        $this->call('POST', route('payments.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertUnauthorized();
        $this->assertSame('Unpaid', $invoice->fresh()->status);
        $headers = ['HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_local_fake_only'), 'CONTENT_TYPE' => 'application/json'];
        $this->call('POST', route('payments.webhook'), [], [], [], $headers, $body)->assertStatus(422);
        app(TenantContext::class)->set($school->id);
        $this->assertSame('Unpaid', $invoice->fresh()->status);
        $this->call('POST', route('payments.webhook'), [], [], [], $headers, $body)->assertOk();
        $this->call('POST', route('payments.webhook'), [], [], [], $headers, $body)->assertOk();
        app(TenantContext::class)->set($school->id);
        $this->assertSame('Paid', $invoice->fresh()->status);
        $this->assertSame('Verified', PaymentAttempt::findOrFail($attempt->id)->status);
        $this->assertSame(1, Payment::where('invoice_id', $invoice->id)->count());
        $this->actingAs($bursar)->get(route('payments.index'))->assertOk()->assertSee('OAK-'.$school->id.'-'.str_pad((string) $invoice->id, 8, '0', STR_PAD_LEFT));
    }
}
