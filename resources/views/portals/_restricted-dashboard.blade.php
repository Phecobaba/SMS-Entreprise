@php($restrictedName = $overview['selected']['name'] ?? 'This student')
<div class="oak-notice oak-notice-info"><x-icon name="lock" /><div><strong>Read-only access</strong><p>{{ $restrictedName }} can open past report cards and receipts. Exams, assignments, attendance, e-notes and payments are unavailable.</p></div></div>
<div class="oak-two-col oak-section">
    <section><div class="oak-section-head"><h2>Past report cards</h2><a class="oak-link" href="{{ route('results.mine.index', $overview['kind'] === 'parent' ? ['student' => $overview['selected']['id']] : []) }}" wire:navigate>All results</a></div>
        @forelse($overview['results'] as $result)<div class="oak-row"><div><h3>{{ $result['term'] }}</h3><p>Compiled {{ $result['compiled_at']?->format('j M Y') ?? '—' }}</p></div><div class="oak-row-end">@if($result['available'])<a class="oak-link" href="{{ route('results.mine.show', ['card' => $result['id'], 'student' => $overview['kind'] === 'parent' ? $overview['selected']['id'] : null]) }}" wire:navigate>View result</a>@else<span class="oak-status-pill oak-status-warning">Payment required</span>@endif</div></div>@empty<div class="oak-empty"><x-icon name="report" /><strong>No report cards yet</strong></div>@endforelse
    </section>
    <section><div class="oak-section-head"><h2>Past receipts</h2><a class="oak-link" href="{{ route('payments.mine.index', $overview['kind'] === 'parent' ? ['student' => $overview['selected']['id']] : []) }}" wire:navigate>All receipts</a></div>
        @forelse($overview['payments']['recent'] as $invoice)<div class="oak-row"><div><h3>{{ $invoice->purpose }}</h3><p>{{ $invoice->payment?->receipt_number ?? 'Receipt' }} · paid {{ $invoice->payment?->paid_at?->format('j M Y') ?? '—' }}</p></div><span class="oak-status-pill oak-status-success">Paid</span></div>@empty<div class="oak-empty"><x-icon name="receipt" /><strong>No receipts yet</strong></div>@endforelse
    </section>
</div>
