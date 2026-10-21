<div class="ad-metrics">
    <div class="oak-metric"><span>Outstanding (term invoices)</span><strong class="oak-metric-accent">₦{{ number_format($overview['outstanding_kobo'] / 100) }}</strong></div>
    <div class="oak-metric"><span>Collected this term</span><strong class="oak-metric-success">₦{{ number_format($overview['collected_kobo'] / 100) }}</strong></div>
    <a class="oak-metric" href="{{ route('fees.not-invoiced.index') }}"><span>Not invoiced</span><strong>{{ $overview['not_invoiced_count'] }}</strong></a>
</div>

<section class="oak-section"><div class="oak-section-head"><h2>Do next</h2></div><div class="ad-actions-row"><a class="ad-action ad-primary" href="{{ route('payments.index') }}" wire:navigate><span>Open an invoice</span><strong><x-icon name="wallet" />Record a payment</strong></a><a class="ad-action" href="{{ route('fees.generate.index') }}"><span>Term fees</span><strong>Generate invoices</strong></a><a class="ad-action" href="{{ route('fees.not-invoiced.index') }}"><span>Late enrolments</span><strong>Not invoiced</strong></a></div></section>

<section class="oak-section"><div class="oak-section-head"><h2>Recent payments</h2><a class="oak-link" href="{{ route('payments.index') }}" wire:navigate>All payments</a></div>
    @forelse($overview['recent'] as $payment)
        @php($invoice = $payment->invoice)
        <div class="oak-list-row"><span>{{ $invoice?->student?->user?->name ?? $invoice?->candidate?->name ?? 'Payer' }}<br><span class="oak-muted">Receipt {{ $payment->receipt_number }} · {{ $payment->method === 'Online' ? 'Paystack' : 'Bank teller' }} · {{ $payment->paid_at?->timezone('Africa/Lagos')->format('D j M') }}</span></span><strong>₦{{ number_format($payment->amount_kobo / 100) }}</strong></div>
    @empty
        <div class="oak-empty"><x-icon name="wallet" /><strong>No payments this term yet</strong>Payments appear here as they come in.</div>
    @endforelse
</section>
