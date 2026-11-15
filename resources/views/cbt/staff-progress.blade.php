<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Live progress · {{ $record->title }}</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-3 text-slate-900 sm:p-5">@include('core._shell-start')
<main class="mx-auto max-w-6xl rounded-lg bg-white p-4 shadow sm:p-7" data-progress-page data-refresh-url="{{ route('cbt.staff.progress.refresh', ['exam' => $record, 'status' => $filter, 'page' => $pupils->currentPage()]) }}">
    <div class="flex flex-wrap items-start justify-between gap-3"><div><a href="{{ route('cbt.staff.show', $record) }}" class="inline-flex min-h-11 items-center text-blue-700 underline">Back to exam</a><h1 class="text-2xl font-bold">Live progress · {{ $record->title }}</h1><p class="text-sm text-slate-600">{{ $record->schoolClass?->name }} · {{ $record->subject?->name }}</p></div><a href="{{ route('cbt.staff.progress', ['exam' => $record, 'status' => $filter, 'page' => $pupils->currentPage()]) }}" class="inline-flex min-h-11 items-center rounded border px-4">Refresh</a></div>
    @if(session('status'))<div class="mt-4 rounded border border-green-300 bg-green-50 p-3 text-green-800" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="mt-4 rounded border border-red-300 bg-red-50 p-3 text-red-800" role="alert">{{ $errors->first() }}</div>@endif
    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-slate-600"><span data-updated-at>Updated at {{ now('Africa/Lagos')->format('H:i:s') }}</span><span class="hidden text-red-700" data-refresh-error></span></div>
    <form method="get" class="mt-5 flex flex-wrap items-end gap-3"><label class="grid gap-1 font-medium">Status<select name="status" class="min-h-11 rounded border px-3"><option value="">All</option>@foreach(['not_started'=>'Not started','writing'=>'Writing','submitted'=>'Submitted','absent'=>'Absent'] as $value=>$label)<option value="{{ $value }}" @selected($filter===$value)>{{ $label }}</option>@endforeach</select></label><button class="inline-flex min-h-11 items-center rounded border px-4" type="submit">Filter</button></form>
    <div data-progress-content>@include('cbt._progress-content')</div>
</main>
<script>
(() => {
    const page = document.querySelector('[data-progress-page]');
    if (!page) return;
    let stopped = false;
    let timer;
    const hasOpenOrFocusedForm = () => page.querySelector('details[open]') || (page.contains(document.activeElement) && document.activeElement.matches('input, select, textarea, button'));
    const schedule = () => { clearTimeout(timer); if (!stopped) timer = setTimeout(refresh, 10000); };
    const fail = (message) => {
        stopped = true;
        const target = page.querySelector('[data-refresh-error]');
        target.classList.remove('hidden');
        target.innerHTML = message + ' <a class="underline" href="{{ route('login') }}">Sign in again</a>.';
    };
    const refresh = async () => {
        if (stopped || document.hidden || hasOpenOrFocusedForm()) { schedule(); return; }
        try {
            const response = await fetch(page.dataset.refreshUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) { fail('Live progress stopped.'); return; }
            const data = await response.json();
            page.querySelector('[data-progress-content]').innerHTML = data.html;
            page.querySelector('[data-updated-at]').textContent = 'Updated at ' + data.updated_at;
            schedule();
        } catch (error) { fail('Live progress could not be refreshed.'); }
    };
    document.addEventListener('visibilitychange', () => { if (!document.hidden && !stopped) refresh(); });
    schedule();
})();
</script>
@include('core._shell-end')@livewireScripts</body></html>
