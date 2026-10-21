<div class="ad-section-badge"><x-icon name="school" />{{ $overview['section']->name }}</div>
@foreach($overview['attendance']['not_held'] as $entry)
<div class="oak-notice oak-notice-info ad-notice-action" role="status"><x-icon name="info-circle" /><div><strong>{{ $entry['session'] }} is not held today.</strong> It is left out of attendance percentages. @can('manage_attendance')<a class="oak-link" href="{{ route('attendance.settings') }}" wire:navigate>Sessions &amp; times</a>@endcan</div></div>
@endforeach
<div class="ad-metrics">
    <div class="oak-metric"><span>Students</span><strong>{{ number_format($overview['students']) }}</strong></div>
    <div class="oak-metric"><span>Teachers assigned</span><strong>{{ number_format($overview['teachers']) }}</strong></div>
    @include('core.dashboards._attendance-metric')
    <div class="oak-metric"><span>Results awaiting approval</span><strong>{{ $overview['approval_count'] }}</strong></div>
</div>

@include('core.dashboards._result-queue')

<div class="oak-two-col oak-section">
    <section><div class="oak-section-head"><h2>Attendance alerts</h2><a class="oak-link" href="{{ route('attendance.alerts') }}" wire:navigate>All alerts</a></div>
        @forelse($overview['attendance']['alerts'] as $alert)<a class="oak-list-row" href="{{ $alert['url'] }}" wire:navigate><span>{{ $alert['class'] }}<br><span class="oak-muted">{{ $alert['session'] }} · {{ $alert['date']->format('D j M') }}</span></span><span class="oak-status-pill oak-status-warning">Not marked</span></a>@empty<div class="oak-empty"><x-icon name="circle-check" /><strong>All rolls marked</strong>Every class roll due so far today is marked.</div>@endforelse
    </section>
    <section><div class="oak-section-head"><h2>Admissions and attendance</h2></div><div class="ad-attn">
        <div class="ad-attn-row"><span><x-icon name="plus" /> Admission offers awaiting a reply</span><strong>{{ $overview['offers_count'] }}</strong></div>
        @can('manage_attendance')<a href="{{ route('attendance.settings') }}" wire:navigate><span><x-icon name="clock" /> Alert times and sessions not held</span><x-icon name="chevron-right" /></a>@endcan
    </div></section>
</div>
