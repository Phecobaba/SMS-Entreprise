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
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Core\TenantContext;
use App\Modules\Notifications\Services\InAppNotifications;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\ManualPaymentSubmission;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentAttempt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentLedger
{
    public const PAYSTACK_PAID = 'Paid';

    public const PAYSTACK_FAILED = 'Failed';

    public const PAYSTACK_CONFIRMING = 'Still confirming';

    public function invoiceCountForStudents(array $studentIds): int
    {
        return $studentIds === [] ? 0 : Invoice::whereIn('student_id', $studentIds)->count();
    }

    public function outstandingKobo(?User $viewer = null, ?int $sectionId = null): int
    {
        $query = Invoice::with(['payment', 'partPayments.payment'])
            ->where('status', '!=', 'Voided')->where('kind', Invoice::KIND_TERM_FEE);
        if ($viewer) {
            $armIds = app(SectionAccess::class)->visibleArmIds($viewer, $sectionId);
            if ($armIds !== null) {
                $studentIds = Student::whereIn('class_arm_id', $armIds)->select('id');
                $query->whereIn('student_id', $studentIds);
            }
        }

        return (int) $query->get()->sum(fn (Invoice $invoice): int => app(InvoiceLifecycle::class)->balanceKobo($invoice));
    }

    public function dashboardSummary(): array
    {
        $term = app(AcademicCalendar::class)->termForSummary();

        if (! $term) {
            return [
                'term' => null,
                'outstanding_kobo' => 0,
                'collected_kobo' => 0,
                'recent' => collect(),
                'not_invoiced_count' => 0,
                'awaiting_approval_count' => ManualPaymentSubmission::where('status', ManualPaymentSubmission::STATUS_AWAITING)->count(),
            ];
        }

        return [
            'term' => trim($term->name.' · '.($term->session?->name ?? '')),
            'outstanding_kobo' => (int) Invoice::with(['payment', 'partPayments.payment'])->where('term_id', $term->id)
                ->where('kind', Invoice::KIND_TERM_FEE)->where('status', '!=', 'Voided')->get()
                ->sum(fn (Invoice $invoice): int => app(InvoiceLifecycle::class)->balanceKobo($invoice)),
            'collected_kobo' => (int) Payment::whereHas('invoice', fn ($query) => $query->where('term_id', $term->id))
                ->sum('amount_kobo'),
            'recent' => Payment::with(['invoice.student.user', 'invoice.candidate'])
                ->whereHas('invoice', fn ($query) => $query->where('term_id', $term->id))
                ->latest('paid_at')->limit(4)->get(),
            'not_invoiced_count' => Student::active()->whereNotIn('id', Invoice::where('kind', Invoice::KIND_TERM_FEE)
                ->where('term_id', $term->id)->select('student_id'))->count(),
            'awaiting_approval_count' => ManualPaymentSubmission::where('status', ManualPaymentSubmission::STATUS_AWAITING)->count(),
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
        $invoices = Invoice::with(['lines', 'payment', 'attempts', 'awaitingManualSubmission', 'partPayments.payment', 'partPayments.attempts', 'partPayments.awaitingManualSubmission'])
            ->where('student_id', $studentId)->whereNull('parent_invoice_id')->latest('id')->limit(5)->get();
        $latestTermInvoice = Invoice::with(['awaitingManualSubmission', 'partPayments'])->where('student_id', $studentId)->where('kind', Invoice::KIND_TERM_FEE)
            ->latest('id')->first();
        $latestUnpaidTuition = $latestTermInvoice?->status === 'Unpaid' ? $latestTermInvoice : null;

        return [
            'recent' => $invoices,
            'outstanding_kobo' => $this->termFeeOutstandingKobo($studentId),
            'latest_unpaid_tuition' => $latestUnpaidTuition,
            'latest_unpaid_tuition_payable' => $latestUnpaidTuition && ! $latestUnpaidTuition->awaitingManualSubmission
                && ! app(InvoiceLifecycle::class)->hasOpenParts($latestUnpaidTuition)
                && app(PaystackCredentials::class)->isConfigured()
                && PaymentAttempt::where('invoice_id', $latestUnpaidTuition->id)
                    ->latest('id')->value('status') !== 'Pending',
            'latest_unpaid_tuition_payable_kobo' => $latestUnpaidTuition
                ? app(InvoiceLifecycle::class)->balanceKobo($latestUnpaidTuition) : null,
        ];
    }

    public function portalInvoices(int $studentId, User $viewer): array
    {
        app(ResultPaymentAccess::class)->authorizeOwnership($studentId, $viewer);
        $readOnly = ! app(StudentDirectory::class)->isActive($studentId);
        $rows = Invoice::with(['lines', 'payment', 'attempts', 'awaitingManualSubmission', 'partPayments.payment', 'partPayments.attempts', 'partPayments.awaitingManualSubmission'])
            ->where('student_id', $studentId)->whereNull('parent_invoice_id')
            ->when($readOnly, fn ($query) => $query->where('status', 'Paid')->where(function ($paid) {
                $paid->whereHas('payment')->orWhereHas('partPayments.payment');
            }))
            ->latest('id')->paginate(20)->withQueryString();

        return [
            'rows' => $rows,
            'terms' => app(ResultAcademicCatalog::class)->labels([], $rows->pluck('term_id')->filter()->all())['terms'],
            'outstanding_kobo' => $readOnly ? 0 : $this->termFeeOutstandingKobo($studentId),
            'read_only' => $readOnly,
            'online_payments_available' => app(PaystackCredentials::class)->isConfigured(),
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
            'latest_unpaid_tuition_payable_kobo' => null,
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

    /** @return array<int, array{status: string, outstanding_kobo: int}> */
    public function termFeeStatusByStudentIds(array $studentIds): array
    {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));
        $statuses = array_fill_keys($studentIds, ['status' => 'No invoice', 'outstanding_kobo' => 0]);
        if ($studentIds === []) {
            return $statuses;
        }

        $invoices = Invoice::with(['payment', 'partPayments.payment'])->whereIn('student_id', $studentIds)
            ->where('kind', Invoice::KIND_TERM_FEE)->where('status', '!=', 'Voided')->get();
        $lifecycle = app(InvoiceLifecycle::class);
        foreach ($invoices->groupBy('student_id') as $studentId => $rows) {
            $outstanding = (int) $rows->sum(fn (Invoice $invoice): int => $lifecycle->balanceKobo($invoice));
            $paid = (int) $rows->sum(fn (Invoice $invoice): int => $lifecycle->paidKobo($invoice));
            $statuses[(int) $studentId] = [
                'status' => $outstanding === 0 ? 'Paid' : ($paid > 0 ? 'Part-paid' : 'Owing'),
                'outstanding_kobo' => $outstanding,
            ];
        }

        return $statuses;
    }

    /** @return array<int, int> */
    private function termFeeOutstandingKoboByStudentIds(array $studentIds): array
    {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));
        $outstanding = array_fill_keys($studentIds, 0);
        if ($studentIds === []) {
            return $outstanding;
        }

        $invoices = Invoice::with(['payment', 'partPayments.payment'])->whereIn('student_id', $studentIds)
            ->where('kind', Invoice::KIND_TERM_FEE)->where('status', '!=', 'Voided')->get();
        if ($invoices->isEmpty()) {
            return $outstanding;
        }

        foreach ($invoices as $invoice) {
            $outstanding[$invoice->student_id] += app(InvoiceLifecycle::class)->balanceKobo($invoice);
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

    public function settleManualSubmission(Invoice $invoice, int $amountKobo, User $actor): Payment
    {
        if ($invoice->kind === Invoice::KIND_TERM_FEE && app(InvoiceLifecycle::class)->hasOpenParts($invoice)) {
            throw ValidationException::withMessages(['payment' => 'Pay the open part-payment first, or ask the Bursar to void it.']);
        }
        if ($amountKobo !== app(InvoiceLifecycle::class)->balanceKobo($invoice)) {
            throw ValidationException::withMessages(['payment' => 'The recorded amount does not match the invoice balance.']);
        }

        return $this->settle($invoice, $amountKobo, 'Manual', null, $actor);
    }

    public function beginPaystack(Invoice $invoice, User $actor): PaymentAttempt
    {
        $secret = app(PaystackCredentials::class)->secretKeyOrFail();
        if ($invoice->status !== 'Unpaid') {
            throw ValidationException::withMessages(['payment' => 'This invoice is already paid.']);
        }
        if ($invoice->manualSubmissions()->where('status', ManualPaymentSubmission::STATUS_AWAITING)->exists()) {
            throw ValidationException::withMessages(['payment' => 'The recorded bank payment is awaiting approval. Do not pay this invoice again.']);
        }
        if ($invoice->kind === Invoice::KIND_TERM_FEE && app(InvoiceLifecycle::class)->hasOpenParts($invoice)) {
            throw ValidationException::withMessages(['payment' => 'Pay the open part-payment first, or ask the Bursar to void it.']);
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
        $payableKobo = app(InvoiceLifecycle::class)->balanceKobo($invoice);
        $attempt = PaymentAttempt::create([
            'invoice_id' => $invoice->id, 'reference' => $reference,
            'amount_kobo' => $payableKobo, 'initiated_by' => $actor->id,
            'status' => 'Pending',
        ]);
        try {
            $httpResponse = Http::withToken($secret)->acceptJson()->connectTimeout(5)->timeout(15)
                ->post(rtrim(config('services.paystack.base_url'), '/').'/transaction/initialize', [
                    'email' => $email, 'amount' => $payableKobo,
                    'currency' => 'NGN', 'reference' => $reference,
                    'callback_url' => route('payments.verify'),
                ]);
        } catch (ConnectionException $exception) {
            $this->failPaystackInitialisation($attempt, 'network_error', exception: $exception);
        } catch (\Throwable $exception) {
            $this->failPaystackInitialisation($attempt, 'unexpected_error', exception: $exception);
        }
        if (! $httpResponse->successful()) {
            $this->failPaystackInitialisation(
                $attempt,
                $httpResponse->status() === 401 ? 'credentials_rejected' : 'http_error',
                $httpResponse->status(),
                $httpResponse->json('message'),
            );
        }
        $response = $httpResponse->json();
        if (($response['status'] ?? false) !== true || ! is_string($response['data']['authorization_url'] ?? null)) {
            $this->failPaystackInitialisation($attempt, 'invalid_response', $httpResponse->status(), $response['message'] ?? null);
        }
        $attempt->update(['authorization_url' => $response['data']['authorization_url']]);

        return $attempt;
    }

    private function failPaystackInitialisation(
        PaymentAttempt $attempt,
        string $category,
        ?int $httpStatus = null,
        mixed $paystackMessage = null,
        ?\Throwable $exception = null,
    ): never {
        $attempt->update(['status' => 'Failed']);
        Log::warning('Paystack payment initialisation failed.', [
            'category' => $category,
            'http_status' => $httpStatus,
            'paystack_message' => is_string($paystackMessage) ? $paystackMessage : null,
            'exception_class' => $exception ? $exception::class : null,
            'exception_message' => $exception ? $this->safeExternalMessage($exception->getMessage()) : null,
        ]);

        throw ValidationException::withMessages([
            'payment' => 'We could not start the payment. Please try again, or contact the school office.',
        ]);
    }

    public function verifyPaystack(string $reference): ?Payment
    {
        return $this->verifyPaystackResult($reference)['payment'];
    }

    /** @return array{status: string, payment: ?Payment} */
    public function verifyPaystackResult(string $reference): array
    {
        $secret = app(PaystackCredentials::class)->secretKeyOrFail();
        $attempt = PaymentAttempt::where('reference', $reference)->firstOrFail();
        $existing = Payment::where('invoice_id', $attempt->invoice_id)->first();
        if ($existing) {
            $attempt->update(['status' => 'Verified']);

            return ['status' => self::PAYSTACK_PAID, 'payment' => $existing];
        }
        if ($attempt->status === 'Failed') {
            return ['status' => self::PAYSTACK_FAILED, 'payment' => null];
        }
        try {
            $response = Http::withToken($secret)->acceptJson()->connectTimeout(5)->timeout(15)
                ->get(rtrim(config('services.paystack.base_url'), '/').'/transaction/verify/'.rawurlencode($reference))
                ->throw()->json();
        } catch (ConnectionException $exception) {
            Log::warning('Paystack payment verification could not reach the provider.', [
                'category' => 'network_error',
                'exception_class' => $exception::class,
                'exception_message' => $this->safeExternalMessage($exception->getMessage()),
            ]);

            return ['status' => self::PAYSTACK_CONFIRMING, 'payment' => null];
        } catch (RequestException $exception) {
            Log::warning('Paystack payment verification was not accepted by the provider.', [
                'category' => 'http_error',
                'http_status' => $exception->response->status(),
                'paystack_message' => is_string($exception->response->json('message')) ? $exception->response->json('message') : null,
                'exception_class' => $exception::class,
                'exception_message' => $this->safeExternalMessage($exception->getMessage()),
            ]);

            return ['status' => self::PAYSTACK_CONFIRMING, 'payment' => null];
        }
        $data = $response['data'] ?? [];
        if (($response['status'] ?? false) !== true || ($data['status'] ?? null) !== 'success') {
            if (($response['status'] ?? false) === true && ($data['status'] ?? null) === 'failed'
                && ($data['reference'] ?? null) === $reference) {
                $attempt->update(['status' => 'Failed']);

                return ['status' => self::PAYSTACK_FAILED, 'payment' => null];
            }

            return ['status' => self::PAYSTACK_CONFIRMING, 'payment' => null];
        }
        if (($data['reference'] ?? null) !== $reference || ($data['currency'] ?? null) !== 'NGN'
            || ! is_numeric($data['amount'] ?? null) || (int) $data['amount'] !== (int) $attempt->amount_kobo) {
            throw ValidationException::withMessages(['payment' => 'Verified transaction does not match this invoice.']);
        }

        $payment = $this->settle(
            $attempt->invoice()->firstOrFail(),
            (int) $attempt->amount_kobo,
            'Online',
            $reference,
            User::findOrFail($attempt->initiated_by),
        );

        return ['status' => self::PAYSTACK_PAID, 'payment' => $payment];
    }

    public function checkPaystackAgain(PaymentAttempt $attempt, User $actor): string
    {
        $attempt->loadMissing('invoice');
        $invoice = $attempt->invoice;
        $staff = $actor->can('manage_payments');
        if (! $staff && ! app(BillingRecipients::class)->owns($invoice->candidate_id, $invoice->student_id, $actor)) {
            abort(403);
        }

        DB::transaction(function () use ($attempt): void {
            $locked = PaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->last_checked_at?->gt(now()->subSeconds(30))) {
                throw ValidationException::withMessages(['payment' => 'Please wait 30 seconds before checking this payment again.']);
            }
            $locked->update(['last_checked_at' => now()]);
        });

        $result = $this->verifyPaystackResult($attempt->reference);
        if ($staff) {
            app(AuditRecorder::class)->record(
                $actor,
                'paystack_check_again',
                $attempt,
                ['reference' => $attempt->reference, 'result' => $result['status']],
                'Payments',
                'Checked a pending Paystack payment.',
            );
        }

        return $result['status'];
    }

    private function safeExternalMessage(string $message): string
    {
        $message = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[email redacted]', $message) ?? '';

        return Str::limit($message, 500, '...');
    }

    private function settle(Invoice $invoice, int $amountKobo, string $method, ?string $reference, User $actor): Payment
    {
        return DB::transaction(function () use ($invoice, $amountKobo, $method, $reference, $actor): Payment {
            if ($invoice->parent_invoice_id) {
                Invoice::whereKey($invoice->parent_invoice_id)->lockForUpdate()->firstOrFail();
            }
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
            if ($locked->kind === Invoice::KIND_TERM_FEE && app(InvoiceLifecycle::class)->hasOpenParts($locked)) {
                throw ValidationException::withMessages(['payment' => 'Pay the open part-payment first, or ask the Bursar to void it.']);
            }
            if ($amountKobo !== app(InvoiceLifecycle::class)->balanceKobo($locked)) {
                throw ValidationException::withMessages(['payment' => 'The payment amount no longer matches the invoice balance.']);
            }
            $payment = Payment::create([
                'invoice_id' => $locked->id, 'amount_kobo' => $amountKobo,
                'method' => $method, 'gateway_reference' => $reference,
                'confirmed_by' => $actor->id, 'paid_at' => now(),
                'receipt_number' => 'OAK-'.app(TenantContext::class)->id().'-'.str_pad((string) $locked->id, 8, '0', STR_PAD_LEFT),
            ]);
            $locked->update(['status' => 'Paid']);
            app(InvoiceLifecycle::class)->syncParentAfterSettlement($locked, $actor);
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
                    'Payment confirmed', $message, route('payments.mine.index', ['student' => $locked->student_id], false),
                );
            } else {
                app(InAppNotifications::class)->send([app(BillingRecipients::class)->userIdFor($locked->candidate_id, null)], 'Payment confirmed', $message);
            }

            return $payment;
        });
    }
}
