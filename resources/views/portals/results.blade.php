@extends('portals.layout')
@section('title', 'Results')
@section('content')
<div class="oak-section-head"><div><h1>Report cards</h1><p class="oak-subpage-note">{{ $context['kind'] === 'parent' ? $context['selected']['name']."'s" : 'Your' }} term results</p></div></div>
<section class="oak-section">
@forelse($cards as $card)
    <div class="oak-row"><div><h3>{{ $card['term'] }}</h3><p>{{ $card['class'] }} · Compiled {{ $card['compiled_at']?->format('j M Y') ?? '—' }}</p></div><div class="oak-row-end">
        @if($card['available'])<a class="oak-btn oak-btn-quiet" href="{{ route('results.mine.show', ['card' => $card['id'], 'student' => $context['student_id']]) }}" wire:navigate>View result</a>
        @else<span class="oak-status-pill oak-status-warning">Payment required</span>@if($card['invoice'])<button class="oak-btn oak-btn-primary" type="button" data-open-sheet="result-pay-{{ $card['id'] }}">Pay</button>@endif@endif
    </div></div>
    @if(! $card['available'] && $card['invoice'])<div class="oak-scrim" id="result-pay-{{ $card['id'] }}" role="dialog" aria-modal="true" aria-label="Pay tuition"><div class="oak-sheet"><h2>Pay ₦{{ number_format($card['invoice']->amount_kobo / 100, 0) }}</h2><p class="oak-sheet-sub">This report card opens after the term's tuition invoice is verified paid. You will return to Fees after Paystack.</p><div class="oak-sheet-actions"><button class="oak-btn oak-btn-quiet" type="button" data-close-sheet>Cancel</button><form method="post" action="{{ route('payments.mine.begin', ['invoice' => $card['invoice']->id, 'student' => $context['student_id']]) }}">@csrf<button class="oak-btn oak-btn-primary" type="submit">Continue to Paystack</button></form></div></div></div>@endif
@empty<div class="oak-empty"><x-icon name="report" /><strong>No report cards yet</strong><p>Approved report cards will appear here.</p></div>@endforelse
</section>
@if($cards?->hasPages()){{ $cards->links() }}@endif
@endsection
