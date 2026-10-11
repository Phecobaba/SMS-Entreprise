<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admissions\Services\BillingRecipients;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Payments\Models\Expense;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Payments\Services\PaymentLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(): View
    {
        return view('payments.index', [
            'invoices' => Invoice::latest()->paginate(20, ['*'], 'invoices_page'),
            'payments' => Payment::with('invoice')->latest()->paginate(20, ['*'], 'payments_page'),
            'expenses' => Expense::latest()->paginate(20, ['*'], 'expenses_page'),
            'paymentRule' => SchoolSetting::first()?->result_access_payment_rule ?? 'TermInvoicePaid',
        ]);
    }

    public function invoice(Request $request, PaymentLedger $ledger): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['nullable', 'integer'], 'candidate_id' => ['nullable', 'integer'],
            'term_id' => ['nullable', 'integer'], 'purpose' => ['required', Rule::in(['Tuition', 'Registration', 'Offer Deposit'])],
            'amount_kobo' => ['required', 'integer', 'min:1'], 'due_date' => ['required', 'date'],
        ]);
        $ledger->invoice($data, $request->user());

        return back()->with('status', 'Invoice created.');
    }

    public function manual(int $invoice, Request $request, PaymentLedger $ledger): RedirectResponse
    {
        $ledger->manualPayment(Invoice::findOrFail($invoice), $request->user());

        return back()->with('status', 'Invoice settled and receipt recorded.');
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

        return back()->with('status', 'Expense recorded.');
    }

    public function rule(Request $request): RedirectResponse
    {
        $data = $request->validate(['result_access_payment_rule' => ['required', Rule::in(['None', 'TermInvoicePaid'])]]);
        SchoolSetting::firstOrCreate([])->update($data);

        return back()->with('status', 'Result access payment rule updated.');
    }
}
