# Brief for Codex: build the Admin, Head and Bursar screens from `docs/design/admin/`

Read `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` first (D1 to D15 are the decisions), then this file, then the screen and its spec.
Follow `CONTRIBUTING.md`. The student and parent batches are built; reuse their partials.

## Rules
- The HTML files are **references, not code to paste**. Reuse the existing partials and CSS (`../student/shared/`); add only what is in `shared/oak-admin.css`.
- **Build only rows marked Ready** in `INDEX.md`, for the batch named. If a screen is needed and not Ready, stop and report.
- Do the foundations brief's **Verify first** list for what you touch and record Confirmed or Different (file and line) in its "Verification results" section.
- Do not invent table, column or permission names. Where a spec says **NEW**, take the name from the foundations brief; if it has none, choose the smallest name, log it in the Decisions Log and update `docs/schema.md` in the same session.
- Hiding a field is a convenience. **The server validates and ignores irrelevant fields** (D5). Section scoping is enforced **server-side** on every query, never only in the view.
- Match the reference at **1280px and 390px** and report deviations in `PROGRESS.md`. No horizontal overflow; 44px tap targets on phones.
- Update `docs/design-system.md` and `docs/schema.md` when you add components or data, in the same session.
- Record each open question in `PROGRESS.md` under "Blockers / Open Questions". Do not resolve them.

## Batch 4: Admin shell, dashboards, academic structure, bulk assignment, promotion

### Order of work
1. **Foundations A (backend may start now):** sections, `level.section_id`, configurable head title, a person's section assignment, `view_all_sections`, departments, a nullable department on a class, and **section scoping** of the result approval queue, attendance alerts, analytics, class and student lists, admission offers and default term comments. Verify first items 11 (level section, class department, candidate applied level) and 7 (teacher assignments persistent or per term; `schema.md` says persistent).
2. **Screens for that data:** Sections and levels, Departments, Classes (Admin and head, both writable within their section, see the revision below) per `specs/academic-sections.md`, `academic-departments.md`, `academic-classes.md`.
3. **Shell and navigation by role** (`specs/shell-and-navigation.md`), then the **Admin** and **Head** dashboards. Build the **Bursar** dashboard after batch 5 data exists, or without the Awaiting approval and Not invoiced cards.
4. **Foundations F:** bulk subject assignment (`specs/bulk-subjects.md`) and teacher assignments by teacher and by class (`specs/teacher-assignments.md`).
5. **Promotion** (`specs/promotion.md`): add the class **position** first (it drives the suggested next class; see the class-position revision below), a **Graduated** student status, the bulk move, the final-class graduation and the single-student move. There is **no undo**.

### Tests required (ARCHITECTURE.md section 8, trust-sensitive)
- A **head cannot see or approve another section's** results, attendance alerts, admission offers, class or student lists, or counts, including by editing ids or `?section=`.
- Without `view_all_sections` a user cannot request another section; a head with no assigned section sees only the not-assigned state.
- Moving a **level** between sections changes what heads see immediately and deletes nothing.
- **Bulk subject assignment is idempotent** (run twice, no duplicates); the preview counts equal the applied counts; no result row changes.
- **Teacher assignments:** the server rejects any class and subject pair not in `class_subjects`; running twice adds nothing; removing an assignment leaves results untouched.
- With **no departments**, no department field is accepted or rendered; a duplicate department name is rejected.
- **Promotion** leaves past results and attendance unchanged, refuses destinations outside the actor's scope, and re-running the same move changes nothing.
- Dashboard figures follow the section. **Additive figures** (students, results awaiting approval, attendance alerts, admission offers, unpaid invoices) in All equal the sum of the sections. **Teachers assigned counts distinct teachers in scope**: a teacher assigned in both sections counts once in All, so All can be less than the sum.
- **Graduating** keeps results, attendance and invoices readable and removes the student from active lists and counts; the **single-student move** validates class and arm.
- A **department cannot be deleted** while any class uses it.
Everything else: verify manually at 1280px and 390px.

### Decided by the owner (2026-10-11)
**Heads write within their own section** (classes, bulk subject assignment, teacher assignments, promotion); the Admin acts in any section; see the revisions below. **One teacher per class subject** in the UI. The Bursar sees the **whole school** (no switcher); **single-section schools have no switcher**.
One head is shown per section but **section assignments are many-to-many**. The Admin dashboard lists **submitted results only**. A department cannot be deleted while a class uses it.
Promotion: **class order field**, **Graduated** status for the final class (history kept), **no undo**, separate **single-student move**.

### Sidebar fix (all roles)
The nav list must scroll on its own with the brand and Sign out pinned (`.oak-sidebar-links { flex: 1 1 auto; min-height: 0; overflow-y: auto }`). Apply it in the **shared** shell CSS, not only for Admin. Check at 1366×650, 1366×720 and 1366×768.

### Heads-up: attendance is moving to twice a day
Build the dashboards' "Attendance today" and "Classes with attendance not marked" **behind `AttendanceService`**. Do not hardcode one roll per day. Proposal and open questions: `docs/design/ATTENDANCE_TWICE_DAILY.md`.

### Still open (do not resolve silently)
Promotion's **order semantics** (school-wide or per level), the **default arm**, any **session or term link**, and whether **graduated students keep portal access**.

## Attendance sessions (morning and afternoon): ready, build after the student and parent attendance pages are re-issued
Read `docs/design/ATTENDANCE_TWICE_DAILY.md` and `specs/attendance-sessions.md` first. Order:
1. **Record it:** `PROGRESS.md` Decisions Log; `docs/schema.md` (session on attendance records and alerts; per-section alert times; sessions not held); the attendance-locking line in `ARCHITECTURE.md` section 8.
2. **Migration:** nullable `session` (null = legacy whole day, counts as both sessions), new unique keys, per-section times, sessions-not-held table. No backfill.
3. **`AttendanceService`:** every method takes a session; `dashboardToday` returns Morning and Afternoon; `portalSummary` covers the last 30 **school days**; a session marked not held is excluded from percentages and from "marked"; Excused sessions are excluded from the percentage.
4. **Alert job:** every 5 minutes, per section and session, honouring that section's due time and "not held". **Regression to prove:** both sessions not held for a date gives no alerts (today the job raises one for every arm on a weekday holiday).
5. **Screens:** `attendance-alerts`, `head-attendance-alerts`, `attendance-settings`, `head-attendance-settings`, then the re-issued dashboards and the student and parent Attendance pages. The Teacher marking page opens on Morning before 12:00 and Afternoon from 12:00 (WAT); the saved session is the tab chosen, never the clock.

### Tests required (trust-sensitive)
One record per student per date per session; session saved = tab posted, not the clock; lock per session; legacy rows count as two sessions in the percentage; Excused, not-held and unmarked sessions are excluded from the percentage; a head reads and changes only their own section's times and not-held sessions (an Admin any), always audit-logged; Morning must be earlier than Afternoon (server-side); no alerts for a not-held session and its open alerts resolve; a past not-held entry cannot be removed; `portalSummary` returns 30 school days; payment gating still hides attendance entirely.

### Still open (do not resolve silently)
A reason note on "not held"; marks made before a session is declared not held (kept and excluded in the design); older unfixable alerts; whether removing today's entry re-raises an alert.

## Class position and head write access (2026-10-18): no typed order number

Replaces the typed Order input. Screens: `academic-classes.html`, `head-classes.html`, `classes-arrange.html`, `head-arrange.html`, `head-bulk-subjects.html`, `head-teacher-assignments.html`, `promotion.html`, `head-promotion.html` (INDEX rows 7, 8, 11, 26, 28 to 31). Specs: `academic-classes.md`, `classes-arrange.md`, `bulk-subjects.md`, `teacher-assignments.md`, `promotion.md`.

### Rules
- Store a **position** number on the class (name it, log it). Nobody types it; no screen shows it. One school-wide sequence; **classes at the same step share a position**; arms carry none.
- **Add or edit:** "Comes after [class]" (or First class in the level). With departments: **New step after** (new position right after, later steps shift in one transaction) or **Same step as** (same position; the department must differ from every class on that step). First class of an empty section: placed at the section boundary with no question asked.
- **Move up/down:** swaps the **whole step** with the adjacent step **inside the same level**; never across a level or section boundary.
- **Arrange your classes** is shown once (Admin: both sections; head: their own). The pre-filled order is a guess (section, level, natural sort of name) and is **never used until saved**. Store a per-section **arranged** marker (who, when). **Promotion is refused (server-side) until it is set.**
- **Suggested next class:** the class at the next step; with departments, the one with the **same department tag**. If more than one class sits at the next step and the source has no department, **no destination is pre-selected**; refuse the move until each student has one.
- **Heads** create, edit, move and delete classes, run bulk subject assignment and teacher assignments **only in their own section**; the **Admin** in any section. Name section-scoped permissions yourself and log them. The server refuses anything outside the actor's section.
- **Proposal, not decided:** deleting a class is blocked while it or any arm has students (any status), results, attendance records or invoices. The block names what exists. Another proposal: the Admin places the first class of an empty section if the head cannot.

### Verify first (record Confirmed or Different with file and line)
How classes and class arms are stored and ordered today (the Foundations A Classes list); where classes are referenced by results, attendance, invoices and students (for the delete block); whether the class Order input was left out of the build.

### Tests required (trust-sensitive)
A head cannot create, edit, move or delete a class, or run bulk or teacher assignment, outside their section, even by editing ids (the Admin can); a same-step class with the same department is refused; moving a step swaps all classes sharing the position; renumbering is transactional; a class with students, results, attendance or invoices cannot be deleted (proposal) and an empty class can; **Promotion is refused until the arrangement is saved**; with two classes at the next step and no source department the move is refused until each student has a class.

### Still open (do not resolve silently)
More than two classes on one step; a class with no department on a step whose other classes have departments; whether a head may change a class's level; three-section schools.

## Promotion revision (2026-10-17): heads promote in their own section, Awaiting admission, registration numbers

Supersedes the Batch 4 line "promotion is Admin only". Build **only when** the class **position** and the Graduated status exist. The Admin `promotion.html` is now re-issued (2026-10-18). Screens: `screens/head-promotion.html`, `screens/head-incoming.html` (INDEX rows 26 and 27). The Admin `promotion.html` is **not** re-issued yet; build the Admin page from the head components plus a section filter and report differences.

### Rules
- A head promotes **only inside their own section** (source and destination); the **server** refuses anything else. The Admin promotes any section.
- The last class of a section that is followed by another section (Primary 6) moves pupils to **Awaiting admission** (NEW status), **immediately** removing them from that section's class lists, attendance rosters, counts and automatic invoicing. The last class of the school (SS3) moves to **Graduated**. Results, attendance and invoices stay readable for both.
- The Secondary head or the Admin **accepts** each pupil (class and arm, editable; default same arm name else first arm) or marks them **not continuing** (Graduated). **Manual only: no scheduled lapse, no session or term link.** Bulk "mark all remaining as not continuing" has a preview, confirmation and report.
- **Registration numbers:** keep by default; optional new Secondary number at acceptance (unique school-wide, editable, old number kept in history and searchable, every change logged). After acceptance the Admin, or the head of the pupil's **current** section, may change a number (section-scoped permission; name for you to match). Check where the number is printed or used for lookup before enabling edits.
- Destination class, arm and class Order are **editable**. No undo; corrections use Move one student.

### Tests required (trust-sensitive)
A head cannot promote from or to a class outside their section, and cannot accept or renumber outside it; re-running a move or an acceptance changes nothing; Primary 6 promotion removes pupils from Primary rosters, counts and invoicing while results, attendance and invoices stay readable; a duplicate registration number is refused school-wide; "mark all remaining" affects only pupils still waiting; past results and attendance rows are unchanged.

### Still open (do not resolve silently)
Three-section schools; correcting a pupil sent to Awaiting admission by mistake; portal access while Awaiting admission (design assumes read-only); who edits the number of a pupil still Awaiting admission (proposal: Secondary head and Admin only); automatic term invoice on acceptance (proposal).

## Batch 5: fees and payments (2026-10-18)

Screens: INDEX rows 12 to 15b. Specs: `fee-schedule.md`, `invoices-generate.md`, `invoices-not-invoiced.md`, `invoice-detail.md`, `payment-record.md`, `payment-approvals.md`, `payment-gateway.md`, plus the "Online payment isn't set up yet" notes in `student/specs/fees.md` and `parent-fees.md`. Money: **integer kobo in storage, naira only on screen**.

### Order of work (the backend order is in `BACKEND_HANDOFF.md` items 7 to 9)
1. Fee items, schedule lines, invoice lines (copied at issue), bulk generation (preview, idempotent), single invoice with extras, Not invoiced.
2. Manual payment: bank, teller number (unique once, school-wide), date, amount, optional photo; Awaiting approval, approve, reject; a school setting for one-step.
3. Part-payment as a linked invoice; adjustment lines; void rules.
4. Payment gateway settings (`manage_payment_gateway`, Admin only), encrypted keys, test connection, the not-set-up state on student, parent and Bursar screens.

### Verify first (record Confirmed or Different with file and line)
`ResultPaymentAccess` (any Paid Tuition invoice satisfies the lock today); `PaymentLedger::portalSummary` (sums every unpaid invoice); what `PaymentController::manual` records; whether a separate one-off invoice already exists; how Paystack keys are read.

### The two traps (D10)
The result lock must read **only the term invoice**; outstanding is the **term invoice's balance**, never the sum of unpaid invoices.

### Tests required (trust-sensitive)
An issued invoice never changes when the schedule changes; running bulk generation twice creates no duplicates and the preview counts equal the applied counts; an invoice total equals the sum of its lines; a teller number cannot be recorded twice (race-safe); an unapproved manual payment settles nothing; approval issues exactly one receipt; only an Admin approves; a settled part-payment does not unlock results and the final one does; outstanding is counted once; settled plus open part-payments never exceed the term total; a term invoice with a settled part-payment cannot be voided; a part-payment is voidable only while unpaid; the secret key never appears in any response, log or audit entry; a saved secret verifies a webhook signature; a test public key with a live secret key is refused; with no keys the Pay screens show the not-set-up state and manual payment still works.

### Open questions (do not decide; record in PROGRESS.md)
Several matching scopes for one student; removal of fee items in use; partial manual payments; manual-payment rejection wording and teller reuse after rejection; the Paystack dashboard location for the webhook; the wording when keys cannot be decrypted; whether an Admin may override the void block.

## Batch 6: staff, students, import, CBT upload, Activity log, ticker events, Admission requirements
Not delivered yet. Do not build. Scope recorded so far (design arrives first):
- **First-login "Set your password"** screen for every role; **staff Account page** (change password).
- **Head Staff list and detail**, section-scoped: no salary column; a **record-discipline form** (query, warning, commendation). Heads never see salary, exit records or documents and cannot edit employment details. The list excludes Admin accounts, users with `view_all_sections` and the other head.
- **Admin staff form:** a **section assignment field** (many-to-many).
- Add staff (one-time credentials with print, admin password reset), add student, bulk import wizard, registration-number settings, CBT CSV upload and guarded delete, Activity log, ticker events, "Admission requirements".
- The matrix with 20+ subjects was designed early and is part of batch 4 (`teacher-assignments`, state "Many subjects").

Tests that will be required (confirmed when batch 6 ships): a head sees only staff in their own section; the list never contains Admin accounts, `view_all_sections` holders or the other head; a head can never read salary, exit records or documents by any route; a head cannot edit employment details; recording discipline writes the existing audit entry; a creator cannot assign a role above their own; admin-created accounts must change the password at first login.
