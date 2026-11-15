<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $parent->user->name }}</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body>@include('core._shell-start')
<main class="ad-page">
    <header class="ad-page-head"><div><p class="ad-eyebrow">Parent record</p><h1>{{ $parent->user->name }}</h1><p>{{ $parent->user->username ? '@'.$parent->user->username : 'No username' }}</p></div><a class="oak-btn oak-btn-quiet" href="{{ route('parents.index') }}">Back to parents</a></header>
    @if(session('status'))<div class="oak-notice oak-notice-success" role="status">{{ session('status') }}</div>@endif
    <section class="oak-card"><h2>Account details</h2><dl class="ad-detail-list"><div><dt>Email</dt><dd>{{ $parent->user->email ?: 'No email' }}</dd></div><div><dt>Email status</dt><dd>{{ $parent->user->email_verified_at ? 'Confirmed' : 'Not confirmed' }}</dd></div></dl>
    @can('manage_parent_accounts')
        <div class="ad-actions"><form method="post" action="{{ route('parents.reset', $parent) }}">@csrf<button class="oak-btn oak-btn-quiet" type="submit">Reset password</button></form></div>
        <form class="oak-form-row" method="post" action="{{ route('parents.email.update', $parent) }}">@csrf @method('put')<div class="oak-field"><label for="parent-email">Add or change email</label><input id="parent-email" type="email" name="email" value="{{ old('email', $parent->user->email) }}" required></div><button class="oak-btn oak-btn-primary" type="submit">Save email</button></form>
        @error('email')<p class="oak-error">{{ $message }}</p>@enderror
    @endcan</section>
    <section class="oak-card"><h2>Children</h2><div class="oak-table-wrap"><table class="oak-table"><thead><tr><th>Name</th><th>Class</th><th>Section</th><th>Type</th><th>Fee status</th><th></th></tr></thead><tbody>
    @forelse($parent->students as $student)
        @php($fee = $student->fee_summary)
        <tr><td><a class="oak-btn oak-btn-quiet" href="{{ route('students.show', $student) }}">{{ $student->user->name }}</a></td><td>{{ $student->classArm?->schoolClass?->name ?? 'No class' }} {{ $student->classArm?->name }}</td><td>{{ $student->classArm?->schoolClass?->level?->section?->name ?? 'No section' }}</td><td>{{ $student->learner_word }}</td><td>{{ $fee['status'] }}@if($fee['outstanding_kobo'] > 0)<br><span class="oak-sub">&#8358;{{ number_format($fee['outstanding_kobo'] / 100, 2) }} outstanding</span>@endif</td><td>@can('manage_parent_accounts')<form method="post" action="{{ route('parents.children.destroy', [$parent, $student]) }}" onsubmit="return confirm('Unlink {{ addslashes($student->user->name) }} from this parent?')">@csrf @method('delete')<button class="oak-btn oak-btn-danger" type="submit">Unlink child</button></form>@endcan</td></tr>
    @empty<tr><td colspan="6">No linked children in your scope.</td></tr>@endforelse
    </tbody></table></div></section>
    @can('manage_parent_accounts')
    <section class="oak-card"><h2>Link child</h2><form class="oak-form-row" method="get" action="{{ route('parents.show', $parent) }}"><div class="oak-field"><label for="child-search">Find a child</label><input id="child-search" name="child_search" value="{{ $childSearch }}" placeholder="Name or username"></div><button class="oak-btn oak-btn-quiet" type="submit">Search</button></form>
    @if($childSearch !== '')<div class="oak-stack">@forelse($studentChoices as $student)<form class="oak-form-row" method="post" action="{{ route('parents.children.store', $parent) }}">@csrf<input type="hidden" name="student_id" value="{{ $student->id }}"><span><strong>{{ $student->user->name }}</strong><br><span class="oak-sub">{{ $student->classArm?->schoolClass?->name }} {{ $student->classArm?->name }}</span></span><button class="oak-btn oak-btn-primary" type="submit">Link child</button></form>@empty<p>No unlinked children match this search.</p>@endforelse</div>@endif
    @error('student_id')<p class="oak-error">{{ $message }}</p>@enderror</section>
    @endcan
</main>@include('core._shell-end') @livewireScripts</body></html>
