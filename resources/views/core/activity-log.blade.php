<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Activity log</title>@vite(['resources/css/app.css','resources/js/app.js'])</head><body>@include('core._shell-start')
<main class="ad-page ac-page al-page"><header class="ad-page-head"><div><p class="ad-eyebrow">Administration</p><h1>Activity log</h1><p>Review recorded changes across the school.</p></div></header>
<div class="oak-notice oak-notice-info">Passwords, temporary passwords, tokens and payment keys are never shown here.</div>
<form method="get" action="{{ route('activity-log.index') }}" class="ad-card al-filters">
    <label>From<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
    <label>To<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
    <label>Module<select name="module"><option value="">All modules</option>@foreach($modules as $module)<option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $module }}</option>@endforeach</select></label>
    <label>Person<input name="person" value="{{ $filters['person'] ?? '' }}" maxlength="100" placeholder="Name"></label>
    <div class="al-filter-actions"><button class="oak-btn oak-btn-primary">Apply filters</button><a class="oak-btn oak-btn-quiet" href="{{ route('activity-log.index') }}" wire:navigate>Clear</a></div>
</form>
<section class="ad-card al-list" aria-label="Recorded activity">
@forelse($entries as $entry)
    <article class="al-entry">
        <time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->timezone('Africa/Lagos')->format('j M Y, g:i a') }}</time>
        <div><strong>{{ $entry->actor_name ?: 'Unknown user' }}</strong><small>{{ $entry->actor_role ?: 'Role not recorded' }}</small></div>
        <span class="oak-status-pill oak-status-neutral">{{ $presenter->module($entry) }}</span>
        <p>{{ $presenter->description($entry) }}</p>
    </article>
@empty
    <div class="oak-empty"><strong>No activity found</strong>Try changing the filters, or check again after a recorded action.</div>
@endforelse
</section>
{{ $entries->links() }}
</main>
@include('core._shell-end') @livewireScripts</body></html>
