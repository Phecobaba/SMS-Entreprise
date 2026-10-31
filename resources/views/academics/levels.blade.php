<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Levels · Oakfield</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body>
@php $pageTitle = 'Levels'; $headSection = $sections->first(); @endphp
@include('core._shell-start')
<main class="oak-dashboard" style="max-width:1000px">
    <div class="oak-dashboard-heading"><div><h1>{{ $canViewAll ? 'Academic structure' : (($headSection?->name ?? 'Your section').' levels') }}</h1><p>{{ $canViewAll ? 'Levels, grouped by section' : 'Create and manage the levels in your section.' }}</p></div><span class="oak-role-pill">{{ $canViewAll ? 'Admin' : ($headSection?->head_title ?? 'Head') }}</span></div>
    @if($canViewAll)@include('academics._tabs')@endif
    @if(session('status'))<div class="oak-notice oak-notice-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="oak-notice oak-notice-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if(!$hasSection)
        <div class="oak-locked"><x-icon name="lock" /><h3>No section assigned</h3><p>Ask the Admin to assign you to a section before managing levels.</p></div>
    @else
        @unless($canViewAll)<div class="oak-notice oak-notice-info"><x-icon name="info" /><div>You see and change only <strong>{{ $headSection?->name }}</strong>.</div></div>@endunless
        <div class="ad-actions" style="justify-content:flex-end;margin:-6px 0 10px"><button class="oak-btn oak-btn-primary" type="button" data-open-sheet="add-level">Add a level</button></div>

        @if($canViewAll)
            @forelse($sections as $section)
                <section class="ad-card" style="padding:6px 16px"><div class="ad-card-head"><div><h3>{{ $section->name }}</h3></div></div>
                    @forelse($levels->where('section_id', $section->id) as $level)
                        @include('academics._level-row', ['level' => $level])
                    @empty<div class="oak-empty"><strong>No levels yet</strong>Add the first level for {{ $section->name }}.</div>@endforelse
                </section>
            @empty
                <div class="oak-empty"><strong>No sections yet</strong>Add a Section before creating a Level.</div>
            @endforelse
        @else
            <section class="ad-card" style="padding:6px 16px">
                @forelse($levels as $level)@include('academics._level-row', ['level' => $level])
                @empty<div class="oak-empty"><strong>No levels in {{ $headSection?->name }}</strong>Add the first level for your section.</div>@endforelse
            </section>
        @endif

        <x-oak.sheet id="add-level" label="Add level"><form method="post" action="{{ route('academics.levels.store') }}" class="oak-sheet">@csrf
            <h2>Add a level</h2>
            @if($canViewAll)
                <div class="oak-field"><label for="new-level-section">Section</label><select id="new-level-section" name="section_id" required><option value="">Choose a section</option>@foreach($sections as $section)<option value="{{ $section->id }}" @selected((string)old('section_id') === (string)$section->id)>{{ $section->name }}</option>@endforeach</select><span class="oak-hint">Choose carefully. A section can't be changed after you save.</span>@error('section_id')<span class="oak-error" role="alert">{{ $message }}</span>@enderror</div>
            @else
                <p class="oak-sheet-sub">It is added to {{ $headSection?->name }}.</p>
            @endif
            <div class="oak-field"><label for="new-level-name">Level name</label><input id="new-level-name" name="name" value="{{ old('name') }}" required maxlength="100" placeholder="e.g. Junior Secondary">@error('name')<span class="oak-error" role="alert">{{ $message }}</span>@enderror</div>
            <div class="oak-sheet-actions"><button type="button" class="oak-btn oak-btn-secondary" data-close-sheet>Cancel</button><button class="oak-btn oak-btn-primary">Add level</button></div>
        </form></x-oak.sheet>
    @endif
</main>
@include('core._shell-end') @livewireScripts
</body></html>
