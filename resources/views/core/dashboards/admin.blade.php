@if($overview['show_section_switcher'])
<div class="ad-scope"><div class="ad-switch" aria-label="Section">
    <a class="ad-seg" href="{{ route('dashboard', ['section' => 'all']) }}" aria-current="{{ $overview['selected_section_id'] ? 'false' : 'true' }}" wire:navigate>All</a>
    @foreach($overview['sections'] as $section)<a class="ad-seg" href="{{ route('dashboard', ['section' => $section->id]) }}" aria-current="{{ $overview['selected_section_id'] === $section->id ? 'true' : 'false' }}" wire:navigate>{{ $section->name }}</a>@endforeach
</div><span class="oak-muted">Every figure and list follows this selection.</span></div>
@endif

@foreach($overview['attendance']['not_held'] as $entry)
<div class="oak-notice oak-notice-info ad-notice-action" role="status"><x-icon name="info-circle" /><div><strong>The {{ strtolower($entry['session']) }} is not held today for {{ $entry['section'] }}.</strong> Its alert is switched off and it is left out of attendance percentages. @can('manage_attendance')<a class="oak-link" href="{{ route('attendance.settings', ['section' => $overview['selected_section_id']]) }}" wire:navigate>Sessions &amp; times</a>@endcan</div></div>
@endforeach

<div class="ad-metrics">
    <div class="oak-metric"><span>Students</span><strong>{{ number_format($overview['students']) }}</strong></div>
    <div class="oak-metric"><span>Teachers assigned</span><strong>{{ number_format($overview['teachers']) }}</strong>@if(!$overview['selected_section_id'])<small>Each teacher is counted once across sections.</small>@endif</div>
    @include('core.dashboards._attendance-metric')
    <div class="oak-metric"><span>Unpaid tuition invoices</span><strong class="oak-metric-accent">₦{{ number_format($overview['outstanding_kobo'] / 100) }}</strong></div>
</div>

<div class="oak-two-col oak-section">
    <section><div class="oak-section-head"><h2>Needs attention</h2></div><div class="ad-attn">
        <a href="{{ route('results.index') }}" wire:navigate><span><x-icon name="report" /> Results awaiting approval</span><strong>{{ $overview['approval_count'] }}</strong></a>
        <a href="{{ route('attendance.alerts') }}" wire:navigate><span><x-icon name="calendar-check" /> Rolls not marked by their deadline today</span><strong>{{ $overview['attendance']['alerts_count'] }}</strong></a>
        @can('manage_admissions')<a href="{{ route('admissions.index') }}" wire:navigate><span><x-icon name="plus" /> Admission offers awaiting a reply</span><strong>{{ $overview['offers_count'] }}</strong></a>@endcan
        @can('manage_attendance')<a href="{{ route('attendance.settings', ['section' => $overview['selected_section_id']]) }}" wire:navigate><span><x-icon name="clock" /> Alert times and sessions not held</span><x-icon name="chevron-right" /></a>@endcan
    </div></section>
    <section><div class="oak-section-head"><h2>Quick actions</h2></div><div class="ad-actions-row">
        @can('manage_admissions')<a class="ad-action ad-primary" href="{{ route('admissions.index') }}" wire:navigate><span>Applications and offers</span><strong><x-icon name="plus" />Admissions</strong></a>@endcan
        @can('manage_staff_records')<a class="ad-action" href="{{ route('staff.index') }}" wire:navigate><span>People and records</span><strong><x-icon name="user" />Staff HR</strong></a>@endcan
        @can('manage_payments')<a class="ad-action" href="{{ route('payments.index') }}" wire:navigate><span>Fees and receipts</span><strong><x-icon name="wallet" />Invoices</strong></a>@endcan
    </div></section>
</div>

@include('core.dashboards._result-queue')
