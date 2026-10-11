<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Apply for admission</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-5 text-slate-900">
@include('core._shell-start')@include('core._public-header')<main class="mx-auto max-w-xl rounded-lg bg-white p-7 shadow">
<a wire:navigate class="text-blue-700 underline" href="{{ route('home') }}">Home</a><h1 class="mt-4 text-2xl font-bold">Apply for admission</h1>
<p class="mt-2 text-sm text-slate-600">Create an account to track your application. Once Bursar issues a registration invoice, you can pay it from My Admission.</p>
@if($errors->any())<div class="mt-4 rounded bg-red-50 p-3 text-red-800"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="post" action="{{ route('admissions.register.store') }}" class="mt-5 space-y-4">@csrf
<label class="block">Candidate name<input required name="name" value="{{ old('name') }}" class="mt-1 w-full rounded border p-2"></label>
<label class="block">Email<input required type="email" name="email" value="{{ old('email') }}" class="mt-1 w-full rounded border p-2"></label>
<label class="block">Phone<input required name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded border p-2"></label>
<label class="block">Password (12 characters minimum)<input required type="password" name="password" class="mt-1 w-full rounded border p-2"></label>
<label class="block">Confirm password<input required type="password" name="password_confirmation" class="mt-1 w-full rounded border p-2"></label>
<button class="rounded bg-blue-700 px-5 py-2 text-white">Register</button>
</form></main>@include('core._shell-end')
@livewireScripts
</body></html>
