<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Awaiting approval</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>@include('core._shell-start')
<main class="oak-dashboard ad-payment-page" id="main">
    <div class="oak-dashboard-heading"><div><h1>Awaiting approval</h1><p>Bank payments recorded and not yet approved</p></div></div>
    <nav class="ad-tabs"><a href="{{ route('payments.record.index') }}">Record a payment</a><a href="{{ route('payments.approvals.index') }}" aria-current="page">Awaiting approval ({{ $waiting->total() }})</a></nav>
    @if(session('status'))<div class="oak-notice oak-notice-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="oak-notice oak-notice-danger" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if($canApprove)
        <section class="ad-card"><div class="ad-card-head"><div><h2>One-step approval</h2><p>Admin only. Off is the default.</p></div></div>
            <form method="post" action="{{ route('payments.approval-setting.update') }}" @if(!$oneStep) onsubmit="return confirm('Turn on one-step approval? Bank payments the Bursar records will be approved straight away and issue a receipt. Existing waiting payments will stay in the queue. You can turn it off again.')" @endif>@csrf @method('PUT')<input type="hidden" name="enabled" value="{{ $oneStep ? 0 : 1 }}"><button class="oak-btn {{ $oneStep ? 'oak-btn-secondary' : 'oak-btn-primary' }}" type="submit">{{ $oneStep ? 'Turn off' : 'Turn on' }}</button></form>
            <p class="ad-meta">{{ $oneStep ? 'On: new bank payments are approved straight away. Existing waiting payments stay in this queue.' : 'Off: an Admin approves each payment before the invoice shows Paid.' }} Each change is logged with who and when.</p>
        </section>
    @else
        <div class="oak-notice oak-notice-info"><div><strong>You see this list read-only.</strong><p>Approval: {{ $oneStep ? 'one step. New payments are approved straight away.' : 'two steps. An Admin approves each payment.' }} Only an Admin can change this.</p></div></div>
    @endif
    <section class="ad-card ad-payment-queue">
        @forelse($waiting as $submission)
            @php($invoice = $submission->invoice) @php($owner = $invoice->student?->user?->name ?? $invoice->candidate?->name ?? 'Payer')
            <article class="ad-payment-row"><div><strong>{{ $owner }}</strong><small>{{ $invoice->student?->classArm?->schoolClass?->name }} · Recorded by {{ $submission->recorder?->name }}</small></div><div><strong>{{ $submission->bank }} · teller {{ $submission->teller_number }}</strong><small>Paid {{ $submission->paid_on->format('D j M Y') }}</small>@if($submission->photo_path)<a class="oak-btn oak-btn-quiet" href="{{ route('payments.manual-submissions.slip', $submission) }}">View slip</a>@endif</div><strong>₦{{ number_format($submission->amount_kobo / 100, 2) }}</strong><div class="ad-payment-actions">@if($canApprove)<form method="post" action="{{ route('payments.manual-submissions.approve', $submission) }}" onsubmit="return confirm('Approve this payment? The invoice will be marked Paid, one receipt will be issued, and this cannot be undone.')">@csrf<button class="oak-btn oak-btn-primary">Approve</button></form><form method="post" action="{{ route('payments.manual-submissions.reject', $submission) }}">@csrf<label>Reason<input name="reason" maxlength="1000" required></label><button class="oak-btn oak-btn-secondary">Reject</button></form>@else<span class="ad-meta">Admin approves</span>@endif</div></article>
        @empty<div class="oak-empty"><x-icon name="circle-check" /><strong>{{ $oneStep ? 'This school approves new payments in one step' : 'Nothing is waiting' }}</strong><p>{{ $oneStep ? 'New payments are approved and receipted as soon as they are recorded.' : 'Payments the Bursar records appear here until an Admin handles them.' }}</p></div>@endforelse
    </section>
    @if($waiting->hasPages()){{ $waiting->links() }}@endif
    @if($rejected->isNotEmpty())<section class="oak-section"><div class="oak-section-head"><h2>Recently rejected</h2></div>@foreach($rejected as $submission)<div class="oak-list-row"><span><strong>{{ $submission->invoice->student?->user?->name ?? $submission->invoice->candidate?->name ?? 'Payer' }}</strong><small>Recorded by {{ $submission->recorder?->name }} · {{ $submission->rejection_reason }}</small></span><span class="oak-status-pill oak-status-danger">Rejected</span></div>@endforeach</section>@endif
</main>@include('core._shell-end') @livewireScripts</body></html>
