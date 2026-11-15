@extends('portals.layout')
@section('title', 'Fees')
@section('content')
@php
    $money = fn (int $kobo) => '₦'.number_format($kobo / 100, $kobo % 100 ? 2 : 0);
    $lifecycle = app(\App\Modules\Payments\Services\InvoiceLifecycle::class);
    $needsPaymentEmail = $context['kind'] === 'parent' && blank(auth()->user()->email);
    $firstPayable = null;
    if (!$fees['read_only'] && $fees['online_payments_available']) {
        foreach ($fees['rows'] as $candidateInvoice) {
            $firstPayable = $candidateInvoice->partPayments->first(function ($part) {
                return $part->status === 'Unpaid' && !$part->awaitingManualSubmission
                    && $part->attempts->sortByDesc('id')->first()?->status !== 'Pending';
            });
            if (!$firstPayable && $candidateInvoice->status === 'Unpaid'
                && !$candidateInvoice->awaitingManualSubmission
                && !$candidateInvoice->partPayments->contains('status', 'Unpaid')
                && $candidateInvoice->attempts->sortByDesc('id')->first()?->status !== 'Pending') {
                $firstPayable = $candidateInvoice;
            }
            if ($firstPayable) break;
        }
    }
@endphp
<div class="oak-section-head"><div><h1>{{ $fees['read_only'] ? 'Receipts' : 'Fees' }}</h1><p class="oak-subpage-note">{{ $fees['read_only'] ? 'Read-only payment history' : (($context['kind'] === 'parent' ? $context['selected']['name']."'s invoices" : 'Your invoices').' and payment status') }}</p></div></div>
@if($context['selected']['registration_number'] ?? null)<p><strong>Registration number:</strong> {{ $context['selected']['registration_number'] }}</p>@endif
@if(session('status'))<div class="oak-notice oak-notice-success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="oak-notice oak-notice-danger" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if($fees['read_only'])<div class="oak-notice oak-notice-info"><x-icon name="lock" /><div><strong>Read-only access</strong><p>Only past paid receipts are available for this student.</p></div></div>@endif
@unless($fees['read_only'])
<div class="fe-summary"><div><span>Outstanding</span><strong>{{ $money($fees['outstanding_kobo']) }}</strong></div>@if($firstPayable)<button class="oak-btn oak-btn-primary" type="button" data-open-sheet="pay-sheet-{{ $firstPayable->id }}">Pay {{ $money($firstPayable->parent_invoice_id ? $firstPayable->amount_kobo : $lifecycle->balanceKobo($firstPayable)) }}</button>@endif</div>
@if(!$fees['online_payments_available'])<div class="oak-notice oak-notice-info"><div><strong>Online payment isn't set up yet</strong><p>You can't pay online right now. To pay, please visit the bursar with your bank teller.</p></div></div>@endif
@endunless
<section class="oak-section"><div class="oak-section-head"><h2>Invoices</h2></div>
@forelse($fees['rows'] as $invoice)
    @php($paid = $lifecycle->paidKobo($invoice)) @php($balance = $lifecycle->balanceKobo($invoice)) @php($openParts = $invoice->partPayments->where('status', 'Unpaid'))
    @php($attempt = $invoice->attempts->sortByDesc('id')->first()) @php($manual = $invoice->awaitingManualSubmission)
    @php($state = $invoice->status === 'Paid' ? 'Paid' : ($invoice->status === 'Voided' ? 'Voided' : ($manual ? 'Awaiting approval' : ($attempt?->status === 'Pending' ? 'Confirming payment' : ($attempt?->status === 'Failed' ? 'Payment failed' : ($paid > 0 ? 'Part paid' : ($invoice->due_date?->lt(today()) ? 'Overdue' : 'Unpaid')))))))
    <details class="oak-row fe-inv" @if($loop->first) open @endif><summary><div><h3>{{ $invoice->purpose }}@if($invoice->term_id) · {{ $fees['terms'][$invoice->term_id] ?? 'Term #'.$invoice->term_id }}@endif</h3><p>Due {{ $invoice->due_date?->format('j M Y') ?? 'not set' }}</p></div><span class="oak-row-end"><strong>{{ $money($invoice->amount_kobo) }}</strong><span class="oak-status-pill {{ $state === 'Paid' ? 'oak-status-success' : ($state === 'Awaiting approval' ? 'oak-status-await' : 'oak-status-warning') }}">{{ $state }}</span><x-icon class="chev" name="chevron-right" /></span></summary>
        <div class="oak-row-body">
            @if($invoice->lines->isNotEmpty())<table class="fe-lines"><tbody>@foreach($invoice->lines as $line)<tr><td>{{ $line->description }}</td><td>{{ $money($line->amount_kobo) }}</td></tr>@endforeach<tr class="fe-total"><td>Total</td><td>{{ $money($invoice->amount_kobo) }}</td></tr></tbody></table>@else<p>Invoice total: <strong>{{ $money($invoice->amount_kobo) }}</strong></p>@endif
            @if($paid > 0 && $invoice->kind === \App\Modules\Payments\Models\Invoice::KIND_TERM_FEE)<p><strong>{{ $money($paid) }} of {{ $money($invoice->amount_kobo) }} paid · balance {{ $money($balance) }}</strong></p><div class="h-2 overflow-hidden rounded bg-slate-200"><div class="h-full bg-emerald-600" style="width:{{ min(100, round($paid * 100 / $invoice->amount_kobo)) }}%"></div></div>@endif
            @if($invoice->payment?->receipt)<p>Paid {{ $invoice->payment->paid_at?->format('j M Y') }} · Receipt {{ $invoice->payment->receipt->number }} · <a class="oak-link inline-flex min-h-11 items-center" href="{{ route('receipts.download',$invoice->payment->receipt) }}">Download</a></p>@elseif($invoice->payment?->receipt_number)<p>Paid {{ $invoice->payment->paid_at?->format('j M Y') }} · Receipt {{ $invoice->payment->receipt_number }}</p>@endif
            @if($state === 'Confirming payment')<p>Paystack is confirming this payment. This invoice stays open until verification succeeds.</p><form method="post" action="{{ route('payments.attempts.check', $attempt) }}">@csrf<button class="oak-btn oak-btn-secondary" type="submit">Check again</button></form>@endif
            @if($state === 'Payment failed')<p>The last attempt failed. You can try again.</p>@endif
            @if($manual)<p>The school has recorded your bank payment and is confirming it. You don't need to pay again.</p><h4 class="fe-sub">Recorded payment</h4><div class="fe-receipt"><span>Bank payment · recorded {{ $manual->created_at->format('j M Y') }}</span><strong>{{ $money($manual->amount_kobo) }}</strong></div><p class="fe-visit"><x-icon name="bell" /><span>You'll get a notification when the school approves it.</span></p>@endif
            @if($invoice->partPayments->isNotEmpty())
            <h4 class="fe-sub">Part-payments</h4>
            @foreach($invoice->partPayments as $part)
                @php($partAttempt=$part->attempts->sortByDesc('id')->first())
                @php($partManual=$part->awaitingManualSubmission)
                <div class="fe-pp"><span><strong>Part payment · {{ $money($part->amount_kobo) }}</strong><br>Due {{ $part->due_date->format('j M Y') }} · {{ $part->status }}
                    @if($part->notes)<br>{{ $part->notes }}@endif
                    @if($part->payment?->receipt)<br>Receipt {{ $part->payment->receipt->number }} · <a class="oak-link inline-flex min-h-11 items-center" href="{{ route('receipts.download',$part->payment->receipt) }}">Download</a>@elseif($part->payment?->receipt_number)<br>Receipt {{ $part->payment->receipt_number }}@endif
                    @if($partManual)<br>Awaiting approval @endif
                    @if($partAttempt?->status === 'Pending')<br>Confirming payment<form method="post" action="{{ route('payments.attempts.check', $partAttempt) }}">@csrf<button class="oak-btn oak-btn-secondary" type="submit">Check again</button></form>@endif
                    @if($partAttempt?->status === 'Failed')<br>Payment failed @endif
                </span><span>@if(!$fees['read_only'] && $fees['online_payments_available'] && $part->status === 'Unpaid' && !$partManual && $partAttempt?->status !== 'Pending')<button class="oak-btn oak-btn-secondary" type="button" data-open-sheet="pay-sheet-{{ $part->id }}">Pay {{ $money($part->amount_kobo) }}</button>@endif</span></div>
                @if(!$fees['read_only'] && $fees['online_payments_available'] && $part->status === 'Unpaid' && !$partManual && $partAttempt?->status !== 'Pending')<div class="oak-scrim" id="pay-sheet-{{ $part->id }}" role="dialog" aria-modal="true" aria-labelledby="pay-title-{{ $part->id }}"><div class="oak-sheet"><h2 id="pay-title-{{ $part->id }}">Pay {{ $money($part->amount_kobo) }}</h2><p class="oak-sheet-sub">{{ $context['kind'] === 'parent' ? 'For '.$context['selected']['name'].'. ' : '' }}This pays one part of the term invoice. You will return here after Paystack checks it.</p><form method="post" action="{{ route('payments.mine.begin', ['invoice' => $part->id, 'student' => $context['student_id']]) }}">@csrf @if($needsPaymentEmail)<label class="block">Email for your payment receipt<input class="mt-1 min-h-11 w-full rounded border px-3" type="email" name="payment_email" required autocomplete="email"></label><p class="oak-sheet-sub">We will also send a link to confirm this email for password recovery.</p>@endif<div class="oak-sheet-actions"><button class="oak-btn oak-btn-quiet" type="button" data-close-sheet>Cancel</button><button class="oak-btn oak-btn-primary" type="submit">Continue to Paystack</button></div></form></div></div>@endif
            @endforeach
            @endif
            @if(!$fees['read_only'] && $invoice->status === 'Unpaid' && $invoice->partPayments->isEmpty() && !$manual)<p class="fe-visit">To pay in parts, please visit the bursar.</p>@endif
            @if(!$fees['read_only'] && $fees['online_payments_available'] && $invoice->status === 'Unpaid' && $openParts->isEmpty() && !$manual && $state !== 'Confirming payment')<button class="oak-btn oak-btn-primary" type="button" data-open-sheet="pay-sheet-{{ $invoice->id }}">Pay {{ $money($balance) }}</button><div class="oak-scrim" id="pay-sheet-{{ $invoice->id }}" role="dialog" aria-modal="true" aria-labelledby="pay-title-{{ $invoice->id }}"><div class="oak-sheet"><h2 id="pay-title-{{ $invoice->id }}">Pay {{ $money($balance) }}</h2><p class="oak-sheet-sub">{{ $context['kind'] === 'parent' ? 'For '.$context['selected']['name'].'. ' : '' }}You will continue to Paystack and return here. The invoice is paid only after verification.</p><form method="post" action="{{ route('payments.mine.begin', ['invoice' => $invoice->id, 'student' => $context['student_id']]) }}">@csrf @if($needsPaymentEmail)<label class="block">Email for your payment receipt<input class="mt-1 min-h-11 w-full rounded border px-3" type="email" name="payment_email" required autocomplete="email"></label><p class="oak-sheet-sub">We will also send a link to confirm this email for password recovery.</p>@endif<div class="oak-sheet-actions"><button class="oak-btn oak-btn-quiet" type="button" data-close-sheet>Cancel</button><button class="oak-btn oak-btn-primary" type="submit">Continue to Paystack</button></div></form></div></div>@endif
        </div>
    </details>
@empty<div class="oak-empty"><x-icon name="receipt" /><strong>{{ $fees['read_only'] ? 'No receipts yet' : 'No invoices yet' }}</strong><p>{{ $fees['read_only'] ? 'Paid receipts will remain available here.' : 'Invoices from the school will appear here.' }}</p></div>@endforelse
</section>
@if($fees['rows']->hasPages()){{ $fees['rows']->links() }}@endif
@endsection
