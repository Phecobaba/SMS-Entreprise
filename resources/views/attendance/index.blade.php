<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Attendance</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-5 text-slate-900">
@include('core._shell-start')<main class="mx-auto max-w-4xl rounded-lg bg-white p-7 shadow">
<a wire:navigate href="{{ route('home') }}" class="text-blue-700 underline">Home</a><h1 class="mt-4 text-2xl font-bold">Daily attendance</h1>
<p class="mt-2 text-slate-600">School date: {{ $today }} (WAT). Select a class arm and mark its Morning or Afternoon roll.</p>
@if(session('status'))<p class="mt-3 rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</p>@endif
@if($errors->any())<div class="mt-3 rounded bg-red-50 p-3 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="ad-actions mt-4">@can('view_attendance_alerts')<a wire:navigate href="{{ route('attendance.alerts') }}" class="oak-btn oak-btn-secondary">Attendance alerts</a>@endcan @can('manage_attendance')<a wire:navigate href="{{ route('attendance.settings') }}" class="oak-btn oak-btn-secondary">Sessions &amp; times</a>@endcan</div>
<section class="mt-7"><h2 class="text-lg font-semibold">My class arms</h2><ul class="mt-3 space-y-2">
@forelse($arms as $arm)
<li class="flex items-center justify-between border-b pb-2"><span>{{ $arm->schoolClass?->name }} {{ $arm->name }} · {{ $counts[$arm->id] ?? 0 }} students</span><a wire:navigate class="text-blue-700 underline" href="{{ route('attendance.day', $arm->id) }}">Mark today</a></li>
@empty<li>No class arm is assigned to you yet.</li>@endforelse
</ul><div class="mt-3">{{ $arms->links() }}</div></section>
@can('manage_attendance')<section class="mt-7 rounded border p-4"><h2 class="text-lg font-semibold">Assign form teacher</h2><p class="text-sm text-slate-600">The user must have the mark_attendance permission. Use the Teacher role for teacher accounts.</p>
<form method="post" action="{{ route('attendance.form-teachers.store') }}" class="mt-3 flex flex-wrap gap-2">@csrf
<select required name="class_arm_id" class="rounded border p-2"><option value="">Class arm</option>@foreach($arms as $arm)<option value="{{ $arm->id }}">{{ $arm->schoolClass?->name }} {{ $arm->name }}</option>@endforeach</select>
<select required name="user_id" class="rounded border p-2"><option value="">User</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>@endforeach</select>
<button class="rounded bg-blue-700 px-4 py-2 text-white">Assign</button></form></section>@endcan
</main>@include('core._shell-end')
@livewireScripts
</body></html>
