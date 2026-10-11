@php
    $sheetId = $sheetId ?? 'start-exam-'.$exam->id;
    $isResume = ($status ?? null) === 'Resume';
@endphp
<div class="oak-scrim" id="{{ $sheetId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $sheetId }}-title">
    <form class="oak-sheet" method="post" action="{{ route('cbt.start', $exam) }}">
        @csrf
        <h2 id="{{ $sheetId }}-title">{{ $isResume ? 'Resume' : 'Start' }} {{ $exam->title }}?</h2>
        <p class="oak-sheet-sub">{{ $isResume ? 'The clock kept running while you were away.' : "Read this first. You can't pause once you begin." }}</p>
        <ul class="oak-rules">
            <li><x-icon name="clock" /><span><b>{{ $exam->duration }} minutes</b>@isset($exam->questions_count) for <b>{{ $exam->questions_count }} {{ \Illuminate\Support\Str::plural('question', $exam->questions_count) }}</b>@endisset. The clock starts when you press Start.</span></li>
            <li><x-icon name="device-floppy" /><span>Your answers save as you go. If time runs out, we submit what you have.</span></li>
            <li><x-icon name="wifi-off" /><span>If your connection drops, keep answering. Your time keeps running.</span></li>
            <li><x-icon name="lock" /><span>You can take this exam once. Submitted answers cannot be changed.</span></li>
        </ul>
        <div class="oak-sheet-actions"><button class="oak-btn oak-btn-secondary" type="button" data-close-sheet>Not yet</button><button class="oak-btn oak-btn-primary" type="submit">{{ $isResume ? 'Resume exam' : 'Start exam' }}</button></div>
    </form>
</div>
