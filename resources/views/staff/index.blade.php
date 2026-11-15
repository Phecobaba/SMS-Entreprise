<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Staff HR</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head><body>
@include('core._shell-start')
<main class="ad-page hr-page">
    <header class="ad-page-head"><div><p class="ad-eyebrow">Administration</p><h1>Staff HR</h1><p>Employment records, salary history and private staff information.</p></div><a class="oak-btn oak-btn-primary" href="#add-employment-record">Add employment record</a></header>
    @if(session('status'))<div class="oak-notice oak-notice-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="oak-notice oak-notice-danger" role="alert">{{ $errors->first() }}</div>@endif
    <section class="ad-card" id="add-employment-record"><div class="ad-card-head"><div><h2>Add employment record</h2><p>Create the sign-in account first, then add the person's employment details here.</p></div></div>
        <form method="post" action="{{ route('staff.store') }}" class="hr-form">@csrf
            <div class="grid gap-2" x-data="{ query: '', selected: @js($selectedUser), results: [], message: '', async search() { this.selected = null; if (this.query.trim().length < 2) { this.results = []; this.message = 'Enter at least 2 characters.'; return; } const response = await fetch(@js(route('staff.accounts.search')) + '?q=' + encodeURIComponent(this.query), { headers: { Accept: 'application/json' } }); if (!response.ok) { this.results = []; this.message = 'Staff search is unavailable. Try again.'; return; } const data = await response.json(); this.results = data.results; this.message = this.results.length ? '' : 'No matching staff accounts.'; } }">
                <label>Staff account search<input type="search" x-model="query" @input.debounce.300ms="search" placeholder="Name or email" autocomplete="off"></label>
                <input type="hidden" name="user_id" :value="selected?.id || ''" required>
                <p x-show="selected" class="rounded border border-green-300 bg-green-50 p-3"><strong x-text="selected?.name"></strong> <span x-text="selected?.role"></span> <button type="button" class="oak-link min-h-11 px-2" @click="selected=null; query=''; results=[]">Change</button></p>
                <div x-show="!selected && results.length" class="grid max-h-56 overflow-y-auto rounded border" role="listbox">
                    <template x-for="account in results" :key="account.id"><button type="button" class="min-h-11 border-b px-3 text-left last:border-0" @click="selected=account; results=[]"><strong x-text="account.name"></strong> <span class="text-sm text-slate-500" x-text="account.role"></span></button></template>
                </div>
                <p class="text-sm text-slate-600" x-show="!selected && message" x-text="message"></p>
            </div>
            <label>Role title<input name="role_title" value="{{ old('role_title') }}" required></label>
            <label>Contract type<input name="contract_type" value="{{ old('contract_type') }}" required></label>
            <label>Employment date<input type="date" name="employment_date" value="{{ old('employment_date') }}" required></label>
            <button class="oak-btn oak-btn-primary" type="submit">Add employment record</button>
        </form>
    </section>
    <section class="ad-card"><div class="ad-card-head"><div><h2>Employment records</h2><p>{{ $staff->total() }} staff records</p></div></div>
        <div class="hr-record-list">@forelse($staff as $person)<a class="oak-list-row" href="{{ route('staff.show', $person) }}" wire:navigate><span><strong>{{ $person->user->name }}</strong><small>{{ $person->role_title }} · {{ $person->contract_type }}</small></span>@include('core._status-pill', ['status' => $person->status])</a>@empty<p>No employment records yet.</p>@endforelse</div>
        {{ $staff->links() }}
    </section>
</main>
@include('core._shell-end') @livewireScripts</body></html>
