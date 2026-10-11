<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Attendance alerts</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-5 text-slate-900">
@include('core._shell-start')<main class="mx-auto max-w-4xl rounded-lg bg-white p-7 shadow">
<a wire:navigate href="{{ route('attendance.index') }}" class="text-blue-700 underline">Attendance</a><h1 class="mt-4 text-2xl font-bold">Missing-attendance alerts</h1>
<p class="mt-2 text-slate-600">An alert appears when an enrolled class arm has not fully marked its weekday roster by 17:00 WAT.</p>
<ul class="mt-5 space-y-3">@forelse($alerts as $alert)
<li class="rounded border border-amber-200 bg-amber-50 p-3"><a wire:navigate class="text-blue-700 underline" href="{{ route('attendance.day', ['arm' => $alert->class_arm_id, 'date' => $alert->date->format('Y-m-d')]) }}">{{ $arms[$alert->class_arm_id]?->schoolClass?->name ?? 'Class' }} {{ $arms[$alert->class_arm_id]?->name ?? $alert->class_arm_id }} · {{ $alert->date->format('d M Y') }}</a></li>
@empty<li>No unresolved attendance alerts.</li>@endforelse</ul><div class="mt-3">{{ $alerts->links() }}</div>
</main>@include('core._shell-end')
@livewireScripts
</body></html>
