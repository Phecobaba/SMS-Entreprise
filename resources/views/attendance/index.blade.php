<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Attendance</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-5 text-slate-900">
@include('core._shell-start')<main class="mx-auto max-w-4xl rounded-lg bg-white p-7 shadow">
<a wire:navigate href="{{ route('home') }}" class="oak-btn oak-btn-quiet">Home</a><h1 class="mt-4 text-2xl font-bold">Daily attendance</h1>
<p class="mt-2 text-slate-600">School date: {{ $today }} (WAT). Select a class arm and mark its Morning or Afternoon roll.</p>
@if(session('status'))<p class="mt-3 rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</p>@endif
@if($errors->any())<div class="mt-3 rounded bg-red-50 p-3 text-red-800" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="ad-actions mt-4">@can('view_attendance_alerts')<a wire:navigate href="{{ route('attendance.alerts') }}" class="oak-btn oak-btn-secondary">Attendance alerts</a>@endcan @can('manage_attendance')<a wire:navigate href="{{ route('attendance.settings') }}" class="oak-btn oak-btn-secondary">Sessions &amp; times</a>@endcan</div>
<section class="mt-7"><h2 class="text-lg font-semibold">{{ auth()->user()->can('manage_attendance') ? 'Class arms' : 'My class arms' }}</h2><ul class="mt-3 space-y-3">
@forelse($arms as $arm)
@php($groups = $formTeacherCandidateGroups[$arm->schoolClass?->level?->section_id] ?? null)
<li class="rounded border p-3"><div class="flex flex-wrap items-center justify-between gap-3"><span><strong>{{ $arm->schoolClass?->name }} {{ $arm->name }}</strong> · {{ $counts[$arm->id] ?? 0 }} students
@if($arm->formTeacher) · Form teacher: {{ $arm->formTeacher->name }}@endif</span>@can('mark_attendance')<a wire:navigate class="oak-btn oak-btn-secondary" href="{{ route('attendance.day', $arm->id) }}">Mark today</a>@endcan</div>
@can('manage_attendance')<form method="post" action="{{ route('attendance.form-teachers.store') }}" class="mt-3 flex flex-wrap items-end gap-2">@csrf<input type="hidden" name="class_arm_id" value="{{ $arm->id }}"><label class="oak-field min-w-56"><span>Form teacher</span><select required name="user_id"><option value="">Choose a teacher…</option>@if($groups)<optgroup label="{{ $arm->schoolClass?->level?->section?->name }} teachers">@foreach($groups['section'] as $user)<option value="{{ $user->id }}" @selected($arm->form_teacher_user_id === $user->id)>{{ $user->name }}</option>@endforeach</optgroup>@if($groups['other']->isNotEmpty())<optgroup label="Other teachers">@foreach($groups['other'] as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</optgroup>@endif @endif</select></label><button class="oak-btn oak-btn-primary">{{ $arm->form_teacher_user_id ? 'Replace' : 'Assign' }}</button></form>@endcan
</li>
@empty<li>No class arm is assigned to you yet.</li>@endforelse
</ul><div class="mt-3">{{ $arms->links() }}</div></section>
</main>@include('core._shell-end')
@livewireScripts
</body></html>
