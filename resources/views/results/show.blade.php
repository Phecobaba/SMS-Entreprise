<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Subject result</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-5 text-slate-900">
@include('core._shell-start')<main class="mx-auto max-w-3xl rounded-lg bg-white p-7 shadow">
<a wire:navigate href="{{ route('results.index') }}" class="text-blue-700 underline">Results workspace</a><h1 class="mt-4 text-2xl font-bold">{{ $studentName }} · {{ $labels['subjects'][$result->subject_id] ?? 'Subject' }}</h1><p>{{ $labels['terms'][$result->term_id] ?? 'Term' }} · <strong>@include('core._status-pill', ['status' => $result->status])</strong> · {{ $result->total_score }} points / {{ $result->percentage }}% @if($result->grade)· {{ $result->grade }}@endif</p>
@if($result->locked_at)<p class="mt-2 rounded bg-green-50 p-2 text-green-900">Approved and locked at {{ $result->locked_at->format('d M Y H:i') }}.</p>@endif
@if($result->rejection_reason)<p class="mt-2 rounded bg-amber-50 p-2 text-amber-900">Correction requested: {{ $result->rejection_reason }}</p>@endif
@if(session('status'))<p class="mt-4 rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</p>@endif
@if($errors->any())<div class="mt-4 rounded bg-red-50 p-3 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="mt-6"><h2 class="text-lg font-semibold">Component scores</h2>
@can('enter_results')
    @if(in_array($result->status, ['Draft', 'Rejected'], true))
        <form method="post" action="{{ route('results.drafts.update', $result) }}" class="mt-3 space-y-3">@csrf<div class="grid gap-3 sm:grid-cols-2">@foreach($template->components as $entry)@php($item = $result->items->firstWhere('assessment_component_id', $entry->assessment_component_id))<label>{{ $entry->component->name }} / {{ $entry->component->max_score }}<input type="number" step="0.01" min="0" max="{{ $entry->component->max_score }}" name="scores[{{ $entry->assessment_component_id }}]" value="{{ $item?->score }}" class="mt-1 w-full rounded border p-2"></label>@endforeach</div><button class="rounded border px-4 py-2">Save draft</button></form>
        <form method="post" action="{{ route('results.submit', $result) }}" class="mt-3">@csrf<button class="rounded bg-blue-700 px-4 py-2 text-white">Submit for approval</button></form>
    @else
        <p class="mt-2">This result is read-only while submitted or approved.</p>
    @endif
@else
    <ul class="mt-2 list-disc pl-5">@foreach($result->items as $item)<li>{{ $item->component->name }}: {{ $item->score }}</li>@endforeach</ul>
@endcan
</section>
@can('approve_results')
    @if($result->status === 'Submitted')
        <section class="mt-6 rounded border p-4"><h2 class="font-semibold">Review</h2><form method="post" action="{{ route('results.approve', $result) }}" class="mt-3">@csrf<button class="rounded bg-green-700 px-4 py-2 text-white">Approve and lock</button></form><form method="post" action="{{ route('results.reject', $result) }}" class="mt-3 flex gap-2">@csrf<input required name="rejection_reason" placeholder="Reason for correction" class="flex-1 rounded border p-2"><button class="rounded border px-4 py-2">Reject</button></form></section>
    @endif
@endcan
<section class="mt-7"><h2 class="text-lg font-semibold">Audit trail</h2><ol class="mt-2 space-y-2">@foreach($result->audit as $event)<li class="border-b pb-2">{{ $event->created_at->format('d M Y H:i') }} · {{ $event->action }} · User #{{ $event->user_id }} · {{ $event->note }}</li>@endforeach</ol></section>
</main>@include('core._shell-end')
@livewireScripts
</body></html>
