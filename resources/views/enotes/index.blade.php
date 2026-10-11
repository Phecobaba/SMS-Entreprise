<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>E-Notes</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-5 text-slate-900">
@include('core._shell-start')<main class="mx-auto max-w-4xl rounded-lg bg-white p-7 shadow">
<a wire:navigate href="{{ route('dashboard') }}" class="text-blue-700 underline">Dashboard</a><h1 class="mt-4 text-2xl font-bold">E-Notes</h1>
@if(session('status'))<p class="mt-3 rounded bg-green-50 p-3">{{ session('status') }}</p>@endif
@if($errors->any())<div class="mt-3 rounded bg-red-50 p-3">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="mt-6"><h2 class="font-semibold">Upload a note</h2><p class="text-sm text-slate-600">Teachers can upload only for their assigned class and subject. Provide a file or one HTTPS link.</p>
<form method="post" action="{{ route('enotes.store') }}" enctype="multipart/form-data" class="mt-2 grid gap-3">@csrf
<select name="class_id" class="rounded border p-2" required><option value="">Class</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select>
<select name="subject_id" class="rounded border p-2" required><option value="">Subject</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select>
<input name="title" placeholder="Note title" class="rounded border p-2" maxlength="255" required><label>File (PDF, Word, PowerPoint; 10 MB max) <input name="file" type="file" class="block rounded border p-2"></label><input name="link" type="url" placeholder="Or HTTPS link" class="rounded border p-2"><button class="rounded bg-blue-700 p-2 text-white">Upload note</button></form></section>
<section class="mt-8"><h2 class="font-semibold">Find notes</h2><form method="get" class="mt-2 flex flex-wrap gap-2"><select name="class_id" class="rounded border p-2"><option value="">All classes</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected(($filters['class_id'] ?? null) == $class->id)>{{ $class->name }}</option>@endforeach</select><select name="subject_id" class="rounded border p-2"><option value="">All subjects</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected(($filters['subject_id'] ?? null) == $subject->id)>{{ $subject->name }}</option>@endforeach</select><button class="rounded border p-2">Filter</button></form>
<ul class="mt-4 space-y-2">@forelse($notes as $note)<li class="border-b p-2"><span class="font-semibold">{{ $note->title }}</span> · {{ $classLabels[$note->class_id] ?? 'Class #'.$note->class_id }} · {{ $subjectLabels[$note->subject_id] ?? 'Subject #'.$note->subject_id }} · @if($note->file_path)<a class="text-blue-700 underline" href="{{ route('enotes.download', $note->id) }}">Download</a>@else<a class="text-blue-700 underline" href="{{ $note->link }}" rel="noopener noreferrer" target="_blank">Open link</a>@endif</li>@empty<li>No notes match these filters.</li>@endforelse</ul>{{ $notes->links() }}</section>
</main>@include('core._shell-end')
@livewireScripts
</body></html>
