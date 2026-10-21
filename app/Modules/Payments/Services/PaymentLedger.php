<?php

namespace App\Modules\Payments\Services;

use App\Modules\Academics\Services\AcademicCalendar;
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
    public function invoiceCountForStudents(array $studentIds): int
    {
        return $studentIds === [] ? 0 : Invoice::whereIn('student_id', $studentIds)->count();
    }

    public function outstandingKobo(?User $viewer = null, ?int $sectionId = null): int
    {
        $query = Invoice::where('status', 'Unpaid')->where('kind', Invoice::KIND_TERM_FEE);
        if ($viewer) {
            $armIds = app(SectionAccess::class)->visibleArmIds($viewer, $sectionId);
            if ($armIds !== null) {
                $studentIds = Student::whereIn('class_arm_id', $armIds)->select('id');
                $query->whereIn('student_id', $studentIds);
            }
        }

        return (int) $query->sum('amount_kobo');
    }

    public function dashboardSummary(): array
    {
        $term = app(AcademicCalendar::class)->currentTerm();

        if (! $term) {
            return [
                'term' => null,
                'outstanding_kobo' => 0,
                'collected_kobo' => 0,
                'recent' => collect(),
                'not_invoiced_count' => 0,
            ];
        }

        return [
            'term' => trim($term->name.' · '.($term->session?->name ?? '')),
            'outstanding_kobo' => (int) Invoice::where('term_id', $term->id)
                ->where('kind', Invoice::KIND_TERM_FEE)->where('status', 'Unpaid')->sum('amount_kobo'),
            'collected_kobo' => (int) Payment::whereHas('invoice', fn ($query) => $query->where('term_id', $term->id))
                ->sum('amount_kobo'),
            'recent' => Payment::with(['invoice.student.user', 'invoice.candidate'])
                ->whereHas('invoice', fn ($query) => $query->where('term_id', $term->id))
                ->latest('paid_at')->limit(4)->get(),
            'not_invoiced_count' => Student::active()->whereNotIn('id', Invoice::where('kind', Invoice::KIND_TERM_FEE)
                ->where('term_id', $term->id)->select('student_id'))->count(),
        ];
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
        $latestTermInvoice = Invoice::where('student_id', $studentId)->where('kind', Invoice::KIND_TERM_FEE)
            ->latest('id')->first();
        $latestUnpaidTuition = $latestTermInvoice?->status === 'Unpaid' ? $latestTermInvoice : null;

        return [
            'recent' => $invoices,
            'outstanding_kobo' => $this->termFeeOutstandingKobo($studentId),
            'latest_unpaid_tuition' => $latestUnpaidTuition,
            'latest_unpaid_tuition_payable' => $latestUnpaidTuition && PaymentAttempt::where('invoice_id', $latestUnpaidTuition->id)
                ->latest('id')->value('status') !== 'Pending',
        ];
    }

    public function portalInvoices(int $studentId, User $viewer): array
    {
        app(ResultPaymentAccess::class)->authorizeOwnership($studentId, $viewer);
        $readOnly = ! app(StudentDirectory::class)->isActive($studentId);
        $rows = Invoice::with(['payment', 'attempts'])->where('student_id', $studentId)
            ->when($readOnly, fn ($query) => $query->where('status', 'Paid')->whereHas('payment'))
            ->latest('id')->paginate(20)->withQueryString();

        return [
            'rows' => $rows,
            'terms' => app(ResultAcademicCatalog::class)->labels([], $rows->pluck('term_id')->filter()->all())['terms'],
            'outstanding_kobo' => $readOnly ? 0 : $this->termFeeOutstandingKobo($studentId),
            'read_only' => $readOnly,
        ];
    }

    public function portalReceiptSummary(int $studentId, User $viewer): array
    {
        app(ResultPaymentAccess::class)->authorizeOwnership($studentId, $viewer);

        return [
            'recent' => Invoice::with('payment')->where('student_id', $studentId)
                ->where('status', 'Paid')->whereHas('payment')->latest('id')->limit(5)->get(),
            'outstanding_kobo' => 0,
            'latest_unpaid_tuition' => null,
            'latest_unpaid_tuition_payable' => false,
            'read_only' => true,
        ];
    }

    public function wardFeesDue(int $studentId): bool
    {
        return $this->wardFeesDueByStudentIds([$studentId])[$studentId] ?? false;
    }

    public function termFeeOutstandingKobo(int $studentId): int
    {
        return $this->termFeeOutstandingKoboByStudentIds([$studentId])[$studentId] ?? 0;
    }

    /** @return array<int, int> */
    private function termFeeOutstandingKoboByStudentIds(array $studentIds): array
    {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));
        $outstanding = array_fill_keys($studentIds, 0);
        if ($studentIds === []) {
            return $outstanding;
        }

        $invoices = Invoice::whereIn('student_id', $studentIds)->where('kind', Invoice::KIND_TERM_FEE)
            ->get(['id', 'student_id', 'amount_kobo', 'status']);
        if ($invoices->isEmpty()) {
            return $outstanding;
        }

        $recorded = Payment::whereIn('invoice_id', $invoices->pluck('id'))
            ->selectRaw('invoice_id, SUM(amount_kobo) as paid_kobo')
            ->groupBy('invoice_id')
            ->pluck('paid_kobo', 'invoice_id');

        foreach ($invoices as $invoice) {
            if ($invoice->status !== 'Paid') {
                $outstanding[$invoice->student_id] += max(0, $invoice->amount_kobo - (int) ($recorded[$invoice->id] ?? 0));
            }
        }

        return $outstanding;
    }

    public function wardFeesDueByStudentIds(array $studentIds): array
    {
        return array_map(fn (int $outstanding): bool => $outstanding > 0, $this->termFeeOutstandingKoboByStudentIds($studentIds));
    }

    public function invoice(array $data, User $actor): Invoice
    {
        if (((int) ! empty($data['student_id']) + (int) ! empty($data['candidate_id'])) !== 1
            || (int) ($data['amount_kobo'] ?? 0) < 1) {
            throw ValidationException::withMessages(['invoice' => 'Select exactly one student or candidate and a positive amount.']);
        }

        $kind = match ($data['purpose'] ?? null) {
            'Tuition' => Invoice::KIND_TERM_FEE,
            'Registration' => Invoice::KIND_REGISTRATION,
            'Offer Deposit' => Invoice::KIND_OFFER_DEPOSIT,
            default => Invoice::KIND_ONE_OFF,
        };

        return DB::transaction(function () use ($data, $kind, $actor): Invoice {
            $invoice = Invoice::create($data + [
                'kind' => $kind, 'term_fee_student_id' => $kind === Invoice::KIND_TERM_FEE ? $data['student_id'] : null,
                'creation_source' => 'single', 'created_by' => $actor->id, 'status' => 'Unpaid',
            ]);
            $invoice->lines()->create([
                'fee_item_id' => null, 'description' => $invoice->purpose,
                'amount_kobo' => $invoice->amount_kobo, 'is_required' => true,
            ]);

            return $invoice;
        });
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
        if (! $actor->can('manage_payments') && $invoice->student_id
            && ! app(StudentDirectory::class)->isActive($invoice->student_id)) {
            throw ValidationException::withMessages(['payment' => 'Online payment is unavailable while this student has read-only access.']);
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
