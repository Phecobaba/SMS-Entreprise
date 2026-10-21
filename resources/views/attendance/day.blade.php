<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Class attendance</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>
@include('core._shell-start')<main class="oak-dashboard" style="max-width:960px">
<a wire:navigate href="{{ route('attendance.index') }}" class="oak-link">Attendance</a>
<div class="oak-dashboard-heading"><div><h1>{{ $arm->schoolClass?->name }} {{ $arm->name }}</h1><p>{{ $date }} · {{ $roster->count() }} students</p></div></div>
<nav class="ad-tabs" aria-label="Attendance session">@foreach(['Morning','Afternoon'] as $tab)<a wire:navigate href="{{ route('attendance.day', ['arm' => $arm->id, 'date' => $date, 'session' => $tab]) }}" @if($session === $tab) aria-current="page" @endif>{{ $tab }}</a>@endforeach</nav>
<p class="oak-muted">The tab chooses the saved session. The same-day edit window closes at midnight WAT; locked records cannot be edited.</p>
@if(session('status'))<div class="oak-notice oak-notice-success" role="status"><div>{{ session('status') }}</div></div>@endif
@if($errors->any())<div class="oak-notice oak-notice-danger" role="alert"><div>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif
@if($notHeld)
    <div class="oak-notice oak-notice-warning"><div><strong>Not held, no roll needed.</strong><p>This {{ strtolower($session) }} session is excluded from alerts and attendance percentages.</p></div></div>
@elseif($roster->isEmpty())
    <div class="oak-empty">No students are enrolled in this class arm yet.</div>
@else
<form method="post" action="{{ route('attendance.mark', $arm->id) }}" class="ad-card">
@csrf<input type="hidden" name="date" value="{{ $date }}"><input type="hidden" name="session" value="{{ $session }}">
<div class="oak-table-scroll"><table class="oak-table oak-stack"><thead><tr><th>Student</th>@if($session === 'Afternoon')<th>Morning</th>@endif<th>{{ $session }} status</th></tr></thead><tbody>
@foreach($roster as $student)
@php($record = $records[$student->id] ?? null)
<tr><td>{{ $student->user?->name ?? 'Student #'.$student->id }}</td>@if($session === 'Afternoon')<td><span class="oak-status-pill oak-status-neutral">{{ ($morningRecords[$student->id] ?? null)?->status ?? 'Not marked' }}</span></td>@endif<td>
@if($editable && ! $record?->locked)
<select name="statuses[{{ $student->id }}]" class="ad-inline-select" aria-label="Attendance status for {{ $student->user?->name ?? 'Student #'.$student->id }}">
@foreach(['Present','Absent','Late','Excused'] as $status)<option value="{{ $status }}" @selected(($record?->status ?? 'Present') === $status)>{{ $status }}</option>@endforeach
</select>
@else<span class="oak-status-pill oak-status-neutral">{{ $record?->status ?? 'Not marked' }} @if($record?->locked) · Locked @endif</span>@endif
</td></tr>
@endforeach
</tbody></table></div>
@if($editable && ! $records->contains(fn ($record) => $record->locked))<button class="oak-btn oak-btn-primary" style="margin-top:16px">Save {{ strtolower($session) }} roll</button>@endif
</form>
@can('manage_attendance')
@if($editable && $records->count() === $roster->count() && ! $records->contains(fn ($record) => $record->locked))
<form method="post" action="{{ route('attendance.lock', $arm->id) }}" style="margin-top:12px">@csrf<input type="hidden" name="date" value="{{ $date }}"><input type="hidden" name="session" value="{{ $session }}"><button class="oak-btn oak-btn-secondary">Lock {{ strtolower($session) }}</button></form>
@endif
@endcan
@endif
</main>@include('core._shell-end')
@livewireScripts
</body></html>
