<?php

namespace App\Modules\Payments\Services;

use App\Modules\Academics\Services\ResultAcademicCatalog;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Services\BillingRecipients;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Assignments\Services\ParentAccountDirectory;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Notifications\Services\InAppNotifications;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentLedger
{
    public function outstandingKobo(?User $viewer = null): int
    {
        $query = Invoice::where('status', 'Unpaid')->where('purpose', 'Tuition');
        if ($viewer) {
            $armIds = app(SectionAccess::class)->visibleArmIds($viewer);
            if ($armIds !== null) {
                $studentIds = Student::whereIn('class_arm_id', $armIds)->select('id');
                $query->whereIn('student_id', $studentIds);
            }
        }

        return (int) $query->sum('amount_kobo');
    }

    public function candidateInvoices(int $candidateId)
    {
        return Invoice::where('candidate_id', $candidateId)->latest()->paginate(20);
    }

    public function portalSummary(int $studentId, User $viewer): array
    {
        $isOwner = app(StudentDirectory::class)->belongsToUser($studentId, $viewer);
        $isWard = app(ParentAccountDirectory::class)->isWard($studentId, $viewer->id);
        abort_unless($isOwner || $isWard, 403);
        $invoices = Invoice::with('payment')->where('student_id', $studentId)->latest('id')->limit(5)->get();
        $latestUnpaidTuition = Invoice::where('student_id', $studentId)->where('purpose', 'Tuition')
            ->where('status', 'Unpaid')->latest('id')->first();

        return [
            'recent' => $invoices,
            'outstanding_kobo' => (int) Invoice::where('student_id', $studentId)->where('status', 'Unpaid')->sum('amount_kobo'),
            'latest_unpaid_tuition' => $latestUnpaidTuition,
            'latest_unpaid_tuition_payable' => $latestUnpaidTuition && PaymentAttempt::where('invoice_id', $latestUnpaidTuition->id)
                ->latest('id')->value('status') !== 'Pending',
        ];
    }

    public function portalInvoices(int $studentId, User $viewer): array
    {
        app(ResultPaymentAccess::class)->authorizeOwnership($studentId, $viewer);
        $rows = Invoice::with(['payment', 'attempts'])->where('student_id', $studentId)
            ->latest('id')->paginate(20)->withQueryString();

        return [
            'rows' => $rows,
            'terms' => app(ResultAcademicCatalog::class)->labels([], $rows->pluck('term_id')->filter()->all())['terms'],
            'outstanding_kobo' => (int) Invoice::where('student_id', $studentId)
                ->where('status', 'Unpaid')->sum('amount_kobo'),
        ];
    }

    public function wardFeesDue(int $studentId): bool
    {
        $unpaidIds = Invoice::where('student_id', $studentId)->where('status', 'Unpaid')->pluck('id');
        if ($unpaidIds->isEmpty()) {
            return false;
        }
        $attemptStatuses = PaymentAttempt::whereIn('invoice_id', $unpaidIds)->latest('id')->get()
            ->unique('invoice_id')->pluck('status', 'invoice_id');

        return $unpaidIds->contains(fn ($id) => ($attemptStatuses[$id] ?? null) !== 'Pending');
    }

    public function invoice(array $data, User $actor): Invoice
    {
        if (((int) ! empty($data['student_id']) + (int) ! empty($data['candidate_id'])) !== 1
            || (int) ($data['amount_kobo'] ?? 0) < 1) {
            throw ValidationException::withMessages(['invoice' => 'Select exactly one student or candidate and a positive amount.']);
        }

        return Invoice::create($data + ['created_by' => $actor->id, 'status' => 'Unpaid']);
    }

    public function manualPayment(Invoice $invoice, User $actor): Payment
    {
        if (! $actor->can('manage_payments')) {
            throw ValidationException::withMessages(['payment' => 'Payment confirmation requires Bursar access.']);
        }

        return $this->settle($invoice, 'Manual', null, $actor);
    }

    public function beginPaystack(Invoice $invoice, User $actor): PaymentAttempt
    {
        $secret = config('services.paystack.secret_key');
        if (! is_string($secret) || $secret === '') {
            throw ValidationException::withMessages(['payment' => 'Paystack test keys are not configured yet.']);
        }
        if ($invoice->status !== 'Unpaid') {
            throw ValidationException::withMessages(['payment' => 'This invoice is already paid.']);
        }
        if (PaymentAttempt::where('invoice_id', $invoice->id)->latest('id')->value('status') === 'Pending') {
            throw ValidationException::withMessages(['payment' => 'A payment is already being confirmed for this invoice.']);
        }
        if (! $actor->can('manage_payments') && ! app(BillingRecipients::class)->owns($invoice->candidate_id, $invoice->student_id, $actor)) {
            throw ValidationException::withMessages(['payment' => 'This invoice does not belong to you.']);
        }
        $email = $actor->email ?: app(BillingRecipients::class)->emailFor($invoice->candidate_id, $invoice->student_id);
        if (! $email) {
            throw ValidationException::withMessages(['payment' => 'An email address is needed for Paystack. Ask the school office to add one to your account.']);
        }
        $reference = 'oak-'.app(TenantContext::class)->id().'-'.Str::uuid();
        $attempt = PaymentAttempt::create([
            'invoice_id' => $invoice->id, 'reference' => $reference,
            'amount_kobo' => $invoice->amount_kobo, 'initiated_by' => $actor->id,
            'status' => 'Pending',
        ]);
        try {
            $response = Http::withToken($secret)->acceptJson()->timeout(15)
                ->post(rtrim(config('services.paystack.base_url'), '/').'/transaction/initialize', [
                    'email' => $email, 'amount' => $invoice->amount_kobo,
                    'currency' => 'NGN', 'reference' => $reference,
                    'callback_url' => route('payments.verify'),
                ])->throw()->json();
        } catch (\Throwable $exception) {
            $attempt->update(['status' => 'Failed']);
            throw ValidationException::withMessages(['payment' => 'Paystack could not start this payment. Please try again.']);
        }
        if (($response['status'] ?? false) !== true || ! is_string($response['data']['authorization_url'] ?? null)) {
            $attempt->update(['status' => 'Failed']);
            throw ValidationException::withMessages(['payment' => 'Paystack did not return a payment link.']);
        }
        $attempt->update(['authorization_url' => $response['data']['authorization_url']]);

        return $attempt;
    }

    public function verifyPaystack(string $reference): ?Payment
    {
        $secret = config('services.paystack.secret_key');
        if (! is_string($secret) || $secret === '') {
            throw ValidationException::withMessages(['payment' => 'Paystack is not configured.']);
        }
        $attempt = PaymentAttempt::where('reference', $reference)->firstOrFail();
        $response = Http::withToken($secret)->acceptJson()->timeout(15)
            ->get(rtrim(config('services.paystack.base_url'), '/').'/transaction/verify/'.rawurlencode($reference))
            ->throw()->json();
        $data = $response['data'] ?? [];
        if (($response['status'] ?? false) !== true || ($data['status'] ?? null) !== 'success') {
            if (($response['status'] ?? false) === true && ($data['status'] ?? null) === 'failed'
                && ($data['reference'] ?? null) === $reference) {
                $attempt->update(['status' => 'Failed']);
            }

            return null;
        }
        if (($data['reference'] ?? null) !== $reference || ($data['currency'] ?? null) !== 'NGN'
            || ! is_numeric($data['amount'] ?? null) || (int) $data['amount'] !== (int) $attempt->amount_kobo) {
            throw ValidationException::withMessages(['payment' => 'Verified transaction does not match this invoice.']);
        }

        return $this->settle($attempt->invoice()->firstOrFail(), 'Online', $reference, null);
    }

    private function settle(Invoice $invoice, string $method, ?string $reference, ?User $actor): Payment
    {
        return DB::transaction(function () use ($invoice, $method, $reference, $actor): Payment {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $existing = Payment::where('invoice_id', $locked->id)->first();
            if ($existing) {
                if ($method === 'Online' && $existing->gateway_reference !== $reference) {
                    throw ValidationException::withMessages(['payment' => 'This invoice was settled by another transaction.']);
                }

                return $existing;
            }
            if ($locked->status !== 'Unpaid') {
                throw ValidationException::withMessages(['payment' => 'This invoice cannot be settled.']);
            }
            $payment = Payment::create([
                'invoice_id' => $locked->id, 'amount_kobo' => $locked->amount_kobo,
                'method' => $method, 'gateway_reference' => $reference,
                'confirmed_by' => $actor?->id, 'paid_at' => now(),
                'receipt_number' => 'OAK-'.app(TenantContext::class)->id().'-'.str_pad((string) $locked->id, 8, '0', STR_PAD_LEFT),
            ]);
            $locked->update(['status' => 'Paid']);
            if ($reference) {
                PaymentAttempt::where('reference', $reference)->update(['status' => 'Verified']);
            }
            if ($locked->candidate_id && $locked->purpose === 'Registration') {
                app(BillingRecipients::class)->confirmRegistration($locked->candidate_id, $actor);
            }
            $message = 'Your '.$locked->purpose.' payment was confirmed. Receipt: '.$payment->receipt_number.'.';
            if ($locked->student_id) {
                app(InAppNotifications::class)->sendForStudent(
                    $locked->student_id,
                    app(BillingRecipients::class)->userIdFor(null, $locked->student_id),
                    app(StudentDirectory::class)->names([$locked->student_id])[$locked->student_id],
                    'Payment confirmed', $message,
                );
            } else {
                app(InAppNotifications::class)->send([app(BillingRecipients::class)->userIdFor($locked->candidate_id, null)], 'Payment confirmed', $message);
            }

            return $payment;
        });
    }
}
