<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Record a payment</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>@include('core._shell-start')
<main class="oak-dashboard ad-payment-page" id="main">
    <div class="oak-dashboard-heading"><div><h1>Record a payment</h1><p>Record a bank teller payment against one open invoice</p></div></div>
    <nav class="ad-tabs"><a href="{{ route('payments.record.index') }}" aria-current="page">Record a payment</a><a href="{{ route('payments.approvals.index') }}">Awaiting approval</a></nav>
    @if(session('status'))<div class="oak-notice oak-notice-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="oak-notice oak-notice-danger" role="alert"><div><strong>Payment not recorded</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif
    <section class="ad-card"><form method="get" action="{{ route('payments.record.index') }}" class="ad-payment-search"><label for="payment-search">Find an invoice</label><div><input id="payment-search" name="q" value="{{ $query }}" placeholder="Student name or registration number"><button class="oak-btn oak-btn-secondary">Search</button></div></form></section>
    @if($selected)
        @php($owner = $selected->student?->user?->name ?? $selected->candidate?->name ?? 'Payer')
        @php($recentPendingAttempt = $selected->attempts->where('status', 'Pending')->sortByDesc('id')->first(fn ($attempt) => $attempt->created_at?->gte(now()->subHours(48))))
        <section class="ad-card"><div class="ad-card-head"><div><h2>{{ $owner }}</h2><p>{{ $selected->purpose }} · Invoice #{{ $selected->id }} · ₦{{ number_format($selected->amount_kobo / 100, 2) }}</p></div></div>
            @if($recentPendingAttempt)<div class="oak-notice oak-notice-warning" role="alert"><x-icon name="alert-triangle" /><div><strong>A Paystack payment for this invoice is still being confirmed.</strong><p>Check it before recording a manual payment, or the invoice could be credited twice.</p></div></div>@endif
            <form method="post" action="{{ route('payments.manual', $selected) }}" enctype="multipart/form-data" class="ad-form-grid">@csrf
                <label>Bank<input name="bank" value="{{ old('bank') }}" maxlength="120" required></label>
                <label>Teller number<input name="teller_number" value="{{ old('teller_number') }}" maxlength="120" required></label>
                <label>Date paid<input type="date" name="paid_on" value="{{ old('paid_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></label>
                <label>Amount (₦)<input name="amount_naira" inputmode="decimal" value="{{ old('amount_naira', number_format($selected->amount_kobo / 100, 2)) }}" required></label>
                <label class="ad-wide">Teller photo <span class="ad-meta">Optional · JPG, PNG or PDF · up to 3 MB</span><input type="file" name="photo" accept="image/jpeg,image/png,application/pdf"></label>
                <div class="ad-wide"><button class="oak-btn oak-btn-primary" type="submit">Record payment</button></div>
            </form>
        </section>
    @endif
    <section class="ad-card ad-payment-results"><div class="ad-card-head"><div><h2>Open invoices</h2><p>Select the invoice the teller pays</p></div></div>
        @forelse($invoices as $invoice)
            @php($owner = $invoice->student?->user?->name ?? $invoice->candidate?->name ?? 'Payer')
            <a class="oak-list-row" href="{{ route('payments.record.index', ['q' => $query, 'invoice' => $invoice->id]) }}"><span><strong>{{ $owner }}</strong><small>{{ $invoice->student?->user?->username ? ' · '.$invoice->student->user->username : '' }}@if($invoice->student?->registration_number)<br>Registration number: {{ $invoice->student->registration_number }}@endif<br>{{ $invoice->purpose }} · Invoice #{{ $invoice->id }}</small></span><span><strong>₦{{ number_format($invoice->amount_kobo / 100, 2) }}</strong><x-icon name="chevron-right" /></span></a>
        @empty<div class="oak-empty"><x-icon name="wallet" /><strong>No matching open invoices</strong><p>Try another student name or registration number.</p></div>@endforelse
    </section>
    @if($invoices->hasPages()){{ $invoices->links() }}@endif
</main>@include('core._shell-end') @livewireScripts</body></html>
