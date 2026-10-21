<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Teacher assignments · Oakfield</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>
@php
    $pageTitle = 'Teacher assignments';
    $selectedTeacher = $teachers->firstWhere('id', $selectedTeacherId);
    $selectedClass = $classes->firstWhere('id', $selectedClassId);
    $assignmentGroups = $assignments->groupBy(fn($row) => $row->class_id.':'.$row->subject_id);
    $wide = $subjects->count() >= 20;
@endphp
@include('core._shell-start')
<main class="oak-dashboard ad-assignment-page">
    <div class="oak-dashboard-heading"><div><h1>Teacher assignments</h1><p>{{ $canViewAll ? 'Who teaches which subject in which class' : (($section?->name ?? 'Your section').' · who teaches which subject in which class') }}</p></div><span class="oak-role-pill">{{ $canViewAll ? 'Admin' : ($section?->head_title ?? 'Head') }}</span></div>
    @if($canViewAll)@include('academics._tabs')@endif
    @if(session('status'))<div class="oak-notice oak-notice-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="oak-notice oak-notice-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if(!$hasSection || !$section)
        <div class="oak-locked"><x-icon name="lock" /><h3>No section assigned</h3><p>A head works only within their own section. Ask the Admin to assign you in Academic structure. Nothing on this page is shown to you.</p></div>
    @else
        @if($canViewAll && $sections->count() > 1)
            <div class="ad-scope"><nav class="ad-switch" aria-label="Section scope">@foreach($sections as $available)<a class="ad-seg" href="{{ route('academics.teacher-assignments.index', ['section' => $available->id, 'mode' => $mode]) }}" aria-current="{{ $sectionId === $available->id ? 'true' : 'false' }}" wire:navigate>{{ $available->name }}</a>@endforeach</nav></div>
        @elseif(!$canViewAll)<div class="ad-section-badge"><x-icon name="school" />{{ $section->name }} only</div>@endif

        <nav class="ad-tabs" aria-label="Assign by"><a href="{{ route('academics.teacher-assignments.index', ['section' => $sectionId, 'mode' => 'teacher']) }}" @if($mode === 'teacher') aria-current="page" @endif>By teacher</a><a href="{{ route('academics.teacher-assignments.index', ['section' => $sectionId, 'mode' => 'class']) }}" @if($mode === 'class') aria-current="page" @endif>By class</a></nav>

        @if($report)
            <div class="oak-notice oak-notice-success" role="status"><strong>Saved.</strong> {{ $report['added'] }} added, {{ $report['removed'] }} removed, {{ $report['unchanged'] }} unchanged.</div>
        @endif

        @if(isset($preview))
            <section class="ad-card"><div class="ad-card-head"><div><h2>Review before saving</h2><p>Nothing has changed yet. {{ $preview['label'] }}.</p></div></div>
                <div class="ad-summary"><div class="ad-add"><span>Will add</span><strong>{{ $preview['added'] }}</strong></div><div class="ad-fail"><span>Will remove</span><strong>{{ $preview['removed'] }}</strong></div><div class="ad-skip"><span>Unchanged</span><strong>{{ $preview['unchanged'] }}</strong></div></div>
                <ul class="oak-rules">@forelse($preview['changes'] as $change)<li><x-icon name="{{ $change['type'] === 'add' ? 'circle-check' : 'alert-triangle' }}" /><span>{{ ucfirst($change['type']) }} {{ $change['teacher'] }} · {{ $change['subject'] }} in {{ $change['class'] }}.@if($change['type'] === 'remove') <strong>Results already entered stay as they are.</strong>@endif</span></li>@empty<li>No changes to save.</li>@endforelse</ul>
                <form method="post" action="{{ route('academics.teacher-assignments.apply') }}">@csrf<input type="hidden" name="section_id" value="{{ $sectionId }}"><input type="hidden" name="mode" value="{{ $mode }}">@if($mode === 'teacher')<input type="hidden" name="teacher_id" value="{{ $selectedTeacherId }}">@foreach($postedPairs ?? [] as $pair)<input type="hidden" name="pairs[]" value="{{ $pair }}">@endforeach @else<input type="hidden" name="class_id" value="{{ $selectedClassId }}">@foreach($postedTeachers ?? [] as $subjectId => $teacherId)<input type="hidden" name="teachers[{{ $subjectId }}]" value="{{ $teacherId }}">@endforeach @endif<div class="ad-bar"><a class="oak-btn oak-btn-quiet" href="{{ route('academics.teacher-assignments.index', ['section' => $sectionId, 'mode' => $mode, 'teacher_id' => $selectedTeacherId, 'class_id' => $selectedClassId]) }}">Back to editing</a><button class="oak-btn oak-btn-primary" @disabled(($preview['added'] + $preview['removed']) === 0)>Save {{ $preview['added'] + $preview['removed'] }} changes</button></div></form>
            </section>
        @elseif($mode === 'teacher')
            <form method="get" action="{{ route('academics.teacher-assignments.index') }}" class="ad-card ad-picker"><input type="hidden" name="section" value="{{ $sectionId }}"><input type="hidden" name="mode" value="teacher"><div class="oak-field"><label for="teacher_id">Teacher</label><select id="teacher_id" name="teacher_id" onchange="this.form.submit()"><option value="">Choose a teacher…</option>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected($selectedTeacherId === $teacher->id)>{{ $teacher->name }}</option>@endforeach</select></div></form>
            @if($selectedTeacher)
                @if($wide)<p class="ad-scroll-note">This school has {{ $subjects->count() }} subjects. Scroll sideways inside the assignment table.</p>@endif
                <form method="post" action="{{ route('academics.teacher-assignments.review') }}" data-change-form>@csrf<input type="hidden" name="section_id" value="{{ $sectionId }}"><input type="hidden" name="mode" value="teacher"><input type="hidden" name="teacher_id" value="{{ $selectedTeacher->id }}">
                    <div class="ad-scroll" @if($wide) data-wide tabindex="0" role="region" aria-label="Teacher assignment matrix; scroll sideways" @endif><table class="ad-matrix"><thead><tr><th>Class</th>@foreach($subjects as $subject)<th>{{ $subject->name }}</th>@endforeach</tr></thead><tbody>
                        @foreach($levels as $level)<tr class="ad-lvl"><td colspan="{{ $subjects->count() + 1 }}">{{ $level->name }}</td></tr>@foreach($level->classes as $class)<tr><td>{{ $class->name }}</td>@php $offered = $class->classSubjects->keyBy('subject_id'); @endphp @foreach($subjects as $subject)@php $pair = $class->id.':'.$subject->id; $rows = $assignmentGroups->get($pair, collect()); $mine = $rows->contains('user_id', $selectedTeacher->id); $other = $rows->where('user_id', '!=', $selectedTeacher->id)->isNotEmpty(); @endphp @if($offered->has($subject->id))<td data-label="{{ $subject->name }}"><label class="ad-cell" @if($other) title="Another teacher is assigned" @endif><input type="checkbox" name="pairs[]" value="{{ $pair }}" @checked($mine) @disabled($other)><span class="ad-phone-subject">{{ $subject->name }}</span>@if($other)<small>Assigned</small>@endif</label></td>@else<td class="ad-na-cell"><span class="ad-na" aria-label="Not offered">—</span></td>@endif @endforeach</tr>@endforeach @endforeach
                    </tbody></table></div><div class="ad-bar"><span class="ad-meta">Ticked means this teacher teaches it.</span><button class="oak-btn oak-btn-primary" data-review-button disabled>Review changes</button></div>
                </form>
            @endif
        @else
            <form method="get" action="{{ route('academics.teacher-assignments.index') }}" class="ad-card ad-picker"><input type="hidden" name="section" value="{{ $sectionId }}"><input type="hidden" name="mode" value="class"><div class="oak-field"><label for="class_id">Class</label><select id="class_id" name="class_id" onchange="this.form.submit()"><option value="">Choose a class…</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected($selectedClassId === $class->id)>{{ $class->name }}</option>@endforeach</select></div></form>
            @if($selectedClass)<form method="post" action="{{ route('academics.teacher-assignments.review') }}" class="ad-card" data-change-form>@csrf<input type="hidden" name="section_id" value="{{ $sectionId }}"><input type="hidden" name="mode" value="class"><input type="hidden" name="class_id" value="{{ $selectedClass->id }}"><div class="ad-card-head"><div><h2>{{ $selectedClass->name }} · who teaches what</h2><p>Only subjects this class actually has are listed.</p></div></div>
                @foreach($selectedClass->classSubjects->sortBy(fn($item) => $item->subject->name) as $item)@php $rows = $assignmentGroups->get($selectedClass->id.':'.$item->subject_id, collect()); $current = $rows->first()?->user_id; @endphp<div class="ad-class-row ad-teacher-row"><div><strong>{{ $item->subject->name }}</strong>@if(!$current)<small>No teacher yet</small>@endif</div><div><select class="ad-inline-select" name="teachers[{{ $item->subject_id }}]" aria-label="Teacher for {{ $item->subject->name }}"><option value="">Choose a teacher…</option>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected($current === $teacher->id)>{{ $teacher->name }}</option>@endforeach</select></div></div>@endforeach
                <div class="ad-bar"><button class="oak-btn oak-btn-primary" data-review-button disabled>Review changes</button></div></form>@endif
        @endif
        <p class="ad-meta ad-assignment-note">Assignments stay in place from term to term until you change them. Removing one never deletes results already entered.</p>
    @endif
</main>
@include('core._shell-end')
</body></html>
