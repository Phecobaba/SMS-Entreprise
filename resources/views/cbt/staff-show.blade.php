<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $exam->title }}</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-3 text-slate-900 sm:p-5">
@include('core._shell-start')
<main class="mx-auto max-w-4xl rounded-lg bg-white p-4 shadow sm:p-7">
    <a wire:navigate href="{{ route('cbt.staff.index') }}" class="inline-flex min-h-11 items-center text-blue-700 underline">CBT exams</a>
    <div class="flex flex-wrap items-start justify-between gap-3"><div><h1 class="text-2xl font-bold">{{ $exam->title }}</h1><p>@include('core._status-pill', ['status' => $exam->effective_status]) · {{ $exam->duration }} minutes · {{ $exam->result_release_type }} release</p><p class="text-sm text-slate-600">{{ $exam->subject?->name ?? $exam->type?->name }}@if($exam->schoolClass) · {{ $exam->schoolClass->name }} · {{ $exam->schoolClass->level?->section?->name }}@endif</p><p class="text-sm text-slate-600">{{ $exam->closes_automatically ? 'Closes automatically at the end time' : 'Stays open until ended by a Head or Admin' }}</p></div>
        <div class="flex flex-wrap gap-2">@if($exam->class_id)<a href="{{ route('cbt.staff.progress', $exam) }}" class="inline-flex min-h-11 items-center rounded border px-4">Live progress</a>@endif<a href="{{ route('cbt.staff.attempts', $exam) }}" class="inline-flex min-h-11 items-center rounded border px-4">Attempts ({{ $exam->submissions_count }})</a>@if($exam->status === 'Draft')<a href="{{ route('cbt.staff.edit', $exam) }}" class="inline-flex min-h-11 items-center rounded border px-4">Edit exam</a>@endif</div>
    </div>
    @if(session('status'))<p class="mt-3 rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="mt-3 rounded bg-red-50 p-3 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    @if($editing)
        <section class="mt-6 rounded border p-4"><h2 class="text-lg font-semibold">Edit exam</h2>
            <form method="post" action="{{ route('cbt.staff.update', $exam) }}" class="mt-3 grid gap-3 sm:grid-cols-2">@csrf @method('put')
                @include('cbt._exam-fields')
                <div class="flex flex-wrap gap-2 sm:col-span-2"><button class="min-h-11 rounded bg-blue-700 px-4 text-white">Save exam</button><a href="{{ route('cbt.staff.show', $exam) }}" class="inline-flex min-h-11 items-center rounded border px-4">Cancel</a></div>
            </form>
        </section>
    @endif

    <section class="mt-6"><h2 class="text-lg font-semibold">Questions</h2>
        <ol class="mt-2 grid gap-4">
            @forelse($exam->questions as $question)
                <li class="rounded border p-4"><strong>{{ $question->position }}. {{ $question->text }}</strong> ({{ $question->totalMarks() }} {{ \Illuminate\Support\Str::plural('mark', $question->totalMarks()) }})
                    <ul class="mt-2 list-disc pl-5">@foreach($question->options as $option)<li>{{ $option->text }} @if($option->is_correct)<span class="font-semibold text-green-700">Correct · {{ $option->marks }} {{ \Illuminate\Support\Str::plural('mark', $option->marks) }}</span>@endif</li>@endforeach</ul>
                    <p class="mt-2 text-sm text-slate-600">Pupil may choose up to {{ $question->answerLimit() }} {{ \Illuminate\Support\Str::plural('answer', $question->answerLimit()) }}.</p>
                    @if($exam->status === 'Draft' && $exam->submissions_count === 0)
                        <details class="mt-3"><summary class="flex min-h-11 cursor-pointer items-center text-blue-700">Edit question</summary>
                            <form method="post" action="{{ route('cbt.staff.questions.update', [$exam, $question]) }}" class="mt-2">@csrf @method('put') @include('cbt._question-fields', ['question' => $question])</form>
                        </details>
                        <form method="post" action="{{ route('cbt.staff.questions.destroy', [$exam, $question]) }}" class="mt-2">@csrf @method('delete')<button class="min-h-11 rounded border border-red-300 px-4 text-red-700">Delete question</button></form>
                    @endif
                </li>
            @empty<li class="rounded border border-dashed p-4 text-slate-600">No questions yet.</li>@endforelse
        </ol>
    </section>

    @if($exam->status === 'Draft')
        @if($exam->submissions_count === 0)
            <section class="mt-7 rounded border p-4">
                <h2 class="font-semibold">Upload question CSV</h2>
                <p class="mt-1 text-sm text-slate-600">Add up to 200 questions from a CSV file. Nothing is saved until the preview is applied.</p>
                @if(in_array(app(\App\Modules\Core\Services\AppNavigation::class)->staffKind(auth()->user()), ['admin', 'head'], true))
                    <details class="mt-3 rounded border bg-slate-50 p-3"><summary class="flex min-h-11 cursor-pointer items-center font-semibold">How to use</summary><ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-700"><li>Download the template.</li><li>Use one question per row and write only the options you need.</li><li>For one correct option, enter one letter. For several, give each a mark, for example A=2, C=1.</li><li>Upload, check the preview, then Apply.</li><li>If anything is wrong, nothing is saved.</li><li>An exam can have at most 200 questions.</li></ul></details>
                @endif
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('cbt.staff.questions.template', $exam) }}" class="inline-flex min-h-11 items-center rounded border px-4">Download template</a>
                </div>
                <form method="post" enctype="multipart/form-data" action="{{ route('cbt.staff.questions.preview', $exam) }}" class="mt-3 grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                    @csrf
                    <label class="grid gap-1">Question CSV <span class="text-sm text-slate-600">CSV only, up to 1 MB.</span><input class="min-h-11 min-w-0 rounded border p-2" required type="file" name="file" accept=".csv,text/csv"></label>
                    <button class="min-h-11 rounded bg-blue-700 px-4 text-white">Preview upload</button>
                </form>

                @isset($questionUploadPreview)
                    <div class="mt-4 rounded bg-slate-50 p-4">
                        @if($questionUploadPreview['error_count'] > 0)
                            <h3 class="font-semibold text-red-800">Fix these errors and upload again</h3>
                            <p class="mt-1 text-sm text-red-800">{{ $questionUploadPreview['error_count'] }} {{ \Illuminate\Support\Str::plural('error', $questionUploadPreview['error_count']) }} found. Showing the first {{ count($questionUploadPreview['errors']) }}.</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-800">@foreach($questionUploadPreview['errors'] as $error)<li>{{ $error['message'] }}</li>@endforeach</ul>
                        @else
                            <h3 class="font-semibold">Preview</h3>
                            <p class="mt-1 text-sm text-slate-700">{{ $questionUploadPreview['questions_to_add'] }} questions to add · {{ $questionUploadPreview['total_marks'] }} total marks · {{ $questionUploadPreview['resulting_question_count'] }} questions after upload.</p>
                            @if($questionUploadPreview['questions_to_add'] > 20)<p class="mt-1 text-sm text-slate-600">Showing the first 20 questions. All {{ $questionUploadPreview['questions_to_add'] }} validated questions will be applied.</p>@endif
                            <ol class="mt-3 grid gap-3">
                                @foreach(array_slice($questionUploadPreview['rows'], 0, 20) as $row)
                                    <li class="rounded border bg-white p-3"><strong>{{ $exam->questions->count() + $loop->iteration }}. {{ $row['text'] }}</strong><p class="text-sm text-slate-600">{{ $row['answer_limit'] === 1 ? 'Single answer' : 'Multiple answer · pupil may choose up to '.$row['answer_limit'] }} · {{ $row['total_marks'] }} {{ \Illuminate\Support\Str::plural('mark', $row['total_marks']) }}</p><ul class="mt-2 list-disc pl-5 text-sm">@foreach($row['options'] as $option)<li>{{ $option['text'] }}@if($option['is_correct']) <strong class="text-green-700">Marked · {{ $option['marks'] }}</strong>@endif</li>@endforeach</ul></li>
                                @endforeach
                            </ol>
                            <form method="post" action="{{ route('cbt.staff.questions.apply', $exam) }}" class="mt-4">@csrf<input type="hidden" name="upload_token" value="{{ $questionUploadPreview['token'] }}"><button class="min-h-11 rounded bg-blue-700 px-4 text-white">Apply upload</button></form>
                        @endif
                        <form method="post" action="{{ route('cbt.staff.questions.cancel', $exam) }}" class="mt-2">@csrf<input type="hidden" name="upload_token" value="{{ $questionUploadPreview['token'] }}"><button class="min-h-11 rounded border px-4">Cancel upload</button></form>
                    </div>
                @endisset
            </section>
            <form method="post" action="{{ route('cbt.staff.questions.store', $exam) }}" class="mt-7 rounded border p-4">@csrf<h2 class="mb-3 font-semibold">Add question</h2>@include('cbt._question-fields')</form>
        @else<p class="mt-7 rounded border border-amber-300 bg-amber-50 p-4 text-amber-900">Questions, options and marks are locked because an attempt has started.</p>@endif
        <div class="mt-4 flex flex-wrap gap-2"><form method="post" action="{{ route('cbt.staff.publish', $exam) }}">@csrf<button class="min-h-11 rounded bg-blue-700 px-4 text-white">Publish exam</button></form>
            <form method="post" action="{{ route('cbt.staff.destroy', $exam) }}">@csrf @method('delete')<button class="min-h-11 rounded border border-red-300 px-4 text-red-700">Delete draft</button></form></div>
        @if($exam->submissions_count > 0)<p class="mt-2 text-sm text-red-700">This draft has attempts and cannot be deleted.</p>@endif
    @endif
    @if($exam->status === 'Published' && $exam->effective_status !== 'Closed')<div class="mt-7 flex flex-wrap gap-2">@if($exam->result_release_type === 'Manual' && ! $exam->is_released)<form method="post" action="{{ route('cbt.staff.release', $exam) }}">@csrf<button class="min-h-11 rounded border px-4">Release results</button></form>@endif<details class="rounded border p-3"><summary class="flex min-h-11 cursor-pointer items-center font-semibold text-red-700">End exam</summary><p class="mt-2 text-sm">This ends the exam immediately and submits every pupil who is still writing from their saved answers. Pupils cannot start afterward. This cannot be undone.</p><form method="post" action="{{ route('cbt.staff.close', $exam) }}" class="mt-3">@csrf<button class="min-h-11 rounded bg-red-700 px-4 text-white">Confirm end exam</button></form></details></div>@endif
    @if($exam->effective_status === 'Closed' && $exam->result_release_type === 'Manual' && ! $exam->is_released)<form method="post" action="{{ route('cbt.staff.release', $exam) }}" class="mt-7">@csrf<button class="min-h-11 rounded border px-4">Release results</button></form>@endif
    @if($exam->type?->feeds_results && $exam->term_id && $exam->assessment_component_id)<a href="{{ route('cbt.staff.conversion', $exam) }}" class="mt-7 inline-flex min-h-11 items-center rounded bg-blue-700 px-4 text-white">Convert scores into Results</a>@endif
</main>
@include('core._shell-end')
@livewireScripts
</body></html>
