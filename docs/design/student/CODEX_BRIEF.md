# Brief for Codex: build the Student role from `docs/design/student/`

You are restyling and extending an existing Laravel 12 + Blade + Livewire (`wire:navigate`) + Tailwind v4 app.
Follow `CONTRIBUTING.md` for task workflow and Definition of Done. Read `ARCHITECTURE.md` first.

## Rules
- The HTML files are **references**, not code to paste. Build shared Blade partials/components **once** (sidebar, mobile band, tab bar,
  ticker, status pill, metric card, banner/notice, sheet, table) and reuse them. Do not create a second stylesheet: move the shared CSS into `resources/css/app.css`.
- Use the existing tokens (`--color-*` in `app.css`) plus the new ones in `DESIGN_AMENDMENTS.md`.
- Match the reference at **desktop (≥1280px) and phone (390px)**. Report any deviation in `PROGRESS.md`. No horizontal overflow.
- Replace existing views; do **not** leave duplicates. Keep `@persist`, `wire:navigate`, `wire:current`, and the teal progress bar.
- Server-side rules in `ARCHITECTURE.md` §6 are unchanged. Never add a role-name check; use permissions/profile data.
- Do not build screens that are not yet in `INDEX.md` with status "Ready for review".

## Order of work (first batch)
1. **Docs housekeeping (small commit):** merge `DESIGN_AMENDMENTS.md` into `docs/design-system.md` (tokens, new components, nav, ticker);
   add the new columns below to `docs/schema.md`; add `docs/design/student/` to the `AGENTS.md` reference table; add one line to the
   Definition of Done in `CONTRIBUTING.md`: *"A UI task is not done until it matches its reference in `docs/design/` at desktop and phone widths; report deviations."*
   Log the pull-forward of student pages in `PROGRESS.md` and the decisions below in the Decisions Log.
2. **Shared shell + component partials** from `screens/components.html` (including the 44px buttons, `oak-stack` tables, sheet, toast, ticker).
   Update `_shell-start` (tab order, richer More menu, bell badge) and `AppNavigation` for the student branch.
3. **Dashboard** (`specs/dashboard.md`).
4. **Exams list + start sheet** (`specs/exams.md`) including the `closes_at` migration.
5. **Exam taking** (`specs/exam-taking.md`) including autosave, flags, and server-side remaining time.

## Trust-sensitive tests required (`ARCHITECTURE.md` §8)
Autosave rejects answers after the grace window and for another student's attempt; `start()` rejects after `closes_at`; submit still scores
from saved answers when the final POST is late; an answer for another exam's option is rejected. Everything else: manual verification.

## Schema changes agreed
`exams.closes_at` (nullable datetime) · `exam_answers.flagged` (boolean, default false) · `exam_answers.selected_option_id` nullable.

## Open (not for you to resolve silently)
Student routes for Results list, Attendance, Fees, E-Notes and Profile do not exist yet. Their designs are not done; wait for them.
Until then the sidebar and tab bar may link to the current dashboard anchors for those items.


---

# Batch 2 (rows 4–13 in `INDEX.md`)

Batch 1 (shell, dashboard, exams list, exam taking) is already built. Do not redo it. Build batch 2 in this order; each item names the spec to follow.
Follow every rule above. Move `oak-student-batch2.css` into `resources/css/app.css`; do not add a second stylesheet.

1. **Login** (`specs/login.md`): guest layout, real logo. Smallest and shared by all roles, so do it first.
2. **Fees** (`specs/fees.md`) and **Results list** (`specs/results.md`): both introduce a real student route and replace the `dashboard#…` anchors in `AppNavigation`.
   Fix `payments.verify` so a returning payer lands on `/my-fees`, not the public homepage.
3. **Report card** (`specs/report-card.md`): replace the staff view for students. **Do not show classmates' names or averages.**
   Replace the raw 403 for locked cards with the locked state.
4. **Exam result** (`specs/exam-result.md`): replace the 403 for unreleased results with the waiting states; add the student's own answer to the payload.
5. **Attendance** (`specs/attendance.md`), **Assignments** (`specs/assignments.md`), **Notifications** (`specs/notifications.md`).
6. **E-Notes** (`specs/enotes.md`): new student access path to class notes. Trust-sensitive: a feature test is required (a student can open only their own class's notes; no other class, no download by id guessing).
7. **Profile** (`specs/profile.md`): details first; change-password only if you are told to include it.

After the last item, update `AppNavigation` so every student link is a real page (no `#anchors`), and update `PROGRESS.md`.

## Tests required in batch 2 (`ARCHITECTURE.md` §8)
E-notes scoping (above) · unreleased exam result reveals nothing but the release time · locked report card and locked attendance return the locked state, not data ·
`payments.verify` never settles from the redirect alone.

## Decisions pending (do not resolve silently; log under Blockers/Open Questions)
1. Students see class broadsheet on the report card? (design: no)
2. Should Excused attendance count against the percentage? (design: unspecified; recommend no)
3. Add a `url` column to `notifications` so rows can link to their subject?
4. Students pay their own invoices? (current code: yes)


---

# Batch 3: Parent (rows 14–22) and student re-issues

Companion to `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` (the build decisions). Follow every rule above. Reuse the student views and components; the Parent role adds the ward bar and ward-scoped data.
Put `oak-parent.css` into `resources/css/app.css`.

1. **Ward selection** (`specs/parent-ward.md`): decide how the chosen child follows the parent across pages (proposal: `?student=` on every parent link, first child by default). Replace the auto-submit `<select>` with the ward bar + dialog. Validate every ward id server-side.
2. **Parent dashboard** (`specs/parent-dashboard.md`), including the no-child-linked state.
3. **Results list, report card, attendance, assignments** (`specs/parent-*.md`): ward-scoped. Share the student templates with a role flag instead of copying them. **No classmates' names or averages.** Locked states render no data.
4. **Fees v2** (`specs/parent-fees.md`, `specs/fees.md` update): ship the summary, invoice list, Pay sheet and the corrected return trip first. **Line items and part-payments depend on the Admin foundations migrations**; build them when those exist, not before. Do not invent table or column names.
5. **Notifications and profile** (`specs/parent-notifications.md`, `specs/parent-profile.md`).
6. **Student re-issues:** report-card label by section head title, login "Email or username", fees v2 (all with the NEW-data caveats in their specs).

## Tests required in batch 3 (`ARCHITECTURE.md` §8)
A parent cannot view another child's report card, attendance, assignments, invoices or notifications by changing `?student=`, a card id or an invoice id · locked states do not include scores, positions, subjects or percentages in the response body · `payments.verify` never settles from the redirect alone (already required).

## Decided by the owner (2026-10-10)
"Fees due" pill stays (status only, own children only, state-driven) · Awaiting approval shown to parents and students for a recorded manual payment, **Pay hidden on that invoice (proposal, confirm)** · notifications name the child in the text, parent copy only · no parent phone number · no all-children assignments view · fees stay a single-child view · pagers render only when there is more than one page.

## Still open (do not resolve silently)
Heads seeing a section-scoped Staff list · what a parent sees if a recorded payment is rejected · whether a partial manual payment hides Pay for the remaining items · notification deep links.


---

# Attendance sessions (morning and afternoon): student and parent screens
Build after the backend in `docs/design/admin/CODEX_BRIEF.md` ("Attendance sessions") exists. Re-issued and ready: `screens/dashboard.html`, `screens/parent-dashboard.html`, `screens/attendance.html`, `screens/parent-attendance.html`; new CSS `shared/oak-attendance.css` (move it into `resources/css/app.css`).
`portalSummary` must return the last **30 school days** with both sessions; the percentage is (Present + Late) / (Present + Late + Absent) sessions, with Excused, not-held and not-yet-marked sessions excluded. A day with no session held is not a school day. Locked states stay neutral. Tests: see the admin brief's attendance tests (percentage maths with legacy rows, 30 school days, payment gating).
