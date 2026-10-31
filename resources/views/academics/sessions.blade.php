<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sessions & terms · Oakfield</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body>
@php $pageTitle = 'Sessions & terms'; @endphp
@include('core._shell-start')
<main class="oak-dashboard" style="max-width:1000px">
    <div class="oak-dashboard-heading"><div><h1>Academic structure</h1><p>Sessions and their terms</p></div><span class="oak-role-pill">Admin</span></div>
    @include('academics._tabs')
    @if(session('status'))<div class="oak-notice oak-notice-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="oak-notice oak-notice-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="ad-actions" style="justify-content:flex-end;margin:-6px 0 10px"><button class="oak-btn oak-btn-secondary" type="button" data-open-sheet="add-term" @disabled($sessions->isEmpty())>Add a term</button><button class="oak-btn oak-btn-primary" type="button" data-open-sheet="add-session">Add a session</button></div>

    @forelse($sessions as $session)
        @php
            $today = today(config('app.timezone', 'Africa/Lagos'));
            $sessionState = $today->betweenIncluded($session->start_date, $session->end_date) ? 'Current' : ($session->start_date->isAfter($today) ? 'Future' : 'Past');
        @endphp
        <section class="ad-card" style="padding:6px 16px">
            <div class="ad-card-head"><div><h3>{{ $session->name }}</h3><p>{{ $sessionState }} · {{ $session->terms_count }} {{ Str::plural('term', $session->terms_count) }}</p></div><div class="ad-actions"><button class="ad-iconbtn" type="button" aria-label="Edit session {{ $session->name }}" data-open-sheet="edit-session-{{ $session->id }}"><x-icon name="pencil" /></button><button class="ad-iconbtn" type="button" aria-label="Delete session {{ $session->name }}" data-open-sheet="delete-session-{{ $session->id }}"><x-icon name="trash" /></button></div></div>
            @forelse($session->terms as $term)
                @php $termState = $today->betweenIncluded($term->start_date, $term->end_date) ? 'Current' : ($term->start_date->isAfter($today) ? 'Future' : 'Past'); @endphp
                <div class="ad-class-row" style="grid-template-columns:minmax(0,1fr) auto"><div><h4>{{ $term->name }}</h4><small class="ad-meta">{{ $termState }} · {{ $term->start_date->format('j M Y') }} – {{ $term->end_date->format('j M Y') }}</small></div><div class="ad-actions"><button class="ad-iconbtn" type="button" aria-label="Edit {{ $term->name }} {{ $session->name }}" data-open-sheet="edit-term-{{ $term->id }}"><x-icon name="pencil" /></button><button class="ad-iconbtn" type="button" aria-label="Delete {{ $term->name }} {{ $session->name }}" data-open-sheet="delete-term-{{ $term->id }}"><x-icon name="trash" /></button></div></div>
                @include('academics._term-sheets', ['term' => $term])
            @empty<div class="oak-empty"><strong>No terms yet</strong>Add the first term for {{ $session->name }}.</div>@endforelse
        </section>
        @include('academics._session-sheets', ['session' => $session])
    @empty
        <div class="oak-empty"><x-icon name="info" /><strong>No sessions yet</strong>Add a session, then its terms.</div>
    @endforelse

    <x-oak.sheet id="add-session" label="Add session"><form method="post" action="{{ route('academics.sessions.store') }}" class="oak-sheet">@csrf<h2>Add a session</h2><div class="ad-form-grid"><div class="oak-field ad-wide"><label>Name</label><input name="name" required placeholder="e.g. 2027/2028"></div><div class="oak-field"><label>Starts</label><input type="date" name="start_date" required></div><div class="oak-field"><label>Ends</label><input type="date" name="end_date" required></div></div><div class="oak-sheet-actions"><button type="button" class="oak-btn oak-btn-secondary" data-close-sheet>Cancel</button><button class="oak-btn oak-btn-primary">Add session</button></div></form></x-oak.sheet>
    <x-oak.sheet id="add-term" label="Add term"><form method="post" action="{{ route('academics.terms.store') }}" class="oak-sheet">@csrf<h2>Add a term</h2><div class="ad-form-grid"><div class="oak-field ad-wide"><label>Session</label><select name="session_id" required>@foreach($sessionOptions as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select></div><div class="oak-field ad-wide"><label>Name</label><input name="name" required placeholder="e.g. First Term"></div><div class="oak-field"><label>Starts</label><input type="date" name="start_date" required></div><div class="oak-field"><label>Ends</label><input type="date" name="end_date" required></div></div><div class="oak-sheet-actions"><button type="button" class="oak-btn oak-btn-secondary" data-close-sheet>Cancel</button><button class="oak-btn oak-btn-primary">Add term</button></div></form></x-oak.sheet>
</main>
@include('core._shell-end') @livewireScripts
</body></html>
