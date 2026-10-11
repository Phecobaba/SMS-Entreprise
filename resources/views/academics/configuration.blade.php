<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Academic configuration</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
@include('core._shell-start')
    <main class="mx-auto max-w-6xl space-y-6 px-4 py-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a wire:navigate href="{{ route('dashboard') }}" class="text-sm text-blue-700 underline">Back to dashboard</a>
                <h1 class="mt-2 text-3xl font-semibold">Academic configuration</h1>
                <p class="text-slate-600">Set up levels and sessions, then classes, arms, and subjects.</p>
            </div>
        </div>

        @if (session('status'))
            <div role="status" class="rounded border border-green-300 bg-green-50 p-3 text-green-900">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded border border-red-300 bg-red-50 p-3 text-red-900">
                <p class="font-medium">Please fix the following:</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-lg bg-white p-5 shadow" aria-labelledby="levels-heading">
                <h2 id="levels-heading" class="text-xl font-semibold">Academic levels</h2>
                <form method="post" action="{{ route('academics.levels.store') }}" class="mt-4 flex flex-wrap gap-2">
                    @csrf
                    <input name="name" required maxlength="100" placeholder="e.g. Primary" aria-label="Academic level name" class="min-w-48 flex-1 rounded border p-2">
                    <button class="rounded bg-blue-700 px-4 py-2 text-white">Add level</button>
                </form>
                <ul class="mt-4 divide-y">@forelse ($levels as $level)<li class="py-2">{{ $level->name }}</li>@empty<li class="py-2 text-slate-500">No levels yet.</li>@endforelse</ul>
                <div class="mt-3">{{ $levels->links() }}</div>
            </section>

            <section class="rounded-lg bg-white p-5 shadow" aria-labelledby="sessions-heading">
                <h2 id="sessions-heading" class="text-xl font-semibold">Sessions</h2>
                <form method="post" action="{{ route('academics.sessions.store') }}" class="mt-4 grid gap-2 sm:grid-cols-2">
                    @csrf
                    <input name="name" required maxlength="100" placeholder="e.g. 2026/2027" aria-label="Session name" class="rounded border p-2 sm:col-span-2">
                    <label class="text-sm">Starts <input type="date" name="start_date" required class="mt-1 w-full rounded border p-2"></label>
                    <label class="text-sm">Ends <input type="date" name="end_date" required class="mt-1 w-full rounded border p-2"></label>
                    <button class="rounded bg-blue-700 px-4 py-2 text-white sm:col-span-2">Add session</button>
                </form>
                <ul class="mt-4 divide-y">@forelse ($sessions as $session)<li class="py-2">{{ $session->name }} <span class="text-sm text-slate-500">({{ $session->start_date->toDateString() }}–{{ $session->end_date->toDateString() }})</span></li>@empty<li class="py-2 text-slate-500">No sessions yet.</li>@endforelse</ul>
                <div class="mt-3">{{ $sessions->links() }}</div>
            </section>

            <section class="rounded-lg bg-white p-5 shadow" aria-labelledby="terms-heading">
                <h2 id="terms-heading" class="text-xl font-semibold">Terms</h2>
                <form method="post" action="{{ route('academics.terms.store') }}" class="mt-4 grid gap-2 sm:grid-cols-2">
                    @csrf
                    <select name="session_id" required aria-label="Session for term" class="rounded border p-2 sm:col-span-2"><option value="">Select session</option>@foreach ($sessionOptions as $session)<option value="{{ $session->id }}">{{ $session->name }}</option>@endforeach</select>
                    <input name="name" required maxlength="100" placeholder="e.g. First Term" aria-label="Term name" class="rounded border p-2 sm:col-span-2">
                    <label class="text-sm">Starts <input type="date" name="start_date" required class="mt-1 w-full rounded border p-2"></label>
                    <label class="text-sm">Ends <input type="date" name="end_date" required class="mt-1 w-full rounded border p-2"></label>
                    <button class="rounded bg-blue-700 px-4 py-2 text-white sm:col-span-2">Add term</button>
                </form>
                <ul class="mt-4 divide-y">@forelse ($terms as $term)<li class="py-2">{{ $term->session->name }} — {{ $term->name }}</li>@empty<li class="py-2 text-slate-500">No terms yet.</li>@endforelse</ul>
                <div class="mt-3">{{ $terms->links() }}</div>
            </section>

            <section class="rounded-lg bg-white p-5 shadow" aria-labelledby="classes-heading">
                <h2 id="classes-heading" class="text-xl font-semibold">Classes</h2>
                <form method="post" action="{{ route('academics.classes.store') }}" class="mt-4 grid gap-2 sm:grid-cols-2">
                    @csrf
                    <select name="academic_level_id" required aria-label="Academic level for class" class="rounded border p-2"><option value="">Select level</option>@foreach ($levelOptions as $level)<option value="{{ $level->id }}">{{ $level->name }}</option>@endforeach</select>
                    <input name="name" required maxlength="100" placeholder="e.g. Primary 1" aria-label="Class name" class="rounded border p-2">
                    <button class="rounded bg-blue-700 px-4 py-2 text-white sm:col-span-2">Add class</button>
                </form>
                <ul class="mt-4 divide-y">@forelse ($classes as $class)<li class="py-2">{{ $class->name }} <span class="text-sm text-slate-500">({{ $class->level->name }})</span></li>@empty<li class="py-2 text-slate-500">No classes yet.</li>@endforelse</ul>
                <div class="mt-3">{{ $classes->links() }}</div>
            </section>

            <section class="rounded-lg bg-white p-5 shadow" aria-labelledby="arms-heading">
                <h2 id="arms-heading" class="text-xl font-semibold">Class arms</h2>
                <form method="post" action="{{ route('academics.arms.store') }}" class="mt-4 grid gap-2 sm:grid-cols-2">
                    @csrf
                    <select name="class_id" required aria-label="Class for arm" class="rounded border p-2"><option value="">Select class</option>@foreach ($classOptions as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select>
                    <input name="name" required maxlength="100" placeholder="e.g. A" aria-label="Arm name" class="rounded border p-2">
                    <button class="rounded bg-blue-700 px-4 py-2 text-white sm:col-span-2">Add arm</button>
                </form>
                <ul class="mt-4 divide-y">@forelse ($arms as $arm)<li class="py-2">{{ $arm->schoolClass->name }} — {{ $arm->name }}</li>@empty<li class="py-2 text-slate-500">No arms yet.</li>@endforelse</ul>
                <div class="mt-3">{{ $arms->links() }}</div>
            </section>

            <section class="rounded-lg bg-white p-5 shadow" aria-labelledby="subjects-heading">
                <h2 id="subjects-heading" class="text-xl font-semibold">Subjects</h2>
                <form method="post" action="{{ route('academics.subjects.store') }}" class="mt-4 flex flex-wrap gap-2">
                    @csrf
                    <input name="name" required maxlength="100" placeholder="e.g. Mathematics" aria-label="Subject name" class="min-w-48 flex-1 rounded border p-2">
                    <button class="rounded bg-blue-700 px-4 py-2 text-white">Add subject</button>
                </form>
                <ul class="mt-4 divide-y">@forelse ($subjects as $subject)<li class="py-2">{{ $subject->name }}</li>@empty<li class="py-2 text-slate-500">No subjects yet.</li>@endforelse</ul>
                <div class="mt-3">{{ $subjects->links() }}</div>
            </section>

            <section class="rounded-lg bg-white p-5 shadow lg:col-span-2" aria-labelledby="assignments-heading">
                <h2 id="assignments-heading" class="text-xl font-semibold">Subjects offered by each class</h2>
                <form method="post" action="{{ route('academics.class-subjects.store') }}" class="mt-4 grid gap-2 sm:grid-cols-3">
                    @csrf
                    <select name="class_id" required aria-label="Class for subject assignment" class="rounded border p-2"><option value="">Select class</option>@foreach ($classOptions as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select>
                    <select name="subject_id" required aria-label="Subject to assign" class="rounded border p-2"><option value="">Select subject</option>@foreach ($subjectOptions as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select>
                    <button class="rounded bg-blue-700 px-4 py-2 text-white">Assign subject</button>
                </form>
                <ul class="mt-4 divide-y">@forelse ($assignments as $assignment)<li class="py-2">{{ $assignment->schoolClass->name }} — {{ $assignment->subject->name }}</li>@empty<li class="py-2 text-slate-500">No class subjects yet.</li>@endforelse</ul>
                <div class="mt-3">{{ $assignments->links() }}</div>
            </section>
        </div>
    </main>
@include('core._shell-end')
@livewireScripts
</body>
</html>
