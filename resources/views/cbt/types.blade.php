<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Exam types</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-3 text-slate-900 sm:p-5">@include('core._shell-start')
<main class="mx-auto max-w-4xl rounded-lg bg-white p-4 shadow sm:p-7">
<a href="{{ route('cbt.staff.index') }}" class="inline-flex min-h-11 items-center text-blue-700 underline">Back to CBT exams</a>
<h1 class="text-2xl font-bold">Exam types</h1><p class="text-sm text-slate-600">Choose which types may feed scores into Results.</p>
@if(session('status'))<p class="mt-3 rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</p>@endif
@if($errors->any())<div class="mt-3 rounded bg-red-50 p-3 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="mt-6 rounded border p-4"><h2 class="font-semibold">Create exam type</h2>
<form method="post" action="{{ route('cbt.staff.types.store') }}" class="mt-3 grid gap-3 sm:grid-cols-[1fr_auto_auto_auto]">@csrf
<label>Name<input name="name" required maxlength="100" class="mt-1 min-h-11 w-full rounded border p-2"></label>
<label class="flex min-h-11 items-center gap-2"><input type="hidden" name="feeds_results" value="0"><input type="checkbox" name="feeds_results" value="1"> Feeds Results</label>
<label class="flex min-h-11 items-center gap-2"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" checked> Active</label>
<button class="min-h-11 self-end rounded bg-blue-700 px-4 text-white">Create</button></form></section>
<section class="mt-6 grid gap-3"><h2 class="font-semibold">Current types</h2>
@foreach($types as $type)<article class="rounded border p-4"><form method="post" action="{{ route('cbt.staff.types.update', $type) }}" class="grid gap-3 sm:grid-cols-[1fr_auto_auto_auto]">@csrf @method('put')
<label>Name<input name="name" required maxlength="100" value="{{ $type->name }}" class="mt-1 min-h-11 w-full rounded border p-2"></label>
<label class="flex min-h-11 items-center gap-2"><input type="hidden" name="feeds_results" value="0"><input type="checkbox" name="feeds_results" value="1" @checked($type->feeds_results)> Feeds Results</label>
<label class="flex min-h-11 items-center gap-2"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($type->active)> Active</label>
<button class="min-h-11 self-end rounded border px-4">Save</button></form>
<div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm text-slate-600"><span>{{ $type->exams_count }} exam{{ $type->exams_count === 1 ? '' : 's' }}</span>@if($type->exams_count === 0)<form method="post" action="{{ route('cbt.staff.types.destroy', $type) }}">@csrf @method('delete')<button class="min-h-11 rounded border border-red-300 px-4 text-red-700">Delete unused type</button></form>@else<span>In use — deactivate instead of deleting</span>@endif</div>
</article>@endforeach</section>
</main>@include('core._shell-end')@livewireScripts</body></html>
