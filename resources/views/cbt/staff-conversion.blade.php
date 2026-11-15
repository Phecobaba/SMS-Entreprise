<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Convert CBT scores</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-3 text-slate-900 sm:p-5">
@include('core._shell-start')
<main class="mx-auto max-w-3xl rounded-lg bg-white p-4 shadow sm:p-7">
    <a href="{{ route('cbt.staff.show', $exam) }}" class="inline-flex min-h-11 items-center text-blue-700 underline">Back to exam</a>
    <h1 class="text-2xl font-bold">{{ $applied ? 'Conversion complete' : 'Convert scores into Results?' }}</h1>
    <p class="mt-1 text-slate-600">{{ $exam->title }} · {{ $exam->subject?->name }} · {{ $exam->schoolClass?->name }}</p>
    @if($applied)<p class="mt-4 rounded bg-green-50 p-3 text-green-800">The previewed conversion was applied.</p>@else<p class="mt-4 rounded bg-amber-50 p-3 text-amber-900">CBT scores replace the existing typed score for this component only while the Result is still Draft. This does not submit or approve a Result.</p>@endif

    <dl class="mt-5 grid gap-3 sm:grid-cols-3">
        <div class="rounded border p-4"><dt class="text-sm text-slate-600">Scores written</dt><dd class="text-2xl font-bold">{{ $summary['write_count'] }}</dd></div>
        <div class="rounded border p-4"><dt class="text-sm text-slate-600">Existing scores replaced</dt><dd class="text-2xl font-bold">{{ $summary['replace_count'] }}</dd></div>
        <div class="rounded border p-4"><dt class="text-sm text-slate-600">Pupils skipped</dt><dd class="text-2xl font-bold">{{ $summary['skipped_count'] }}</dd></div>
    </dl>

    @if($summary['skipped'])<section class="mt-6"><h2 class="font-semibold">Skipped pupils</h2><ul class="mt-2 grid gap-2">@foreach($summary['skipped'] as $pupil)<li class="rounded border p-3"><strong>{{ $pupil['name'] }}</strong><span class="block text-sm text-slate-600">{{ $pupil['reason'] }}</span></li>@endforeach</ul></section>@endif

    @unless($applied)<form method="post" action="{{ route('cbt.staff.convert', $exam) }}" class="mt-6">@csrf<button class="min-h-11 rounded bg-blue-700 px-4 text-white">Confirm conversion</button></form>@endunless
</main>
@include('core._shell-end')
@livewireScripts
</body></html>
