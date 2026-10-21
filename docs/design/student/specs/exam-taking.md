# Spec: Exam taking — `screens/exam-taking.html`

Focus mode: **no sidebar, no tab bar, no ticker, no bell.** There is deliberately no exit link; closing the page does not pause the clock.
States in the toolbar: Normal, Under 5 min, Under 1 min, Connection lost, Resumed, Submit review, Time's up.

## What exists today
- `ExamParticipantController::sit` loads all questions with options into `cbt/sit.blade.php`: one long form posting `answers[questionId]=optionId` to `cbt.submit`.
  A client countdown from `data-deadline` auto-submits at zero.
- `ExamWorkflow::submit` accepts the answers **only** if now ≤ `started_at + duration + 30s`; after that it ignores posted answers and scores only already-saved `exam_answers`.
- **Nothing saves answers before submit.** A refresh, crash or dropped connection loses every answer, and a late submit scores zero.

## What the design needs (this is real build work, not styling)
1. **Autosave endpoint** `POST /my-exams/attempts/{submission}/answers` (JSON: `question_id`, `option_id` or null, `flagged`). Same validation and `assertOwner` as `submit`,
   rejects after the grace window, returns 204. The page sends on every change, retries with backoff, and keeps a copy in `localStorage` so "Connection lost" can promise nothing is lost.
   The indicator shows Saving… / Answers saved / Saved on this device.
2. **Resume**: `sit` prefills saved answers and flags and passes `remaining_seconds` computed **on the server**. The timer counts down from that value using `performance.now()` deltas, not `Date.now()`, so a wrong device clock cannot help or hurt.
   If the attempt is already expired when `sit` loads, auto-submit server-side and redirect.
3. **Flags**: migration adds `exam_answers.flagged boolean default false` and makes `selected_option_id` nullable (a flagged-but-unanswered question needs a row).
   Update `docs/schema.md` and keep the composite foreign key. Flags never affect scoring.
4. **One question at a time** client-side (all questions are already in the page, which is right for flaky connections). Keep the real `<form>` and radio inputs so no-JS and the existing `submit` route still work.
5. **Submit review** (sheet): counts Answered / Not answered / Check later are **independent** (a question can be answered and checked). Numbers are buttons that jump to the question. Posts the same form to `cbt.submit`.
6. **Time's up**: at zero show the overlay and submit the form; the server remains the authority.
7. **Timer announcements**: `role="timer"` is not read every second; a polite live region announces 10, 5 and 1 minute marks. Warning colour under 5 min, danger under 1 min (never colour alone: the banner also appears).
8. **Never send `is_correct` to the browser.** Today's view doesn't; keep it that way when serialising questions.
9. Candidates sitting entrance exams get the same screen; header shows the candidate's name and no class.

## Layout
Desktop: question card + 260px navigator panel. Phone: bottom bar (previous · grid sheet · next, with "Review" on the last question); navigator opens as a bottom sheet.
Answer rows are real radio inputs, ≥ 56px tall; letters A–D.
