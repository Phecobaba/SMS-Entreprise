# Admin, sections and fees: build brief

Status: decisions recorded 2026-10-10. Source: the owner's review of the Principal, Admin and Bursar
dashboards. This file is the durable record of those decisions. Where it conflicts with an older
doc, this file wins until the older doc is updated (update it in the same session).

## How to use this file

1. Register it: add a row for it to the `AGENTS.md` reference table, and log the decisions below in
   `PROGRESS.md` (Decisions Log). Do not move anything into "Blockers / Open Questions" unless it is
   listed under "Open" here.
2. Do the **Verify first** checklist before building. Record Confirmed or Different (file and line)
   in the "Verification results" section at the bottom of this file.
3. Keep the **Status** table at the bottom current. A new agent should be able to resume from it.
4. Backend may start before screens exist. Build **no new screen** until `docs/design/*/INDEX.md`
   marks it Ready. If a screen is needed and not designed, stop and report.
5. When a decision changes the data model, update `docs/schema.md` in the same session. When it changes
   payments, update `docs/payments.md`.

## Glossary (use these words exactly, in code comments, labels and docs)

| Term | Meaning |
|---|---|
| Section | A group of levels run by one head. Oakfield has two: **Primary School** and **Secondary School**. |
| Level | A stage inside a section: Pre-nursery, Nursery, Kindergarten, Primary (1-6), Junior Secondary (JS1-3), Senior Secondary (SS1-3). |
| Class | One year group inside a level (for example SS2 Science). Subjects attach to classes. |
| Arm | A parallel group of the same class (SS2 Science Gold, SS2 Science Blue). Arms share the class's subjects. |
| Department | An optional tag on a class (Science, Arts). Not an arm. |

Hierarchy: Section > Level > Class > Arm. A level belongs to exactly one section.

## Decisions

### D1. Sections and the two heads
- Add sections. Each level belongs to one section. Oakfield: Primary School = Pre-nursery, Nursery,
  Kindergarten, Primary; Secondary School = Junior Secondary, Senior Secondary. The school owns this
  data (Admin edits it); do not hardcode Oakfield's levels.
- Do **not** create a role per section. Keep one Principal role (what a head may do). Assign the section
  to the person (what they may see). A section has a configurable head title ("Head Teacher",
  "Principal") used for display.
- New permission `view_all_sections` for Admin: sees everything, with an All / Primary School /
  Secondary School switcher. A head has no switcher and sees only their section.
- Section scoping applies to: result approval queue, attendance alerts, analytics cards, class and
  student lists, admission offers, and default term comments (`term_default_comments` needs a section).
- The printed report card's second comment label follows the section's head title.
- Sections are tenant-owned data (`school_id`, `BelongsToTenant`).
- **Open:** whether staff and the Staff list are scoped by section for heads. Not decided. Until it is,
  leave heads' Staff access as built and add no section to staff. Report what you find; do not decide.

### D2. Departments (optional)
- A school defines its own list of departments (Oakfield: Science, maybe Arts later). A class may carry
  one department tag. Departments are classes, never arms: SS2 Science and SS2 Arts are separate classes.
- If a school defines no departments, no department field, filter or label appears anywhere.
- Promotion (for example SS1 to SS2) needs a destination choice per student or group, defaulting to the
  same department when one exists.

### D3. Bulk subject assignment
- Scope: level, then optional department, then classes (each can be unticked), then subjects.
- Show a preview ("adds 12, skips 3 existing") before confirming. Idempotent: running it twice adds
  nothing new.
- Removing a class-subject assignment must never delete results already entered.

### D4. Bulk teacher assignments
- Two entry points: by teacher (classes x subjects) and by class (who teaches each subject).
- Offer only subject and class pairs the class actually has. Same preview and idempotency rules.
- Removing an assignment must never delete results already entered.

### D5. Dynamic forms
- When an option makes another field irrelevant, hide that field in the UI.
- The server must also ignore and clear irrelevant fields and validate only relevant ones. Hiding is a
  convenience, never a security boundary.

### D6. Accounts for staff and students
- Admin creates a staff login and the staff record in one form. Same for students (via add student).
- Add a nullable `username`, unique per school. Login accepts email or username. Make `email` nullable
  if it is currently required (verify first).
- Admin sets or generates a temporary password, shown once, printable. The user must change it at first
  login. Add an admin-initiated password reset. There is still no "forgot password" flow (deferred).
- A creator can assign only roles they are allowed to assign. A head cannot create an Admin.
- Admin-created staff may have no email; do not require one.

### D7. Students and registration numbers
- Add student singly and by bulk CSV import: upload, validate the whole file, preview with row errors,
  confirm, then a result report. Guardian columns link parents to students.
- Registration number: the school sets the format (prefix, optional year part, zero-padded sequence;
  a different prefix per section is allowed). Auto-generated by default; manual override allowed (for
  importing existing students) with a uniqueness check per school.
- Generate numbers safely (two people adding students at once must never get the same number).
- The number never encodes the class (it must not change at promotion). A candidate number is a
  different thing; the registration number is assigned at enrolment or conversion.
- If a fee schedule exists, invoice a new student automatically at enrolment.
- Bursar gets a "Not invoiced" list.

### D8. Fee schedule and invoice lines
- A fee item catalog (name) managed with `manage_fee_schedule` (Bursar and Admin by default).
- A fee schedule attaches items and amounts to a term and a scope (level, class or department). Each
  item has a **Required** tick. Ticked items go on every invoice automatically. Unticked items are
  extras the Bursar adds to an individual student's invoice.
- One invoice per student per term. Items are paid together. Results unlock when that invoice is fully
  paid. If an item should not hold results back, issue it as a separate one-off invoice (existing
  capability; verify).
- An invoice copies its lines when issued. Later price changes never alter invoices already issued.
  Corrections: void (only if nothing is paid) and reissue, or add an adjustment line with a reason.
  Both are logged.
- Bulk generate: choose a term and scope (level, section, whole school), show a preview ("412 students,
  389 will be invoiced, 23 already have one"), skip students who already have the invoice, so running
  it twice never duplicates. Also keep single-student invoices.
- Money stays stored as integer kobo. Screens and receipts show naira only (for example ₦45,000);
  never show kobo to users.
- Itemised lines show on Bursar invoice screens, the receipt, and an expandable row on student and
  parent fees pages (those screens get a small design update).
- **Open:** whether a Parent can see all linked siblings in one fees view. Do not decide until the
  owner confirms the intended Parent fees navigation and scope.

### D9. Manual (bank teller) payments
- Record bank, teller number, date, amount, optional teller photo. A teller number can be accepted only
  once (the same slip cannot settle two invoices).
- Two steps: Bursar records it ("Awaiting approval"); Admin approves, which settles the invoice and
  issues the receipt. A school setting allows single-step. Verify what already exists before building.

### D10. Part-payment on request
- A parent meets the Bursar or Admin, who creates a **linked part-payment invoice** against the term
  invoice with a fixed amount, due date and note. It is paid like any invoice (Paystack or manual): one
  invoice, one payment, exact amount match. Payers never type an amount.
- The term invoice becomes Paid when settled part-payments sum to its total. Results and attendance
  stay locked until then.
- Rules: the sum of settled and open part-payments can never exceed the term total; allowed only on term
  fee invoices (not registration or offer-deposit invoices); a part-payment can be voided only while
  unpaid; a term invoice that has a settled part-payment cannot be voided; creation and voids are
  logged with the actor and note.
- **Two traps to avoid:** (1) the result lock must read only the term invoice. A paid part-payment must
  never satisfy it. (2) Outstanding is the term invoice's balance, never the sum of all unpaid invoices
  (no double counting).
- Each part-payment has its own receipt. The term invoice shows "₦20,000 of ₦45,000 paid · balance
  ₦25,000" with its receipts. Student and parent fees pages show that line and a Pay button for the open
  part-payment.

### D11. Paystack keys entered in the Admin dashboard
- New permission `manage_payment_gateway`, Admin only by default. The Bursar does not hold it.
- The secret key is write-only: saved masked ("sk_live_••••1234"), never shown again, never in a
  response, log, error message or audit entry. The public key may be displayed.
- Store encrypted with Laravel's encryption, per school. If the app key is lost or rotated the keys
  cannot be decrypted and must be re-entered; document this and the need to back up the app key.
- Changing keys requires the Admin's password again, is audit-logged (who and when, never values), shows
  "last changed by", and notifies the other Admins in-app.
- Check prefixes (do not allow a test and a live key together), show a Test / Live badge, and a "Test
  connection" button (harmless read-only Paystack call).
- Show the webhook URL to copy with short instructions. Confirm Paystack's current dashboard location
  for the webhook setting; do not guess.
- With no keys saved, replace Pay buttons with "Online payment isn't set up yet"; manual payments keep
  working.
- Lookup order: saved keys first, environment variable as fallback. This changes `docs/payments.md`
  ("keys only in the environment"); update it. Webhook signature verification must use the saved secret.

### D12. Activity log
- A read-only screen over existing audit data: tabs by module, filters, pagination, behind its own
  permission. Sensitive-view entries need tighter access. Verify which audit tables exist first.

### D13. Notification ticker for every role
- Show the shared ticker on every role's dashboard. Staff roles receive almost no notifications today,
  so add events: result submitted (to the head of that section), result approved or rejected (to the
  teacher), offer accepted (to Admin). Use the notification `url` column agreed in student batch 2.

### D14. CBT bulk upload and delete
- UTF-8 CSV with a downloadable template: question, options A-D, correct answer, marks, explanation.
  Validate the whole file, preview row errors, import all-or-nothing. Text only (no images).
- Deleting questions or an exam is blocked once submissions exist. Bulk delete is guarded the same way.

### D15. Wording
- Relabel "Admission checklist items" as "Admission requirements", with helper text: what a candidate
  must complete before becoming a student; conversion is blocked until every requirement is ticked,
  unless an Admin overrides with a logged reason.

## Verify first (record results at the bottom)

1. `ResultPaymentAccess`: how it finds the invoice it checks; what happens when a student has no
   invoice; whether a paid part-payment-style invoice could satisfy it.
2. How "outstanding" is computed (`PortalOverview`, `PaymentLedger::portalSummary`).
3. Whether an invoice can have more than one payment; the invoice purposes; where the Paystack amount
   match lives.
4. Whether `students` has a registration number column and any uniqueness rule.
5. Whether `users.email` is required; how login looks users up.
6. Which audit tables exist and what they record.
7. Whether teacher assignments are per term or persistent.
8. What the manual payment flow records today and who can settle.
9. Where Paystack keys are read today.
10. What limits exist on which roles a user may assign.
11. Whether levels have a section, classes a department, candidates an applied level (needed to scope
    admission offers by section).

## Build order

A. Sections, departments, section assignment and scoping (with tests).
B. Accounts, students, bulk import, registration numbers, username login.
C. Fee catalog, schedule, invoice lines, bulk generate, manual approval.
D. Part-payment (with tests), after C.
E. Payment gateway settings.
F. Bulk subject and teacher assignment, CBT bulk upload.
G. Activity log and staff ticker events.

Screens follow `docs/design/` and are built only when marked Ready.

## Tests required (trust-sensitive, ARCHITECTURE.md section 8)

- A head cannot see or approve another section's data.
- A creator cannot assign a role above their own; admin-created accounts must change the password at
  first login.
- A student with no invoice is handled by the result lock as verified in step 1 (write down the rule).
- An invoice's total equals the sum of its lines; an issued invoice does not change when the schedule
  changes; the Paystack amount equals the invoice total.
- A settled part-payment does not unlock results; the final one does; outstanding is counted once; the
  cap holds; a part-payment cannot be created on a registration or offer-deposit invoice.
- A teller number cannot be used twice.
- The saved Paystack secret is never returned in any response; webhook signature checking uses it.
- CBT delete is blocked once submissions exist.
- Bulk operations are idempotent (run twice, no duplicates).

Everything else: verify manually at 1280px and 390px.

## Not now (deferred or rejected)

- Forgot-password flow (deferred; add to `ROADMAP.md` deferred scope).
- Scholarships, sibling discounts, instalment plans beyond on-request part-payment.
- A role per section; arms used as departments; separate Tuition and Other-charges invoices; several
  payments on one invoice.

## Verification results (Codex fills in)

Completed 2026-10-10 before Admin-foundations application code was written.
“Different” below means the current implementation differs from the target
or anticipated structure, not that the brief must expand. None of the
differences changes D1-D15 scope, so the build may proceed.

1. **Different — payment access is vulnerable to a future part-payment-shaped
   Tuition invoice.** Results require any `Paid` Tuition invoice for the same
   Student and Term with a related Payment; no invoice produces the same
   authorization failure, while payment rule `None` bypasses the gate
   (`app/Modules/Payments/Services/ResultPaymentAccess.php:22-30`). Attendance
   checks the latest Tuition invoice and likewise denies no invoice
   (`app/Modules/Payments/Services/ResultPaymentAccess.php:33-44`). Because no
   parent/part-payment discriminator exists, a paid invoice shaped as Tuition
   for that term could satisfy the Result query. **Recorded rule for the
   required test:** under `TermInvoicePaid`, a Student with no term invoice is
   locked; item D must make only the parent term invoice authoritative.
2. **Different — outstanding currently double-counts the future model.**
   `PortalOverview` selects one Student/ward and delegates payment data to
   `PaymentLedger::portalSummary`
   (`app/Modules/Portals/Services/PortalOverview.php:21-40,51-59`). The ledger
   currently sums every Unpaid invoice for that Student, regardless of purpose
   or relationship (`app/Modules/Payments/Services/PaymentLedger.php:31-43`),
   so linked part-payment invoices would be counted in addition to their term
   invoice unless item D replaces this with the term invoice balance.
3. **Confirmed — one settled Payment per Invoice; three current purposes; exact
   gateway amount check.** The database uniquely constrains
   `(school_id, invoice_id)` (`app/Modules/Payments/Database/Migrations/2026_10_04_000900_create_payments_tables.php:48-64`)
   and the model exposes a `HasOne` Payment
   (`app/Modules/Payments/Models/Invoice.php:23-26`). HTTP creation allows only
   Tuition, Registration, or Offer Deposit
   (`app/Modules/Payments/Http/Controllers/PaymentController.php:28-34`).
   Paystack verification requires the provider amount to equal the stored
   attempt amount before settlement
   (`app/Modules/Payments/Services/PaymentLedger.php:98-117`).
4. **Different — Students have no registration-number column or uniqueness
   rule.** The table currently contains identity, placement, optional Candidate,
   and admission date only
   (`app/Modules/Admissions/Database/Migrations/2026_10_03_000200_create_admissions_tables.php:82-96`).
5. **Confirmed — email is required and login is email-only.** `users.email` is
   non-null with school-scoped uniqueness
   (`app/Modules/Core/Database/Migrations/2026_10_03_000000_create_core_tables.php:34-45`),
   and login validates an email field then passes it directly to `Auth::attempt`
   (`app/Modules/Core/Http/Controllers/AuthController.php:22-31`).
6. **Different — there is no implemented generic `audit_logs` table despite its
   schema-doc entry.** Implemented audit/history data is: Candidate status
   transitions (from/to, actor, reason, override, time)
   (`app/Modules/Admissions/Database/Migrations/2026_10_03_000200_create_admissions_tables.php:26-39`);
   Result actions (Result, action, actor, note, time)
   (`app/Modules/Results/Database/Migrations/2026_10_03_000300_create_results_tables.php:118-129`);
   Result-card/default comment changes (Card/Term, actor, type, scope, text,
   time) (`app/Modules/Results/Database/Migrations/2026_10_03_000303_create_result_cards.php:42-56`);
   Staff sensitive views (Staff, viewer, Salary/Disciplinary, time)
   (`app/Modules/StaffHR/Database/Migrations/2026_10_04_001000_create_staff_hr_tables.php:78-88`);
   and Notification delivery status
   (`app/Modules/Notifications/Database/Migrations/2026_10_09_000100_create_notifications_tables.php:25-33`).
   `docs/schema.md:27-29` is therefore ahead of the code and item G must either
   implement it for new events or explicitly reconcile the schema reference.
7. **Confirmed — Teacher assignments are persistent, not per Term.** The table
   contains User, Class, and Subject only and uniquely constrains that triple
   per school
   (`app/Modules/Academics/Database/Migrations/2026_10_03_000302_create_teacher_subject_assignments.php:11-22`).
8. **Different — manual settlement is currently immediate and captures no bank
   teller details.** Any user with `manage_payments` can settle an Invoice in
   one call (`app/Modules/Payments/Services/PaymentLedger.php:56-63`), creating
   a full-amount Manual Payment and marking the Invoice Paid
   (`app/Modules/Payments/Services/PaymentLedger.php:120-143`). The controller
   accepts no bank, teller number/date, amount, or image and reports immediate
   settlement (`app/Modules/Payments/Http/Controllers/PaymentController.php:40-45`).
9. **Confirmed — Paystack keys are environment/config only today.** The service
   config reads `PAYSTACK_SECRET_KEY`
   (`config/services.php:38-41`); initialize and verify read that configured
   secret (`app/Modules/Payments/Services/PaymentLedger.php:65-69,98-106`), as
   does webhook HMAC verification
   (`app/Modules/Payments/Http/Controllers/PaystackWebhookController.php:14-19`).
10. **Different — no general role-assignment workflow or creator hierarchy
    limit exists.** Existing creation paths assign only fixed system roles:
    Candidate at registration and Student at conversion
    (`app/Modules/Admissions/Services/AdmissionPipeline.php:59-65,206-209`),
    while the bootstrap command assigns School Admin
    (`app/Modules/Core/Console/BootstrapSchoolAdmin.php:83-95`). Staff creation
    currently selects an existing User and creates only the Staff record
    (`app/Modules/StaffHR/Http/Controllers/StaffController.php:21-38`). Item B
    must introduce the allowed-role boundary with the combined account forms.
11. **Different — none of the three scope links exists.** Academic Levels have
    no Section, and Classes have no Department
    (`app/Modules/Academics/Database/Migrations/2026_10_03_000100_create_academics_tables.php:11-18,44-54`);
    Candidates have no applied Level
    (`app/Modules/Admissions/Database/Migrations/2026_10_03_000200_create_admissions_tables.php:13-24`).

### Attendance sessions verification (2026-10-18)

This is the required pre-build snapshot; the Status table below records the
post-build state.

1. **Different — attendance keys are whole-day keys.** Records are unique on
   school, Student and date, while alerts are unique on school, Arm and date;
   neither table has a session column
   (`app/Modules/Attendance/Database/Migrations/2026_10_04_000501_create_attendance_tables.php:21,35`).
2. **Different — marking, locking and alert generation are whole-day.**
   `markDay` and `lockDay` accept Arm/date but no session, and
   `generateMissingAlerts` groups only by Arm/date
   (`app/Modules/Attendance/Services/AttendanceService.php:68-158`).
3. **Different — dashboard and portal summaries are whole-day.**
   `dashboardToday` returns one nullable percentage and `portalSummary` takes
   the latest 30 records rather than 30 school dates or two sessions
   (`app/Modules/Attendance/Services/AttendanceService.php:21-42,166-178`).
4. **Confirmed — attendance is hidden by the existing payment gate.** It first
   verifies Student/ward ownership, then, unless the rule is `None`, requires
   the latest Tuition invoice to be Paid and backed by a Payment
   (`app/Modules/Payments/Services/ResultPaymentAccess.php:53-66`).
5. **Different — the alert schedule is one 16:00 weekday run and scheduler
   timezones are implicit.** The other entries are the 00:15 offer lapse and
   one-minute entrance-CBT sync; application timezone is UTC
   (`routes/console.php:11-13`; `config/app.php:68`).
6. **Confirmed — the current form teacher is stored on the Class Arm.** Marking
   requires `mark_attendance`; a manager is Section-scoped and an ordinary
   marker must match `class_arms.form_teacher_user_id`
   (`app/Modules/Academics/Services/FormTeacherAssignments.php:28-38`;
   `app/Modules/Academics/Database/Migrations/2026_10_04_000500_add_form_teacher_to_class_arms.php:12-16`).
7. **Different — there is no generic audit write target today.** Only
   feature-specific audit models/tables exist, including Result audit and Staff
   sensitive-view audit; no migration creates `audit_logs`
   (`app/Modules/Results/Models/ResultAuditLog.php:8`;
   `app/Modules/StaffHR/Models/StaffSensitiveViewAudit.php:8`).

### Class ordering and notification URL verification (2026-10-18)

This is the required pre-build snapshot.

1. **Confirmed — the hierarchy and tenant links exist; Different — only
   Classes are about to gain an order.** Classes belong to Levels and may have
   a Department; Arms belong to Classes and carry no order
   (`app/Modules/Academics/Database/Migrations/2026_10_03_000100_create_academics_tables.php:44-64`;
   `app/Modules/Academics/Database/Migrations/2026_10_10_000100_add_sections_and_departments.php:12-45`).
   Levels belong to Sections, but neither Levels nor Sections has a stored
   order. Their current screens sort by name
   (`app/Modules/Academics/Http/Controllers/AcademicStructureController.php:28-29`).
2. **Confirmed — the current Classes list has no typed/display order.** It is
   Section-scoped, eager-loads Level/Section, Department and Arms, counts Arms,
   Subjects and Students, then orders by Level id and name and paginates 20
   (`app/Modules/Academics/Http/Controllers/AcademicStructureController.php:124-156`).
   Its Add/Edit forms contain Level, name and optional Department only
   (`resources/views/academics/classes.blade.php:15-21`).
3. **Confirmed — notifications are text-only before this task.** The migration
   has no URL, the model fillable list has no URL, and `send()` accepts only
   recipients, title and body
   (`app/Modules/Notifications/Database/Migrations/2026_10_09_000100_create_notifications_tables.php:11-22`;
   `app/Modules/Notifications/Models/Notification.php:8-16`;
   `app/Modules/Notifications/Services/InAppNotifications.php:13-38`).
4. **Confirmed — every current notification creation uses that service.** New
   assignment, approved result and confirmed payment paths call
   `sendForStudent`, while candidate payment calls `send`; the service itself
   is the only place that creates `Notification` rows
   (`app/Modules/Assignments/Services/AssignmentService.php:40`;
   `app/Modules/Results/Services/ResultWorkflow.php:160`;
   `app/Modules/Payments/Services/PaymentLedger.php:227-234`;
   `app/Modules/Notifications/Services/InAppNotifications.php:21-36`).
   No existing event supplies a destination.
5. **Different — there is no complete cross-module Class-deletion query API.**
   Admissions exposes per-Arm Student counts
   (`app/Modules/Admissions/Services/StudentDirectory.php:22-41`), but Results,
   Attendance and Payments expose no small Class-usage method. Academics cannot
   implement the requested history block without adding those module-owned
   service methods; it must not query their tables directly.

### Promotion 5a verification (2026-10-18)

This is the required pre-build snapshot.

1. **Confirmed — Students have no status or waiting-source fields today;
   `class_arm_id` is required.** The Student table contains tenant, User,
   required Class Arm, optional Candidate and admission date fields. Its
   same-school Class Arm foreign key is non-null, so the present placement
   cannot represent “Awaiting admission” without retaining the last Arm and
   adding explicit state (`app/Modules/Admissions/Database/Migrations/2026_10_03_000200_create_admissions_tables.php:82-95`;
   `app/Modules/Admissions/Models/Student.php:11-29`; `docs/schema.md:75-77`).
2. **Confirmed — Results, report cards and Attendance snapshot placement.**
   Results gained their own indexed `class_arm_id`
   (`app/Modules/Results/Database/Migrations/2026_10_03_000301_add_result_snapshot_columns.php:11-16`),
   report cards store an indexed Class Arm independently of the Student's
   current placement
   (`app/Modules/Results/Database/Migrations/2026_10_03_000303_create_result_cards.php:11-28`),
   and every Attendance row stores both Student and Class Arm
   (`app/Modules/Attendance/Database/Migrations/2026_10_04_000501_create_attendance_tables.php:11-25`).
   Moving a Student therefore need not rewrite any of those historical rows.
3. **Confirmed — Student reads are concentrated in `StudentDirectory`, with
   two structural exceptions.** Its methods currently read all statuses for
   whole-school/Section counts, Arm counts, full and dashboard rosters,
   grouped Arm counts, history IDs, self/portal lookup, selected wards,
   ownership, current placement, paginated roster, names and User IDs
   (`app/Modules/Admissions/Services/StudentDirectory.php:12-107`). The Class
   model exposes an unfiltered `students` history relationship used by the
   Classes count (`app/Modules/Academics/Models/SchoolClass.php:40-43`;
   `app/Modules/Academics/Http/Controllers/AcademicStructureController.php:125-151`),
   while Payments directly selects Student IDs by visible Arm for the scoped
   dashboard total
   (`app/Modules/Payments/Services/PaymentLedger.php:29-40`). These are the
   explicit count/roster call sites that must become Active-only; history
   lookups must remain unfiltered.
4. **Confirmed — dashboard and operational rosters use that directory.** Admin
   and Head Students figures call `count`, the Teacher figure calls
   `countForArm`, Attendance uses grouped counts and both roster methods, and
   Assignments creates/checks submissions from `rosterForArm`
   (`app/Modules/Core/Services/DashboardOverview.php:16-43`;
   `app/Modules/Attendance/Services/AttendanceService.php:41,69,86,112,159,177`;
   `app/Modules/Assignments/Services/AssignmentService.php:24-35,103-105`).
5. **Confirmed — portals and ward lists intentionally resolve every linked
   Student today.** `PortalContext` finds a Student login with
   `forPortalUser`, obtains Parent ward IDs from the pivot, then loads every
   linked Student through `portalStudents`
   (`app/Modules/Portals/Services/PortalContext.php:27-68`;
   `app/Modules/Assignments/Services/ParentAccountDirectory.php:11-36`).
   These paths, plus `forUser`, `findForPortal`, `belongsToUser`, names and
   User-ID lookups, must remain status-unfiltered so Awaiting/Graduated history
   and ownership still resolve.
6. **Confirmed — payment gating and notification recipients do not depend on
   an Active roster.** Result/attendance access checks Student ownership and
   Parent linkage, then invoices (`app/Modules/Payments/Services/ResultPaymentAccess.php:35-73`);
   portal fees likewise use ownership plus Student invoices
   (`app/Modules/Payments/Services/PaymentLedger.php:48-77`). Notifications
   receive the Student User ID and find linked Parent User IDs through
   `parent_student`, rather than querying a class roster
   (`app/Modules/Notifications/Services/InAppNotifications.php:14-19`;
   `app/Modules/Assignments/Services/ParentAccountDirectory.php:32-36`).
   These reads must continue to find non-Active Students.
7. **Different — Sections have no stored order.** A Section stores its name,
   head title, arrangement marker and head assignments only; its Levels are
   currently displayed in name order (`app/Modules/Academics/Models/Section.php:13-40`;
   `docs/schema.md:31-51`). Classes alone carry the saved school-wide
   `position` sequence (`docs/schema.md:56-62`). For the two-Section rule, a
   final Section step can therefore be classified only by whether another
   Section has a Class at a higher saved Class position; no Section order may
   be inferred from IDs or names. Three-Section behavior remains an owner
   blocker.

## Status (Codex keeps current)

| Item | Status | Notes |
|---|---|---|
| Promotion revision 5a | Complete | Active/Awaiting admission/Graduated status, explicit Active operational reads, restricted historical portal access, permission- and Section-scoped transactional promotion, audit writes, Admin/Head screens, final states and arrangement lock are built. Browser checks pass at 1280px/390px; focused tests pass (5 tests / 38 assertions), and the full suite passes (38 tests / 484 assertions). Incoming 5b and registration-number 5c remain unbuilt. |
| Class order, Class management, Arrange, notification URL | Complete | One transactional Academics ordering service now owns the school-wide Class position steps, relative placement, scoped writes, audit records, arrangement markers and guarded deletion. Admin and Head references are implemented at Ready rows 7, 8, 28 and 29. `notifications.url` is optional and same-site-only. Manual 1280px/390px checks pass; focused tests pass (4 tests / 66 assertions), and the full suite passes (33 tests / 446 assertions). |
| Attendance sessions | Complete | Morning/Afternoon schema, service rules, tenant-aware five-minute alerts, generic audit writes, Section-scoped management, Ready Admin/Head screens, minimal undesigned Teacher tabs, and re-issued Student/Parent views are built. Trust-sensitive tests cover the required session, legacy, percentage, scope, audit, not-held, 30-day and payment-gate rules (7 tests / 43 assertions); full suite: 29 tests / 373 assertions. |
| A. Sections, departments, scoping | Complete | Tenant-scoped backend and Ready Sections/Levels, Departments, editable Admin Classes, and read-only head Classes screens are built. Head assignments use `section_user`; Section selection is validated server-side. Manual 1280px/390px checks pass. Pint, migration, Blade cache, Vite build, focused scope test (22 assertions), and full PHP 8.2/GD suite (22 tests / 350 assertions) pass. Class order remains outside A because its Promotion semantics are still owner-blocked. |
| B. Accounts, students, import, reg numbers | Not started | |
| C. Fees, lines, bulk invoice, manual approval | Not started | |
| D. Part-payment | Not started | |
| E. Payment gateway settings | Not started | |
| F. Bulk assignment, CBT import | In progress | Bulk Subject and Teacher assignment is complete with Admin/Head Section scope, preview/apply parity, idempotency and history-preserving removal. CBT bulk upload/delete remains. |
| G. Activity log, staff ticker events | Not started | |
