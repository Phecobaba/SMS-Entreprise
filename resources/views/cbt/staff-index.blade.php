<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>CBT exams</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-3 text-slate-900 sm:p-5">
@include('core._shell-start')
<main class="mx-auto max-w-5xl rounded-lg bg-white p-4 shadow sm:p-7">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><a wire:navigate href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center text-blue-700 underline">Dashboard</a><h1 class="text-2xl font-bold">CBT exams</h1></div>
        <div class="flex flex-wrap gap-2">@can('view_all_sections')<a wire:navigate href="{{ route('cbt.staff.types.index') }}" class="inline-flex min-h-11 items-center rounded border px-4">Exam types</a>@endcan<a wire:navigate href="{{ route('cbt.results.index') }}" class="inline-flex min-h-11 items-center rounded border px-4">View CBT results</a></div>
    </div>
    @if(session('status'))<p class="mt-3 rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="mt-3 rounded bg-red-50 p-3 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <section class="mt-7"><h2 class="text-lg font-semibold">Create exam</h2><p class="text-sm text-slate-600">Student exams use a class and subject. Entrance CBT is available only to the school office.</p>
        <form method="post" action="{{ route('cbt.staff.exams.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2">@csrf
            @include('cbt._exam-fields', ['exam' => null])
            <button class="min-h-11 rounded bg-blue-700 px-4 py-2 text-white sm:col-span-2">Create draft exam</button>
        </form>
    </section>

    <section class="mt-7"><h2 class="text-lg font-semibold">Exams</h2>
        <div class="mt-2 grid gap-3">
            @forelse($exams as $exam)
                <article class="rounded border p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2"><div><a wire:navigate class="inline-flex min-h-11 items-center font-semibold text-blue-700 underline" href="{{ route('cbt.staff.show', $exam) }}">{{ $exam->title }}</a><p class="text-sm text-slate-600">{{ $exam->subject?->name ?? $exam->type?->name }}@if($exam->schoolClass) · {{ $exam->schoolClass->name }} · {{ $exam->schoolClass->level?->section?->name }}@elseif($exam->pass_mark !== null && $exam->levels->isNotEmpty()) · {{ $exam->levels->pluck('name')->join(', ') }}@endif</p>@if($exam->pass_mark !== null && $exam->levels->isEmpty())<p class="text-sm font-semibold text-amber-700">No Level linked: visible to all candidates</p>@endif</div><span>@include('core._status-pill', ['status' => $exam->effective_status])</span></div>
                    <p class="text-sm">{{ $exam->submissions_count }} attempt{{ $exam->submissions_count === 1 ? '' : 's' }} · {{ $exam->result_release_type }} release</p>
                </article>
            @empty<p class="rounded border border-dashed p-5 text-slate-600">No exams in your Sections yet.</p>@endforelse
        </div>
        <div class="mt-4">{{ $exams->links() }}</div>
    </section>
</main>
@include('core._shell-end')
@livewireScripts
</body></html>
