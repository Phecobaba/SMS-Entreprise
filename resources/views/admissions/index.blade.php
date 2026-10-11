<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admissions</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-5 text-slate-900">
@include('core._shell-start')<main class="mx-auto max-w-5xl rounded-lg bg-white p-7 shadow">
<a wire:navigate class="text-blue-700 underline" href="{{ route('dashboard') }}">Dashboard</a><h1 class="mt-4 text-2xl font-bold">Admissions</h1>
@if(session('status'))<p class="mt-4 rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</p>@endif
@if($errors->any())<div class="mt-4 rounded bg-red-50 p-3 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="mt-6 rounded border p-4"><h2 class="font-semibold">Admission checklist items</h2><ul class="mt-2 list-inside list-disc">@foreach($items as $item)<li>{{ $item->label }}</li>@endforeach</ul>
<form method="post" action="{{ route('admissions.items.store') }}" class="mt-3 flex gap-2">@csrf<input required name="label" placeholder="New requirement" class="flex-1 rounded border p-2"><button class="rounded bg-blue-700 px-4 text-white">Add item</button></form></section>
<div class="mt-2">{{ $items->links() }}</div>
<h2 class="mt-7 text-lg font-semibold">Candidates</h2><div class="mt-3 overflow-x-auto"><table class="w-full text-left"><thead><tr class="border-b"><th class="p-2">Name</th><th class="p-2">Status</th><th class="p-2">Offer</th><th class="p-2">Registered</th></tr></thead><tbody>@forelse($candidates as $candidate)<tr class="border-b"><td class="p-2"><a wire:navigate class="text-blue-700 underline" href="{{ route('admissions.show', $candidate) }}">{{ $candidate->name }}</a></td><td class="p-2">@include('core._status-pill', ['status' => $candidate->status])</td><td class="p-2">{{ $candidate->offer?->status ?? '—' }}</td><td class="p-2">{{ $candidate->created_at->format('d M Y') }}</td></tr>@empty<tr><td class="p-3" colspan="4">No candidates yet. Share <a wire:navigate class="text-blue-700 underline" href="{{ route('admissions.register') }}">the registration form</a>.</td></tr>@endforelse</tbody></table></div>
<div class="mt-4">{{ $candidates->links() }}</div></main>@include('core._shell-end')
@livewireScripts
</body></html>
