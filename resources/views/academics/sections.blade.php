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
                <div class="oak-field ad-wide"><label for="section-head">Head</label><select id="section-head" name="head_user_id"><option value="">Not assigned yet</option>@foreach($headOptions as $head)<option value="{{ $head->id }}">{{ $head->name }}</option>@endforeach</select><small>Only users with result-approval permission are listed.</small></div>
                <div class="ad-wide"><button class="oak-btn oak-btn-primary">Add section</button></div>
            </form>
        </section>
    @else
        <div class="ad-grid-2">
            @foreach($sections as $section)
                <section class="ad-card"><div class="ad-card-head"><div><h2>{{ $section->name }}</h2><p>{{ $section->levels_count }} {{ Str::plural('level', $section->levels_count) }} · {{ $section->head_title }}</p></div></div>
                    <div class="ad-chips">@forelse($section->levels as $level)<span class="ad-tag">{{ $level->name }}</span>@empty<span class="ad-meta">No levels assigned yet.</span>@endforelse</div>
                    <details class="ad-edit"><summary class="oak-btn oak-btn-quiet">Edit section</summary>
                        <form method="post" action="{{ route('academics.sections.update', $section) }}" class="ad-form-grid">@csrf @method('PUT')
                            <div class="oak-field"><label>Name</label><input name="name" required maxlength="100" value="{{ $section->name }}"></div>
                            <div class="oak-field"><label>Head title</label><input name="head_title" required maxlength="100" value="{{ $section->head_title }}"><small>Used for the section head's comment on report cards.</small></div>
                            <div class="oak-field ad-wide"><label>Head</label><select name="head_user_id"><option value="">Not assigned</option>@foreach($headOptions as $head)<option value="{{ $head->id }}" @selected($section->heads->contains($head))>{{ $head->name }}</option>@endforeach</select></div>
                            <div class="ad-wide"><button class="oak-btn oak-btn-primary">Save section</button></div>
                        </form>
                    </details>
                </section>
            @endforeach
        </div>
        <section class="ad-card oak-section"><div class="ad-card-head"><div><h2>Levels</h2><p>Moving a level moves its classes and students under the new section head. Past results and attendance stay unchanged.</p></div></div>
            @if($levels->contains(fn($level) => $level->section_id === null))<div class="oak-notice oak-notice-warning"><strong>Assign every level.</strong>&nbsp; Section-scoped work is not ready while a level is unassigned.</div>@endif
            @forelse($levels as $level)
                <form class="ad-level-row" method="post" action="{{ route('academics.levels.section', $level) }}" onsubmit="return confirm('Move {{ addslashes($level->name) }} and all of its classes and students to this section? Nothing will be deleted.');">@csrf @method('PUT')
                    <div><strong>{{ $level->name }}</strong><div class="ad-meta">{{ $level->classes_count }} {{ Str::plural('class', $level->classes_count) }}</div></div>
                    <label><span class="sr-only">Section for {{ $level->name }}</span><select class="ad-inline-select" name="section_id" required><option value="">Choose section</option>@foreach($sections as $section)<option value="{{ $section->id }}" @selected($level->section_id === $section->id)>{{ $section->name }}</option>@endforeach</select></label>
                    <button class="oak-btn oak-btn-secondary">Save</button>
                </form>
            @empty<div class="oak-empty"><strong>No levels yet</strong>Add levels from Sessions, terms & subjects.</div>@endforelse
        </section>
        <div class="ad-bar"><button class="oak-btn oak-btn-primary" type="button" data-open-sheet="add-section-sheet">Add section</button></div>
        <x-oak.sheet id="add-section-sheet" labelledby="add-section-title"><div class="oak-sheet"><h2 id="add-section-title">Add a section</h2><form method="post" action="{{ route('academics.sections.store') }}" class="ad-form-grid">@csrf
            <div class="oak-field"><label>Name</label><input name="name" required maxlength="100"></div><div class="oak-field"><label>Head title</label><input name="head_title" required maxlength="100"></div>
            <div class="oak-field ad-wide"><label>Head</label><select name="head_user_id"><option value="">Not assigned</option>@foreach($headOptions as $head)<option value="{{ $head->id }}">{{ $head->name }}</option>@endforeach</select></div>
            <div class="oak-sheet-actions ad-wide"><button type="button" class="oak-btn oak-btn-quiet" data-close-sheet>Cancel</button><button class="oak-btn oak-btn-primary">Add section</button></div>
        </form></div></x-oak.sheet>
    @endif
</main>
@include('core._shell-end') @livewireScripts
</body></html>
