<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Incoming from Primary · Oakfield</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>
@php($pageTitle = 'Promotion')
@include('core._shell-start')
<main class="oak-dashboard pr-page in-page">
    <div class="oak-dashboard-heading">
        <div><h1>Promotion</h1><p>Accept pupils arriving from the previous section.</p></div>
        <span class="oak-role-pill">{{ $canViewAll ? 'Admin' : ($section->head_title ?? 'Head') }}</span>
    </div>

    <nav class="ad-tabs" aria-label="Promotion areas"><a href="{{ route('academics.promotion.index', ['section' => $canViewAll ? $selectedSection : null]) }}">Promote classes</a><a href="{{ route('academics.promotion.incoming.index', ['section' => $selectedSection]) }}" aria-current="page">Incoming from Primary</a></nav>

    @if($errors->any())<div class="oak-notice oak-notice-danger" role="alert"><div><b>We couldn't review this action.</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

    @if(!$canViewAll)<div class="ad-section-badge"><x-icon name="school" /> {{ $section->name }}</div>@endif
    @if($canViewAll && $sections->count() > 1)
        <section class="ad-card"><form method="get" action="{{ route('academics.promotion.incoming.index') }}" class="ad-form-grid ad-filter-row"><label>Receiving section<select class="ad-inline-select" name="section" onchange="this.form.submit()">@foreach($sections as $available)<option value="{{ $available->id }}" @selected($selectedSection === $available->id)>{{ $available->name }}</option>@endforeach</select></label></form></section>
    @endif

    <div class="oak-notice oak-notice-info"><x-icon name="info-circle" /><div><b>Accept, not continue.</b><p>Acceptance keeps each pupil's current registration number. It does not create an invoice. Past results, attendance and receipts stay on record.</p></div></div>

    @if($report)
        <section class="oak-notice oak-notice-success pr-report" role="status"><x-icon name="circle-check" /><div>
            <b>{{ $report['intent'] === 'accept' ? 'Acceptance complete.' : 'Not continuing complete.' }}</b>
            <p>
                {{ $report['affected'] }} {{ $report['intent'] === 'accept' ? 'accepted' : 'marked as not continuing' }}.
                @if($report['unchanged']) {{ $report['unchanged'] }} already accepted and unchanged. @endif
                {{ $report['remaining'] }} still waiting.
            </p>
        </div></section>
    @endif

    @if($plan)
        <section class="ad-card pr-review">
            <div class="ad-card-head"><div><h2>{{ $intent === 'accept' ? 'Review the acceptance' : 'Review not continuing' }}</h2><p>{{ $intent === 'accept' ? $plan['accepted'].' pupils will be accepted.' : $plan['graduated'].' pupils will be marked as not continuing.' }}</p></div><span class="ad-tag">{{ $plan['rows']->count() }} selected</span></div>
            <div class="oak-notice oak-notice-danger"><x-icon name="info-circle" /><div><b>This can't be undone.</b> Confirm only after checking every pupil.</div></div>
            <div class="pr-review-list">
                @foreach($plan['rows'] as $row)
                    @php($pupil = $intent === 'accept' ? $row['student'] : $row)
                    <div class="pr-review-row"><div><b>{{ $pupil->user?->name }}</b><small>{{ $pupil->awaitingFromClassArm?->schoolClass?->name }}</small></div><span>{{ $intent === 'accept' ? $row['arm']->schoolClass?->name.' · '.$row['arm']->name : 'Not continuing' }}</span></div>
                @endforeach
            </div>
            @if($intent === 'accept')<p class="ad-meta"><b>Registration numbers:</b> Keep each pupil's current number.</p>@endif
            <form method="post" action="{{ $intent === 'accept' ? route('academics.promotion.incoming.accept') : route('academics.promotion.incoming.not-continuing') }}" class="ad-bar">@csrf
                <input type="hidden" name="section_id" value="{{ $selectedSection }}"><input type="hidden" name="intent" value="{{ $intent }}">
                @if($plan['all_remaining'] ?? false)<input type="hidden" name="all_remaining" value="1">@endif
                @foreach($plan['rows'] as $row)
                    @php($pupil = $intent === 'accept' ? $row['student'] : $row)
                    <input type="hidden" name="students[]" value="{{ $pupil->id }}">
                    @if($intent === 'accept')<input type="hidden" name="class[{{ $pupil->id }}]" value="{{ $row['arm']->class_id }}"><input type="hidden" name="arm[{{ $pupil->id }}]" value="{{ $row['arm']->id }}">@endif
                @endforeach
                <a class="oak-btn oak-btn-secondary" href="{{ route('academics.promotion.incoming.index', ['section' => $selectedSection]) }}">Back</a>
                <button class="oak-btn oak-btn-primary">{{ $intent === 'accept' ? 'Accept '.$plan['accepted'].' pupils' : 'Confirm not continuing' }}</button>
            </form>
        </section>
    @else
        <form method="post" action="{{ route('academics.promotion.incoming.review') }}" data-incoming-form>@csrf
            <input type="hidden" name="section_id" value="{{ $selectedSection }}"><input type="hidden" name="intent" value="accept" data-intent>
            <section class="ad-card pr-roster">
                <div class="ad-card-head"><div><h2>Waiting for {{ $section->name }}</h2><p>{{ $students->total() }} pupils awaiting admission</p></div><label class="ad-choice"><input type="checkbox" data-select-all> Select this page</label></div>
                <div class="ad-prom-head in-head"><span>Pupil</span><span>Join class</span><span>Arm</span></div>
                @forelse($students as $student)
                    @php($suggestion = $suggestions[$student->id])
                    <div class="ad-prom-row in-row" data-incoming-row>
                        <label class="pr-person"><input type="checkbox" name="students[]" value="{{ $student->id }}" data-pupil><span><b>{{ $student->user?->name }}</b>@if($hasRegistrationNumber)<small>{{ $student->registration_number }}</small>@endif<small>{{ $student->awaitingFromClassArm?->schoolClass?->name }} - waiting {{ (int) $student->awaiting_admission_at?->diffInDays(now()) }} days</small></span></label>
                        <label><span class="in-mobile-label">Join class</span><select class="ad-inline-select" name="class[{{ $student->id }}]" data-row-class><option value="">Choose a class</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected($suggestion['class']?->id === $class->id)>{{ $class->name }}@if($class->department) · {{ $class->department->name }}@endif</option>@endforeach</select></label>
                        <label><span class="in-mobile-label">Arm</span><select class="ad-inline-select" name="arm[{{ $student->id }}]" data-row-arm><option value="">Choose an arm</option>@foreach($classes as $class)@foreach($class->arms->sortBy('name', SORT_NATURAL) as $arm)<option value="{{ $arm->id }}" data-class="{{ $class->id }}" @selected($suggestion['arm']?->id === $arm->id)>{{ $arm->name }}</option>@endforeach@endforeach</select></label>
                    </div>
                @empty
                    <div class="oak-empty"><x-icon name="users" /><strong>No pupils are waiting for this section.</strong><p>When Primary 6 pupils are promoted, they will appear here.</p></div>
                @endforelse
            </section>
            @if($students->hasPages())<div class="pr-pages">{{ $students->links() }}</div>@endif
            @if($students->count())<div class="ad-bar in-actions"><span class="ad-meta">Select pupils, then review before anything changes.</span><button class="oak-btn oak-btn-secondary" type="submit" data-not-continuing disabled>Mark selected as not continuing</button><button class="oak-btn oak-btn-primary" type="submit" data-accept disabled>Review the acceptance</button></div>@endif
        </form>

        @if($students->total())
            <form method="post" action="{{ route('academics.promotion.incoming.review') }}" class="ad-card in-all-form">@csrf
                <input type="hidden" name="section_id" value="{{ $selectedSection }}"><input type="hidden" name="intent" value="not_continuing"><input type="hidden" name="all_remaining" value="1">
                <div><h2>Close the waiting list</h2><p>Review every pupil still waiting before marking all remaining as not continuing.</p></div><button class="oak-btn oak-btn-danger">Mark all remaining as not continuing</button>
            </form>
        @endif
    @endif
</main>
@include('core._shell-end')
@livewireScripts
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-incoming-form]');
    if (!form) return;
    const rows = Array.from(form.querySelectorAll('[data-incoming-row]'));
    const accept = form.querySelector('[data-accept]'); const notContinuing = form.querySelector('[data-not-continuing]'); const intent = form.querySelector('[data-intent]');
    function filterArms(row) { const klass = row.querySelector('[data-row-class]'); const arm = row.querySelector('[data-row-arm]'); Array.from(arm.options).forEach(function(option) { if (!option.dataset.class) return; option.hidden = option.dataset.class !== klass.value; option.disabled = option.dataset.class !== klass.value; }); if (arm.selectedOptions[0]?.disabled) arm.value = ''; }
    function update() { const selected = rows.filter(row => row.querySelector('[data-pupil]').checked); notContinuing.disabled = selected.length === 0; accept.disabled = selected.length === 0 || selected.some(row => !row.querySelector('[data-row-class]').value || !row.querySelector('[data-row-arm]').value); }
    rows.forEach(function(row) { filterArms(row); row.querySelectorAll('input,select').forEach(el => el.addEventListener('change', function() { if (el.matches('[data-row-class]')) filterArms(row); update(); })); });
    form.querySelector('[data-select-all]')?.addEventListener('change', function(event) { rows.forEach(row => row.querySelector('[data-pupil]').checked = event.target.checked); update(); });
    accept?.addEventListener('click', function() { intent.value = 'accept'; }); notContinuing?.addEventListener('click', function() { intent.value = 'not_continuing'; }); update();
});
</script>
</body></html>
