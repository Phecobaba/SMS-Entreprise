<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $attempt->exam->title }} · Oakfield</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="oak-exam-body" data-attempt="{{ $attempt->id }}" data-remaining="{{ $remainingSeconds }}" data-autosave-url="{{ route('cbt.answers.store', $attempt) }}">
@php
    $profile = app(\App\Modules\Core\Services\CurrentSchoolProfile::class)->landingPage();
    $saved = $attempt->answers->keyBy('question_id');
@endphp
<header class="ex-top"><div class="ex-id">@if($profile['logo_url'] ?? null)<span class="oak-logo-crop"><img class="oak-logo-mark" src="{{ $profile['logo_url'] }}" alt=""></span>@endif<div><h1>{{ $attempt->exam->title }}</h1><p>{{ auth()->user()->name }}</p></div></div><div class="ex-tools"><span class="ex-save" id="exam-save" role="status"><x-icon name="circle-check" /><span class="ex-save-text">Answers saved</span></span><span class="ex-timer" id="exam-timer" role="timer" aria-label="Time left"><x-icon name="clock" /><span id="exam-time">{{ intdiv($remainingSeconds, 60) }}:{{ str_pad((string) ($remainingSeconds % 60), 2, '0', STR_PAD_LEFT) }}</span></span></div></header>
<div class="ex-bar" aria-hidden="true"><i id="exam-progress"></i></div><div class="sr-only" id="exam-announce" role="status" aria-live="polite"></div>
<div class="ex-notices"><div class="oak-notice oak-notice-danger" id="exam-offline" hidden><x-icon name="wifi-off" /><div><strong>No connection.</strong> Keep answering. Your answers are kept on this device and will save when you are back online. Your time is still running, so do not close this page.</div></div><div class="oak-notice oak-notice-warning" id="exam-low" hidden><x-icon name="alert-triangle" /><div><strong>Less than 5 minutes left.</strong> Check any questions you marked, then submit.</div></div>@if($attempt->answers->isNotEmpty())<div class="oak-notice oak-notice-info" id="exam-resumed"><x-icon name="info-circle" /><div><strong>Welcome back.</strong> Your earlier answers are restored. The clock kept running while you were away.</div></div>@endif</div>
@if($errors->any())<div class="ex-notices"><div class="oak-notice oak-notice-danger" role="alert"><x-icon name="alert-triangle" /><div>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div></div>@endif
<form id="exam-form" method="post" action="{{ route('cbt.submit', $attempt) }}">@csrf
<main class="ex-main"><section><div class="ex-card">
    @foreach($questions as $question)
    @php($answer = $saved->get($question->id))
    <article class="ex-question" data-question="{{ $question->id }}" data-position="{{ $loop->index }}" data-flagged="{{ $answer?->flagged ? '1' : '0' }}"><div class="ex-qhead"><span>Question {{ $loop->iteration }} of {{ $questions->count() }} · {{ number_format((float) $question->marks, (float) $question->marks == (int) $question->marks ? 0 : 2) }} {{ \Illuminate\Support\Str::plural('mark', (float) $question->marks) }}</span><button class="ex-flag" type="button" aria-pressed="{{ $answer?->flagged ? 'true' : 'false' }}"><x-icon name="flag" /><span>{{ $answer?->flagged ? 'Marked to check later' : 'Check later' }}</span></button></div><h2 class="ex-q">{{ $question->text }}</h2><fieldset class="ex-options"><legend class="sr-only">Choose one answer</legend>
        @foreach($question->options as $option)<label class="ex-opt"><input type="radio" name="answers[{{ $question->id }}]" value="{{ $option->id }}" @checked((int) $answer?->selected_option_id === $option->id)><span class="ex-letter">{{ chr(65 + $loop->index) }}</span><span>{{ $option->text }}</span></label>@endforeach
    </fieldset></article>
    @endforeach
</div><div class="ex-nav-row"><button class="oak-btn oak-btn-secondary" id="exam-prev" type="button"><x-icon name="chevron-left" />Previous</button><button class="oak-btn oak-btn-primary" id="exam-next" type="button"><span>Next</span><x-icon name="chevron-right" /></button></div></section>
<aside class="ex-panel" aria-label="Question navigator"><h2>Questions</h2><p class="ex-count" data-counts></p><div class="ex-grid" data-grid></div><div class="ex-legend"><span><i class="a"></i>Answered</span><span><i></i>Not answered</span><span><i class="f"></i>Marked to check later</span></div><button class="oak-btn oak-btn-secondary oak-btn-block" type="button" data-review>Review and submit</button></aside></main>
<div class="ex-mobilebar"><button class="oak-btn oak-btn-secondary" id="exam-prev-mobile" type="button" aria-label="Previous question"><x-icon name="chevron-left" /></button><button class="oak-btn ex-gridbtn" type="button" data-open-sheet="exam-nav-sheet"><x-icon name="list-check" /><span id="exam-position">1 / {{ $questions->count() }}</span></button><button class="oak-btn oak-btn-primary" id="exam-next-mobile" type="button"><span>Next</span><x-icon name="chevron-right" /></button></div>
<div class="oak-scrim" id="exam-nav-sheet" role="dialog" aria-modal="true" aria-label="All questions"><div class="oak-sheet"><h2>All questions</h2><p class="oak-sheet-sub" data-counts></p><div class="ex-grid" data-grid></div><div class="ex-legend"><span><i class="a"></i>Answered</span><span><i></i>Not answered</span><span><i class="f"></i>Marked to check later</span></div><div class="oak-sheet-actions"><button class="oak-btn oak-btn-quiet" type="button" data-close-sheet>Back to question</button><button class="oak-btn oak-btn-secondary" type="button" data-review>Review and submit</button></div></div></div>
<div class="oak-scrim" id="exam-review-sheet" role="dialog" aria-modal="true" aria-labelledby="exam-review-title"><div class="oak-sheet"><h2 id="exam-review-title">Submit your exam?</h2><p class="oak-sheet-sub" id="exam-review-summary"></p><div class="oak-metric-grid oak-metric-grid-three"><div class="oak-metric"><span>Answered</span><strong class="oak-metric-success" id="review-answered">0</strong></div><div class="oak-metric"><span>Not answered</span><strong id="review-unanswered">0</strong></div><div class="oak-metric"><span>Check later</span><strong class="oak-metric-accent" id="review-flagged">0</strong></div></div><div class="ex-review-block" id="review-unanswered-block"><strong>Not answered — tap a number to go there</strong><div class="ex-chip-row" id="review-unanswered-list"></div></div><div class="ex-review-block" id="review-flagged-block"><strong>Marked to check later</strong><div class="ex-chip-row" id="review-flagged-list"></div></div><div class="oak-sheet-actions"><button class="oak-btn oak-btn-secondary" type="button" data-close-sheet>Go back</button><button class="oak-btn oak-btn-primary" type="submit">Submit exam</button></div></div></div>
</form>
<div class="ex-timeup" id="exam-timeup" role="alertdialog" aria-labelledby="exam-timeup-title"><div><x-icon name="clock" /><h2 id="exam-timeup-title">Time's up</h2><p>We are submitting your saved answers now. Keep this page open.</p></div></div>
<script>
(() => {
    const body = document.body, form = document.getElementById('exam-form');
    const questions = [...document.querySelectorAll('.ex-question')], total = questions.length;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const storageKey = `oakfield-exam-${body.dataset.attempt}`;
    let current = 0, remaining = Number(body.dataset.remaining), submitted = false;
    const initialRemaining = remaining, timerStartedAt = performance.now();
    const state = {};
    try { Object.assign(state, JSON.parse(localStorage.getItem(storageKey) || '{}')); } catch (_) {}
    questions.forEach((question) => {
        const id = question.dataset.question;
        state[id] ??= { option_id: question.querySelector('input:checked')?.value ?? null, flagged: question.dataset.flagged === '1', pending: false };
        const localOption = state[id].option_id;
        if (localOption && question.querySelector(`input[value="${CSS.escape(String(localOption))}"]`)) question.querySelector(`input[value="${CSS.escape(String(localOption))}"]`).checked = true;
        question.dataset.flagged = state[id].flagged ? '1' : '0';
    });
    const persist = () => localStorage.setItem(storageKey, JSON.stringify(state));
    const saveLabel = document.getElementById('exam-save'), offline = document.getElementById('exam-offline');
    const setSave = (text, icon = 'device-floppy') => { saveLabel.innerHTML = `<svg class="oak-i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/></svg><span class="ex-save-text">${text}</span>`; };
    const queueSave = async (id, delay = 0) => {
        state[id].pending = true; persist(); setSave('Saving…');
        if (delay) await new Promise((resolve) => setTimeout(resolve, delay));
        try {
            const response = await fetch(body.dataset.autosaveUrl, { method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf}, body: JSON.stringify({question_id: Number(id), option_id: state[id].option_id ? Number(state[id].option_id) : null, flagged: Boolean(state[id].flagged)}) });
            if (!response.ok) throw new Error(String(response.status));
            state[id].pending = false; persist(); offline.hidden = true; setSave('Answers saved');
        } catch (error) {
            offline.hidden = false; setSave('Saved on this device');
            if (!submitted && remaining > 0 && error.message !== '422' && error.message !== '403') window.setTimeout(() => queueSave(id, Math.min(8000, Math.max(1000, delay * 2))), 1000);
        }
    };
    questions.forEach((question) => {
        const id = question.dataset.question, flag = question.querySelector('.ex-flag');
        question.querySelectorAll('input[type="radio"]').forEach((input) => input.addEventListener('change', () => { state[id].option_id = input.value; queueSave(id); paint(); }));
        flag.addEventListener('click', () => { state[id].flagged = !state[id].flagged; question.dataset.flagged = state[id].flagged ? '1' : '0'; flag.setAttribute('aria-pressed', String(state[id].flagged)); flag.querySelector('span').textContent = state[id].flagged ? 'Marked to check later' : 'Check later'; queueSave(id); paint(); });
        if (state[id].pending) queueSave(id);
    });
    const counts = () => ({ answered: questions.filter((q) => q.querySelector('input:checked')).length, flagged: questions.filter((q) => state[q.dataset.question].flagged).length });
    const go = (index) => { current = Math.max(0, Math.min(total - 1, index)); render(); window.scrollTo(0, 0); };
    const cell = (question, index, sheet) => { const button = document.createElement('button'); button.type = 'button'; button.className = 'ex-cell'; button.textContent = String(index + 1); const answered = Boolean(question.querySelector('input:checked')), flagged = state[question.dataset.question].flagged; if (answered) button.dataset.answered = ''; if (flagged) button.dataset.flagged = ''; if (index === current) button.setAttribute('aria-current', 'true'); button.setAttribute('aria-label', `Question ${index + 1}, ${answered ? 'answered' : 'not answered'}${flagged ? ', marked to check later' : ''}`); button.addEventListener('click', () => { go(index); sheet?.removeAttribute('data-open'); }); return button; };
    const paint = () => { const value = counts(), text = `${value.answered} answered · ${total - value.answered} left${value.flagged ? ` · ${value.flagged} marked` : ''}`; document.querySelectorAll('[data-counts]').forEach((node) => node.textContent = text); document.querySelectorAll('[data-grid]').forEach((grid) => { grid.replaceChildren(...questions.map((question, index) => cell(question, index, grid.closest('.oak-scrim')))); }); document.getElementById('exam-progress').style.width = `${value.answered / total * 100}%`; };
    const render = () => { questions.forEach((q, i) => q.hidden = i !== current); document.getElementById('exam-position').textContent = `${current + 1} / ${total}`; const last = current === total - 1; [document.getElementById('exam-prev'), document.getElementById('exam-prev-mobile')].forEach((b) => b.disabled = current === 0); [document.getElementById('exam-next'), document.getElementById('exam-next-mobile')].forEach((b) => b.querySelector('span').textContent = last ? (b.id.endsWith('mobile') ? 'Review' : 'Review and submit') : 'Next'); paint(); };
    const chips = (host, list) => host.replaceChildren(...list.map((index) => { const button = document.createElement('button'); button.type = 'button'; button.className = 'ex-chip'; button.textContent = String(index + 1); button.addEventListener('click', () => { document.getElementById('exam-review-sheet').removeAttribute('data-open'); go(index); }); return button; }));
    const review = () => { const unanswered = [], flagged = []; questions.forEach((q, i) => { if (!q.querySelector('input:checked')) unanswered.push(i); if (state[q.dataset.question].flagged) flagged.push(i); }); document.getElementById('review-answered').textContent = total - unanswered.length; document.getElementById('review-unanswered').textContent = unanswered.length; document.getElementById('review-flagged').textContent = flagged.length; document.getElementById('review-unanswered-block').hidden = !unanswered.length; document.getElementById('review-flagged-block').hidden = !flagged.length; chips(document.getElementById('review-unanswered-list'), unanswered); chips(document.getElementById('review-flagged-list'), flagged); document.getElementById('exam-review-summary').textContent = `You cannot change answers after submitting. You have ${format(remaining)} left.${unanswered.length ? ' Unanswered questions score zero.' : ''}`; document.getElementById('exam-nav-sheet').removeAttribute('data-open'); document.getElementById('exam-review-sheet').setAttribute('data-open', ''); };
    const next = () => current === total - 1 ? review() : go(current + 1);
    document.getElementById('exam-prev').onclick = document.getElementById('exam-prev-mobile').onclick = () => go(current - 1); document.getElementById('exam-next').onclick = document.getElementById('exam-next-mobile').onclick = next; document.querySelectorAll('[data-review]').forEach((button) => button.addEventListener('click', review));
    const format = (seconds) => `${Math.floor(Math.max(0, seconds) / 60)}:${String(Math.max(0, seconds) % 60).padStart(2, '0')}`;
    const announced = new Set();
    const tick = () => { remaining = Math.max(0, initialRemaining - Math.floor((performance.now() - timerStartedAt) / 1000)); document.getElementById('exam-time').textContent = format(remaining); const timer = document.getElementById('exam-timer'), low = document.getElementById('exam-low'); timer.dataset.tone = remaining <= 60 ? 'critical' : remaining <= 300 ? 'low' : ''; low.hidden = !(remaining <= 300 && remaining > 0); [[600,'10 minutes left'],[300,'5 minutes left'],[60,'1 minute left']].forEach(([mark,text]) => { if (remaining <= mark && !announced.has(mark)) { announced.add(mark); document.getElementById('exam-announce').textContent = text; } }); if (remaining === 0 && !submitted) { submitted = true; document.getElementById('exam-timeup').setAttribute('data-open',''); localStorage.removeItem(storageKey); form.requestSubmit(); } };
    form.addEventListener('submit', () => { submitted = true; localStorage.removeItem(storageKey); });
    render(); tick(); window.setInterval(tick, 250);
})();
</script>
</body></html>
