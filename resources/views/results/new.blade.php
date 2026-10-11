<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Enter result</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-5 text-slate-900">
@include('core._shell-start')<main class="mx-auto max-w-2xl rounded-lg bg-white p-7 shadow">
<a wire:navigate href="{{ route('results.index') }}" class="text-blue-700 underline">Results workspace</a><h1 class="mt-4 text-2xl font-bold">Enter result for {{ $studentName }}</h1><p class="text-sm text-slate-600">Template: {{ $template->name }}. Save a draft first; submit when every component is entered.</p>
@if($errors->any())<div class="mt-4 rounded bg-red-50 p-3 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="post" action="{{ route('results.drafts.store') }}" class="mt-5 space-y-4">@csrf<input type="hidden" name="student_id" value="{{ $studentId }}">
<label class="block">Subject<select required name="subject_id" class="mt-1 w-full rounded border p-2"><option value="">Choose subject</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></label>
<label class="block">Term<select required name="term_id" class="mt-1 w-full rounded border p-2"><option value="">Choose term</option>@foreach($terms as $term)<option value="{{ $term->id }}">{{ $term->session->name }} · {{ $term->name }}</option>@endforeach</select></label>
<fieldset><legend class="font-semibold">Component scores</legend><div class="mt-2 grid gap-3 sm:grid-cols-2">@foreach($template->components as $entry)<label>{{ $entry->component->name }} / {{ $entry->component->max_score }}<input type="number" step="0.01" min="0" max="{{ $entry->component->max_score }}" name="scores[{{ $entry->assessment_component_id }}]" class="mt-1 w-full rounded border p-2"></label>@endforeach</div></fieldset>
<button class="rounded bg-blue-700 px-4 py-2 text-white">Save draft</button></form></main>@include('core._shell-end')
@livewireScripts
</body></html>
