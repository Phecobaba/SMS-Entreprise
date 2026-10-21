# Student design reference

Design source of truth for the **Student** role. These HTML files are *references, not code to paste*.
Build shared Blade partials once; map every colour to the tokens in `docs/design-system.md` (plus the
amendments in `DESIGN_AMENDMENTS.md`). Open any file in a browser; resize to see phone behaviour
(breakpoint 767px, same as `app.css`). The dark **State** bar at the bottom of each file is a design-only
toggle for showing locked/empty/error states. Do not build it.

Read in this order: `CODEX_BRIEF.md` → `DESIGN_AMENDMENTS.md` → `screens/components.html` → the screen + its spec.

## Status

| # | Screen | File | Spec | Status |
|---|---|---|---|---|
| 0 | Component sheet | `screens/components.html` | — | Built by Codex (batch 1) |
| 1 | Dashboard (+ ticker, locked, empty) | `screens/dashboard.html` | `specs/dashboard.md` | Built by Codex (batch 1); **re-issued for attendance sessions: ready** |
| 2 | Exams list (+ start-exam sheet) | `screens/exams.html` | `specs/exams.md` | Built by Codex (batch 1) |
| 3 | Exam taking | `screens/exam-taking.html` | `specs/exam-taking.md` | Built by Codex (batch 1) |
| 4 | Exam result (6 release variants) | `screens/exam-result.html` | `specs/exam-result.md` | **Batch 2: ready** |
| 5 | Results list | `screens/results.html` | `specs/results.md` | **Batch 2: ready** |
| 6 | Report card | `screens/report-card.html` | `specs/report-card.md` | **Batch 2: ready** (needs your privacy decision) |
| 7 | Attendance (morning and afternoon) | `screens/attendance.html` | `specs/attendance.md` | **Batch 2: ready**; **re-issued for attendance sessions: ready** |
| 8 | Assignments | `screens/assignments.html` | `specs/assignments.md` | **Batch 2: ready** |
| 9 | Fees | `screens/fees.html` | `specs/fees.md` | **Batch 2: ready** |
| 10 | E-Notes | `screens/enotes.html` | `specs/enotes.md` | **Batch 2: ready** (new access path, test required) |
| 11 | Notifications | `screens/notifications.html` | `specs/notifications.md` | **Batch 2: ready** |
| 12 | Profile | `screens/profile.html` | `specs/profile.md` | **Batch 2: ready** (password change optional) |
| 13 | Login (all roles) | `screens/login.html` | `specs/login.md` | **Batch 2: ready** |

| 14 | Ward bar + switcher (every parent page) | `screens/parent-dashboard.html`, `components.html` | `specs/parent-ward.md` | **Batch 3: ready** |
| 15 | Parent dashboard (2 children, 1 child, locked, empty, no child linked) | `screens/parent-dashboard.html` | `specs/parent-dashboard.md` | **Batch 3: ready**; **re-issued for attendance sessions: ready** |
| 16 | Parent results list | `screens/parent-results.html` | `specs/parent-results.md` | **Batch 3: ready** |
| 17 | Parent report card (Principal / Head teacher label) | `screens/parent-report-card.html` | `specs/parent-report-card.md` | **Batch 3: ready** |
| 18 | Parent attendance (morning and afternoon) | `screens/parent-attendance.html` | `specs/parent-attendance.md` | **Batch 3: ready**; **re-issued for attendance sessions: ready** |
| 19 | Parent assignments | `screens/parent-assignments.html` | `specs/parent-assignments.md` | **Batch 3: ready** |
| 20 | Parent fees (line items, part-payments, receipts) | `screens/parent-fees.html` | `specs/parent-fees.md` | **Batch 3: ready** (line items, part-payments and the manual-payment record are NEW data; includes Awaiting approval) |
| 21 | Parent notifications | `screens/parent-notifications.html` | `specs/parent-notifications.md` | **Batch 3: ready** (child named in the text; sender change, no schema change) |
| 22 | Parent profile | `screens/parent-profile.html` | `specs/parent-profile.md` | **Batch 3: ready** |

**Batch 2 = rows 4–13. Batch 3 = rows 14–22 (Parent) plus three re-issued student files.** Rows 0–3 are already built; do not redo them. New CSS: batch 2 in `shared/oak-student-batch2.css`, batch 3 (ward bar, fees v2) in `shared/oak-parent.css`.

## Navigation (student)

Desktop sidebar (150px): Dashboard, Exams, Results, Assignments, Attendance, Fees, E-Notes, then My profile and Sign out
pinned at the bottom. Phone: Home, Exams, Results, Work + **More** (Attendance, Fees, E-Notes, Notifications, My profile, Sign out).
Notifications is also the bell in the header / dashboard heading. See `DESIGN_AMENDMENTS.md` §3 for what this changes in `AppNavigation`.

## Folder map

```
docs/design/student/
  INDEX.md  CODEX_BRIEF.md  DESIGN_AMENDMENTS.md
  shared/   tokens.css  oak-student.css  oak-exam.css  oak-student-batch2.css  oak-parent.css  oak-attendance.css
  screens/  *.html (standalone, inline CSS + inline SVG, no CDN)
  specs/    one .md per screen
```

`shared/oak-student.css` and `oak-exam.css` are the canonical styles every screen inlines. Class names reuse the app's
existing `oak-*` vocabulary (`oak-app-shell`, `oak-sidebar`, `oak-metric`, `oak-status-pill`, …); new ones are listed in
`DESIGN_AMENDMENTS.md` §2. Move them into `resources/css/app.css` rather than creating a second stylesheet.


## Batch 3 changes to existing student screens (re-issued)

| File | What changed | Spec |
|---|---|---|
| `screens/fees.html` | Expandable invoices with line items, part-payments, receipts; "visit the bursar" line; Pay sheet names the amount; **Awaiting approval** state | `specs/fees.md` (update section) |
| `screens/report-card.html` | Second comment label follows the section's head title; new "Primary: Head teacher" state; labels read "Form teacher's / Principal's / Proprietor's comment" | `specs/report-card.md` (update section) |
| `screens/login.html` | Field is **Email or username** | `specs/login.md` (update section) |

Parent navigation: desktop sidebar Dashboard, Results, Attendance, Fees, Assignments (+ My profile, Sign out). Phone tabs: Home, Results, Attendance, Fees + More (Assignments, Notifications, My profile, Sign out).
In the design files, switching the child only changes the labels (name, class, section, head title); the sample data stays Jane's.
Parent screens not designed (by brief): exams, e-notes.

## Decisions (owner, 2026-10-10)

- The **"Fees due" pill stays** in the ward switcher: status only, own children only. It is state-driven (shown when a payment is due, hidden when paid, confirming or awaiting approval).
- **Parents and students see "Awaiting approval"** once staff have recorded a manual payment (dashed pill, clock icon, "The school has recorded your bank payment and is confirming it"). **Pay is hidden on that invoice: proposal, confirm.**
- **Notifications name the child in the text** ("For Jane: ..."). No child-tag pill. Parent copy only; the student's copy is unchanged.
- **No parent phone number.** **No all-children assignments view.** **Fees remain a single-child view.**
- Pagers appear **only when there is more than one page** (no "Page 1 of 1" with an enabled Next).

## Still open (do not decide)

- Whether heads see a Staff list scoped to their section.
- Opened by this review, recorded in `specs/parent-fees.md`: what a parent sees if staff reject a recorded payment; whether a part-covering manual payment hides Pay for the remaining items.
- Deep links (`url`) on notifications.

## Attendance sessions (re-issued 2026-10-12)
Attendance is taken twice a day. Re-issued: `dashboard`, `parent-dashboard`, `attendance`, `parent-attendance` (rows 1, 7, 15, 18 above). New CSS: `shared/oak-attendance.css` only. Decisions and tests: `../ATTENDANCE_TWICE_DAILY.md` and `../admin/specs/attendance-sessions.md`. Excused sessions are excluded from the percentage (this closes the earlier open question).
