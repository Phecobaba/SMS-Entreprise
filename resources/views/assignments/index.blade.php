<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Assignments</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-5 text-slate-900">
@include('core._shell-start')<main class="mx-auto max-w-4xl rounded-lg bg-white p-7 shadow">
<a wire:navigate href="{{ route('dashboard') }}" class="text-blue-700 underline">Dashboard</a><h1 class="mt-4 text-2xl font-bold">Assignments</h1>
@if(session('status'))<p class="mt-3 rounded bg-green-50 p-3">{{ session('status') }}</p>@endif
@if($errors->any())<div class="mt-3 rounded bg-red-50 p-3">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="mt-6"><h2 class="font-semibold">Create for a class</h2><form method="post" action="{{ route('assignments.store') }}" class="mt-2 grid gap-3">@csrf
<select name="class_arm_id" class="rounded border p-2" required><option value="">Class arm</option>@foreach($arms as $arm)<option value="{{ $arm->id }}">{{ $arm->schoolClass?->name }} {{ $arm->name }}</option>@endforeach</select>
<select name="subject_id" class="rounded border p-2" required><option value="">Subject</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select>
<input name="title" placeholder="Assignment title" class="rounded border p-2" required maxlength="255"><textarea name="description" placeholder="Instructions (optional)" class="rounded border p-2"></textarea>
<label>Due date <input type="date" name="due_date" class="rounded border p-2" required></label><button class="rounded bg-blue-700 p-2 text-white">Create assignment</button></form></section>
<section class="mt-8"><h2 class="font-semibold">Recent assignments</h2><ul class="mt-2 space-y-2">@forelse($assignments as $assignment)<li><a wire:navigate class="text-blue-700 underline" href="{{ route('assignments.show', $assignment->id) }}">{{ $assignment->title }}</a> · due {{ $assignment->due_date->format('d M Y') }}</li>@empty<li>No assignments yet.</li>@endforelse</ul>{{ $assignments->links() }}</section>
@can('manage_assignments')<section class="mt-8"><h2 class="font-semibold">Link a parent account to a student</h2><p class="text-sm text-slate-600">Choose an existing active login and an enrolled student.</p><form method="post" action="{{ route('assignments.parent-wards.store') }}" class="mt-2 grid gap-3">@csrf
<select name="parent_user_id" class="rounded border p-2" required><option value="">Parent login</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>@endforeach</select>
<select name="student_id" class="rounded border p-2" required><option value="">Student</option>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->user?->name }}</option>@endforeach</select><button class="rounded border p-2">Link parent and student</button></form>{{ $students->links() }}</section>@endcan
@can('manage_assignments')<section class="mt-8"><h2 class="font-semibold">Create a parent login</h2><form method="post" action="{{ route('assignments.parents.store') }}" class="mt-2 grid gap-3">@csrf
<input name="name" placeholder="Parent name" class="rounded border p-2" required><input type="email" name="email" placeholder="Email" class="rounded border p-2" required><input type="password" name="password" placeholder="Temporary password (12+ characters)" minlength="12" class="rounded border p-2" required>
<select name="student_id" class="rounded border p-2" required><option value="">Student</option>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->user?->name }}</option>@endforeach</select><button class="rounded bg-blue-700 p-2 text-white">Create parent and link ward</button></form></section>@endcan
</main>@include('core._shell-end')
@livewireScripts
</body></html>
