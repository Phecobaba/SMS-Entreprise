@extends('portals.layout')
@section('title', 'Fees')
@section('content')
@php($money = fn (int $kobo) => '₦'.number_format($kobo / 100, $kobo % 100 ? 2 : 0))
<div class="oak-section-head"><div><h1>{{ $fees['read_only'] ? 'Receipts' : 'Fees' }}</h1><p class="oak-subpage-note">{{ $fees['read_only'] ? 'Read-only payment history' : (($context['kind'] === 'parent' ? $context['selected']['name']."'s invoices" : 'Your invoices').' and payment status') }}</p></div></div>
@if(session('status'))<div class="oak-notice oak-notice-success" role="status">{{ session('status') }}</div>@endif
@if($fees['read_only'])<div class="oak-notice oak-notice-info"><x-icon name="lock" /><div><strong>Read-only access</strong><p>Only past receipts are available for this student.</p></div></div>@endif
@unless($fees['read_only'])
<div class="fe-summary"><div><span>Outstanding</span><strong>{{ $money($fees['outstanding_kobo']) }}</strong></div>
    @php($firstPayable = $fees['rows']->first(fn ($invoice) => $invoice->status === 'Unpaid' && $invoice->attempts->sortByDesc('id')->first()?->status !== 'Pending'))
    @if($firstPayable)<button class="oak-btn oak-btn-primary" type="button" data-open-sheet="pay-sheet-{{ $firstPayable->id }}">Pay {{ $money($firstPayable->amount_kobo) }}</button>@endif
</div>
@endunless
<section class="oak-section"><div class="oak-section-head"><h2>Invoices</h2></div>
@forelse($fees['rows'] as $invoice)
    @php($attempt = $invoice->attempts->sortByDesc('id')->first())
    @php($state = $invoice->status === 'Paid' ? 'Paid' : ($attempt?->status === 'Pending' ? 'Confirming payment' : ($attempt?->status === 'Failed' ? 'Payment failed' : ($invoice->due_date?->lt(today()) ? 'Overdue' : 'Unpaid'))))
    <details class="oak-row fe-inv"><summary><div><h3>{{ $invoice->purpose }}@if($invoice->term_id) · {{ $fees['terms'][$invoice->term_id] ?? 'Term #'.$invoice->term_id }}@endif</h3><p>Due {{ $invoice->due_date?->format('j M Y') ?? 'not set' }} · {{ $money($invoice->amount_kobo) }}</p></div><span class="oak-row-end"><span class="oak-status-pill {{ $state === 'Paid' ? 'oak-status-success' : 'oak-status-warning' }}">{{ $state }}</span><x-icon name="chevron-right" /></span></summary>
        <div class="oak-row-body">
            <p>Invoice total: <strong>{{ $money($invoice->amount_kobo) }}</strong></p>
            @if($invoice->payment?->receipt_number)<p>Paid {{ $invoice->payment->paid_at?->format('j M Y') }} · Receipt {{ $invoice->payment->receipt_number }}</p>@endif
            @if($state === 'Confirming payment')<p>Paystack is confirming this payment. This invoice stays open until verification succeeds.</p>@endif
            @if($state === 'Payment failed')<p>The last attempt failed. You can try again.</p>@endif
            @if(!$fees['read_only'] && $invoice->status === 'Unpaid')<p class="fe-visit">To pay in parts, please visit the bursar.</p>@endif
            @if(!$fees['read_only'] && $invoice->status === 'Unpaid' && $state !== 'Confirming payment')<button class="oak-btn oak-btn-primary" type="button" data-open-sheet="pay-sheet-{{ $invoice->id }}">Pay {{ $money($invoice->amount_kobo) }}</button>@endif
        </div>
    </details>
    @if(!$fees['read_only'] && $invoice->status === 'Unpaid' && $state !== 'Confirming payment')
        <div class="oak-scrim" id="pay-sheet-{{ $invoice->id }}" role="dialog" aria-modal="true" aria-labelledby="pay-title-{{ $invoice->id }}"><div class="oak-sheet"><h2 id="pay-title-{{ $invoice->id }}">Pay {{ $money($invoice->amount_kobo) }}</h2><p class="oak-sheet-sub">{{ $context['kind'] === 'parent' ? 'For '.$context['selected']['name'].'. ' : '' }}You will continue to Paystack and return to this fees page. The invoice is paid only after verification.</p><div class="oak-sheet-actions"><button class="oak-btn oak-btn-quiet" type="button" data-close-sheet>Cancel</button><form method="post" action="{{ route('payments.mine.begin', ['invoice' => $invoice->id, 'student' => $context['student_id']]) }}">@csrf<button class="oak-btn oak-btn-primary" type="submit">Continue to Paystack</button></form></div></div></div>
    @endif
@empty<div class="oak-empty"><x-icon name="receipt" /><strong>{{ $fees['read_only'] ? 'No receipts yet' : 'No invoices yet' }}</strong><p>{{ $fees['read_only'] ? 'Paid receipts will remain available here.' : 'Invoices from the school will appear here.' }}</p></div>@endforelse
</section>
@if($fees['rows']->hasPages()){{ $fees['rows']->links() }}@endif
@endsection
