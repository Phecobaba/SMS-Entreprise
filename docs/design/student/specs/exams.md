# Spec: Exams list + start sheet — `screens/exams.html`

States: **Normal**, **Attempt in progress**, **Just submitted**, **No exams**.

## What exists today
- Route `cbt.mine` → `ExamParticipantController::index` → `ExamWorkflow::availableFor()` (Published exams for the student's class, paginated 20; candidates get entrance exams).
- `resources/views/cbt/participant-index.blade.php`: flat list, one "Start or resume" POST button per exam, `session('status')` flash.
- `POST cbt.start` creates the attempt (`started_at = now()`) and redirects to `cbt.sit`, or to `cbt.result`/`cbt.mine` if already submitted.

## What the design changes
1. **Sections** by status, derived per exam from the student's submission:
   Open now (no submission, scheduled time passed or null, before `closes_at`) · Resume (submission In Progress, show time left) ·
   Coming up (`scheduled_at` in the future) · Waiting for results (Submitted, `!isReleased`) · Finished (Submitted + released; show "Score x / y" only if `show_score`, else "View result") ·
   **Not taken** (`closes_at` passed, no submission).
2. **Start sheet** replaces the one-tap button: a confirm step with the rules, and a form that POSTs to `cbt.start`. "Resume exam" opens the same sheet with a line about time left.
   Candidates (entrance exams) use the same page; hide class/subject meta for them.
3. **Flash**: `session('status')` renders as `oak-notice-success` ("Exam submitted…").
4. Pagination via `oak-pager`. Eager-load submissions for the whole page in one query (no N+1).

## New data (agreed)
- `exams.closes_at` nullable datetime. `ExamWorkflow::start()` must reject after it (server-side), and the form builder should let staff set it.
  If null, the exam stays open until staff close it.

## Notes
- Device-side time left on Resume is a display; the server decides the deadline (`started_at + duration`).
- Do not show correct answers or scores here unless the exam's release flags allow it (reuse `isReleased`/`show_score`).
