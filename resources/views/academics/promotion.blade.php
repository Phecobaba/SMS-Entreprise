<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Promotion · Oakfield</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>
@php
    $pageTitle = 'Promotion';
    $actorSection = !$canViewAll ? $sections->first() : null;
    $ownWaiting = $actorSection && $waiting->contains('id', $actorSection->id);
    $finalStatus = $source && $nextStep->isEmpty() ? app(\App\Modules\Academics\Services\PromotionCatalog::class)->finalStatus($source) : null;
@endphp
@include('core._shell-start')
<main class="oak-dashboard pr-page">
    <div class="oak-dashboard-heading">
        <div><h1>Promotion</h1><p>Review every placement before you apply it.</p></div>
        <span class="oak-role-pill">{{ $canViewAll ? 'Admin' : ($actorSection?->head_title ?? 'Head') }}</span>
    </div>

    <nav class="ad-tabs" aria-label="Promotion areas"><a href="{{ route('academics.promotion.index') }}" aria-current="page">Promote classes</a>@if($showIncomingTab)<a href="{{ route('academics.promotion.incoming.index', ['section' => $incomingSectionId]) }}">Incoming from Primary</a>@endif</nav>

    @if(session('status'))<div class="oak-notice oak-notice-success" role="status"><x-icon name="circle-check" /><div>{{ session('status') }}</div></div>@endif
    @if($errors->any())<div class="oak-notice oak-notice-danger" role="alert"><div><b>We couldn't review this promotion.</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

    @if(!$canPromote)
        <div class="oak-locked"><x-icon name="lock" /><h3>You don't have access to Promotion</h3><p>Ask an administrator if you need to promote students.</p></div>
    @elseif(!$hasSection)
        <div class="oak-locked"><x-icon name="lock" /><h3>You haven't been assigned to a section yet</h3><p>A head can promote students only inside their assigned section.</p></div>
    @elseif($waiting->isNotEmpty())
        <section class="ad-card pr-blocked">
            <x-icon name="lock" />
            <h2>Arrange your classes first</h2>
            @if($ownWaiting || $canViewAll)
                <p>Promotion stays locked until every section has a saved class order.</p>
                <a class="oak-btn oak-btn-primary" href="{{ route('academics.classes.arrange') }}" wire:navigate>Arrange classes</a>
            @else
                <p>Waiting for {{ $waiting->pluck('name')->join(', ') }} to be arranged.</p>
                <a class="oak-btn oak-btn-secondary" href="{{ route('academics.classes.index') }}" wire:navigate>View classes</a>
            @endif
        </section>
    @else
        @if(!$canViewAll)
            <div class="ad-section-badge"><x-icon name="school" /> {{ $actorSection?->name }}</div>
            <p class="pr-scope-note">You can only act inside {{ $actorSection?->name }}.</p>
        @endif

        @if($report)
            <section class="oak-notice oak-notice-success pr-report" role="status">
                <x-icon name="circle-check" /><div><b>Promotion applied.</b><p>{{ $report['moved'] }} moved, {{ $report['stayed'] }} stayed, {{ $report['skipped'] }} skipped because their placement had changed.</p>@if($report['final_status'])<p>Final status: {{ $report['final_status'] }}.</p>@endif</div>
            </section>
        @endif

        <section class="ad-card">
            <form method="get" action="{{ route('academics.promotion.index') }}" class="ad-form-grid ad-filter-row">
                @if($canViewAll)
                    <label>Section<select class="ad-inline-select" name="section" onchange="this.form.submit()">@foreach($sections as $section)<option value="{{ $section->id }}" @selected($selectedSection === $section->id)>{{ $section->name }}</option>@endforeach</select></label>
                @endif
                <label>Source class<select class="ad-inline-select" name="source" onchange="this.form.submit()">@foreach($classes as $class)<option value="{{ $class->id }}" @selected($source?->id === $class->id)>{{ $class->name }}</option>@endforeach</select></label>
                @if($source && $source->arms->count() > 1)
                    <label>Arm<select class="ad-inline-select" name="arm" onchange="this.form.submit()"><option value="">All arms</option>@foreach($source->arms->sortBy('name', SORT_NATURAL) as $arm)<option value="{{ $arm->id }}" @selected(request()->integer('arm') === $arm->id)>{{ $arm->name }}</option>@endforeach</select></label>
                @endif
            </form>
        </section>

        @if(!$source)
            <div class="oak-empty"><x-icon name="school" /><strong>No arranged classes in this section.</strong></div>
        @elseif($plan)
            <section class="ad-card pr-review">
                <div class="ad-card-head"><div><h2>Review promotion</h2><p>Will move {{ $plan['moved'] }}, will stay {{ $plan['stayed'] }}@if($plan['skipped']), will skip {{ $plan['skipped'] }}@endif.</p></div><span class="ad-tag">{{ $plan['rows']->count() }} selected</span></div>
                <div class="oak-notice oak-notice-danger"><x-icon name="info-circle" /><div><b>This can't be undone.</b> Check every destination before applying.</div></div>
                <div class="pr-review-list">@foreach($plan['rows'] as $row)<div class="pr-review-row"><div><b>{{ $row['student']->user?->name }}</b><small>{{ $source->name }} · {{ $row['student']->classArm?->name }}</small></div><span>{{ $row['destination'] }}</span></div>@endforeach</div>
                <form method="post" action="{{ route('academics.promotion.apply') }}" class="ad-bar">@csrf
                    <input type="hidden" name="section_id" value="{{ $selectedSection }}"><input type="hidden" name="source_class_id" value="{{ $source->id }}"><input type="hidden" name="source_arm_id" value="{{ request()->integer('arm') ?: '' }}">
                    @foreach($submittedPlacements as $studentId => $choice)
                        <input type="hidden" name="students[]" value="{{ $studentId }}"><input type="hidden" name="class[{{ $studentId }}]" value="{{ $choice['class_id'] }}"><input type="hidden" name="arm[{{ $studentId }}]" value="{{ $choice['arm_id'] }}">@if($choice['stay'])<input type="hidden" name="stay[{{ $studentId }}]" value="1">@endif
                    @endforeach
                    <a class="oak-btn oak-btn-secondary" href="{{ route('academics.promotion.index', ['section' => $selectedSection, 'source' => $source->id, 'arm' => request()->integer('arm') ?: null]) }}">Back</a><button class="oak-btn oak-btn-primary">Apply promotion</button>
                </form>
            </section>
        @else
            @if($finalStatus)
                <div class="oak-notice oak-notice-info"><x-icon name="info-circle" /><div><b>Final class: {{ $finalStatus }}</b><p>{{ $finalStatus === 'Awaiting admission' ? 'These students will leave active rosters and wait for admission into the next section.' : 'These students will leave active rosters as graduates.' }} Past results, attendance and receipts remain available.</p></div></div>
            @elseif($nextStep->count() > 1 && !$source->department_id)
                <div class="oak-notice oak-notice-info"><x-icon name="info-circle" /><div><b>Choose a class for every student.</b> More than one class is on the next step and the source class has no department.</div></div>
            @endif

            <form method="post" action="{{ route('academics.promotion.review') }}" data-promotion-form>@csrf
                <input type="hidden" name="section_id" value="{{ $selectedSection }}"><input type="hidden" name="source_class_id" value="{{ $source->id }}"><input type="hidden" name="source_arm_id" value="{{ request()->integer('arm') ?: '' }}">
                @if(!$finalStatus && $students?->count())
                    <div class="pr-bulk ad-card"><label class="ad-choice"><input type="checkbox" data-select-all checked> Select this page</label><div><label>Apply class to selected<select class="ad-inline-select" data-bulk-class><option value="">Choose a class</option><option value="{{ $source->id }}">Stay in {{ $source->name }}</option>@foreach($nextStep as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select></label><label>Apply arm to selected<select class="ad-inline-select" data-bulk-arm><option value="">Choose an arm</option>@foreach($nextStep as $class)@foreach($class->arms->sortBy('name', SORT_NATURAL) as $arm)<option value="{{ $arm->id }}" data-class="{{ $class->id }}">{{ $class->name }} · {{ $arm->name }}</option>@endforeach@endforeach</select></label><button class="oak-btn oak-btn-secondary" type="button" data-apply-bulk>Apply to selected</button></div></div>
                @endif
                <section class="ad-card pr-roster">
                    <div class="ad-card-head"><div><h2>{{ $source->name }}</h2><p>{{ $students?->total() ?? 0 }} active students</p></div>@if($source->department)<span class="ad-dept">{{ $source->department->name }}</span>@endif</div>
                    @forelse($students ?? [] as $student)
                        @php($suggestion = $suggestions[$student->id])
                        <div class="pr-student" data-student-row>
                            <label class="pr-person"><input type="checkbox" name="students[]" value="{{ $student->id }}" checked><span><b>{{ $student->user?->name }}</b><small>{{ $student->classArm?->name }}</small></span></label>
                            @if($finalStatus)
                                <span class="ad-tag">{{ $finalStatus }}</span>
                            @else
                                <label>Destination class<select class="ad-inline-select" name="class[{{ $student->id }}]" data-row-class><option value="">Choose a class</option><option value="{{ $source->id }}">Stay in {{ $source->name }}</option>@foreach($nextStep as $class)<option value="{{ $class->id }}" @selected($suggestion['class']?->id === $class->id)>{{ $class->name }}</option>@endforeach</select></label>
                                <label>Arm<select class="ad-inline-select" name="arm[{{ $student->id }}]" data-row-arm><option value="">Choose an arm</option>@foreach($nextStep as $class)@foreach($class->arms->sortBy('name', SORT_NATURAL) as $arm)<option value="{{ $arm->id }}" data-class="{{ $class->id }}" @selected($suggestion['arm']?->id === $arm->id)>{{ $arm->name }}</option>@endforeach@endforeach</select></label>
                            @endif
                        </div>
                    @empty
                        <div class="oak-empty"><x-icon name="users" /><strong>No active students in this class.</strong></div>
                    @endforelse
                </section>
                @if($students?->hasPages())<div class="pr-pages">{{ $students->links() }}</div>@endif
                @if($students?->count())<div class="ad-bar"><span class="ad-meta">Preview first. Nothing changes yet.</span><button class="oak-btn oak-btn-primary">Preview promotion</button></div>@endif
            </form>

            <details class="ad-card ad-edit pr-move-one"><summary class="oak-btn oak-btn-secondary">Move one student</summary><form method="post" action="{{ route('academics.promotion.move-one') }}" class="ad-form-grid">@csrf
                <label>Student<select class="ad-inline-select" name="student_id" required><option value="">Choose a student</option>@foreach($students ?? [] as $student)<option value="{{ $student->id }}">{{ $student->user?->name }}</option>@endforeach</select></label>
                <label>Destination class<select class="ad-inline-select" name="destination_class_id" data-single-class required><option value="">Choose a class</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select></label>
                <label>Destination arm<select class="ad-inline-select" name="destination_arm_id" data-single-arm required><option value="">Choose an arm</option>@foreach($classes as $class)@foreach($class->arms->sortBy('name', SORT_NATURAL) as $arm)<option value="{{ $arm->id }}" data-class="{{ $class->id }}">{{ $class->name }} · {{ $arm->name }}</option>@endforeach@endforeach</select></label>
                <div><button class="oak-btn oak-btn-primary">Move student</button></div>
            </form></details>
        @endif
    @endif
</main>
@include('core._shell-end')
@livewireScripts
<script>
document.addEventListener('DOMContentLoaded', function () {
    function filterArms(classSelect, armSelect) {
        if (!classSelect || !armSelect) return;
        const classId = classSelect.value;
        Array.from(armSelect.options).forEach(function (option) {
            if (!option.dataset.class) return;
            option.hidden = option.dataset.class !== classId;
            option.disabled = option.dataset.class !== classId;
        });
        if (armSelect.selectedOptions[0]?.disabled) armSelect.value = '';
    }
    document.querySelectorAll('[data-student-row]').forEach(function (row) {
        const klass = row.querySelector('[data-row-class]'); const arm = row.querySelector('[data-row-arm]');
        filterArms(klass, arm); klass?.addEventListener('change', function () { filterArms(klass, arm); });
    });
    const all = document.querySelector('[data-select-all]');
    all?.addEventListener('change', function () { document.querySelectorAll('[data-student-row] input[type="checkbox"]').forEach(function (box) { box.checked = all.checked; }); });
    document.querySelector('[data-apply-bulk]')?.addEventListener('click', function () {
        const classId = document.querySelector('[data-bulk-class]').value; const armId = document.querySelector('[data-bulk-arm]').value;
        document.querySelectorAll('[data-student-row]').forEach(function (row) { if (!row.querySelector('input[type="checkbox"]').checked) return; const klass = row.querySelector('[data-row-class]'); const arm = row.querySelector('[data-row-arm]'); if (classId) klass.value = classId; filterArms(klass, arm); if (armId && !arm.querySelector('option[value="'+armId+'"]')?.disabled) arm.value = armId; });
    });
    const singleClass = document.querySelector('[data-single-class]'); const singleArm = document.querySelector('[data-single-arm]');
    filterArms(singleClass, singleArm); singleClass?.addEventListener('change', function () { filterArms(singleClass, singleArm); });
});
</script>
</body></html>
