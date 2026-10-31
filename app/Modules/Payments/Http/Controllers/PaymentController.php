<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admissions\Services\BillingRecipients;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Payments\Models\Expense;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\ManualPaymentSubmission;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Payments\Services\InvoiceLifecycle;
use App\Modules\Payments\Services\ManualPaymentService;
use App\Modules\Payments\Services\PaymentLedger;
use App\Modules\Payments\Services\PaystackCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(PaystackCredentials $credentials): View
    {
        return view('payments.index', [
            'invoices' => Invoice::latest()->paginate(20, ['*'], 'invoices_page'),
            'payments' => Payment::with('invoice')->latest()->paginate(20, ['*'], 'payments_page'),
            'expenses' => Expense::latest()->paginate(20, ['*'], 'expenses_page'),
            'paymentRule' => SchoolSetting::first()?->result_access_payment_rule ?? 'TermInvoicePaid',
            'onlinePaymentsAvailable' => $credentials->isConfigured(),
        ]);
    }

    public function show(Invoice $invoice, InvoiceLifecycle $lifecycle, PaystackCredentials $credentials): View
    {
        $invoice->load([
            'student.user', 'student.classArm.schoolClass', 'candidate', 'lines', 'payment', 'attempts',
            'awaitingManualSubmission', 'parentInvoice', 'partPayments.payment', 'partPayments.attempts',
            'partPayments.awaitingManualSubmission',
        ]);

        return view('payments.invoice-detail', [
            'invoice' => $invoice,
            'paidKobo' => $lifecycle->paidKobo($invoice),
            'balanceKobo' => $lifecycle->balanceKobo($invoice),
            'openPartKobo' => $lifecycle->openPartKobo($invoice),
            'allocatableKobo' => $lifecycle->allocatableKobo($invoice),
            'displayState' => $lifecycle->displayState($invoice),
            'history' => $lifecycle->history($invoice),
            'onlinePaymentsAvailable' => $credentials->isConfigured(),
        ]);
    }

    public function part(Invoice $invoice, Request $request, InvoiceLifecycle $lifecycle): RedirectResponse
    {
        $data = $request->validate([
            'amount_naira' => ['required', 'regex:/^\s*(?:\d{1,3}(?:,\d{3})*|\d+)(?:\.\d{1,2})?\s*$/'],
            'due_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $lifecycle->createPart($invoice, $this->nairaToKobo($data['amount_naira']), $data['due_date'], $data['note'] ?? null, $request->user());

        return redirect()->route('payments.invoices.show', $invoice)
            ->with('status', 'Part-payment created. It now has its own payable balance and receipt.');
    }

    public function voidPart(Invoice $invoice, Request $request, InvoiceLifecycle $lifecycle): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $lifecycle->voidPart($invoice, $data['reason'] ?? null, $request->user());

        return redirect()->route('payments.invoices.show', $invoice->parent_invoice_id ?: $invoice->id)
            ->with('status', 'Part-payment voided. Its amount is available to allocate again.');
    }

    public function adjustment(Invoice $invoice, Request $request, InvoiceLifecycle $lifecycle): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:120'],
            'amount_naira' => ['required', 'regex:/^\s*-?(?:\d{1,3}(?:,\d{3})*|\d+)(?:\.\d{1,2})?\s*$/', 'not_in:0,0.00'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $lifecycle->addAdjustment($invoice, $data['description'], $this->signedNairaToKobo($data['amount_naira']), $data['reason'], $request->user());

        return redirect()->route('payments.invoices.show', $invoice)
            ->with('status', 'Adjustment added. The original invoice lines were not changed.');
    }

    public function voidInvoice(Invoice $invoice, Request $request, InvoiceLifecycle $lifecycle): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $lifecycle->voidInvoice($invoice, $data['reason'], $request->user());

        return redirect()->route('payments.invoices.show', $invoice)->with('status', 'Invoice voided.');
    }

    public function invoice(Request $request, PaymentLedger $ledger): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['nullable', 'integer'], 'candidate_id' => ['nullable', 'integer'],
            'term_id' => ['nullable', 'integer'], 'purpose' => ['required', Rule::in(['Registration', 'Offer Deposit'])],
            'amount_naira' => ['required', 'regex:/^[0-9,]+$/'], 'due_date' => ['required', 'date'],
        ]);
        $data['amount_kobo'] = (int) str_replace(',', '', $data['amount_naira']) * 100;
        unset($data['amount_naira']);
        $data['term_id'] = null;
        $ledger->invoice($data, $request->user());

        return redirect()->route('payments.index')->with('status', 'Invoice created.');
    }

    public function record(Request $request): View
    {
        $query = trim((string) $request->query('q'));
        $invoices = Invoice::with(['student.user', 'student.registrationHistory', 'student.classArm.schoolClass', 'candidate'])
            ->whereIn('kind', [Invoice::KIND_TERM_FEE, Invoice::KIND_PART_PAYMENT, Invoice::KIND_ONE_OFF])
            ->where('status', 'Unpaid')
            ->where(function ($builder): void {
                $builder->where('kind', '!=', Invoice::KIND_TERM_FEE)
                    ->orWhereDoesntHave('partPayments', fn ($parts) => $parts->where('status', 'Unpaid'));
            })
            ->whereDoesntHave('manualSubmissions', fn ($builder) => $builder->where('status', ManualPaymentSubmission::STATUS_AWAITING))
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($owner) use ($query): void {
                    $needle = mb_strtolower($query);
                    $owner->whereHas('student.user', fn ($user) => $user
                        ->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw('LOWER(username) LIKE ?', ["%{$needle}%"]))
                        ->orWhereHas('student', fn ($student) => $student
                            ->whereRaw('LOWER(registration_number) LIKE ?', ["%{$needle}%"])
                            ->orWhereHas('registrationHistory', fn ($history) => $history
                                ->whereRaw('LOWER(new_number) LIKE ?', ["%{$needle}%"])
                                ->orWhereRaw('LOWER(old_number) LIKE ?', ["%{$needle}%"])))
                        ->orWhereHas('candidate', fn ($candidate) => $candidate->where('name', 'like', "%{$query}%"));
                });
            })->latest('id')->paginate(20)->withQueryString();

        $selected = $request->integer('invoice')
            ? Invoice::with(['student.user', 'student.classArm.schoolClass', 'candidate'])->findOrFail($request->integer('invoice'))
            : null;

        return view('payments.record', compact('invoices', 'selected', 'query'));
    }

    public function manual(int $invoice, Request $request, ManualPaymentService $manual): RedirectResponse
    {
        $data = $request->validate([
            'bank' => ['required', 'string', 'max:120'],
            'teller_number' => ['required', 'string', 'max:120'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'amount_naira' => ['required', 'regex:/^\s*(?:\d{1,3}(?:,\d{3})*|\d+)(?:\.\d{1,2})?\s*$/'],
            'photo' => ['nullable', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max(ManualPaymentService::PHOTO_MAX_KILOBYTES)],
        ]);
        $data['amount_kobo'] = $this->nairaToKobo($data['amount_naira']);
        $result = $manual->record(Invoice::findOrFail($invoice), $data, $request->user(), $request->file('photo'));

        return redirect()->route('payments.record.index')->with(
            'status', $result['approved'] ? 'Recorded and approved. One receipt was issued.' : 'Payment recorded and waiting for Admin approval.'
        );
    }

    public function approvals(Request $request): View
    {
        $waiting = ManualPaymentSubmission::with([
            'invoice.student.user', 'invoice.student.classArm.schoolClass', 'invoice.candidate', 'recorder',
        ])->where('status', ManualPaymentSubmission::STATUS_AWAITING)->oldest()->paginate(20);
        $rejected = ManualPaymentSubmission::with(['invoice.student.user', 'invoice.candidate', 'recorder', 'decider'])
            ->where('status', ManualPaymentSubmission::STATUS_REJECTED)->latest('decided_at')->limit(10)->get();

        return view('payments.approvals', [
            'waiting' => $waiting,
            'rejected' => $rejected,
            'oneStep' => (bool) (SchoolSetting::first()?->one_step_payment_approval ?? false),
            'canApprove' => $request->user()->can('approve_payments'),
        ]);
    }

    public function approve(ManualPaymentSubmission $submission, Request $request, ManualPaymentService $manual): RedirectResponse
    {
        $result = $manual->approve($submission, $request->user());

        return redirect()->route('payments.approvals.index')->with('status', $result['handled']
            ? 'Already handled. Nothing was changed and the list is up to date.'
            : 'Approved. The invoice is Paid and one receipt was issued.');
    }

    public function reject(ManualPaymentSubmission $submission, Request $request, ManualPaymentService $manual): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $changed = $manual->reject($submission, $request->user(), $data['reason']);

        return redirect()->route('payments.approvals.index')->with('status', $changed
            ? 'Rejected. The invoice is still unpaid and the teller number can be recorded again.'
            : 'Already handled. Nothing was changed and the list is up to date.');
    }

    public function oneStep(Request $request, ManualPaymentService $manual): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $manual->setOneStep((bool) $data['enabled'], $request->user());

        return redirect()->route('payments.approvals.index')->with('status', 'One-step approval is now '.($data['enabled'] ? 'on.' : 'off.'));
    }

    public function slip(ManualPaymentSubmission $submission, Request $request)
    {
        abort_unless($request->user()->can('manage_payments') && $submission->photo_path, 404);

        return Storage::disk('local')->download($submission->photo_path);
    }

    public function begin(int $invoice, Request $request, PaymentLedger $ledger): RedirectResponse
    {
        $attempt = $ledger->beginPaystack(Invoice::findOrFail($invoice), $request->user());

        return redirect()->away($attempt->authorization_url);
    }

    public function verify(Request $request, PaymentLedger $ledger): RedirectResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:80']]);
        $ledger->verifyPaystack($data['reference']);

        $attempt = PaymentAttempt::with('invoice')->where('reference', $data['reference'])->first();
        $invoice = $attempt?->invoice;
        if ($request->user() && $invoice?->student_id
            && app(BillingRecipients::class)->owns($invoice->candidate_id, $invoice->student_id, $request->user())) {
            return redirect()->route('payments.mine.index', ['student' => $invoice->student_id])
                ->with('status', 'Payment verification checked. Your invoice status is shown below.');
        }

        return redirect()->route('home')->with('status', 'Payment verification checked.');
    }

    public function expense(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'], 'category' => ['required', 'string', 'max:100'],
            'amount_kobo' => ['required', 'integer', 'min:1'], 'vendor' => ['required', 'string', 'max:255'],
        ]);
        Expense::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('payments.index')->with('status', 'Expense recorded.');
    }

    public function rule(Request $request): RedirectResponse
    {
        $data = $request->validate(['result_access_payment_rule' => ['required', Rule::in(['None', 'TermInvoicePaid'])]]);
        SchoolSetting::firstOrCreate([])->update($data);

        return redirect()->route('payments.index')->with('status', 'Result access payment rule updated.');
    }

    private function nairaToKobo(string $amount): int
    {
        $normalised = str_replace([',', ' '], '', trim($amount));
        [$naira, $fraction] = array_pad(explode('.', $normalised, 2), 2, '');

        return ((int) $naira * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function signedNairaToKobo(string $amount): int
    {
        $normalised = str_replace([',', ' '], '', trim($amount));
        $negative = str_starts_with($normalised, '-');
        $kobo = $this->nairaToKobo(ltrim($normalised, '-'));

        return $negative ? -$kobo : $kobo;
    }
}
