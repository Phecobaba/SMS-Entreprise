@extends('portals.layout')
@section('title', 'Attendance')
@section('content')
<div class="oak-section-head"><div><h1>Attendance</h1><p class="oak-subpage-note">{{ $context['kind'] === 'parent' ? $context['selected']['name']."'s" : 'Your' }} last 30 school days</p></div></div>
@if($locked)
    <div class="oak-notice oak-notice-warning"><x-icon name="lock" /><div><strong>Attendance is locked.</strong><p>It opens after the latest tuition invoice is verified paid.</p><a class="oak-btn oak-btn-primary" href="{{ route('payments.mine.index', ['student' => $context['student_id']]) }}" wire:navigate>View fees</a></div></div>
@elseif($data)
    <div class="oak-metric-grid oak-metric-grid-three"><div class="oak-metric"><span>Attendance</span><strong>{{ $data['percentage'] === null ? '—' : $data['percentage'].'%' }}</strong></div><div class="oak-metric"><span>Sessions present</span><strong>{{ $data['present_sessions'] }} of {{ $data['counted_sessions'] }}</strong></div><div class="oak-metric"><span>Sessions absent</span><strong>{{ $data['absent_sessions'] }}</strong></div></div>
    @if($data['strip']->isNotEmpty())<section class="oak-section"><h2>Last 30 school days</h2><div class="at-grid">@foreach($data['strip'] as $day)<span class="at-cell" title="{{ $day['date']->format('j M Y') }}: Morning {{ $day['sessions']['Morning'] }}, Afternoon {{ $day['sessions']['Afternoon'] }}"><small>{{ $day['date']->format('j M') }}</small><b>M {{ mb_substr($day['sessions']['Morning'], 0, 1) }} · A {{ mb_substr($day['sessions']['Afternoon'], 0, 1) }}</b></span>@endforeach</div><p class="at-legend">Present and Late count as present. Excused, Not held and Not marked sessions are excluded.</p></section>@endif
    <section class="oak-section"><h2>Day by day</h2>@forelse($data['rows'] as $day)<div class="oak-row"><div><h3>{{ $day['date']->format('D, j M Y') }}</h3></div><div class="oak-row-end at-session-pills"><span>Morning: @include('core._status-pill', ['status' => $day['sessions']['Morning']])</span><span>Afternoon: @include('core._status-pill', ['status' => $day['sessions']['Afternoon']])</span></div></div>@empty<div class="oak-empty">No attendance has been marked yet.</div>@endforelse</section>
    @if($data['rows']->hasPages()){{ $data['rows']->links() }}@endif
@endif
@endsection
