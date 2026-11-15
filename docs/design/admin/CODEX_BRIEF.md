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

### Order of work (the build order and current state are in `PROGRESS.md` under Completed and Next Up)
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

## Academic setup revision (2026-10-23): Section ownership of Levels and Subjects, head variants

Screens: INDEX rows 32 to 35 (`academic-levels`, `head-levels`, `academic-subjects`, `head-subjects`), plus `academic-sections` (Levels now read-only) and the three Promotion screens (Graduated wording). Spec: `specs/academic-setup.md`.

### Rules
- A Level's or Subject's Section is set **at creation and never changes**; there is no move for anyone. Remove the Admin Section selector from the Level edit sheet, the per-Level selectors, the disabled first-run selectors and the "Move level" confirmation from Sections and levels. An update request carrying a different Section is refused.
- **Admin** create sheet: required Section. **Head** create sheet: no Section field; the server uses the head's assigned Section and refuses any other. Heads create, edit and delete only in their Section (`manage_section_levels`, `manage_section_subjects`).
- The Subjects screen is one list with a Section tag per row and a Section filter; the same name may exist once per Section. The Admin sidebar links Bulk subjects, Teacher assignments and Promotion under Academics.
- Head navigation (order Classes, Levels, Subjects, Bulk subjects, Teacher assignments, Promotion): add Levels and Subjects to the sidebar and the phone More list (and the missing Bulk subjects, Teacher assignments, Promotion rows in More).

### Tests (trust-sensitive)
See `specs/academic-setup.md`: an existing Section never changes; a head cannot act outside their Section by editing ids; a head's create ignores a posted Section; an Admin create without a Section is refused; per-Section Subject name uniqueness.

### Still open (do not resolve silently)
A head assigned to more than one Section; a wrong-Section Level or Subject that already has classes, teachers or results cannot be deleted; how existing Levels get their first Section after migration.

## Batch 6: accounts, people and sign-in (design delivered)

Screens and specs: INDEX rows 16, 16b, 17, 17b, 18, 19 and 37 to 43. Batch 6A and 6B are built; rows 44 and 46 remain spec-only or deferred. Build the remaining work in the order recorded in `PROGRESS.md`. Specs: `staff-add`, `staff-access`, `sign-in-rules`, `account`, `students`, `student-add`, `students-import`, `student-access`, `registration-numbers`, `head-staff`, `activity-log`, `ticker-events`, `student/specs/set-password`.

### Rules that apply everywhere
- **Credentials are shown once.** A temporary password is made on the server, stored **only as a hash**, shown only in the response to the request that made it, with `Cache-Control: no-store`. It is **never** logged, written to `audit_logs`, the session or flash, a queue payload, a notification, an email or a file. Print slip is the browser printing the on-screen panel.
- **Admin-created and Admin-reset accounts must change the password at first sign-in** (`users.must_change_password`, NEW). While set, every route except set-password, sign-out and assets redirects there, including AJAX, Livewire and exam or payment routes.
- **A creator cannot assign a role above their own**, on create and on role change, enforced on the server. The role order is an open question (the design proposes Admin, Head, Bursar, Teacher).
- **A head cannot see or record anything outside their Section**, even by editing ids. Staff payloads use an allow-list so salary, exit records and documents cannot reach a head by any route.
- **A bulk import with any invalid row creates nothing.** Check writes nothing; Confirm is one transaction, re-validated, idempotent.
- Hiding a field is a convenience; the server validates. Times are Africa/Lagos. Naira in kobo on storage.

### Stated current behaviour Codex must confirm first (I have not seen these)
Today's Staff HR and Students pages and forms; whether `students` has a registration number column (my plan: no, NEW); whether username login and case-insensitive matching work today; `section_user` usage; `staff_disciplinary_log` and `staff_sensitive_view_audit` behaviour; which modules write `audit_logs`; the existing password rule and any change-password route; `notifications.url`.

### NEW data (Codex names columns and logs them)
`users.must_change_password`; a role ranking; registration number on students with a history table; registration-number settings (prefix, length, year flag, per-Section prefixes) and a sequence; a temporary import record (no passwords); a view-activity-log permission; section-scoped registration-number permission (already proposed 2026-10-17); head Staff permissions.

### Tests (required, trust-sensitive)
- A creator cannot assign a role above their own (form, forged POST, edited ids; also on role change).
- Admin-created accounts, and accounts after an Admin reset, must change the password at first login and cannot reach any other route until they do.
- Credentials are shown once and are never logged or stored in plain text (search logs, `audit_logs`, session store, queue table, flash, database).
- A head cannot see or record anything outside their Section by editing ids; the list excludes Admins, `view_all_sections` holders, the other head and themself; salary, exit records and documents are unreachable for a head; a head with no Section sees the locked state and gets no data.
- A bulk import with any invalid row creates nothing (students, users, parents, links, invoices all zero); confirm twice creates nothing the second time.
- Registration numbers are unique school-wide and race-safe; the number never contains the class; a format change never alters issued numbers; a changed number keeps its history and every change is logged.
- Username uniqueness is school-wide and case-insensitive; login accepts any case; the failure message does not say which part was wrong.
- A reset signs the account out everywhere; a parent reset covers all their children.
- The Activity log never contains a password, token or payment key.

### Still open (listed in the specs, **not decided**)
- A **head assigned to more than one Section** (the data allows it; the screens show one, with no switcher).
- **Three-section schools** ("the other head" becomes "the other heads"; the Section select and list generalise).
- **Who edits a registration number once a pupil is Awaiting admission** (proposal in the 2026-10-17 note: the Secondary head and Admin only).
- **What a parent with several children signs in with** (the design: one login for the family using the parent's own username or email, with a child switcher).
- The role order and whether Heads create Teachers; the sequence rule for numbers (one counter or one per prefix); whether a number in history blocks reuse; what "Skipped" covers in an import and the row limits; how the import's one-time credentials are delivered without storing them (and not through a queue); who a "teacher in both Sections" belongs to for Head Staff; whether students and parents can change their own password later (`student/specs/profile.md` calls it optional).

## Batch 6 follow-up reissue — additive implementation guidance

Keep all earlier instructions and Ready/Pending rows above. Batch 6B is **already built**: Students list, Add student, combined Student record/sign-in/number and Registration numbers settings. Do not rebuild those from scratch. Verify current Laravel behavior first; apply only design corrections. The Student record is ONE page with identity edit, username, password reset and number history. Registration numbers belongs under Administration. Admission requirements (built heading) uses D15 Admin override: require a reason BEFORE confirmation; save it in the audit entry; checklist items stay unticked and the UI says conversion was approved by override. Employment status is changed through Exit record, never through generic employment edit. Reset-link email expires in 60 minutes. Preserve original full specs and append Follow-up changes; never replace with a summary. Do not implement Results configuration redesign or reusable help pattern without v2 notes.

## Teacher Results — implementation handoff (design addition, 2026-11-03)

**Scope:** Teacher Results landing → subject/class pair → score entry. Verify the current Laravel routes, result templates, subject/class assignments, form-teacher arm ownership and CSV import service before changing code. Keep all earlier CODEX_BRIEF instructions and Ready/Pending rows above.

1. Replace only the **Teacher** flat Results workspace UI with one card per assigned `(subject, class)` across all assigned Sections, label `<Subject> · <Class>` (e.g. "Mathematics · JSS 2"; class names are unique and the class already identifies the Section, so Level and Section are not shown). One subject/class pair includes every arm; do not create elective enrollment logic. Show current-Term Draft/Submitted/Approved counts and Enter scores.
2. A **form teacher** sees an additional My class destination showing class-wide **approved** results and compiled report cards for their assigned **arm only**. Plain subject Teachers cannot see or access this route. A Teacher may have subject assignments in multiple Sections.
3. Pair page lists only pupils in the pair's class (all arms) with name, arm, registration number, score status, action. All selectors and lookups are scoped server-side. Empty assignments and no-approved states are mandatory.
4. CSV: design proposes a **server-generated pair-and-Term roster template** keyed by registration number (never pupil name). Verify registration-number availability and the current CSV pipeline with the Backend Agent before implementing; if any pupil lacks a valid number, disable this simplified import with an explanation. Preview and validate all rows before any apply; preserve existing locks and atomic behavior.
5. Score entry uses the existing **actual configured template/components**, not hardcoded sample CA values. Preserve Draft → Submitted → Approved behavior, authorization, audit and edit locks.
6. Visual QA at 1280px and 390px; no page horizontal overflow; visible controls ≥44px; all design state buttons switch their content. Preview state toolbar is not shipped to production.

**Trust-sensitive tests:** cross-Section teacher pair allowed when assigned; forged unassigned pair denied; subject Teacher sees all arms in assigned class but cannot access My class; form teacher can access own arm only; another arm denied; no elective per-pupil exclusions; CSV forged/cross-pair/duplicate/approved/stale rows refused; score submission/approval locks preserved. The HTML is illustrative, not a claim that these backend behaviors are built.

## Registration number format — implementation handoff (design addition, 2026-11-03)

**Scope:** replace the single school prefix and the per-Section/per-Level prefix fallback built in 6B with a composable format. Verify the current 6B settings, allocation and every place a number is shown or searched before changing code. Keep all earlier CODEX_BRIEF instructions. Do not change number history, Head/Admin change rights, Awaiting-admission rules or Promotion renumbering (5c).

1. Settings: tick School / Section / Level / Year (fixed order, Number always last), separator, number length 3 to 6 (grows when exhausted), Year style (4 or 2 digits), school code, one code per Section and per Level. Migrate existing prefixes to codes so no issued number changes.
2. Server rules: every Section/Level needs a code when its part is on; codes unique per group (Level codes unique school-wide when Section is not in the number); school code 2 to 6 letters or numbers; format changes affect new numbers only; the full number stays unique school-wide inside the existing race-safe allocation; next number never below the highest issued; numbers once held are never reused.
3. Movement policy stays as built (three choices, locks after the first number, school-wide counter for "keep digits"); examples in the UI follow the chosen format; if neither Section nor Level is in the format, the choice has no effect and the page says so.
4. Confirm that the separator options and 2-digit year are safe everywhere the number is displayed, searched or printed; drop any option that is not.
5. Visual QA at 1280px and 390px; no horizontal overflow; controls at least 44px; the design State toolbar is not shipped.

**Trust-sensitive tests:** server refuses a part with missing codes; duplicate codes refused; format change leaves issued numbers unchanged; concurrent allocation stays unique; policy locked after the first number; next number cannot be lowered; only the permitted role can save.