<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Assignment ticks</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-5 text-slate-900">
@include('core._shell-start')<main class="mx-auto max-w-4xl rounded-lg bg-white p-7 shadow"><a wire:navigate href="{{ route('assignments.index') }}" class="text-blue-700 underline">Assignments</a>
<h1 class="mt-4 text-2xl font-bold">{{ $assignment->title }}</h1><p class="mt-2">Due {{ $assignment->due_date->format('d M Y') }} · {{ $assignment->description }}</p>
@if(session('status'))<p class="mt-3 rounded bg-green-50 p-3">{{ session('status') }}</p>@endif
<ul class="mt-6 space-y-3">@foreach($roster as $student)<li class="flex flex-wrap items-center justify-between gap-2 border-b p-2"><span>{{ $student->user?->name }}</span>
@if(isset($rows[$student->id]))<form method="post" action="{{ route('assignments.mark', [$assignment->id, $student->id]) }}" class="flex gap-2">@csrf<select name="status" class="rounded border p-2">@foreach(['Not Submitted', 'Submitted', 'Late'] as $status)<option value="{{ $status }}" @selected($rows[$student->id]->status === $status)>{{ $status }}</option>@endforeach</select><button class="rounded bg-blue-700 px-3 py-2 text-white">Save tick</button></form>@endif</li>@endforeach</ul>
</main>@include('core._shell-end')
@livewireScripts
</body></html>
