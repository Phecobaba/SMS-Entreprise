<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My classes and subjects</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="teacher-results-page">
@include('core._shell-start')
<main class="tr-workspace">
    <div class="tr-eyebrow">Teacher · Results</div>
    <h1 class="tr-title">My classes and subjects</h1>
    @if(session('status'))<p class="tr-notice tr-notice-success">{{ session('status') }}</p>@endif

    @if($returnedResults)
        <section class="tr-section" id="returned-results">
            <div class="tr-section-head"><div><div class="tr-eyebrow">Needs correction</div><h2>Returned results</h2><p class="tr-muted">Open a result to see the rejection reason and correct its scores.</p></div><span class="tr-pill tr-status-rejected">{{ $returnedResults->total() }} Rejected</span></div>
            <div class="tr-table-wrap"><table class="tr-table"><thead><tr><th>{{ $pairs->first()['learnerTerm'] ?? 'Student' }}</th><th>Subject and class</th><th>Reason</th><th></th></tr></thead><tbody>
                @forelse($returnedResults as $result)<tr><td data-label="{{ $pairs->first()['learnerTerm'] ?? 'Student' }}"><strong>{{ $returnedNames[$result->student_id] ?? 'Student #'.$result->student_id }}</strong></td><td data-label="Subject and class">{{ $returnedLabels[$result->id] }}</td><td data-label="Reason">{{ $result->rejection_reason }}</td><td><a class="tr-btn" href="{{ route('results.show', $result) }}">Open result</a></td></tr>@empty<tr><td colspan="4">No returned results remain.</td></tr>@endforelse
            </tbody></table></div>
            @if($returnedResults->hasPages())<div class="mt-4">{{ $returnedResults->links() }}</div>@endif
        </section>
    @endif

    @if(!$term)
        <div class="tr-empty"><h2>No current term is configured</h2><p>Scores and current-term counts will appear after the school office configures a term containing today.</p></div>
    @elseif($pairs->isEmpty())
        <div class="tr-empty"><h2>No subject assignments yet</h2><p>Your school has not assigned you a subject and class. Ask the school office or your Section Head to review your teaching assignments.</p><a class="tr-btn tr-btn-secondary" href="{{ route('results.index') }}">Refresh assignments</a></div>
    @else
        <section class="tr-section">
            <div class="tr-section-head"><h2>My subject assignments</h2><span class="tr-pill">Current term · {{ $termLabel }}</span></div>
            <div class="tr-card-grid">
                @foreach($pairs as $pair)
                    <article class="tr-card">
                        <div class="tr-eyebrow">Subject assignment</div>
                        <h2>{{ $pair['label'] }}</h2>
                        <p class="tr-muted">All arms · {{ $pair['pupils'] }} {{ $pair['pupils'] === 1 ? strtolower($pair['learnerTerm']) : strtolower($pair['learnersTerm']) }} · {{ $term->name }}</p>
                        <div class="tr-metrics">
                            <div><strong>{{ $pair['draft'] }}</strong><span>Draft</span></div>
                            <div><strong>{{ $pair['submitted'] }}</strong><span>Submitted</span></div>
                            <div><strong>{{ $pair['approved'] }}</strong><span>Approved</span></div>
                        </div>
                        <a class="tr-btn" href="{{ route('results.teacher.pair', [$pair['class_id'], $pair['subject_id']]) }}">Enter scores</a>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if($formArms->isNotEmpty())
        <section class="tr-section">
            <h2>My class</h2>
            <div class="tr-card-grid">
                @foreach($formArms as $arm)
                    <article class="tr-card">
                        <div class="tr-eyebrow">Form teacher · own arm only</div>
                        <h2>{{ app(\App\Modules\Academics\Services\TeacherFacingLabel::class)->arm($arm->schoolClass?->name, $arm->name) }}</h2>
                        <p class="tr-muted">Class-wide approved results and compiled report cards for your assigned arm.</p>
                        <span class="tr-pill tr-pill-approved">Approved only</span>
                        <a class="tr-btn tr-btn-secondary" href="{{ route('results.teacher.class', $arm->id) }}">View approved results and cards</a>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</main>
@include('core._shell-end')
@livewireScripts
</body></html>
