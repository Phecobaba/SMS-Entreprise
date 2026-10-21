<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Assign subjects · Oakfield</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>
@php
    $pageTitle = 'Assign subjects';
    $selectedLevel = $levels->firstWhere('id', $selectedLevelId);
    $availableClasses = $selectedLevel?->classes ?? collect();
    $availableDepartments = $availableClasses->pluck('department')->filter()->unique('id')->sortBy('name')->values();
    $effectiveStep = $step === 'department' && $availableDepartments->isEmpty() ? 'classes' : $step;
    $filteredClasses = $selectedDepartmentId ? $availableClasses->where('department_id', $selectedDepartmentId) : $availableClasses;
    $stepNumber = ['level' => 1, 'department' => 2, 'classes' => 3, 'subjects' => 4, 'review' => 5][$effectiveStep] ?? 1;
@endphp
@include('core._shell-start')
<main class="oak-dashboard ad-assignment-page">
    <div class="oak-dashboard-heading"><div><h1>Assign subjects to classes</h1><p>{{ $canViewAll ? 'Add the same subjects to many classes at once' : (($section?->name ?? 'Your section').' · add the same subjects to many classes at once') }}</p></div><span class="oak-role-pill">{{ $canViewAll ? 'Admin' : ($section?->head_title ?? 'Head') }}</span></div>
    @if($canViewAll)@include('academics._tabs')@endif
    @if(session('status'))<div class="oak-notice oak-notice-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="oak-notice oak-notice-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if(!$hasSection || !$section)
        <div class="oak-locked"><x-icon name="lock" /><h3>No section assigned</h3><p>A head works only within their own section. Ask the Admin to assign you in Academic structure. Nothing on this page is shown to you.</p></div>
    @else
        @if($canViewAll && $sections->count() > 1)
            <div class="ad-scope"><nav class="ad-switch" aria-label="Section scope">@foreach($sections as $available)<a class="ad-seg" href="{{ route('academics.bulk-subjects.index', ['section' => $available->id]) }}" aria-current="{{ $sectionId === $available->id ? 'true' : 'false' }}" wire:navigate>{{ $available->name }}</a>@endforeach</nav></div>
        @elseif(!$canViewAll)<div class="ad-section-badge"><x-icon name="school" />{{ $section->name }} only</div>@endif

        @if($report)
            <div class="oak-notice oak-notice-success" role="status"><strong>{{ $report['added'] ? 'Assignment complete.' : 'Nothing to add.' }}</strong> {{ $report['added'] }} added, {{ $report['skipped'] }} already there and skipped, {{ $report['failed'] }} failed.</div>
        @endif

        <ol class="ad-stepper" aria-label="Progress">
            @foreach(['Level','Department','Classes','Subjects','Review'] as $number => $label)<li @if($number + 1 < $stepNumber) data-done @elseif($number + 1 === $stepNumber) aria-current="step" @endif>{{ $label }}</li>@endforeach
        </ol>
        <p class="ad-step-short">Step {{ $stepNumber }} of 5: {{ ['Level','Department','Classes','Subjects','Review'][$stepNumber - 1] }}</p>

        @if($effectiveStep === 'level')
            <section class="ad-card"><div class="ad-card-head"><div><h2>Choose a level</h2><p>Classes from this level will be available in the next steps.</p></div></div>
                <form method="get" action="{{ route('academics.bulk-subjects.index') }}" class="ad-form-grid"><input type="hidden" name="section" value="{{ $sectionId }}"><input type="hidden" name="step" value="department"><div class="oak-field ad-wide"><label for="level_id">Level</label><select id="level_id" name="level_id" required><option value="">Choose a level…</option>@foreach($levels as $level)<option value="{{ $level->id }}">{{ $level->name }}</option>@endforeach</select></div><div class="ad-bar ad-wide"><button class="oak-btn oak-btn-primary">Continue</button></div></form>
            </section>
        @elseif($effectiveStep === 'department')
            <section class="ad-card"><div class="ad-card-head"><div><h2>Choose a department</h2><p>Optional. Show all classes or narrow the list.</p></div></div>
                <form method="get" action="{{ route('academics.bulk-subjects.index') }}" class="ad-form-grid"><input type="hidden" name="section" value="{{ $sectionId }}"><input type="hidden" name="step" value="classes"><input type="hidden" name="level_id" value="{{ $selectedLevelId }}"><div class="oak-field ad-wide"><label for="department_id">Department</label><select id="department_id" name="department_id"><option value="">All departments</option>@foreach($availableDepartments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select></div><div class="ad-bar ad-wide"><a class="oak-btn oak-btn-quiet" href="{{ route('academics.bulk-subjects.index', ['section' => $sectionId]) }}">Back</a><button class="oak-btn oak-btn-primary">Continue</button></div></form>
            </section>
        @elseif($effectiveStep === 'classes')
            <section class="ad-card"><div class="ad-card-head"><div><h2>Choose classes</h2><p>All matching classes are selected. Untick any you do not want.</p></div></div>
                <form method="get" action="{{ route('academics.bulk-subjects.index') }}" data-require-choice><input type="hidden" name="section" value="{{ $sectionId }}"><input type="hidden" name="step" value="subjects"><input type="hidden" name="level_id" value="{{ $selectedLevelId }}">@if($selectedDepartmentId)<input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">@endif
                    <div class="ad-choice-list">@forelse($filteredClasses as $class)<label class="ad-choice"><input type="checkbox" name="class_ids[]" value="{{ $class->id }}" checked><span><strong>{{ $class->name }}</strong>@if($class->department)<small>{{ $class->department->name }}</small>@endif</span></label>@empty<p class="ad-meta">No classes match this choice.</p>@endforelse</div>
                    <p class="ad-inline-error" data-choice-error hidden>Choose at least one class.</p>
                    <div class="ad-bar"><a class="oak-btn oak-btn-quiet" href="{{ route('academics.bulk-subjects.index', ['section' => $sectionId, 'step' => $availableDepartments->isEmpty() ? 'level' : 'department', 'level_id' => $selectedLevelId]) }}">Back</a><button class="oak-btn oak-btn-primary" @disabled($filteredClasses->isEmpty())>Continue</button></div>
                </form>
            </section>
        @elseif($effectiveStep === 'subjects')
            <section class="ad-card"><div class="ad-card-head"><div><h2>Choose subjects</h2><p>These subjects will be added to each selected class.</p></div></div>
                <form method="post" action="{{ route('academics.bulk-subjects.review') }}" data-require-choice>@csrf<input type="hidden" name="section_id" value="{{ $sectionId }}"><input type="hidden" name="level_id" value="{{ $selectedLevelId }}">@if($selectedDepartmentId)<input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">@endif @foreach($selectedClassIds as $classId)<input type="hidden" name="class_ids[]" value="{{ $classId }}">@endforeach
                    <div class="ad-choice-grid">@foreach($subjects as $subject)<label class="ad-choice"><input type="checkbox" name="subject_ids[]" value="{{ $subject->id }}"><span>{{ $subject->name }}</span></label>@endforeach</div>
                    <p class="ad-inline-error" data-choice-error hidden>Choose at least one subject.</p>
                    <div class="ad-bar"><a class="oak-btn oak-btn-quiet" href="{{ route('academics.bulk-subjects.index', ['section' => $sectionId, 'step' => 'classes', 'level_id' => $selectedLevelId, 'department_id' => $selectedDepartmentId]) }}">Back</a><button class="oak-btn oak-btn-primary">Review assignment</button></div>
                </form>
            </section>
        @elseif($effectiveStep === 'review')
            <section class="ad-card"><div class="ad-card-head"><div><h2>Review before adding</h2><p>Nothing has changed yet.</p></div></div>
                <div class="ad-summary"><div class="ad-add"><span>Will add</span><strong>{{ $preview['added'] }}</strong></div><div class="ad-skip"><span>Already there, skipped</span><strong>{{ $preview['skipped'] }}</strong></div><div><span>Classes</span><strong>{{ $preview['classes'] }}</strong></div></div>
                <div class="oak-table-scroll"><table><thead><tr><th>Class</th><th>Will add</th><th>Skipped</th></tr></thead><tbody>@foreach($preview['perClass'] as $row)<tr><td>{{ $row['class'] }}</td><td>{{ $row['add'] }}</td><td>{{ $row['skipped'] }}</td></tr>@endforeach</tbody></table></div>
                <form method="post" action="{{ route('academics.bulk-subjects.apply') }}">@csrf<input type="hidden" name="section_id" value="{{ $sectionId }}"><input type="hidden" name="level_id" value="{{ $selectedLevelId }}">@if($selectedDepartmentId)<input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">@endif @foreach($selectedClassIds as $classId)<input type="hidden" name="class_ids[]" value="{{ $classId }}">@endforeach @foreach($selectedSubjectIds as $subjectId)<input type="hidden" name="subject_ids[]" value="{{ $subjectId }}">@endforeach<div class="ad-bar"><a class="oak-btn oak-btn-quiet" href="{{ route('academics.bulk-subjects.index', ['section' => $sectionId, 'step' => 'subjects', 'level_id' => $selectedLevelId, 'department_id' => $selectedDepartmentId, 'class_ids' => $selectedClassIds]) }}">Back to subjects</a><button class="oak-btn oak-btn-primary" @disabled($preview['added'] === 0)>{{ $preview['added'] ? 'Add '.$preview['added'].' assignments' : 'Nothing to add' }}</button></div></form>
            </section>
        @endif
    @endif
</main>
@include('core._shell-end')
</body></html>
