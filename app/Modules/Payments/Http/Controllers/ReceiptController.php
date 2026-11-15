<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Services\ApplicationWorkflow;
use App\Modules\Admissions\Services\BillingRecipients;
use App\Modules\Assignments\Services\ParentAccountDirectory;
use App\Modules\Core\Models\User;
use App\Modules\Payments\Models\Receipt;
use App\Modules\Payments\Services\ReceiptDelivery;
use App\Modules\Payments\Services\ReceiptPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('manage_payments'), 403);

        return view('payments.receipts', ['receipts' => Receipt::latest('issued_at')->paginate(20)]);
    }

    public function show(Receipt $receipt, Request $request): View
    {
        abort_unless($request->user()->can('manage_payments'), 403);

        return view('payments.receipt-show', compact('receipt'));
    }

    public function download(Receipt $receipt, Request $request, ReceiptPdf $pdf): Response
    {
        $user = $request->user();
        abort_unless($user && ($user->can('manage_payments') || $this->owns($receipt, $user)), 403);

        return $this->pdf($receipt, $pdf);
    }

    public function application(string $application, Request $request, ApplicationWorkflow $workflow, ReceiptPdf $pdf): Response
    {
        $record = Application::where('public_reference', $application)->firstOrFail();
        $cookieOwner = $workflow->byToken($request->cookie(config('admissions.cookie')))?->is($record) ?? false;
        abort_unless($cookieOwner || $request->hasValidSignature(), 404);
        $receipt = $record->receipt()->latest('issued_at')->firstOrFail();

        return $this->pdf($receipt, $pdf);
    }

    public function resend(Receipt $receipt, Request $request, ReceiptDelivery $delivery): RedirectResponse
    {
        abort_unless($request->user()->can('manage_payments'), 403);
        $address = $this->email($receipt);
        if (! $address) {
            return back()->withErrors(['receipt' => 'No email address is available for this receipt.']);
        }
        $delivery->resend($receipt, $address, $request->user());

        return back()->with('status', 'Receipt email scheduled to send.');
    }

    private function owns(Receipt $receipt, User $user): bool
    {
        $receipt->loadMissing('payment.invoice');
        $invoice = $receipt->payment?->invoice;

        return $invoice && app(BillingRecipients::class)->owns($invoice->candidate_id, $invoice->student_id, $user);
    }

    private function email(Receipt $receipt): ?string
    {
        $receipt->loadMissing(['application', 'payment.invoice']);
        if ($receipt->application) {
            return $receipt->application->guardian_email;
        }
        $invoice = $receipt->payment?->invoice;
        if (! $invoice) {
            return null;
        }
        if ($invoice->student_id) {
            $ids = app(ParentAccountDirectory::class)->userIdsForStudents([$invoice->student_id]);
            $email = User::whereIn('id', $ids)->whereNotNull('email')->value('email');
            if ($email) {
                return $email;
            }
        }

        return app(BillingRecipients::class)->emailFor($invoice->candidate_id, $invoice->student_id);
    }

    private function pdf(Receipt $receipt, ReceiptPdf $pdf): Response
    {
        return response($pdf->bytes($receipt), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$receipt->number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
