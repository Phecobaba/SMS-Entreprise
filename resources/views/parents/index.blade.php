<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Parents</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body>@include('core._shell-start')
<main class="ad-page">
    <header class="ad-page-head"><div><p class="ad-eyebrow">People</p><h1>Parents</h1><p>Find a parent by their details or by a linked child.</p></div></header>
    <form class="oak-card oak-form-row" method="get" action="{{ route('parents.index') }}">
        <div class="oak-field"><label for="parent-search">Search parents</label><input id="parent-search" name="search" value="{{ $search }}" placeholder="Name, username, email or child"></div>
        <button class="oak-btn oak-btn-primary" type="submit">Search</button>
        @if($search !== '')<a class="oak-btn oak-btn-quiet" href="{{ route('parents.index') }}">Clear</a>@endif
    </form>
    <section class="oak-card"><div class="oak-table-wrap"><table class="oak-table"><thead><tr><th>Parent</th><th>Email</th><th>Children</th><th>Fee status</th><th></th></tr></thead><tbody>
    @forelse($parents as $parent)
        <tr><td><strong>{{ $parent->user->name }}</strong><br><span class="oak-sub">{{ $parent->user->username ? '@'.$parent->user->username : 'No username' }}</span></td>
            <td>{{ $parent->user->email ?: 'No email' }}<br><span class="oak-sub">{{ $parent->user->email_verified_at ? 'Confirmed' : 'Not confirmed' }}</span></td>
            <td>{{ $parent->students_count }}</td><td>{{ $parent->worst_fee_status }}</td>
            <td><a class="oak-btn oak-btn-quiet" href="{{ route('parents.show', $parent) }}">Open</a></td></tr>
    @empty<tr><td colspan="5">No parents match this search.</td></tr>@endforelse
    </tbody></table></div>{{ $parents->links() }}</section>
</main>@include('core._shell-end') @livewireScripts</body></html>
