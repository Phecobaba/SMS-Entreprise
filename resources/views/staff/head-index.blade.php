<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Staff</title>@vite(['resources/css/app.css','resources/js/app.js'])</head><body>
@include('core._shell-start')
<main class="ad-page hs-page"><header class="ad-page-head"><div><p class="ad-eyebrow">People</p><h1>Staff</h1><p>{{ $sectionNames->join(' · ') ?: 'Your Section staff' }}</p></div></header>
@if($locked)
<section class="ad-card oak-locked-state"><h2>No Section assigned</h2><p>Ask the school office to assign your account to a Section before you open Staff.</p></section>
@else
<div class="oak-notice">Only profile, teaching and discipline information for staff linked to your Section is shown.</div>
<form method="get" action="{{ route('head.staff.index') }}" class="ad-card hs-search"><label>Search staff<input name="q" value="{{ $search }}" placeholder="Name"></label><button class="oak-btn oak-btn-primary">Search</button>@if($search)<a class="oak-btn oak-btn-quiet" href="{{ route('head.staff.index') }}" wire:navigate>Clear</a>@endif</form>
<section class="ad-card"><div class="ad-card-head"><div><h2>Section staff</h2><p>{{ $staff->total() }} {{ Str::plural('person', $staff->total()) }}</p></div></div>
<div class="hs-list">@forelse($staff as $person)<article class="hs-row"><div><strong>{{ $person['name'] }}</strong><small>{{ $person['position'] }} · {{ implode(', ', $person['sections']) }}</small></div><div><span>Teaching</span><small>{{ $person['teaching'] ? implode('; ', $person['teaching']) : 'No teaching assignment' }}</small></div><div><span>Discipline</span><small>{{ $person['discipline_count'] }} {{ Str::plural('entry', $person['discipline_count']) }}</small></div><a class="oak-btn oak-btn-quiet" href="{{ route('head.staff.show', $person['id']) }}" wire:navigate>View</a></article>@empty<p>No staff match this search.</p>@endforelse</div>
{{ $staff->links() }}</section>
@endif</main>
@include('core._shell-end') @livewireScripts</body></html>
