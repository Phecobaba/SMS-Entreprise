<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Class attendance</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-5 text-slate-900">
@include('core._shell-start')<main class="mx-auto max-w-4xl rounded-lg bg-white p-7 shadow">
<a wire:navigate href="{{ route('attendance.index') }}" class="text-blue-700 underline">Attendance</a><h1 class="mt-4 text-2xl font-bold">{{ $arm->schoolClass?->name }} {{ $arm->name }} · {{ $date }}</h1>
<p class="mt-2 text-slate-600">{{ $roster->count() }} students. The school-day edit window closes at midnight WAT; locked records cannot be edited.</p>
@if(session('status'))<p class="mt-3 rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</p>@endif
@if($errors->any())<div class="mt-3 rounded bg-red-50 p-3 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if($roster->isEmpty())<p class="mt-6 rounded bg-amber-50 p-3">No students are enrolled in this class arm yet.</p>
@else
<form method="post" action="{{ route('attendance.mark', $arm->id) }}" class="mt-6">
@csrf<input type="hidden" name="date" value="{{ $date }}">
<div class="overflow-x-auto"><table class="w-full text-left"><thead><tr class="border-b"><th class="p-2">Student</th><th class="p-2">Status</th></tr></thead><tbody>
@foreach($roster as $student)
@php($record = $records[$student->id] ?? null)
<tr class="border-b"><td class="p-2">{{ $student->user?->name ?? 'Student #'.$student->id }}</td><td class="p-2">
@if($editable && ! $record?->locked)
<select name="statuses[{{ $student->id }}]" class="w-full rounded border p-2" aria-label="Attendance status for {{ $student->user?->name ?? 'Student #'.$student->id }}">
@foreach(['Present','Absent','Late','Excused'] as $status)<option value="{{ $status }}" @selected(($record?->status ?? 'Present') === $status)>{{ $status }}</option>@endforeach
</select>
@else<span>{{ $record?->status ?? 'Not marked' }} @if($record?->locked) · Locked @endif</span>@endif
</td></tr>
@endforeach
</tbody></table></div>
@if($editable && ! $records->contains(fn ($record) => $record->locked))<button class="mt-4 rounded bg-blue-700 px-4 py-2 text-white">Save full class</button>@endif
</form>
@can('manage_attendance')
@if($editable && $records->count() === $roster->count() && ! $records->contains(fn ($record) => $record->locked))
<form method="post" action="{{ route('attendance.lock', $arm->id) }}" class="mt-3">@csrf<input type="hidden" name="date" value="{{ $date }}"><button class="rounded border px-4 py-2">Lock this day</button></form>
@endif
@endcan
@endif
</main>@include('core._shell-end')
@livewireScripts
</body></html>
