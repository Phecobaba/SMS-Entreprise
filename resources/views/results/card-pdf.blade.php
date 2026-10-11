<!doctype html>
<html lang="en"><head><meta charset="utf-8"><style>
@page { size: A4 portrait; margin: 10mm 12mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #1F3638; background: #fff; margin: 0; }
.header { text-align: center; border-bottom: 2px solid #2E4F56; padding-bottom: 5mm; height: 25mm; }
.logo { float: left; width: 22mm; height: 22mm; object-fit: contain; }
h1 { font-family: Georgia, 'Times New Roman', serif; color: #2E4F56; font-size: 15pt; margin: 0 0 1mm; } h2 { font-size: 10pt; margin: 2mm 0; }
p { margin: 1mm 0; } .meta { margin: 3mm 0; } .meta td { padding: 1mm 2mm; }
table { width: 100%; border-collapse: collapse; } .scores th, .scores td { border-bottom: 1px solid #EFE9DA; padding: 1mm 1.5mm; }
.scores th { background: #F1EAD8; color: #2E4F56; } .scores td:nth-child(n+2) { text-align: center; }
.comments { margin-top: 3mm; } .comments td { border-bottom: 1px solid #DDD3BA; padding: 2mm; vertical-align: top; height: 10mm; }
.footer { margin-top: 3mm; font-size: 7pt; color: #5B6B6A; }
</style></head><body>
<div class="header">@if($profile['logo_data_uri'] ?? null)<img class="logo" src="{{ $profile['logo_data_uri'] }}" alt="School logo">@endif
<h1>{{ $profile['name'] ?? 'School' }}</h1><p>{{ $profile['motto'] ?? '' }}</p><p>{{ $profile['address'] ?? '' }}</p><h2>Student Report Card</h2></div>
<table class="meta"><tr><td><strong>Student:</strong> {{ $studentName }}</td><td><strong>Term:</strong> {{ $term }}</td></tr><tr><td><strong>Subjects approved:</strong> {{ $results->count() }}</td><td><strong>Position:</strong> {{ collect($broadsheet)->firstWhere('student_id', $card->student_id)['position'] ?? '—' }}</td></tr></table>
<table class="scores"><thead><tr><th>Subject</th><th>Components</th><th>Total</th><th>%</th><th>Grade</th>@if($showSubjectRemarks)<th>Remark</th>@endif</tr></thead><tbody>
@foreach($results as $result)<tr><td>{{ $subjects[$result->subject_id] ?? $result->subject_id }}</td><td>@foreach($result->items as $item){{ $item->component->name }} {{ $item->score }}@if(! $loop->last) / @endif @endforeach</td><td>{{ $result->total_score }}</td><td>{{ $result->percentage }}</td><td>{{ $result->grade }}</td>@if($showSubjectRemarks)<td>{{ $subjectRemarks[$result->id] ?? '' }}</td>@endif</tr>@endforeach
</tbody></table>
<table class="comments"><tr><td style="width:30%"><strong>Form Teacher's Comment</strong></td><td>{{ $card->class_teacher_comment }}</td></tr><tr><td><strong>{{ $headCommentLabel }}'s Comment</strong></td><td>{{ $principalComment }}</td></tr><tr><td><strong>Proprietor's Comment</strong></td><td>{{ $proprietorComment }}</td></tr></table>
<div class="footer">@if($card->next_term_begins)Next term begins {{ $card->next_term_begins->format('d M Y') }} · @endif Issued from approved, locked results. Generated {{ now()->format('d M Y') }}.</div>
</body></html>
