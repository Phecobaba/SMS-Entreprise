<?php

namespace App\Modules\Payments\Services;

use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\ApplicationPaymentAttempt;
use App\Modules\Assignments\Services\ParentAccountDirectory;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Core\Services\CurrentSchoolProfile;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\ManualPaymentSubmission;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\Receipt;
use App\Modules\Payments\Models\ReceiptCounter;

class ReceiptIssuer
{
    public function invoicePayment(Payment $payment, Invoice $invoice, User $actor): Receipt
    {
        if ($existing = Receipt::where('payment_id', $payment->id)->first()) {
            return $existing;
        }

        $invoice->loadMissing([
            'lines', 'student.user', 'student.classArm.schoolClass', 'candidate.user', 'candidate.appliedLevel',
            'parentInvoice.payment', 'parentInvoice.partPayments.payment',
        ]);
        $recipient = $invoice->student?->user ?? $invoice->candidate?->user;
        $manual = $payment->method === 'Manual';
        $parent = $invoice->student_id
            ? User::whereIn('id', app(ParentAccountDirectory::class)->userIdsForStudents([$invoice->student_id]))->oldest('id')->first()
            : null;
        $submission = $manual
            ? ManualPaymentSubmission::with('recorder')->where('invoice_id', $invoice->id)->latest('id')->first()
            : null;
        $class = $invoice->student?->classArm;
        $balanceInvoice = $invoice->parentInvoice ?: $invoice;

        $receipt = Receipt::create([
            ...$this->schoolSnapshot(),
            'payment_id' => $payment->id,
            'number' => $this->nextNumber(),
            'payer_name' => ($manual || $actor->can('manage_payments'))
                ? ($parent?->name ?? $recipient?->name ?? 'Payer')
                : $actor->name,
            'child_name' => $invoice->student?->user?->name ?? $invoice->candidate?->name,
            'class_or_level' => $class ? trim($class->schoolClass?->name.' '.$class->name) : $invoice->candidate?->appliedLevel?->name,
            'items' => $invoice->lines->isNotEmpty()
                ? $invoice->lines->map(fn ($line): array => ['description' => $line->description, 'amount_kobo' => (int) $line->amount_kobo])->values()->all()
                : [['description' => $invoice->purpose, 'amount_kobo' => (int) $payment->amount_kobo]],
            'amount_kobo' => $payment->amount_kobo,
            'currency' => 'NGN',
            'method' => $manual ? 'Manual bank payment' : 'Paystack online',
            'gateway_reference' => $payment->gateway_reference,
            'balance_after_kobo' => app(InvoiceLifecycle::class)->balanceKobo($balanceInvoice->fresh()),
            'received_by_name' => $manual ? ($submission?->recorder?->name ?? $actor->name) : null,
            'issued_at' => $payment->paid_at ?? now(),
        ]);
        $payment->update(['receipt_number' => $receipt->number]);
        if ($manual) {
            app(AuditRecorder::class)->record($actor, 'receipt.issued_manually', $receipt, [
                'receipt_id' => $receipt->id, 'payment_id' => $payment->id,
            ], 'Payments', 'Manual payment receipt issued.');
        }

        return $receipt;
    }

    public function applicationPayment(Application $application, ?ApplicationPaymentAttempt $attempt, User|string $payer, string $method, ?string $receivedBy = null, ?User $actor = null): Receipt
    {
        $existing = $attempt
            ? Receipt::where('application_payment_attempt_id', $attempt->id)->first()
            : Receipt::where('application_id', $application->id)->whereNull('application_payment_attempt_id')->first();
        if ($existing) {
            return $existing;
        }
        $application->loadMissing('level');

        $receipt = Receipt::create([
            ...$this->schoolSnapshot(),
            'application_id' => $application->id,
            'application_payment_attempt_id' => $attempt?->id,
            'number' => $this->nextNumber(),
            'payer_name' => $payer instanceof User ? $payer->name : $payer,
            'child_name' => trim($application->child_first_name.' '.$application->child_surname),
            'class_or_level' => $application->level?->name,
            'items' => [['description' => 'Application fee', 'amount_kobo' => (int) $application->fee_amount_kobo]],
            'amount_kobo' => $application->fee_amount_kobo,
            'currency' => $application->currency ?: 'NGN',
            'method' => $method,
            'gateway_reference' => $attempt?->reference,
            'balance_after_kobo' => null,
            'received_by_name' => $receivedBy,
            'issued_at' => $application->registered_at ?? now(),
        ]);
        if ($actor && str_starts_with($method, 'Manual')) {
            app(AuditRecorder::class)->record($actor, 'receipt.issued_manually', $receipt, [
                'receipt_id' => $receipt->id, 'application_id' => $application->id,
            ], 'Payments', 'Manual application-fee receipt issued.');
        }

        return $receipt;
    }

    private function nextNumber(): string
    {
        $year = (int) now('Africa/Lagos')->format('Y');
        ReceiptCounter::firstOrCreate(['issue_year' => $year], ['next_number' => 1]);
        $counter = ReceiptCounter::where('issue_year', $year)->lockForUpdate()->firstOrFail();
        $value = (int) $counter->next_number;
        $counter->update(['next_number' => $value + 1]);

        return 'OAK-'.$year.'-'.str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    private function schoolSnapshot(): array
    {
        $profile = app(CurrentSchoolProfile::class)->printProfile() ?? [];

        return [
            'school_name' => $profile['name'] ?? 'School',
            'school_address' => $profile['address'] ?? null,
            'school_phone' => $profile['contact_phone'] ?? null,
            'school_logo_data_uri' => $profile['logo_data_uri'] ?? null,
        ];
    }
}
