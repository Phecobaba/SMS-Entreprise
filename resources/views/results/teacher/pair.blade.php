<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $pairLabel }}</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="teacher-results-page">
@include('core._shell-start')
<main class="tr-workspace">
    <nav class="tr-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('results.index') }}">My classes and subjects</a><span>/</span><span>{{ $pairLabel }}</span></nav>
    <div class="tr-eyebrow">Assigned subject + class{{ $term ? ' · '.$termLabel : '' }}</div>
    <h1 class="tr-title">{{ $pairLabel }}</h1>
    @if($errors->any())<div class="tr-notice tr-notice-error" role="alert">{{ $errors->first() }}</div>@endif

    @if(!$term)
        <div class="tr-empty"><h2>No current term is configured</h2><p>Score entry is unavailable until the school office configures the current term.</p></div>
    @else
        <section class="tr-section">
            <div class="tr-section-head"><h2>{{ $learnersTerm }} · {{ $totalPupils }}</h2><div class="tr-pill-row"><span class="tr-pill tr-pill-draft">{{ $counts['Draft'] ?? 0 }} draft</span><span class="tr-pill tr-pill-submitted">{{ $counts['Submitted'] ?? 0 }} submitted</span><span class="tr-pill tr-pill-approved">{{ $counts['Approved'] ?? 0 }} approved</span></div></div>
            <form method="get" class="tr-filters">
                <label>Arm<select name="arm"><option value="">All arms</option>@foreach($arms as $arm)<option value="{{ $arm->id }}" @selected((string)request('arm') === (string)$arm->id)>{{ $arm->name }}</option>@endforeach</select></label>
                <label>Search {{ strtolower($learnerTerm) }}<input name="search" value="{{ request('search') }}" placeholder="Name or registration number"></label>
                <button class="tr-btn tr-btn-secondary">Filter</button>
            </form>

            @if($students->isEmpty())
                <div class="tr-empty"><h2>No {{ strtolower($learnersTerm) }} in this class yet</h2><p>The class has no active {{ strtolower($learnersTerm) }} matching this view. Your subject assignment is still visible.</p></div>
            @else
                <div class="tr-table-wrap"><table class="tr-table"><thead><tr><th>{{ $learnerTerm }}</th><th>Arm</th><th>Registration number</th><th>Status</th><th><span class="sr-only">Action</span></th></tr></thead><tbody>
                @foreach($students as $student)
                    @php($result = $resultsByStudent->get($student->id))
                    @php($status = $result?->status ?? 'Not started')
                    <tr><td data-label="{{ $learnerTerm }}"><strong>{{ $student->user?->name ?? $learnerTerm.' #'.$student->id }}</strong></td><td data-label="Arm">{{ $student->classArm?->name }}</td><td data-label="Registration number">{{ $student->registration_number ?: 'Not issued' }}</td><td data-label="Status"><span class="tr-pill tr-status-{{ Str::slug($status) }}">{{ $status }}</span>@if($result?->rejection_reason)<small class="block">Correction requested: {{ $result->rejection_reason }}</small>@endif</td><td><a class="tr-btn" href="{{ route('results.teacher.entry', [$assignment->class_id, $assignment->subject_id, $student->id]) }}">{{ !$result ? 'Enter scores' : (in_array($status, ['Draft', 'Rejected'], true) ? 'Continue scores' : 'View scores') }}</a></td></tr>
                @endforeach
                </tbody></table></div>
                <div class="tr-pagination">{{ $students->links() }}</div>
            @endif
        </section>

        @if($totalPupils > 0)
            <section class="tr-section tr-upload-card" aria-labelledby="score-upload-title">
                <div class="tr-section-head"><div><h2 id="score-upload-title">Upload scores for this subject</h2><p>{{ $pairLabel }} · {{ $termLabel }} only</p></div></div>
                <ol class="tr-upload-steps">
                    <li>Download the roster template for this subject, class and term.</li>
                    <li>Enter every component score for a {{ strtolower($learnerTerm) }}, or leave all their score cells blank.</li>
                    <li>Upload the CSV and review every row before applying it.</li>
                </ol>
                <div class="tr-upload-actions"><a class="tr-btn" href="{{ route('results.teacher.csv.template', [$assignment->class_id, $assignment->subject_id]) }}">Download template</a></div>
                <form method="post" enctype="multipart/form-data" action="{{ route('results.teacher.csv.preview', [$assignment->class_id, $assignment->subject_id]) }}" class="tr-upload-form">
                    @csrf
                    <label for="score-csv">Completed CSV file</label>
                    <input id="score-csv" name="file" type="file" accept=".csv,text/csv" required>
                    <small>CSV only, up to 1 MB. Nothing is saved until you review the preview and press Apply.</small>
                    @error('file')<p class="tr-field-error">{{ $message }}</p>@enderror
                    <button class="tr-btn" type="submit">Upload and preview</button>
                </form>

                @isset($uploadPreview)
                    <div class="tr-upload-preview" aria-live="polite">
                        <h3>Upload preview</h3>
                        <div class="tr-upload-counts"><span><strong>{{ $uploadPreview['ready_count'] }}</strong> ready</span><span><strong>{{ $uploadPreview['skipped_count'] }}</strong> blank rows skipped</span><span><strong>{{ $uploadPreview['error_count'] }}</strong> errors</span></div>
                        @if($uploadPreview['errors'])
                            <div class="tr-upload-errors" role="alert"><h4>Fix these errors and upload the file again</h4><ul>@foreach($uploadPreview['errors'] as $error)<li>{{ $error['message'] }}</li>@endforeach</ul></div>
                        @endif
                        @if($uploadPreview['rows'])
                            <div class="tr-table-wrap"><table class="tr-table tr-preview-table"><thead><tr><th>{{ $learnerTerm }}</th><th>Registration number</th><th>Scores</th></tr></thead><tbody>
                            @foreach($uploadPreview['rows'] as $row)<tr><td data-label="{{ $learnerTerm }}"><strong>{{ $row['student_name'] }}</strong></td><td data-label="Registration number">{{ $row['registration_number'] }}</td><td data-label="Scores">@foreach($row['scores'] as $name => $score)<span>{{ $name }}: {{ $score }}</span>@if(!$loop->last)<span aria-hidden="true"> · </span>@endif @endforeach</td></tr>@endforeach
                            </tbody></table></div>
                        @endif
                        @if($uploadPreview['error_count'] === 0 && $uploadPreview['ready_count'] > 0)
                            <form method="post" action="{{ route('results.teacher.csv.apply', [$assignment->class_id, $assignment->subject_id]) }}" class="tr-apply-form">@csrf<input type="hidden" name="upload_token" value="{{ $uploadPreview['token'] }}"><p>Apply saves {{ $uploadPreview['ready_count'] }} {{ strtolower($learnerTerm) }} draft{{ $uploadPreview['ready_count'] === 1 ? '' : 's' }}. The remaining {{ strtolower($learnersTerm) }} are untouched.</p><div class="tr-actions"><a class="tr-btn tr-btn-secondary" href="{{ route('results.teacher.pair', [$assignment->class_id, $assignment->subject_id]) }}">Cancel</a><button class="tr-btn" type="submit">Apply scores</button></div></form>
                        @endif
                    </div>
                @endisset
            </section>
        @endif
    @endif
</main>
@include('core._shell-end')
@livewireScripts
</body></html>
