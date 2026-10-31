<?php

namespace Tests\Feature\Payments;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Models\StudentRegistrationNumberHistory;
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
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Payments\Services\InvoiceLifecycle;
use App\Modules\Payments\Services\ManualPaymentService;
use App\Modules\Payments\Services\PaymentLedger;
use App\Modules\Payments\Services\ResultPaymentAccess;
use App\Modules\Results\Models\ResultCard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_settled_parts_reduce_outstanding_once_issue_receipts_and_only_the_final_part_unlocks_results(): void
    {
        $f = $this->fixture();
        $lifecycle = app(InvoiceLifecycle::class);
        $first = $lifecycle->createPart($f['invoice'], 2000000, today()->toDateString(), 'First instalment', $f['bursar']);
        $second = $lifecycle->createPart($f['invoice'], 2500000, today()->toDateString(), null, $f['bursar']);

        $this->assertSame(4500000, app(PaymentLedger::class)->termFeeOutstandingKobo($f['student']->id));
        $this->settlePart($first, $f, 'PART-ONE');
        $this->assertSame(2500000, app(PaymentLedger::class)->termFeeOutstandingKobo($f['student']->id));
        $this->assertSame('Unpaid', $f['invoice']->fresh()->status);
        $this->assertThrows(fn () => app(ResultPaymentAccess::class)->authorize($f['card'], $f['student']->user), AuthorizationException::class);

        $this->settlePart($second, $f, 'PART-TWO');
        $this->assertSame('Paid', $f['invoice']->fresh()->status);
        $lifecycle->syncParentAfterSettlement($second->fresh(), $f['admin']);
        $this->assertSame(1, AuditLog::where('action', 'invoice.paid_by_parts')
            ->where('subject_id', $f['invoice']->id)->count());
        $this->assertSame(0, app(PaymentLedger::class)->termFeeOutstandingKobo($f['student']->id));
        app(ResultPaymentAccess::class)->authorize($f['card'], $f['student']->user);
        app(ResultPaymentAccess::class)->authorizeAttendance($f['student']->id, $f['student']->user);
        $this->assertNotNull($first->fresh()->payment?->receipt_number);
        $this->assertNotNull($second->fresh()->payment?->receipt_number);
        $this->assertNotSame($first->fresh()->payment->receipt_number, $second->fresh()->payment->receipt_number);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.part_payment_created', 'subject_id' => $first->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.paid_by_parts', 'subject_id' => $f['invoice']->id]);
    }

    public function test_row_locked_creation_caps_settled_plus_open_parts_even_for_stale_requests(): void
    {
        $f = $this->fixture();
        $lifecycle = app(InvoiceLifecycle::class);
        $stale = Invoice::findOrFail($f['invoice']->id);
        $lifecycle->createPart($f['invoice'], 3000000, today()->toDateString(), null, $f['bursar']);

        $this->assertValidationError(
            fn () => $lifecycle->createPart($stale, 2000000, today()->toDateString(), null, $f['bursar']),
            'amount_naira',
        );
        $this->assertSame(3000000, $f['invoice']->partPayments()->where('status', 'Unpaid')->sum('amount_kobo'));
    }

    public function test_open_part_blocks_direct_whole_payment_and_void_reenables_it_but_paid_or_awaiting_parts_cannot_be_voided(): void
    {
        $f = $this->fixture();
        $lifecycle = app(InvoiceLifecycle::class);
        $part = $lifecycle->createPart($f['invoice'], 1000000, today()->toDateString(), null, $f['bursar']);
        config()->set('services.paystack.secret_key', 'sk_test_local_fake_only');
        config()->set('services.paystack.public_key', 'pk_test_local_fake_only');
        $this->assertValidationError(fn () => app(PaymentLedger::class)->beginPaystack($f['invoice'], $f['bursar']), 'payment');
        $this->assertValidationError(fn () => app(ManualPaymentService::class)->record($f['invoice'], $this->details('WHOLE', 4500000), $f['bursar']), 'invoice');

        $lifecycle->voidPart($part, 'Family changed the instalment plan.', $f['bursar']);
        $submission = app(ManualPaymentService::class)->record($f['invoice'], $this->details('WHOLE', 4500000), $f['bursar'])['submission'];
        $this->assertSame(ManualPaymentSubmission::STATUS_AWAITING, $submission->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.part_payment_voided', 'subject_id' => $part->id]);

        app(ManualPaymentService::class)->reject($submission, $f['admin'], 'Use instalments.');
        $awaiting = $lifecycle->createPart($f['invoice'], 1000000, today()->toDateString(), null, $f['bursar']);
        app(ManualPaymentService::class)->record($awaiting, $this->details('WAITING', 1000000), $f['bursar']);
        $this->assertValidationError(fn () => $lifecycle->voidPart($awaiting, 'Try to void.', $f['bursar']), 'invoice');

        $other = $lifecycle->createPart($f['invoice'], 500000, today()->toDateString(), null, $f['bursar']);
        $this->settlePart($other, $f, 'PAID-PART');
        $this->assertValidationError(fn () => $lifecycle->voidPart($other, 'Try to void paid.', $f['bursar']), 'invoice');
    }

    public function test_only_term_fee_invoices_can_have_parts_and_settled_part_blocks_parent_void_while_empty_invoice_can_be_voided(): void
    {
        $f = $this->fixture();
        $lifecycle = app(InvoiceLifecycle::class);
        foreach ([Invoice::KIND_REGISTRATION, Invoice::KIND_OFFER_DEPOSIT, Invoice::KIND_ONE_OFF] as $kind) {
            $invoice = $this->invoice($f, $kind, 1000000);
            $this->assertValidationError(fn () => $lifecycle->createPart($invoice, 500000, today()->toDateString(), null, $f['bursar']), 'invoice');
        }

        $part = $lifecycle->createPart($f['invoice'], 1000000, today()->toDateString(), null, $f['bursar']);
        $this->assertValidationError(fn () => $lifecycle->createPart($part, 500000, today()->toDateString(), null, $f['bursar']), 'invoice');
        $this->settlePart($part, $f, 'SETTLED');
        $this->assertValidationError(fn () => $lifecycle->voidInvoice($f['invoice'], 'Cancel the invoice.', $f['admin']), 'invoice');

        $voidable = $this->invoice($f, Invoice::KIND_ONE_OFF, 250000);
        $lifecycle->voidInvoice($voidable, 'Charge cancelled.', $f['admin']);
        $this->assertSame('Voided', $voidable->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.voided', 'subject_id' => $voidable->id]);
    }

    public function test_adjustments_are_audited_respect_allocated_money_and_wait_for_manual_approval(): void
    {
        $f = $this->fixture();
        $lifecycle = app(InvoiceLifecycle::class);
        $settled = $lifecycle->createPart($f['invoice'], 1000000, today()->toDateString(), null, $f['bursar']);
        $this->settlePart($settled, $f, 'ADJ-SETTLED');
        $part = $lifecycle->createPart($f['invoice'], 2000000, today()->toDateString(), null, $f['bursar']);
        $lifecycle->addAdjustment($f['invoice'], 'Approved discount', -1000000, 'Sibling discount.', $f['bursar']);
        $this->assertSame(3500000, $f['invoice']->fresh()->amount_kobo);
        $this->assertDatabaseHas('invoice_lines', ['invoice_id' => $f['invoice']->id, 'line_type' => 'adjustment', 'amount_kobo' => -1000000]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.adjusted', 'subject_id' => $f['invoice']->id]);
        $this->assertValidationError(fn () => $lifecycle->addAdjustment($f['invoice'], 'Too much', -600000, 'Invalid reduction.', $f['bursar']), 'amount_naira');
        $this->assertValidationError(fn () => $lifecycle->addAdjustment($f['invoice'], 'Erase total', -3500000, 'Invalid zero total.', $f['bursar']), 'amount_naira');

        app(ManualPaymentService::class)->record($part, $this->details('PENDING', 2000000), $f['bursar']);
        $this->assertValidationError(fn () => $lifecycle->addAdjustment($f['invoice'], 'Late fee', 100000, 'Late payment.', $f['bursar']), 'adjustment');
    }

    public function test_verify_returns_student_and_linked_parent_to_my_fees_and_guest_home(): void
    {
        $f = $this->fixture();
        config()->set('services.paystack.secret_key', 'sk_test_local_fake_only');
        config()->set('services.paystack.public_key', 'pk_test_local_fake_only');
        $attempt = PaymentAttempt::create([
            'invoice_id' => $f['invoice']->id, 'reference' => 'verify-return', 'amount_kobo' => 4500000,
            'initiated_by' => $f['student']->user_id, 'status' => 'Pending',
        ]);
        Http::fake(['*' => Http::response(['status' => true, 'data' => [
            'status' => 'success', 'reference' => $attempt->reference, 'currency' => 'NGN', 'amount' => 4500000,
        ]])]);

        foreach ([$f['student']->user, $f['parent']] as $viewer) {
            $this->actingAs($viewer)->get(route('payments.verify', ['reference' => $attempt->reference]))
                ->assertRedirect(route('payments.mine.index', ['student' => $f['student']->id]));
        }
        auth()->logout();
        $this->get(route('payments.verify', ['reference' => $attempt->reference]))->assertRedirect(route('home'));
    }

    public function test_registration_number_renders_on_financial_views_and_record_payment_searches_current_and_history_without_cross_school_leaks(): void
    {
        $f = $this->fixture();
        $f['student']->update(['registration_number' => 'OLD-ADA-1']);
        StudentRegistrationNumberHistory::create([
            'student_id' => $f['student']->id, 'old_number' => null, 'new_number' => 'OLD-ADA-1',
            'sequence_value' => null, 'changed_by' => $f['admin']->id, 'changed_at' => now()->subMinute(),
        ]);
        $f['student']->update(['registration_number' => 'CURRENT-ADA-1']);
        StudentRegistrationNumberHistory::create([
            'student_id' => $f['student']->id, 'old_number' => 'OLD-ADA-1', 'new_number' => 'CURRENT-ADA-1',
            'sequence_value' => null, 'changed_by' => $f['admin']->id, 'changed_at' => now(),
        ]);

        $this->actingAs($f['bursar'])->get(route('payments.record.index', ['q' => 'old-ada-1']))
            ->assertOk()->assertSee('Ada Learner')->assertSee('Registration number: CURRENT-ADA-1');
        $this->get(route('payments.record.index', ['q' => 'current-ada-1']))
            ->assertOk()->assertSee('Ada Learner');
        $this->get(route('payments.invoices.show', $f['invoice']))
            ->assertOk()->assertSee('Registration number:')->assertSee('CURRENT-ADA-1');

        app(TenantContext::class)->set($f['school']->id);
        $paid = $this->invoice($f, Invoice::KIND_ONE_OFF, 100000);
        $paid->update(['status' => 'Paid']);
        Payment::create([
            'invoice_id' => $paid->id, 'amount_kobo' => 100000, 'method' => 'Manual',
            'confirmed_by' => $f['admin']->id, 'paid_at' => now(), 'receipt_number' => 'REG-RECEIPT-1',
        ]);
        $this->actingAs($f['student']->user)->get(route('payments.mine.index'))
            ->assertOk()->assertSee('Registration number:')->assertSee('CURRENT-ADA-1')->assertSee('REG-RECEIPT-1');
        $this->actingAs($f['parent'])->get(route('payments.mine.index', ['student' => $f['student']->id]))
            ->assertOk()->assertSee('Registration number:')->assertSee('CURRENT-ADA-1')->assertSee('REG-RECEIPT-1');

        $otherSchool = School::create(['name' => 'Other School']);
        app(TenantContext::class)->set($otherSchool->id);
        $otherUser = User::create(['name' => 'Foreign Pupil', 'username' => 'foreign.pupil', 'password' => 'long-password-here']);
        $otherLevel = AcademicLevel::create(['name' => 'Other Level']);
        $otherClass = SchoolClass::create(['academic_level_id' => $otherLevel->id, 'name' => 'Other Class']);
        $otherArm = ClassArm::create(['class_id' => $otherClass->id, 'name' => 'A']);
        $otherStudent = Student::create([
            'user_id' => $otherUser->id, 'class_arm_id' => $otherArm->id,
            'registration_number' => 'FOREIGN-CURRENT', 'status' => Student::STATUS_ACTIVE,
            'admission_date' => today(),
        ]);
        StudentRegistrationNumberHistory::create([
            'student_id' => $otherStudent->id, 'old_number' => null, 'new_number' => 'FOREIGN-OLD',
            'sequence_value' => null, 'changed_by' => $otherUser->id, 'changed_at' => now(),
        ]);
        Invoice::create([
            'student_id' => $otherStudent->id, 'purpose' => 'Other fee', 'kind' => Invoice::KIND_ONE_OFF,
            'creation_source' => 'single', 'amount_kobo' => 100000, 'due_date' => today(),
            'status' => 'Unpaid', 'created_by' => $otherUser->id,
        ]);

        app(TenantContext::class)->set($f['school']->id);
        $this->actingAs($f['bursar'])->get(route('payments.record.index', ['q' => 'foreign-old']))
            ->assertOk()->assertDontSee('Foreign Pupil')->assertDontSee('FOREIGN-CURRENT');
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        SchoolSetting::create([]);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-life@example.test', 'password' => 'long-password-here']);
        $bursar = User::create(['name' => 'Bursar', 'email' => 'bursar-life@example.test', 'password' => 'long-password-here']);
        $studentUser = User::create(['name' => 'Ada Learner', 'email' => 'student-life@example.test', 'password' => 'long-password-here']);
        $parentUser = User::create(['name' => 'Ada Parent', 'email' => 'parent-life@example.test', 'password' => 'long-password-here']);
        foreach (['manage_payments', 'manage_fee_schedule', 'approve_payments'] as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
        $adminRole = Role::create(['name' => 'School Admin']);
        $adminRole->permissions()->attach(Permission::pluck('id'));
        $bursarRole = Role::create(['name' => 'Bursar']);
        $bursarRole->permissions()->attach(Permission::whereIn('name', ['manage_payments', 'manage_fee_schedule'])->pluck('id'));
        $admin->roles()->attach($adminRole);
        $bursar->roles()->attach($bursarRole);
        $level = AcademicLevel::create(['name' => 'Secondary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JS 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A']);
        $student = Student::create(['user_id' => $studentUser->id, 'class_arm_id' => $arm->id, 'status' => Student::STATUS_ACTIVE, 'admission_date' => today()]);
        $parent = ParentProfile::create(['user_id' => $parentUser->id]);
        ParentStudent::create(['parent_id' => $parent->id, 'student_id' => $student->id]);
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $fixture = compact('school', 'admin', 'bursar', 'student', 'parentUser', 'term', 'arm');
        $fixture['parent'] = $parentUser;
        $fixture['invoice'] = $this->invoice($fixture, Invoice::KIND_TERM_FEE, 4500000);
        $fixture['invoice']->lines()->create(['description' => 'Tuition', 'amount_kobo' => 4500000, 'is_required' => true]);
        $fixture['card'] = ResultCard::create(['student_id' => $student->id, 'term_id' => $term->id, 'class_arm_id' => $arm->id, 'compiled_at' => now()]);

        return $fixture;
    }

    private function invoice(array $f, string $kind, int $amount): Invoice
    {
        return Invoice::create([
            'student_id' => $f['student']->id, 'term_fee_student_id' => $kind === Invoice::KIND_TERM_FEE ? $f['student']->id : null,
            'term_id' => $f['term']->id, 'purpose' => $kind, 'kind' => $kind, 'creation_source' => 'single',
            'amount_kobo' => $amount, 'due_date' => today(), 'status' => 'Unpaid', 'created_by' => $f['bursar']->id,
        ]);
    }

    private function settlePart(Invoice $part, array $f, string $teller): Payment
    {
        $manual = app(ManualPaymentService::class);
        $submission = $manual->record($part, $this->details($teller, $part->amount_kobo), $f['bursar'])['submission'];

        return $manual->approve($submission, $f['admin'])['payment'];
    }

    private function details(string $teller, int $amount): array
    {
        return ['bank' => 'GTBank', 'teller_number' => $teller, 'paid_on' => today()->toDateString(), 'amount_kobo' => $amount];
    }

    private function assertValidationError(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail("Expected a validation error for {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
