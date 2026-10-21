<div class="ad-impact">
    <p><strong>{{ $preview['open_alerts'] }}</strong> open {{ Illuminate\Support\Str::plural('alert', $preview['open_alerts']) }} will be cleared.</p>
    <p>The selected {{ $preview['selection'] === 'Both' ? 'sessions' : 'session' }} will be left out of attendance percentages.</p>
    @foreach($preview['sessions'] as $impact)
        @if($impact['marked_students'] > 0)
            <p><strong>{{ $impact['marked_students'] }}</strong> {{ Illuminate\Support\Str::plural('student', $impact['marked_students']) }} already {{ $impact['marked_students'] === 1 ? 'has' : 'have' }} marks for the {{ strtolower($impact['session']) }} session on this date. Those marks stay on record but are not counted.</p>
        @endif
    @endforeach
    <p>The change will be logged with your name.</p>
</div>
