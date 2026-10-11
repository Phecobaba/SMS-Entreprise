<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><title>My exams · Oakfield</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>
@php
    $pageTitle = 'My exams';
    $workflow = app(\App\Modules\CBT\Services\ExamWorkflow::class);
    $groups = collect(['Open now', 'Resume', 'Coming up', 'Waiting for results', 'Finished', 'Not taken'])
        ->mapWithKeys(fn ($status) => [$status => $exams->getCollection()->filter(fn ($exam) => $workflow->displayStatus($exam) === $status)]);
@endphp
@include('core._shell-start')
@include('core._ticker', ['notifications' => $notifications])
<main class="oak-dashboard">
    <div class="oak-dashboard-heading"><div><h1>My exams</h1><p>Published exams and your attempts</p></div><div class="oak-heading-tools"><a class="oak-bell" href="{{ route('notifications.index') }}" wire:navigate aria-label="Notifications{{ $notifications->count() ? ', '.$notifications->count().' unread' : '' }}"><x-icon name="bell" />@if($notifications->count())<b aria-hidden="true">{{ $notifications->count() }}</b>@endif</a><span class="oak-role-pill">{{ $exams->first()?->pass_mark !== null ? 'Candidate' : 'Student' }}</span></div></div>
    @if(session('status'))<div class="oak-notice oak-notice-success" role="status"><x-icon name="circle-check" /><span>{{ session('status') }}</span></div>@endif
    @if($errors->any())<div class="oak-notice oak-notice-danger" role="alert"><x-icon name="alert-triangle" /><div>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif

    @forelse($groups as $group => $rows)
        @if($rows->isNotEmpty())
        <section class="oak-section"><div class="oak-section-head"><h2>{{ $group }}</h2><span class="oak-status-pill {{ match($group) {'Open now' => 'oak-status-success', 'Resume', 'Waiting for results' => 'oak-status-warning', default => 'oak-status-neutral'} }}">{{ $rows->count() }}</span></div><div class="oak-exam-list">
            @foreach($rows as $exam)
                @php($attempt = $exam->submissions->first())
                <article class="oak-exam-row" @if($group === 'Open now') data-open-now @endif><div><h3>{{ $exam->title }}</h3><div class="oak-exam-meta"><span><x-icon name="clock" />{{ $exam->duration }} minutes</span>@if($exam->subject)<span><x-icon name="book" />{{ $exam->subject->name }}</span>@endif @if($exam->scheduled_at)<span>Opens {{ $exam->scheduled_at->timezone('Africa/Lagos')->format('D j M, g:i a') }}</span>@endif @if($exam->closes_at)<span>Closes {{ $exam->closes_at->timezone('Africa/Lagos')->format('D j M, g:i a') }}</span>@endif</div></div>
                    @if(in_array($group, ['Open now', 'Resume'], true))<button class="oak-btn oak-btn-primary" type="button" data-open-sheet="start-exam-{{ $exam->id }}">{{ $group === 'Resume' ? 'Resume exam' : 'Start exam' }}</button>
                    @elseif($group === 'Finished')@if($exam->show_score)<span class="oak-exam-score">Score {{ $attempt->raw_score }} / {{ $attempt->max_score }}</span>@else<a class="oak-link" href="{{ route('cbt.result', $attempt) }}" wire:navigate>View result</a>@endif
                    @else<span class="oak-status-pill {{ $group === 'Waiting for results' ? 'oak-status-warning' : 'oak-status-neutral' }}">{{ $group }}</span>@endif
                </article>
                @if(in_array($group, ['Open now', 'Resume'], true))@include('cbt._start-sheet', ['exam' => $exam, 'status' => $group, 'sheetId' => 'start-exam-'.$exam->id])@endif
            @endforeach
        </div></section>
        @endif
    @empty
    @endforelse
    @if($exams->isEmpty())<div class="oak-empty oak-section"><x-icon name="device-laptop" /><strong>No exams yet</strong>When an exam is published for you, it shows up here.</div>@endif
    @if($exams->hasPages())<nav class="oak-pager" aria-label="Exam pages"><span>Page {{ $exams->currentPage() }} of {{ $exams->lastPage() }}</span><span>@if($exams->onFirstPage())<span>Previous</span>@else<a class="oak-btn oak-btn-quiet" href="{{ $exams->previousPageUrl() }}" wire:navigate>Previous</a>@endif @if($exams->hasMorePages())<a class="oak-btn oak-btn-quiet" href="{{ $exams->nextPageUrl() }}" wire:navigate>Next</a>@endif</span></nav>@endif
</main>
@include('core._shell-end')
@livewireScripts
</body></html>
