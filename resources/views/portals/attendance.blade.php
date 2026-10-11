@extends('portals.layout')
@section('title', 'Attendance')
@section('content')
<div class="oak-section-head"><div><h1>Attendance</h1><p class="oak-subpage-note">{{ $context['kind'] === 'parent' ? $context['selected']['name']."'s" : 'Your' }} last 30 marked school days</p></div></div>
@if($locked)
    <div class="oak-notice oak-notice-warning"><x-icon name="lock" /><div><strong>Attendance is locked.</strong><p>It opens after the latest tuition invoice is verified paid.</p><a class="oak-btn oak-btn-primary" href="{{ route('payments.mine.index', ['student' => $context['student_id']]) }}" wire:navigate>View fees</a></div></div>
@elseif($data)
    <div class="oak-metric-grid oak-metric-grid-three"><div class="oak-metric"><span>Attendance</span><strong>{{ $data['percentage'] === null ? '—' : $data['percentage'].'%' }}</strong></div><div class="oak-metric"><span>Marked days</span><strong>{{ $data['marked_days'] }}</strong></div><div class="oak-metric"><span>Late counts as present</span><strong>Yes</strong></div></div>
    @if($data['strip']->isNotEmpty())<section class="oak-section"><h2>Last 30 marked days</h2><div class="at-grid">@foreach($data['strip'] as $day)<span class="at-cell {{ match($day->status) {'Present'=>'at-p','Late'=>'at-l','Absent'=>'at-a',default=>'at-e'} }}" title="{{ $day->date->format('j M Y') }}: {{ $day->status }}"><small>{{ $day->date->format('j M') }}</small><b>{{ mb_substr($day->status, 0, 1) }}</b><span class="sr-only">{{ $day->status }}</span></span>@endforeach</div><p class="at-legend">P Present · L Late · A Absent · E Excused. Excused days do not lower the percentage.</p></section>@endif
    <section class="oak-section"><h2>Day by day</h2>@forelse($data['rows'] as $day)<div class="oak-row"><div><h3>{{ $day->date->format('D, j M Y') }}</h3></div><div class="oak-row-end">@include('core._status-pill', ['status' => $day->status])</div></div>@empty<div class="oak-empty">No attendance has been marked yet.</div>@endforelse</section>
    @if($data['rows']->hasPages()){{ $data['rows']->links() }}@endif
@endif
@endsection
