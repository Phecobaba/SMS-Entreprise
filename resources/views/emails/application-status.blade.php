<p>{{ $kind === 'registered' ? 'Your application and payment are confirmed.' : 'We received your application. Complete payment using the link below.' }}</p>
@if($kind === 'registered')<p>Application reference: {{ $reference }}. Exam details will follow. Sign-in details will be given at the school on exam day.</p>@endif
<p><a href="{{ $statusUrl }}">View application status</a></p>
