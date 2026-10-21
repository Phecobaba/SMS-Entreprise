<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard · Oakfield</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@include('core._shell-start')
@if($overview['kind'] !== 'bursar')@include('core._ticker', ['notifications' => $notifications])@endif
<main class="oak-dashboard">
    @php
        $hour = (int) now('Africa/Lagos')->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    @endphp
    @include('core.dashboards.heading')
    @if($overview['kind'] === 'admin')
        @include('core.dashboards.admin')
    @elseif($overview['kind'] === 'head')
        @include('core.dashboards.head')
    @elseif($overview['kind'] === 'head_locked')
        <section class="oak-locked"><x-icon name="lock" /><h3>You haven't been assigned to a section yet</h3><p>Ask the Admin to assign your account to a Section. Dashboard figures stay hidden until then.</p></section>
    @elseif($overview['kind'] === 'bursar')
        @include('core.dashboards.bursar')
    @elseif($overview['kind'] === 'teacher')
        <div class="oak-dashboard-actions oak-teacher-actions">
            <a class="oak-action oak-action-primary" href="{{ route('attendance.index') }}" wire:navigate><span aria-hidden="true">✓</span>Mark attendance</a>
            <a class="oak-action oak-action-secondary" href="{{ route('results.index') }}" wire:navigate><span aria-hidden="true">▤</span>Enter scores</a>
        </div>
        <div class="oak-metric-grid oak-metric-grid-three">
            <div class="oak-metric"><span>Students</span><strong>{{ $overview['students'] }}</strong></div>
            <div class="oak-metric"><span>Attendance</span><strong class="oak-metric-success">M {{ ($overview['attendance']['Morning'] ?? null) === null ? '—' : $overview['attendance']['Morning'].'%' }} · A {{ ($overview['attendance']['Afternoon'] ?? null) === null ? '—' : $overview['attendance']['Afternoon'].'%' }}</strong></div>
            <div class="oak-metric"><span>Pending</span><strong class="oak-metric-accent">{{ $overview['pending'] }}</strong></div>
        </div>
        <section class="oak-dashboard-section"><h2 class="oak-roster-heading">Class roster</h2>
            @if($overview['roster'])<div class="oak-table-scroll"><table class="oak-roster"><thead><tr><th>Student</th><th>Status</th></tr></thead><tbody>
                @foreach($overview['roster'] as $row)<tr><td>{{ $row['name'] }}</td><td><span class="oak-status-pill {{ $row['status'] === 'Present' ? 'oak-status-success' : (in_array($row['status'], ['Absent']) ? 'oak-status-danger' : 'oak-status-warning') }}">{{ $row['status'] }}</span></td></tr>@endforeach
            </tbody></table></div>@else<p class="oak-empty">No students are assigned to your form-teacher class yet.</p>@endif
        </section>
    @elseif($overview['kind'] === 'student')
        @if($overview['restricted'] ?? false)
            @include('portals._restricted-dashboard')
        @else
        @php
            $money = fn (int $kobo) => '₦'.number_format($kobo / 100, $kobo % 100 === 0 ? 0 : 2);
            $examWorkflow = app(\App\Modules\CBT\Services\ExamWorkflow::class);
            $openExam = $overview['exams']->first(fn ($exam) => in_array($examWorkflow->displayStatus($exam), ['Open now', 'Resume'], true));
            $unpaidTuition = $overview['payments']['latest_unpaid_tuition'];
        @endphp
        <div class="oak-student-alerts">
            @if($overview['attendance_locked'])
                <div class="oak-notice oak-notice-warning oak-notice-action" role="status"><x-icon name="lock" /><div class="oak-grow"><strong>Results and attendance are locked.</strong><br>They open once the latest tuition invoice is paid. Ask a parent or guardian to pay, or pay it yourself.</div>@if($unpaidTuition)<a class="oak-btn oak-btn-primary" href="{{ route('payments.mine.index') }}" wire:navigate>View fees</a>@endif</div>
            @endif
            @if($openExam)
                @php($openStatus = $examWorkflow->displayStatus($openExam))
                <div class="oak-banner"><div class="oak-banner-main"><div class="oak-banner-icon"><x-icon name="device-laptop" /></div><div><h2>{{ $openExam->title }} {{ $openStatus === 'Resume' ? 'is in progress' : 'is open' }}</h2><p>{{ $openExam->questions_count }} {{ \Illuminate\Support\Str::plural('question', $openExam->questions_count) }} · {{ $openExam->duration }} minutes @if($openExam->closes_at)· closes {{ $openExam->closes_at->timezone('Africa/Lagos')->calendar() }}@endif</p></div></div><button class="oak-btn oak-btn-primary" type="button" data-open-sheet="dashboard-start-sheet"><x-icon name="device-laptop" />{{ $openStatus === 'Resume' ? 'Resume exam' : 'Start exam' }}</button></div>
            @endif
        </div>
        <div class="oak-metric-grid oak-metric-grid-three">
            <div class="oak-metric"><span>Attendance</span>@if($overview['attendance_locked'])<strong class="oak-metric-locked"><x-icon name="lock" />Locked</strong>@else<strong class="oak-metric-success">{{ $overview['attendance']['percentage'] === null ? '—' : $overview['attendance']['percentage'].'%' }}</strong>@if($overview['attendance']['today'])<small>Today: morning {{ strtolower($overview['attendance']['today']['sessions']['Morning']) }}, afternoon {{ strtolower($overview['attendance']['today']['sessions']['Afternoon']) }}</small>@endif @endif</div>
            <div class="oak-metric"><span>Assignments due</span><strong class="{{ $overview['assignments']['pending'] ? 'oak-metric-accent' : '' }}">{{ $overview['assignments']['pending'] }}</strong></div>
            <div class="oak-metric"><span>Fees outstanding</span><strong class="{{ $overview['payments']['outstanding_kobo'] ? 'oak-metric-accent' : 'oak-metric-success' }}">{{ $money($overview['payments']['outstanding_kobo']) }}</strong></div>
        </div>
        <section class="oak-section"><div class="oak-section-head"><h2>My exams</h2><a class="oak-link" href="{{ route('cbt.mine') }}" wire:navigate>All exams</a></div>
            @if($overview['exams']->isNotEmpty())<table class="oak-table oak-stack"><thead><tr><th>Exam</th><th>When</th><th>Status</th></tr></thead><tbody>
                @foreach($overview['exams'] as $exam)@php($status = $examWorkflow->displayStatus($exam))<tr><td>{{ $exam->title }}</td><td>{{ $exam->closes_at ? 'Closes '.$exam->closes_at->timezone('Africa/Lagos')->format('D j M, g:i a') : ($exam->scheduled_at ? $exam->scheduled_at->timezone('Africa/Lagos')->format('D j M, g:i a') : 'Available now') }}</td><td>@if($status === 'Finished' && $exam->show_score)<span class="oak-exam-score">Score {{ $exam->submissions->first()->raw_score }} / {{ $exam->submissions->first()->max_score }}</span>@else<span class="oak-status-pill {{ match($status) {'Open now' => 'oak-status-success', 'Resume', 'Waiting for results' => 'oak-status-warning', default => 'oak-status-neutral'} }}">{{ $status === 'Waiting for results' ? 'Result pending' : $status }}</span>@endif</td></tr>@endforeach
            </tbody></table>@else<div class="oak-empty"><x-icon name="device-laptop" /><strong>No exams yet</strong>When a teacher publishes an exam for your class, it shows up here.</div>@endif
        </section>
        <section id="results" class="oak-section"><div class="oak-section-head"><h2>Report cards</h2><a class="oak-link" href="{{ route('results.mine.index') }}" wire:navigate>All results</a></div>
            @if($overview['results'])<table class="oak-table oak-stack"><thead><tr><th>Term</th><th>Compiled</th><th>Report card</th></tr></thead><tbody>@foreach($overview['results'] as $result)<tr><td>{{ $result['term'] }}</td><td>{{ $result['compiled_at']?->format('j M Y') ?? '—' }}</td><td>@if($result['available'])<a class="oak-link" href="{{ route('results.mine.show', $result['id']) }}" wire:navigate>View result</a>@else<span class="oak-status-pill oak-status-warning">Payment required</span>@endif</td></tr>@endforeach</tbody></table>@else<div class="oak-empty"><x-icon name="report" /><strong>No report cards yet</strong>Your report card appears here after your teachers finish and the principal approves it.</div>@endif
        </section>
        <div class="oak-two-col oak-section">
            <section id="assignments"><div class="oak-section-head"><h2>Assignments</h2><a class="oak-link" href="{{ route('assignments.mine') }}" wire:navigate>See all</a></div>@forelse($overview['assignments']['recent']->take(3) as $submission)<div class="oak-list-row"><span>{{ $submission->assignment?->title ?? 'Assignment' }}<br><span class="oak-muted">Due {{ $submission->assignment?->due_date?->format('D j M') ?? 'not set' }}</span></span>@include('core._status-pill', ['status' => $submission->status])</div>@empty<div class="oak-empty"><x-icon name="clipboard-list" /><strong>Nothing due</strong>New assignments from your teachers appear here.</div>@endforelse</section>
            <section id="enotes"><div class="oak-section-head"><h2>New e-notes</h2><a class="oak-link" href="{{ route('enotes.mine') }}" wire:navigate>See all</a></div>@forelse($overview['enotes'] as $note)<div class="oak-list-row"><span>{{ $note->title }}<br><span class="oak-muted">{{ $note->subject?->name ?? 'Class note' }}</span></span><span class="oak-muted">{{ $note->created_at->diffForHumans(short: true) }}</span></div>@empty<div class="oak-empty"><x-icon name="notebook" /><strong>No notes yet</strong>Notes your teachers upload for your class appear here.</div>@endforelse</section>
        </div>
        <span id="attendance" hidden></span><span id="payments" hidden></span><span id="profile" hidden></span>
        @if($openExam)@include('cbt._start-sheet', ['exam' => $openExam, 'status' => $openStatus, 'sheetId' => 'dashboard-start-sheet'])@endif
        @endif
    @elseif($overview['kind'] === 'parent')
        @if($overview['restricted'] ?? false)
            @include('portals._restricted-dashboard')
        @else
            @include('portals._parent-dashboard')
        @endif
    @elseif($overview['kind'] === 'parent_empty')
        @include('portals._empty')
    @else
        <div class="oak-dashboard-actions oak-general-actions">
            @foreach($dashboardLinks as $link)@if($link['label'] !== 'Dashboard')<a class="oak-action oak-action-secondary" href="{{ $link['url'] }}" wire:navigate>{{ $link['label'] }}</a>@endif @endforeach
        </div>
    @endif
</main>
@include('core._shell-end')
@livewireScripts
</body>
</html>
