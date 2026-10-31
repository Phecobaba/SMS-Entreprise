<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Subjects · Oakfield</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body>
@php $pageTitle = 'Subjects'; $selectedSection = $sectionId ? $sections->firstWhere('id', $sectionId) : null; $headSection = $sections->first(); @endphp
@include('core._shell-start')
<main class="oak-dashboard ad-subject-page">
    <div class="oak-dashboard-heading"><div><h1>{{ $canViewAll ? 'Academic structure' : (($headSection?->name ?? 'Your section').' subjects') }}</h1><p>Subjects {{ $canViewAll ? 'the school' : ($headSection?->name ?? 'your section') }} teaches</p></div><span class="oak-role-pill">{{ $canViewAll ? 'Admin' : ($headSection?->head_title ?? 'Head') }}</span></div>
    @if($canViewAll)@include('academics._tabs')@endif
    @if(session('status'))<div class="oak-notice oak-notice-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="oak-notice oak-notice-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if(!$hasSection)
        <div class="oak-locked"><x-icon name="lock" /><h3>No section assigned</h3><p>Ask the Admin to assign you to a section before managing subjects.</p></div>
    @else
        @unless($canViewAll)<div class="oak-notice oak-notice-info"><x-icon name="info" /><div>You see and change only <strong>{{ $headSection?->name }}</strong>.</div></div>@endunless
        <div class="ad-subject-toolbar">
            @if($canViewAll)<form method="get" action="{{ route('academics.setup') }}" class="ad-section-switcher"><label for="subject-section">Section</label><select id="subject-section" name="section" onchange="this.form.submit()"><option value="">All sections</option>@foreach($sections as $section)<option value="{{ $section->id }}" @selected($section->id === $sectionId)>{{ $section->name }}</option>@endforeach</select></form>@else<span></span>@endif
            <button class="oak-btn oak-btn-primary" type="button" data-open-sheet="add-subject">Add a subject</button>
        </div>
        <section class="ad-card ad-subject-list">
            @forelse($subjects as $subject)
                <div class="ad-class-row ad-subject-row"><div><h4>{{ $subject->name }}</h4><span class="ad-tag">{{ $subject->section?->name }}</span></div><div class="ad-actions">
                    <button type="button" class="ad-iconbtn" aria-label="Edit {{ $subject->name }}" data-open-sheet="edit-subject-{{ $subject->id }}"><x-icon name="pencil" /></button>
                    <button type="button" class="ad-iconbtn" aria-label="Delete {{ $subject->name }}" data-open-sheet="delete-subject-{{ $subject->id }}"><x-icon name="trash" /></button>
                </div></div>
                <x-oak.sheet id="edit-subject-{{ $subject->id }}" label="Edit {{ $subject->name }}"><form method="post" action="{{ route('academics.subjects.update', $subject) }}" class="oak-sheet">@csrf @method('PUT')
                    <h2>Edit subject</h2><p class="oak-sheet-sub">Renaming updates the label everywhere without changing scores or history.</p>
                    <div class="oak-field"><label>Subject name</label><input name="name" value="{{ $subject->name }}" required maxlength="100"></div>
                    <div class="oak-field"><label>Section</label><p style="margin:0">{{ $subject->section?->name }}</p><span class="oak-hint">A subject's section can't be changed. If it is in the wrong section, delete it and create it again.</span></div>
                    <div class="oak-sheet-actions"><button type="button" class="oak-btn oak-btn-secondary" data-close-sheet>Cancel</button><button class="oak-btn oak-btn-primary">Save subject</button></div>
                </form></x-oak.sheet>
                <x-oak.sheet id="delete-subject-{{ $subject->id }}" label="Delete {{ $subject->name }}"><form method="post" action="{{ route('academics.subjects.destroy', $subject) }}" class="oak-sheet">@csrf @method('DELETE')
                    <h2>Delete {{ $subject->name }}?</h2><p class="oak-sheet-sub">{{ $subject->name }} is in {{ $subject->section?->name }}. The server will refuse and list counts if it is assigned to classes or teachers, or has results.</p>
                    <div class="oak-sheet-actions"><button type="button" class="oak-btn oak-btn-secondary" data-close-sheet>Keep it</button><button class="oak-btn oak-btn-danger">Delete {{ $subject->name }}</button></div>
                </form></x-oak.sheet>
            @empty<div class="oak-empty"><strong>No subjects {{ $selectedSection ? 'in '.$selectedSection->name : 'yet' }}</strong>Add a subject, then assign it to classes.</div>@endforelse
            @if($subjects->hasPages()){{ $subjects->links() }}@endif
        </section>

        <x-oak.sheet id="add-subject" label="Add a subject"><form method="post" action="{{ route('academics.subjects.store') }}" class="oak-sheet">@csrf
            <h2>Add a subject</h2>
            @if($canViewAll)
                <div class="oak-field"><label for="new-subject-section">Section</label><select id="new-subject-section" name="section_id" required><option value="">Choose a section</option>@foreach($sections as $section)<option value="{{ $section->id }}" @selected((string)old('section_id', $sectionId) === (string)$section->id)>{{ $section->name }}</option>@endforeach</select><span class="oak-hint">Choose carefully. A section can't be changed after you save.</span>@error('section_id')<span class="oak-error" role="alert">{{ $message }}</span>@enderror</div>
            @else
                <p class="oak-sheet-sub">It is added to {{ $headSection?->name }}.</p>
            @endif
            <div class="oak-field"><label for="new-subject-name">Subject name</label><input id="new-subject-name" name="name" value="{{ old('name') }}" required maxlength="100" placeholder="e.g. Mathematics">@error('name')<span class="oak-error" role="alert">{{ $message }}</span>@enderror</div>
            <div class="oak-sheet-actions"><button type="button" class="oak-btn oak-btn-secondary" data-close-sheet>Cancel</button><button class="oak-btn oak-btn-primary">Add subject</button></div>
        </form></x-oak.sheet>
    @endif
</main>
@include('core._shell-end') @livewireScripts
</body></html>
