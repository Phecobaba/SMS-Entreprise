# Admin design reference

Design source of truth for the **Admin, Head (Principal) and Bursar** roles. Companion to `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md`
(the build decisions, D1 to D15) and `docs/design/DESIGN_BRIEF_PARENT_ADMIN_FEES.md`. Teacher screens are out of scope for now.

The HTML files are **references, not code to paste**. Build shared Blade partials once and reuse them. Open a file in a browser and resize it
(breakpoint 767px). The dark **State** bar switches states for review only; do not build it. **Codex builds only rows marked Ready, and only
for the batch named in the row.** If a screen is needed and not Ready, stop and report.

## What is reused and what is new

- **Reused unchanged** (do not fork): `../student/shared/tokens.css`, `../student/shared/oak-student.css`, `../student/shared/oak-student-batch2.css`, `../student/shared/oak-attendance.css` (the attendance sessions pill and strip),
  and the student `screens/components.html` (buttons, pills, notices, tables, sheets, form fields, pager, ticker).
- **New admin-only styles:** `shared/oak-admin.css` (`ad-*` classes, plus `oak-nav-group` and `data-dense` on the shell). Move these into
  `resources/css/app.css`; do not add a second stylesheet.
- `components.html` (this folder's root) shows only the **new** patterns.
- Same rules as the student batches: 44px tap targets on phones, contrast tokens in `../student/DESIGN_AMENDMENTS.md`, naira only (never kobo), a pager
  only when there is more than one page, and every state in the State bar.

## Status

| # | Screen | File | Spec | Status |
|---|---|---|---|---|
| 0 | New admin components | `components.html` | n/a | **Batch 4: ready** |
| 1 | Shell and navigation by role | all screens | `specs/shell-and-navigation.md` | **Batch 4: ready** (built with the dashboards) |
| 2 | Admin dashboard (All / Primary / Secondary switcher) | `screens/admin-dashboard.html` | `specs/admin-dashboard.md` | **Batch 4: ready**; **re-issued for attendance sessions: ready** |
| 3 | Head dashboard (own section, no switcher) | `screens/head-dashboard.html` | `specs/head-dashboard.md` | **Batch 4: ready**; **re-issued for attendance sessions: ready** |
| 4 | Bursar dashboard | `screens/bursar-dashboard.html` | `specs/bursar-dashboard.md` | **Batch 4: ready**; the Awaiting approval and Not invoiced cards need batch 5 data, and its actions now link to the batch 5 screens |
| 5 | Sections and levels | `screens/academic-sections.html` | `specs/academic-sections.md` | **Batch 4: ready** |
| 6 | Departments | `screens/academic-departments.html` | `specs/academic-departments.md` | **Batch 4: ready** |
| 7 | Classes (Admin): Comes after, Move up/down, delete, Not arranged yet | `screens/academic-classes.html` | `specs/academic-classes.md` | **Batch 4: ready**; **re-issued 2026-10-18: ready** |
| 8 | Classes (head, own section: add, edit, move, delete) | `screens/head-classes.html` | `specs/academic-classes.md` | **Batch 4: ready**; **re-issued 2026-10-18: ready** |
| 9 | Bulk subject assignment (Admin) | `screens/bulk-subjects.html` | `specs/bulk-subjects.md` | **Batch 4: ready** (class names only changed) |
| 10 | Teacher assignments (Admin: by teacher, by class, many-subjects matrix) | `screens/teacher-assignments.html` | `specs/teacher-assignments.md` | **Batch 4: ready** (class names only changed) |
| 11 | Promotion (Admin): section filter, Awaiting admission, Graduated, Choose a class, blocked until arranged | `screens/promotion.html` | `specs/promotion.md` | **Batch 4: ready**; **re-issued 2026-10-18: ready** |
| 22 | **Attendance alerts (Admin)**, with "Session not held?" | `screens/attendance-alerts.html` | `specs/attendance-sessions.md` | **Attendance sessions: ready** |
| 23 | **Attendance alerts (head)** | `screens/head-attendance-alerts.html` | `specs/attendance-sessions.md` | **Attendance sessions: ready** |
| 24 | **Sessions & times (Admin)**: alert times per section, sessions not held | `screens/attendance-settings.html` | `specs/attendance-sessions.md` | **Attendance sessions: ready** |
| 25 | **Sessions & times (head)** | `screens/head-attendance-settings.html` | `specs/attendance-sessions.md` | **Attendance sessions: ready** |
| 26 | **Promotion (head, own section)**: Primary 6 to Awaiting admission, Secondary head view, Choose a class, blocked until arranged | `screens/head-promotion.html` | `specs/promotion.md` | **Batch 4 revision: ready** (needs Awaiting admission status, class position, section-scoped permission, all NEW) |
| 27 | **Incoming from Primary** (Secondary head and Admin): accept, not continuing, registration numbers | `screens/head-incoming.html` | `specs/incoming-admissions.md` | **Batch 4 revision: ready** |
| 28 | **Arrange your classes from first to last** (Admin, both sections) | `screens/classes-arrange.html` | `specs/classes-arrange.md` | **Batch 4 revision: ready** |
| 29 | **Arrange your classes** (head, own section) | `screens/head-arrange.html` | `specs/classes-arrange.md` | **Batch 4 revision: ready** |
| 30 | **Bulk subject assignment (head, own section)** | `screens/head-bulk-subjects.html` | `specs/bulk-subjects.md` | **Batch 4 revision: ready** |
| 31 | **Teacher assignments (head, own section)** | `screens/head-teacher-assignments.html` | `specs/teacher-assignments.md` | **Batch 4 revision: ready** |
| 12 | **Fee schedule**: fee items, scopes, Required tick | `screens/fee-schedule.html` | `specs/fee-schedule.md` | **Batch 5: ready** |
| 12b | **Generate invoices** (preview, report) | `screens/invoices-generate.html` | `specs/invoices-generate.md` | **Batch 5: ready** |
| 12c | **Not invoiced** and single invoice with extras | `screens/invoices-not-invoiced.html` | `specs/invoices-not-invoiced.md` | **Batch 5: ready** |
| 12d | **Invoice detail** (lines, adjustments, void) | `screens/invoice-detail.html` | `specs/invoice-detail.md` | **Batch 5: ready** |
| 13 | **Record a manual payment** | `screens/payment-record.html` | `specs/payment-record.md` | **Batch 5: ready** |
| 13b | **Awaiting approval** (approve or reject) | `screens/payment-approvals.html` | `specs/payment-approvals.md` | **Batch 5: ready** |
| 14 | **Part-payment** (create, void) | in `screens/invoice-detail.html` | `specs/invoice-detail.md` | **Batch 5: ready** |
| 15 | **Payment gateway settings** (Admin only) | `screens/payment-gateway.html` | `specs/payment-gateway.md` | **Batch 5: ready** |
| 15b | **"Online payment isn't set up yet"** on student and parent fees (and the Bursar invoice detail) | `student/screens/fees.html`, `student/screens/parent-fees.html`, `screens/invoice-detail.html` | `student/specs/fees.md`, `parent-fees.md` | **Batch 5: ready** (state added) |
| 32 | **Levels (Admin)**: Add (Section chosen once), edit (name only, Section read-only), delete rules | `screens/academic-levels.html` | `specs/academic-setup.md` | **Academic setup revision (2026-10-23): ready** |
| 33 | **Levels (head, own section)**: no Section field, no switcher | `screens/head-levels.html` | `specs/academic-setup.md` | **Academic setup revision (2026-10-23): ready** |
| 34 | **Subjects (Admin)**: Section tag and filter, Add (Section chosen once), edit, delete rules | `screens/academic-subjects.html` | `specs/academic-setup.md` | **Academic setup revision (2026-10-23): ready** |
| 35 | **Subjects (head, own section)** | `screens/head-subjects.html` | `specs/academic-setup.md` | **Academic setup revision (2026-10-23): ready** |
| 36 | Sessions & terms (Admin only) | `screens/academic-sessions.html` | `specs/academic-setup.md` | Ready (built; not previously listed here) |
| 16 | **Add staff**: role (never above the creator's own), **section assignment field**, one-time credentials with Print | `screens/staff-add.html` | `specs/staff-add.md` | **Batch 6: ready** (proposals flagged in the spec) |
| 16b | **Staff sign-in and access**: Admin password reset, role and Section change | `screens/staff-access.html` | `specs/staff-access.md` | **Batch 6: ready** |
| 17 | **First-login "Set your password"** (every role; also after an Admin reset) | `student/screens/set-password.html` | `student/specs/set-password.md` | **Batch 6: ready** |
| 17b | **Login re-issued**: username hint, forgot-credentials wording | `student/screens/login.html` | `student/specs/login.md` (Batch 6 update) | **Batch 6: ready** (three text lines changed) |
| 18 | **Staff Account page** (change password): Admin and Head | `screens/account.html`, `screens/head-account.html` | `specs/account.md` | **Batch 6: ready** |
| 19 | **Head: Staff list and detail** (section-scoped, no salary column, record-discipline form) | `screens/head-staff.html`, `screens/head-staff-detail.html` | `specs/head-staff.md` | **Batch 6: ready** (open questions in the spec) |
| 20 | Admin staff form: section assignment field | included in row 16 | `specs/staff-add.md` | **Batch 6: ready** (see row 16) |
| 37 | **Students list**: Import and Add buttons, registration number, Sign-in and number link | `screens/students.html` | `specs/students.md` | **Batch 6: proposal** (today's page not seen: Codex compares first) |
| 38 | **Add a student** (automatic or typed number, parent new or existing, one-time credentials) | `screens/student-add.html` | `specs/student-add.md` | **Batch 6: ready** |
| 39 | **Import students** wizard: upload, check, review, report; a bad file creates nothing | `screens/students-import.html` | `specs/students-import.md` | **Batch 6: ready** |
| 40 | **Student sign-in and registration number**: change number, reset student and parent passwords | `screens/student-access.html` | `specs/student-access.md` | **Batch 6: ready** |
| 41 | **Registration numbers** (school settings, per-Section prefix, where numbers are used) | `screens/registration-numbers.html` | `specs/registration-numbers.md` | **Batch 6: ready** (settings are NEW) |
| 42 | **Sign-in rules** (username rules on screen, checker) | `screens/sign-in-rules.html` | `specs/sign-in-rules.md` | **Batch 6: ready** (rules marked Proposal are proposals) |
| 43 | **Activity log** (read-only, tabs by module, own permission) | `screens/activity-log.html` | `specs/activity-log.md` | **Batch 6: ready** |
| 44 | **Ticker events** on every role | spec only | `specs/ticker-events.md` | **Batch 6: spec only**, needs the owner to confirm `notifications.url` |
| 45 | **"Admission requirements"** wording | n/a | `specs/admission-requirements.md` | **Not designed**: screen not seen |
| 46 | CBT CSV upload and guarded delete | n/a | n/a | **Not in Batch 6** (in the handoff plan; depends on the CBT question model, not seen) |
| 21 | Matrix with 20+ subjects (frozen class column, horizontal scroll) | `screens/teacher-assignments.html` (state "Many subjects (22)") | `specs/teacher-assignments.md` | **Batch 4: ready** (designed early) |

**Do not build rows 37 (until compared with today's page), 44, 45 or 46.** Pages in the sidebar for rows that are not Ready appear only once they are Ready and built.

## Folder map

```
docs/design/admin/
  INDEX.md  CODEX_BRIEF.md  components.html
  shared/   oak-admin.css
  screens/  admin-dashboard  head-dashboard  bursar-dashboard  academic-sections  academic-departments
            academic-levels  academic-subjects  academic-sessions  head-levels  head-subjects
            academic-classes  head-classes  bulk-subjects  teacher-assignments  promotion
            attendance-alerts  head-attendance-alerts  attendance-settings  head-attendance-settings
            fee-schedule  invoices-generate  invoices-not-invoiced  invoice-detail  payment-record
            payment-approvals  payment-gateway  head-promotion  head-incoming  head-classes  head-bulk-subjects  head-teacher-assignments
            classes-arrange  head-arrange  (.html)  [academic-classes, promotion, bulk-subjects, teacher-assignments re-issued]
            staff-add  staff-access  sign-in-rules  account  head-account  students  student-add  students-import  student-access
            registration-numbers  head-staff  head-staff-detail  activity-log  (Batch 6; student/screens: set-password, login re-issued)
  specs/    one .md per screen, plus shell-and-navigation.md
```

## Sample data conventions
Sample class names (JSS1 to SS3, Primary 1 to 6) and people are illustrative: classes, sections and departments are school data. Within each file the
numbers agree (412 students = 178 Primary + 234 Secondary; 36 unpaid = 14 + 22; 9 awaiting approval = 4 + 5). **Teachers assigned is a distinct count: 12 + 16 but 27 in All, because one teacher works in both sections.** Only the figures need to be real in the build.

## Decisions carried from the briefs (not reopened)
Sections and the head title are data; the head is a **person assigned to a section**, not a role per section. `view_all_sections` gives the switcher; a head has none.
Departments are optional tags on classes, never arms, and no department UI exists when none are defined. Every bulk action previews, confirms, then reports, and is idempotent.
Removing an assignment never deletes results. Hiding a field is a convenience; the server validates.

## Decisions (owner, 2026-10-11)
- **Heads get a Staff page, scoped to their section (batch 6).** They view staff and **view and record discipline** (query, warning, commendation). They **never see salary, exit records or documents** and **cannot edit employment details**. Their list **excludes Admin accounts, users holding `view_all_sections`, and the other head**.
- **One head is shown per section**, but **section assignments are many-to-many**, so a vice principal can be added later.
- **Bursar: whole school, no switcher.** **Single-section schools: no switcher.**
- **Heads write within their own section (decided 2026-10-18):** they create, edit, move and delete classes, run bulk subject assignment and teacher assignments, and promote; the Admin does all of it in any section. **One teacher per class subject** in the UI.
- **Promotion:** classes get a **position** (nobody types it); the **final class moves students to a Graduated status that keeps their history**; **no undo**; a separate **single-student move**.
- **The Admin dashboard lists submitted results only.** A **department cannot be deleted while a class uses it.**
- **Teachers assigned** is a distinct count in scope (a teacher in both sections counts once in All); only additive figures sum across sections.
- **Sidebar:** the nav area scrolls on its own with the brand and Sign out pinned (see `specs/shell-and-navigation.md`).

## Attendance twice a day (confirmed 2026-10-12)
Defaults A1 to A9 are confirmed with these owner changes: **alert times are set per section by that section's head** (Admin can set any section; changes are logged; **defaults Afternoon 14:00, Morning 08:00**); the marking page **opens on Morning before 12:00 and on Afternoon from 12:00** (default tab only; the saved session is the tab chosen, never the clock); a head or Admin can mark a **session not held** for a date for their section (half days, holidays; it suppresses that session's alert and excludes it from percentages; **no school calendar**); **Excused sessions are excluded from the percentage**; **no "mark the whole day" shortcut**. Alert job today: weekends only, no holiday awareness (spec has the details). See `specs/attendance-sessions.md` and `docs/design/ATTENDANCE_TWICE_DAILY.md`.

## Decisions (owner, 2026-10-23): Level and Subject Sections
- A **Level or Subject is never moved between Sections.** The Section is chosen when it is created and cannot change; a wrong one is deleted and recreated. The Sections screen shows Levels read-only; the Levels and Subjects create sheets (Admin) have a required Section field.
- **Heads create, edit and delete Levels and Subjects in their own Section only**, with no switcher and no Section field on the create sheet (rows 33 and 35). The head sidebar (Classes, Levels, Subjects, ...) and phone More gain Levels and Subjects; the Admin sidebar links Bulk subjects, Teacher assignments and Promotion.
- **Graduated pupils and their parents** keep read-only access to past report cards, paid receipts and payment-gated Attendance for the last 30 recorded school days. **Awaiting admission** stays limited to past report cards and receipts. The three Promotion screens now say so.

## Decisions (owner, 2026-10-17)
- **Promotion:** one school-wide class sequence; default arm = same arm name else first arm, **all editable**; no automatic link to a session or term; **Graduated pupils and their parents** keep read-only access to past report cards, paid receipts and payment-gated Attendance for the last 30 recorded school days; **Awaiting admission** stays limited to past report cards and receipts.
- **Heads promote within their own section.** The last class of a section followed by another section (Primary 6) moves pupils to **Awaiting admission**; the Secondary head or Admin **accepts** them or marks them **not continuing** (Graduated). **Manual only, no automatic lapse.** The Primary head does not mark pupils as leaving.
- **Registration numbers** may be reassigned at acceptance, and afterwards by the Admin or the head of the pupil's current section, with logging.

## Decisions (owner, 2026-10-18)
- **Nobody types a class order number.** People choose positions: **Comes after [class]** (or First class), **New step after** or **Same step as** a class (parallel department classes, which need a different department tag; hidden with no departments), **Move up / Move down** (moves the whole step inside the level), and a one-time **Arrange your classes from first to last** screen (Admin; a head for their own section) pre-filled with a best guess (section, level, name) that the system never uses until it is saved.
- **Promotion is blocked** ("Arrange your classes first") until the arrangement is saved. When more than one class sits at the next step and the source has no department, **no destination is pre-selected** ("Choose a class") and Move is disabled until chosen. The department rule stays.
- **Heads write within their own section only**: classes (create, edit, delete), bulk subject assignment, teacher assignments. An empty section lets the head place the first class (the system places it at the section boundary).

## Proposals (not decided)
- Deleting a class is blocked while it or any arm has students (any status), results, attendance or invoices. The Admin places the first class of an empty section if the head cannot.

## Still open (recorded in the specs, not decided)
- Classes: combinations with more than two classes on a step; whether a head may change a class's level.
- Promotion: three-section schools; correcting a pupil sent to Awaiting admission by mistake; portal access while Awaiting admission; who edits the number of an Awaiting admission pupil.
- Attendance: a **reason note** on "not held"; marks already made before a session is declared not held (design keeps and excludes them); older alerts that can no longer be fixed; whether removing today's not-held entry re-raises an alert.


## Batch 6 follow-up reissue (2026-10-31)

The earlier index rows above remain authoritative. Batch 6B is built: Students list, Add student, combined Student record (identity, sign-in and number), and Registration numbers settings. The HTML is a design reference, not a replacement for implemented code.

| Reference | Current status | Design follow-up |
|---|---|---|
| [Students](screens/students.html) | Batch 6B built | Add student action, list and empty/permission states |
| [Add student](screens/student-add.html) | Batch 6B built | Preserve existing full Add student reference |
| [Student record / sign-in / number](screens/student-access.html) | Batch 6B built | One combined page with name/email/username edit, password reset and number history; replaces separate `student-record.html` reference |
| [Registration numbers](screens/registration-numbers.html) | Batch 6B built | Administration navigation, prefix and counter policies |
| [Shell and navigation](screens/shell-and-navigation.html) | Design correction | Registration numbers under Administration |
| [Staff HR](screens/staff-hr.html) / [Employment record](screens/staff-hr-record.html) | Design correction | HR distinct from account; status through Exit record only |
| [Admission requirements](screens/admission-requirements.html) | Heading built; override design correction | Required reason, audited; unticked items stay unticked |
| [Registration movement preview](screens/registration-movement-preview.html) | Design reference | Placement and Incoming preview; backend verification before implementation |

Results configuration v2 and reusable help pattern v2 are explicitly excluded pending the owner's notes.
