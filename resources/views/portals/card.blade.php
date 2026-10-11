@extends('portals.layout')
@section('title', 'Report card')
@section('content')
@if($locked)
    <div class="rs-waiting"><x-icon name="lock" /><h1>Report card locked</h1><p>This result opens after the term's tuition invoice is verified paid.</p>
        @if($invoice)<button class="oak-btn oak-btn-primary" type="button" data-open-sheet="card-pay-sheet">Pay tuition</button>@else<a class="oak-btn oak-btn-quiet" href="{{ route('payments.mine.index', ['student' => $context['student_id']]) }}" wire:navigate>View fees</a>@endif
    </div>
    @if($invoice)<div class="oak-scrim" id="card-pay-sheet" role="dialog" aria-modal="true" aria-label="Pay tuition"><div class="oak-sheet"><h2>Pay ₦{{ number_format($invoice->amount_kobo / 100, 0) }}</h2><p class="oak-sheet-sub">Continue to Paystack, then return to Fees for verified status.</p><div class="oak-sheet-actions"><button class="oak-btn oak-btn-quiet" type="button" data-close-sheet>Cancel</button><form method="post" action="{{ route('payments.mine.begin', ['invoice' => $invoice->id, 'student' => $context['student_id']]) }}">@csrf<button class="oak-btn oak-btn-primary" type="submit">Continue to Paystack</button></form></div></div></div>@endif
@else
    <div class="rc-head"><div><h1>{{ $studentName }} · {{ $term }}</h1><p>{{ $class }} · Report card</p></div><a class="oak-btn oak-btn-primary" href="{{ route('results.mine.pdf', ['card' => $card->id, 'student' => $context['student_id']]) }}" target="_blank" rel="noopener">Open A4 PDF</a></div>
    <div class="rc-facts"><div class="rc-fact"><span>Position</span><strong>{{ $position ? $position.' of '.$classSize : '—' }}</strong></div><div class="rc-fact"><span>Own average</span><strong>{{ $average === null ? '—' : number_format($average, 1).'%' }}</strong></div><div class="rc-fact"><span>Subjects</span><strong>{{ $results->count() }}</strong></div></div>
    <section class="oak-section"><h2>Subjects</h2><table class="oak-table rc-table"><thead><tr><th>Subject</th><th>Components</th><th>Total</th><th>Percentage</th><th>Grade</th>@if($showSubjectRemarks)<th>Remark</th>@endif</tr></thead><tbody>
        @foreach($results as $result)<tr><td class="rc-subject">{{ $subjects[$result->subject_id] ?? 'Subject' }}</td><td class="rc-compcell"><span class="rc-comp">{{ $result->items->map(fn ($item) => ($item->component?->name ?? 'Component').' '.$item->score)->implode(' · ') }}</span></td><td class="rc-total">{{ $result->total_score }}</td><td class="rc-pct">{{ $result->percentage }}%</td><td class="rc-gradecell"><span class="oak-status-pill oak-status-neutral">{{ $result->grade }}</span></td>@if($showSubjectRemarks)<td class="rc-remark">{{ $subjectRemarks[$result->id] ?? '' }}</td>@endif</tr>@endforeach
    </tbody></table></section>
    <section class="oak-section"><h2>Comments</h2><div class="oak-two-col">@if($card->class_teacher_comment)<div><h3>Form teacher's comment</h3><p>{{ $card->class_teacher_comment }}</p></div>@endif @if($principalComment)<div><h3>{{ $headCommentLabel }}'s comment</h3><p>{{ $principalComment }}</p></div>@endif @if($proprietorComment)<div><h3>Proprietor's comment</h3><p>{{ $proprietorComment }}</p></div>@endif</div>@if($card->next_term_begins)<p class="rc-foot">Next term begins {{ $card->next_term_begins->format('j M Y') }}</p>@endif</section>
@endif
@endsection
