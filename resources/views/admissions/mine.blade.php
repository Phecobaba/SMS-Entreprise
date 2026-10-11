<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>My admission</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-5 text-slate-900">
@include('core._shell-start')<main class="mx-auto max-w-2xl rounded-lg bg-white p-7 shadow">
<h1 class="text-2xl font-bold">My admission</h1><p class="mt-2">{{ $candidate->name }} · <strong>@include('core._status-pill', ['status' => $candidate->status])</strong></p><a wire:navigate class="mt-3 inline-block text-blue-700 underline" href="{{ route('cbt.mine') }}">My entrance exams</a>
<section class="mt-5"><h2 class="font-semibold">Invoices</h2>@forelse($invoices as $invoice)<div class="border-b py-2">{{ $invoice->purpose }} · ₦{{ number_format($invoice->amount_kobo / 100, 2) }} · @include('core._status-pill', ['status' => $invoice->status]) @if($invoice->status === 'Unpaid')<form method="post" action="{{ route('payments.mine.begin', $invoice) }}" class="inline">@csrf<button class="text-blue-700 underline">Pay with Paystack</button></form>@endif</div>@empty<p>Any registration invoice will appear here once Bursar issues it.</p>@endforelse{{ $invoices->links() }}</section>
@if(session('status'))<p class="mt-4 rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</p>@endif
@if($errors->any())<div class="mt-4 rounded bg-red-50 p-3 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if($candidate->status === 'Payment Pending')<p class="mt-4 rounded bg-amber-50 p-3">Registration payment is pending. Pay an issued invoice above with Paystack, or contact Bursar if no invoice appears.</p>@endif
@if($candidate->offer)<section class="mt-6"><h2 class="text-lg font-semibold">Admission offer</h2><p>Status: {{ $candidate->offer->status }} · Respond by {{ $candidate->offer->response_deadline->format('d M Y') }}</p>
@if($candidate->offer->status === 'Pending' && $candidate->offer->response_deadline->isToday() || $candidate->offer->status === 'Pending' && $candidate->offer->response_deadline->isFuture())
<div class="mt-3 flex gap-2">@foreach(['Accepted' => 'Accept', 'Declined' => 'Decline'] as $value => $label)<form method="post" action="{{ route('admissions.mine.respond') }}">@csrf<input type="hidden" name="response" value="{{ $value }}"><button class="rounded border px-4 py-2">{{ $label }} offer</button></form>@endforeach</div>
@endif</section>@endif
<section class="mt-6"><h2 class="text-lg font-semibold">Checklist</h2><ul class="mt-2 list-inside list-disc">@forelse($candidate->checklist as $entry)<li>{{ $entry->item->label }} — {{ $entry->is_complete ? 'Complete' : 'Pending' }}</li>@empty<li>School checklist not configured yet.</li>@endforelse</ul></section>
<section class="mt-6"><h2 class="text-lg font-semibold">Status history</h2><ol class="mt-2 space-y-1">@foreach($candidate->history as $event)<li>{{ $event->changed_at->format('d M Y H:i') }} · {{ $event->to_status }}</li>@endforeach</ol></section>
<form method="post" action="{{ route('logout') }}" class="mt-7">@csrf<button class="text-blue-700 underline">Sign out</button></form>
</main>@include('core._shell-end')
@livewireScripts
</body></html>
