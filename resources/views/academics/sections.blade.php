<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Academic structure · Oakfield</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>
@php($pageTitle = 'Academic structure')
@include('core._shell-start')
<main class="oak-dashboard">
    <div class="oak-dashboard-heading"><div><h1>Sections and levels</h1><p>Set who leads each section and where every academic level belongs.</p></div><span class="oak-role-pill">Admin</span></div>
    @include('academics._tabs')
    @if(session('status'))<div class="oak-notice oak-notice-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="oak-notice oak-notice-danger" role="alert"><div><strong>Please fix the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

    @if($sections->isEmpty())
        <section class="ad-card"><div class="ad-card-head"><div><h2>Create the first section</h2><p>A section groups levels under one head, such as Primary School or Secondary School.</p></div></div>
            <form method="post" action="{{ route('academics.sections.store') }}" class="ad-form-grid">@csrf
                <div class="oak-field"><label for="section-name">Section name</label><input id="section-name" name="name" required maxlength="100" placeholder="e.g. Primary School"></div>
                <div class="oak-field"><label for="section-title">Head title</label><input id="section-title" name="head_title" required maxlength="100" placeholder="e.g. Head Teacher"></div>
                <div class="oak-field"><label for="section-learner-term">Learners are called</label><select id="section-learner-term" name="learner_term" required><option value="Pupil">Pupil</option><option value="Student" selected>Student</option></select></div>
                @include('academics._head-picker', ['pickerId' => 'section-head', 'selectedHeadId' => old('head_user_id'), 'emptyLabel' => 'Not assigned yet', 'wide' => true])
                <div class="ad-wide"><button class="oak-btn oak-btn-primary">Add section</button></div>
            </form>
        </section>
        <section class="ad-card"><div class="ad-card-head"><div><h3>Levels come next</h3><p>Once a section exists, add its levels on the Levels tab. You choose the section when you create a level, and it cannot be changed afterwards.</p></div></div></section>
    @else
        <div class="ad-grid-2">
            @foreach($sections as $section)
                <section class="ad-card"><div class="ad-card-head"><div><h2>{{ $section->name }}</h2><p>{{ $section->levels_count }} {{ Str::plural('level', $section->levels_count) }} · {{ $section->head_title }}</p></div></div>
                    <div class="ad-chips">@forelse($section->levels as $level)<span class="ad-tag">{{ $level->name }}</span>@empty<span class="ad-meta">No levels assigned yet.</span>@endforelse</div>
                    <details class="ad-edit"><summary class="oak-btn oak-btn-quiet">Edit section</summary>
                        <form method="post" action="{{ route('academics.sections.update', $section) }}" class="ad-form-grid">@csrf @method('PUT')
                            <div class="oak-field"><label>Name</label><input name="name" required maxlength="100" value="{{ $section->name }}"></div>
                            <div class="oak-field"><label>Head title</label><input name="head_title" required maxlength="100" value="{{ $section->head_title }}"><small>Used for the section head's comment on report cards.</small></div>
                            <div class="oak-field"><label>Learners are called</label><select name="learner_term" required><option value="Pupil" @selected($section->learner_term === 'Pupil')>Pupil</option><option value="Student" @selected($section->learner_term === 'Student')>Student</option></select></div>
                            @include('academics._head-picker', ['pickerId' => 'section-head-'.$section->id, 'selectedHeadId' => old('head_user_id', $section->heads->first()?->id)])
                            <div class="ad-wide"><button class="oak-btn oak-btn-primary">Save section</button></div>
                        </form>
                    </details>
                </section>
            @endforeach
        </div>
        <section class="ad-card oak-section"><div class="ad-card-head"><div><h2>Levels</h2><p>A Level's Section is fixed when it is created. If it is in the wrong section, delete it and create it again on the Levels tab.</p></div><a class="oak-btn oak-btn-quiet" href="{{ route('academics.levels.index') }}">Manage levels</a></div>
            @forelse($levels as $level)
                <div class="ad-level-row">
                    <div><strong>{{ $level->name }}</strong><div class="ad-meta">{{ $level->classes_count }} {{ Str::plural('class', $level->classes_count) }} · {{ $level->classes->sum('active_students_count') }} {{ Str::plural('student', $level->classes->sum('active_students_count')) }}</div></div>
                    <span>{{ $level->section?->name }}</span>
                </div>
            @empty<div class="oak-empty"><strong>No levels yet</strong>Add levels from the Levels tab.</div>@endforelse
        </section>
        <div class="ad-bar"><button class="oak-btn oak-btn-primary" type="button" data-open-sheet="add-section-sheet">Add section</button></div>
        <x-oak.sheet id="add-section-sheet" labelledby="add-section-title"><div class="oak-sheet"><h2 id="add-section-title">Add a section</h2><form method="post" action="{{ route('academics.sections.store') }}" class="ad-form-grid">@csrf
            <div class="oak-field"><label>Name</label><input name="name" required maxlength="100"></div><div class="oak-field"><label>Head title</label><input name="head_title" required maxlength="100"></div><div class="oak-field"><label>Learners are called</label><select name="learner_term" required><option value="Pupil">Pupil</option><option value="Student" selected>Student</option></select></div>
            @include('academics._head-picker', ['pickerId' => 'new-section-head', 'selectedHeadId' => old('head_user_id'), 'wide' => true])
            <div class="oak-sheet-actions ad-wide"><button type="button" class="oak-btn oak-btn-quiet" data-close-sheet>Cancel</button><button class="oak-btn oak-btn-primary">Add section</button></div>
        </form></div></x-oak.sheet>
    @endif
</main>
@include('core._shell-end') @livewireScripts
</body></html>
