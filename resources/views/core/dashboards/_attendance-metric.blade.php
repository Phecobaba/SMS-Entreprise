<div class="oak-metric">
    <span>Attendance today <x-oak.tooltip text="Percentages use marked Morning and Afternoon rolls. Sessions recorded as not held are left out." label="How Attendance today is calculated" /></span>
    @foreach(\App\Modules\Attendance\Services\AttendanceService::SESSIONS as $sessionName)
        @php($session = $overview['attendance']['sessions'][$sessionName])
        <div class="ad-ampm"><span>{{ $sessionName }}</span>@if($session['not_held'])<b class="ad-nh">Not held</b>@else<b>{{ $session['percentage'] === null ? '—' : $session['percentage'].'%' }}</b>@endif</div>
        @if(!$session['not_held'] && $session['total_arms'])<small>{{ $session['marked_arms'] }} of {{ $session['total_arms'] }} classes marked</small>@endif
    @endforeach
</div>
