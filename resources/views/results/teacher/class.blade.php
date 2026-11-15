<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My class · {{ $armLabel }}</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="teacher-results-page">
@include('core._shell-start')
<main class="tr-workspace">
    <nav class="tr-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('results.index') }}">My classes and subjects</a><span>/</span><span>My class</span></nav>
    <div class="tr-eyebrow">Form teacher · own arm only{{ $term ? ' · '.$termLabel : '' }}</div>
    <h1 class="tr-title">{{ $armLabel }} — approved results</h1>
    @if(!$term)
        <div class="tr-empty"><h2>No current term is configured</h2><p>Approved results will appear after the school office configures the current term.</p></div>
    @elseif($cards->isEmpty())
        <div class="tr-empty"><h2>No approved results yet</h2><p>Your class's approved results and report cards will appear here after approval.</p></div>
    @else
        <section class="tr-card"><div class="tr-section-head"><div><p class="tr-muted">Form-teacher view · Own arm only · {{ $term->name }}</p><h2>Approved report cards</h2></div><span class="tr-pill tr-pill-approved">Approved</span></div>
            <div class="tr-table-wrap"><table class="tr-table"><thead><tr><th>{{ $learnerTerm }}</th><th>Approved subjects</th><th>Report card</th></tr></thead><tbody>
            @foreach($cards as $card)<tr><td data-label="{{ $learnerTerm }}">{{ $studentNames[$card->student_id] ?? $learnerTerm.' #'.$card->student_id }}</td><td data-label="Approved subjects">{{ $approvedCounts[$card->student_id] ?? 0 }} {{ Str::plural('subject', $approvedCounts[$card->student_id] ?? 0) }}</td><td><a class="tr-btn tr-btn-secondary" href="{{ route('results.cards.show', $card->id) }}">View card</a></td></tr>@endforeach
            </tbody></table></div><div class="tr-pagination">{{ $cards->links() }}</div>
        </section>
    @endif
</main>
@include('core._shell-end')
@livewireScripts
</body></html>
