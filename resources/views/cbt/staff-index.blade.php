<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>CBT exams</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head><body class="min-h-screen bg-slate-100 p-5 text-slate-900">
@include('core._shell-start')<main class="mx-auto max-w-5xl rounded-lg bg-white p-7 shadow">
<a wire:navigate href="{{ route('dashboard') }}" class="text-blue-700 underline">Dashboard</a><h1 class="mt-4 text-2xl font-bold">CBT exams</h1>
@if(session('status'))<p class="mt-3 rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</p>@endif
@if($errors->any())<div class="mt-3 rounded bg-red-50 p-3 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="mt-7"><h2 class="text-lg font-semibold">Exam types</h2><p class="text-sm">Current: {{ $types->pluck('name')->join(', ') ?: 'None' }}</p><form method="post" action="{{ route('cbt.staff.types.store') }}" class="mt-2 flex gap-2">@csrf<select name="name" class="rounded border p-2">@foreach(['Internal','Entrance','Mock','Practice','Assessment','Promotional'] as $type)<option>{{ $type }}</option>@endforeach</select><button class="rounded border px-4 py-2">Add type</button></form></section>
<section class="mt-7"><h2 class="text-lg font-semibold">Create exam</h2><p class="text-sm text-slate-600">Entrance exams use a pass mark; student exams use a class and subject. To feed Results, also choose a term and assessment component.</p>
<form method="post" action="{{ route('cbt.staff.exams.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2">@csrf
<label>Type<select required name="exam_type_id" class="mt-1 w-full rounded border p-2">@foreach($types as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></label>
<label>Title<input required name="title" maxlength="160" class="mt-1 w-full rounded border p-2"></label>
<label>Duration, minutes<input required type="number" min="1" max="240" name="duration" value="45" class="mt-1 w-full rounded border p-2"></label>
<label>Start at, school time (WAT; optional)<input type="datetime-local" name="scheduled_at" class="mt-1 w-full rounded border p-2"></label>
<label>Closes at, school time (WAT; optional)<input type="datetime-local" name="closes_at" class="mt-1 w-full rounded border p-2"></label>
<label>Class (student exams)<select name="class_id" class="mt-1 w-full rounded border p-2"><option value="">None</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select></label>
<label>Subject (student exams)<select name="subject_id" class="mt-1 w-full rounded border p-2"><option value="">None</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></label>
<label>Term for Results (optional)<select name="term_id" class="mt-1 w-full rounded border p-2"><option value="">None</option>@foreach($terms as $term)<option value="{{ $term->id }}">{{ $term->name }}</option>@endforeach</select></label>
<label>Results component (optional)<select name="assessment_component_id" class="mt-1 w-full rounded border p-2"><option value="">None</option>@foreach($components as $component)<option value="{{ $component->id }}">{{ $component->name }} / {{ $component->max_score }}</option>@endforeach</select></label>
<label>Entrance pass mark (%)<input type="number" step="0.01" min="0" max="100" name="pass_mark" class="mt-1 w-full rounded border p-2"></label>
<label>Result release<select name="result_release_type" class="mt-1 w-full rounded border p-2"><option>Immediate</option><option>Scheduled</option><option>Manual</option></select></label>
<label>Scheduled release time, school time (WAT)<input type="datetime-local" name="result_release_at" class="mt-1 w-full rounded border p-2"></label>
<div class="space-y-1"><label class="block"><input type="checkbox" name="show_score" value="1"> Show score</label><label class="block"><input type="checkbox" name="show_correct_answers" value="1"> Show correct answers</label><label class="block"><input type="checkbox" name="show_explanations" value="1"> Show explanations</label><label class="block"><input type="checkbox" name="requires_approval" value="1"> Require release approval</label></div>
<button class="rounded bg-blue-700 px-4 py-2 text-white sm:col-span-2">Create draft exam</button></form></section>
<section class="mt-7"><h2 class="text-lg font-semibold">Exams</h2><ul class="mt-2 space-y-2">@forelse($exams as $exam)<li><a wire:navigate class="text-blue-700 underline" href="{{ route('cbt.staff.show', $exam) }}">{{ $exam->title }}</a> · @include('core._status-pill', ['status' => $exam->status]) · {{ $exam->result_release_type }}</li>@empty<li>No exams yet.</li>@endforelse</ul>{{ $exams->links() }}</section>
</main>@include('core._shell-end')
@livewireScripts
</body></html>
