<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Enter scores</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="teacher-results-page">
@include('core._shell-start')
<main class="tr-workspace tr-entry-workspace">
    <nav class="tr-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('results.index') }}">My classes and subjects</a><span>/</span><a href="{{ route('results.teacher.pair', [$assignment->class_id, $assignment->subject_id]) }}">{{ $pairLabel }}</a><span>/</span><span>{{ $student->user?->name }}</span></nav>
    <div class="tr-eyebrow">Score entry{{ $term ? ' · '.$termLabel : '' }}</div>
    <h1 class="tr-title">Enter scores</h1>
    <p class="tr-lede">{{ $student->user?->name ?? $learnerTerm.' #'.$student->id }}@if($student->registration_number) · {{ $student->registration_number }}@endif · {{ $student->classArm?->schoolClass?->name }}, Arm {{ $student->classArm?->name }}</p>
    @if(session('status'))<p class="tr-notice tr-notice-success">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="tr-notice tr-notice-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    @if(!$term)
        <div class="tr-empty"><h2>No current term is configured</h2><p>Score entry is unavailable until the school office configures the current term.</p></div>
    @elseif(!$template)
        <div class="tr-empty"><h2>Result template needed</h2><p>{{ $templateMessage }}</p></div>
    @else
        @php($editable = !$result || in_array($result->status, ['Draft', 'Rejected'], true))
        <section class="tr-card tr-score-card">
            <div class="tr-section-head"><div><div class="tr-eyebrow">Assigned pair</div><h2>{{ $pairLabel }}</h2><p class="tr-muted">{{ $termLabel }} · Scores use the configured assessment components.</p></div><span class="tr-pill tr-status-{{ Str::slug($result?->status ?? 'Not started') }}">{{ $result?->status ?? 'Not started' }}</span></div>
            @if($result?->rejection_reason)<div class="tr-notice">Correction requested: {{ $result->rejection_reason }}</div>@endif

            @if($editable)
                @php($initialScores = $template->components->mapWithKeys(function ($entry) use ($result) {
                    $item = $result?->items?->firstWhere('assessment_component_id', $entry->assessment_component_id);
                    return [(string) $entry->assessment_component_id => old('scores.'.$entry->assessment_component_id, $item?->score)];
                })->all())
                <form method="post" action="{{ route('results.teacher.draft', [$assignment->class_id, $assignment->subject_id, $student->id]) }}" class="tr-score-form" x-data="{ scores: @js($initialScores) }">@csrf
                    <div class="tr-score-grid">
                    @foreach($template->components as $entry)
                        @php($item = $result?->items?->firstWhere('assessment_component_id', $entry->assessment_component_id))
                        <label>{{ $entry->component->name }} <span>Maximum {{ rtrim(rtrim($entry->component->max_score, '0'), '.') }}</span><input type="number" step="0.01" min="0" max="{{ $entry->component->max_score }}" name="scores[{{ $entry->assessment_component_id }}]" value="{{ $initialScores[(string) $entry->assessment_component_id] }}" x-model.number="scores['{{ $entry->assessment_component_id }}']"></label>
                    @endforeach
                    </div>
                    <div class="tr-total"><span>Calculated total</span><strong x-text="Object.values(scores).reduce((sum, value) => sum + (Number(value) || 0), 0).toFixed(2)">{{ number_format((float)($result?->total_score ?? 0), 2) }}</strong><span>points</span></div>
                    <div class="tr-actions"><button class="tr-btn" type="submit">Save draft</button><button class="tr-btn tr-btn-secondary" type="submit" formaction="{{ route('results.teacher.submit', [$assignment->class_id, $assignment->subject_id, $student->id]) }}" onclick="return confirm('Submit these scores for approval? You cannot edit them after submitting unless they are returned.')">Submit for approval</button><a class="tr-btn tr-btn-secondary" href="{{ route('results.teacher.pair', [$assignment->class_id, $assignment->subject_id]) }}">Back to {{ strtolower($learnersTerm) }}</a></div>
                </form>
            @else
                <div class="tr-score-grid tr-readonly">
                    @foreach($template->components as $entry)
                        @php($item = $result->items->firstWhere('assessment_component_id', $entry->assessment_component_id))
                        <div><span>{{ $entry->component->name }} · maximum {{ rtrim(rtrim($entry->component->max_score, '0'), '.') }}</span><strong>{{ $item?->score ?? 'Not entered' }}</strong></div>
                    @endforeach
                </div>
                <div class="tr-total"><span>Total</span><strong>{{ $result->total_score }}</strong><span>points</span></div>
                <div class="tr-notice">{{ $result->status === 'Approved' ? 'This result is approved and read-only. It cannot be changed through score entry.' : 'Submitted for review. Editing is locked until the result is returned for correction.' }}</div>
                <a class="tr-btn tr-btn-secondary" href="{{ route('results.teacher.pair', [$assignment->class_id, $assignment->subject_id]) }}">Back to {{ strtolower($learnersTerm) }}</a>
            @endif
        </section>
    @endif
</main>
@include('core._shell-end')
@livewireScripts
</body></html>
