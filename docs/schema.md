# Schema Reference

> This is the single source of truth for entities and relationships. Check
> here before creating or altering any table, so schema does not drift
> module-to-module. Field lists below are the agreed *concepts* each table
> must capture, not final column-level DDL — exact types/lengths are a
> build-time decision for whoever implements the migrations.
>
> Every table listed below carries a `school_id` foreign key unless
> explicitly noted otherwise (see `ARCHITECTURE.md` §2.1).

## Core
- **schools** — id, name, domain/subdomain, branding fields
- **school_settings** — school_id, logo, colors, about_text, contact_info,
  admissions_form_target (drives the tenant-aware landing page)
- **users** — id, school_id, name, username (nullable, unique within a school),
  email (nullable, unique within a school), password,
  status; roles are assigned through `user_roles`, not a duplicate
  `user_type` field
- **roles** — id, school_id, name (e.g. Admin, Principal, Teacher, Bursar,
  Parent, Student, Candidate)
- **permissions** — id, name (e.g. `approve_result`, `confirm_payment`,
  `view_staff_salary`, `view_all_sections`, `manage_section_subjects`,
  `manage_section_teacher_assignments`, `manage_section_levels`,
  `manage_section_arms`)
- **role_permissions** — school_id, role_id, permission_id (pivot; role
  must belong to the same school)
- **user_roles** — school_id, user_id, role_id (pivot — supports a user
  holding more than one role; both records must belong to the same school)
- **audit_logs** — id, school_id, user_id, action, subject_type, subject_id,
  metadata (JSON), created_at — generic write-only audit trail; Attendance uses
  it for session-time changes and sessions-not-held additions/removals;
  incoming admissions uses `student_incoming_accepted` and
  `student_marked_not_continuing`, with the actor in `user_id`, the Student as
  subject, and the before/destination values in `metadata`

## Academics
- **sections** — id, school_id, name (e.g. Primary School, Secondary
  School), head_title (e.g. Head Teacher, Principal), classes_arranged_by
  (nullable User), classes_arranged_at (nullable timestamp); tenant-owned and name
  unique within a school. Head assignment scopes what a user may see; it
  does not create a section-specific role.
- **section_user** — id, school_id, section_id, user_id; tenant-scoped
  many-to-many assignment of people to Sections, unique per Section/User
  pair. The current UI shows one head, while the data model permits a future
  vice principal without a schema change.
- **departments** — id, school_id, name (e.g. Science, Arts); optional
  school-defined Class tag catalog, unique within a school. If empty, no
  Department field, filter, or label is shown.
- **academic_levels** — id, school_id, section_id, name (Pre-Nursery,
  Nursery, Primary, Secondary); name unique within a school. Every configured
  Level belongs to exactly one Section. The migration temporarily permits null
  only so an existing school can map its Levels without a hardcoded guess;
  section-scoped operation requires that mapping to be completed.
  Sections and Levels have no stored order; their current structure screen
  uses name order. This is why an empty Section's first Class uses the audited
  end-of-sequence fallback.
- **academic_sessions** — id, school_id, name (e.g. 2026/2027),
  start_date, end_date (`sessions` is reserved for Laravel login sessions).
  The current Session is derived from the row whose date range contains the
  current Africa/Lagos date; there is no current flag. Date ranges may overlap,
  but `end_date` must be after `start_date`.
- **terms** — id, school_id, session_id, name, start_date, end_date;
  name unique within a session. The current Term is derived from its date
  range; there is no current flag. Term ranges may overlap and need only end
  after they start. A current Session or Term cannot be deleted.
- **classes** — id, school_id, academic_level_id, department_id (nullable),
  name, position (nullable until the Section's first arrangement); name unique
  within its academic level. Position is one school-wide, system-maintained
  sequence: parallel Department Classes on the same step share it, and nobody
  types or sees it. Department is a Class tag, never an Arm.
- **class_arms** — id, school_id, class_id, name (e.g. "A", "B"),
  form_teacher_user_id (nullable; current form teacher); name unique
  within a class. Historical attendance still records `marked_by`.
- **subjects** — id, school_id, name; name unique within a school. **Current
  implementation note (2026-10-20):** Subjects are still school-wide and do
  not yet have `section_id`. The owner has decided that every Subject belongs
  to exactly one Section, but existing rows require an explicit assignment
  before a non-null same-school Section foreign key can be introduced; Subject
  ownership/edit/delete work is paused rather than inferring that mapping.
- **class_subjects** — id, school_id, class_id, subject_id (which subjects
  a class offers); pair unique within a school
- **teacher_subject_assignments** — id, school_id, user_id, class_id,
  subject_id (which teacher may enter a class's subject results); unique
  teacher/class/subject combination, linked to `class_subjects`. Assignments
  persist across Terms. The current management UI presents one Teacher per
  Class/Subject pair; removing an assignment deletes only this authorization
  row and never a historical Result.

Every Academics child and assignment carries `school_id` and a same-school
composite foreign key to each tenant-owned parent. This makes accidental
cross-school links invalid at the database layer as well as in Eloquent.

## Admissions / Candidates
- **candidates** — id, school_id, user_id (shared login), applied_level_id,
  name, phone, status (Registered,
  Payment Pending, Payment Confirmed, Exam Scheduled, Exam Taken, Result
  Pending, Passed, Failed, Offer Extended, Offer Accepted, Offer Declined,
  Offer Lapsed, Converted to Student)
- **candidate_status_history** — id, school_id, candidate_id, from_status,
  to_status, changed_by (nullable for registration), reason, is_override,
  changed_at (audit trail for every transition and explicit overrides)
- **admission_checklist_items** — id, school_id, label (configurable list,
  e.g. "Documents submitted", "Interview completed")
- **candidate_checklist** — id, school_id, candidate_id, checklist_item_id,
  is_complete, completed_by, completed_at
- **admission_offers** — id, school_id, candidate_id, offered_by, offer_date,
  response_deadline, status (Pending, Accepted, Declined, Lapsed),
  deposit_required (bool), deposit_paid (bool)

## Users (Student / Parent / Staff)
- **students** — id, school_id, user_id, class_arm_id, candidate_id
  (nullable — links back to admission record), status (`Active`,
  `Awaiting admission`, `Graduated`; existing/default `Active`),
  awaiting_from_class_arm_id (nullable same-school source Arm),
  awaiting_admission_at (nullable), admission_date. `class_arm_id` remains the
  last/current placement so historical Results, report cards, Attendance and
  invoices remain linked. Operational rosters and counts opt in to the
  explicit `active()` scope; portal ownership and history lookups do not use a
  global status scope.
  The Incoming from Primary workflow pages Awaiting students by their retained
  source Arm. Acceptance changes `status` to `Active`, assigns the selected
  destination Arm and clears the two awaiting fields without creating an
  invoice. Not continuing changes `status` to `Graduated`, keeps
  `class_arm_id` and historical rows intact, and clears the awaiting fields.
- **parents** — id, school_id, user_id (one parent profile per login)
- **parent_student** — id, school_id, parent_id, student_id (tenant-scoped
  pivot — supports multi-ward, unique parent/student pair)
- **staff** — id, school_id, user_id, role_title, employment_date,
  contract_type, status (Active, Resigned, Terminated)
- **staff_salary_history** — id, school_id, staff_id, amount_kobo (NGN),
  effective_from, effective_to (nullable) — history table, not a single
  overwritten field; salary periods for a staff member must not overlap
- **staff_disciplinary_log** — id, school_id, staff_id, type (Query,
  Warning, Commendation), date, description, issued_by, resolution
- **staff_documents** — id, school_id, staff_id, document_type, file_path
  (private storage), uploaded_at
- **staff_exit_records** — id, school_id, staff_id, exit_type (Resigned,
  Terminated, Sacked), exit_date, notice_period, notes
- **staff_sensitive_view_audit** — id, school_id, staff_id, viewed_by,
  data_type (Salary/Disciplinary), viewed_at; this is a dedicated table,
  separate from general `audit_logs`. Every sensitive-data read writes a
  row, including repeated views; it has no standard created_at/updated_at
  columns

## CBT
- **exam_types** — id, school_id, name (Internal, Entrance, Mock,
  Practice, Assessment, Promotional)
- **exams** — id, school_id, exam_type_id, subject_id (nullable for
  entrance), class_id (nullable for entrance), term_id (nullable for
  entrance), assessment_component_id (nullable for entrance), title,
  duration (minutes), scheduled_at, closes_at (nullable), status
  (Draft/Published/Closed),
  pass_mark (percentage, entrance only), result_release_type
  (Immediate/Scheduled/Manual), result_release_at, is_released,
  show_score, show_correct_answers, show_explanations, requires_approval,
  approved_for_release_at, created_by. Internal exams with a component
  feed converted scores to Results only after submission; entrance exams
  update Admissions outcome only on release.
- **questions** — id, school_id, exam_id, text, marks, explanation
  (nullable), position
- **question_options** — id, school_id, question_id, text, is_correct;
  exactly one correct option per published question, validated by CBT
  publishing (not a portable database constraint)
- **exam_submissions** — id, school_id, exam_id, candidate_id (nullable),
  student_id (nullable), started_at, submitted_at, raw_score,
  max_score, converted_score (nullable), status (In Progress/Submitted);
  one attempt per participant/exam
- **exam_answers** — id, school_id, exam_submission_id, question_id,
  selected_option_id (nullable), flagged (boolean, default false); one
  answer/flag state per question per attempt

## Result Management
- **assessment_components** — id, school_id, name (CA1, CA2, Assignment,
  Exam), max_score — defines **how a total is built**
- **grading_schemes** — id, school_id, name — defines **what grade a
  total maps to**
- **grade_bands** — id, grading_scheme_id, min_score, max_score, grade,
  remark; tenant-owned with school_id; scores represent percentage boundaries
- **result_templates** — id, school_id, academic_level_id, assessment
  component set + grading scheme assignment (ties the two concepts above
  to a level/class); optional class_id overrides the level-wide template;
  scope_key is unique per school (`level:{id}` or `class:{id}`);
  show_subject_remarks boolean defaults true and controls whether the card
  shows each subject's matching `grade_bands.remark` (no teacher remark input)
- **result_template_components** — id, school_id, result_template_id,
  assessment_component_id, position (the ordered component set)
- **results** — id, school_id, student_id, class_arm_id, subject_id, term_id,
  result_template_id (snapshots class/template for this term), status
  (Draft, Submitted, Approved, Rejected), submitted_by, approved_by,
  rejection_reason, total_score, percentage, grade, remark, locked_at;
  Approved is the locked state
- **result_items** — id, school_id, result_id, assessment_component_id,
  score (the individual CA1/CA2/Exam/etc. entries that sum to the result
  total)
- **result_audit_log** — id, school_id, result_id, action (Upload, Edit,
  Submit, Approve, Reject), user_id, note, created_at
- **result_cards** — id, school_id, student_id, term_id, class_arm_id
  (term placement snapshot), compiled_at, class_teacher_comment,
  principal_comment, proprietor_comment, next_term_begins (nullable date) —
  a **term-level** wrapper around a student's full set of subject
  `results`, holding the comment fields and metadata that belong to the
  whole report card rather than to any single subject. This is what gets
  rendered as the printable document (see "Report Card Rendering" below),
  not the `results` table itself.
- **term_default_comments** — id, school_id, term_id, section_id, comment_type
  (Principal, Proprietor), comment_text — optional standard remark that
  supplies the effective remark at compile/render time whenever a student's
  personalized `result_cards.principal_comment` / `proprietor_comment` is
  null. It can still be overridden per student afterward. **Resolved:** the school chooses, per term and per
  student, whether to use the default or a personalized comment — both
  are supported simultaneously, there is no system-level mode switch (see
  `ARCHITECTURE.md` §12).
- **result_card_comment_audit** — id, school_id, result_card_id (nullable
  for term defaults), term_id, section_id, user_id, comment_type, scope (Default or
  Personalized), comment_text, created_at; every comment change records
  the author and effective text, including clearing an override.

## Report Card Rendering (New — Phase 2 Addition)

A `result_card` is compiled from a student's approved `results` for a term
plus the `result_cards` comment/metadata row, and rendered as a PDF:

- **Source of branding**: school logo, name, address, and motto are pulled
  from `school_settings` (already defined under Core) — not duplicated
  onto the result card tables.
- **Constraint**: must render as a single A4 page. See `ARCHITECTURE.md`
  §11 for the rendering approach and how the one-page constraint is
  enforced.
- **Role mapping — resolved**: "Proprietor" is **not** a distinct row in
  the `roles` table. It is the `School Admin` role, granted the
  `add_proprietor_comment` permission (separate from `add_principal_comment`,
  which the same or a different user may hold) — see `ARCHITECTURE.md`
  §12.
- **Section head label**: the printed card resolves its second comment label
  from the card placement's Section `head_title`; stored Principal-comment
  permission and data semantics remain unchanged.

## Attendance
- **attendance_records** — id, school_id, student_id, class_arm_id, date,
  session (Morning, Afternoon; nullable only for legacy whole-day rows), status
  (Present, Absent, Late, Excused), marked_by, locked (bool); student/date/session
  unique per school. A null legacy session counts as both sessions and is never
  backfilled; new split-session writes are refused when that Student/date has a
  legacy row, avoiding nullable-unique ambiguity in MySQL and SQLite.
- **attendance_alerts** — id, school_id, class_arm_id, date, session (Morning,
  Afternoon; nullable only for legacy alerts), resolved (bool); unique per
  arm/date/session and resolved when that session's full roll is marked or the
  Section session is marked not held.
- **attendance_alert_settings** — id, school_id, section_id, morning_due_at,
  afternoon_due_at, updated_by, created_at, updated_at; one row per Section.
  Missing rows resolve to 08:00 and 14:00 Africa/Lagos.
- **attendance_sessions_not_held** — id, school_id, section_id, date, session,
  marked_by, created_at; unique per Section/date/session. These sessions raise
  no alerts and are excluded from percentages; removal is limited to today or
  future dates.

## Assignments (Mode A — Phase 3)
- **assignments** — id, school_id, subject_id, class_arm_id, title,
  description, due_date, created_by; same-school foreign keys, indexed by
  class arm/due date
- **assignment_submissions** — id, school_id, assignment_id, student_id,
  status (Submitted, Not Submitted, Late), marked_by (nullable), marked_at
  (nullable); unique assignment/student pair. Rows are created for the
  full roster when an assignment is published and updated by a teacher's
  tick. No student file/text submission exists in Mode A by design.

## E-Notes
- **enotes** — id, school_id, subject_id, class_id, title, file_path
  (nullable, private local storage) or link (nullable, HTTPS), uploaded_by,
  created_at, updated_at; exactly one content source required by the service.
  Same-school foreign keys and indexed class/subject filtering.

## Payments / Bursar
- **fee_items** — id, school_id, name; unique name per school. The catalog
  stores names only; prices live on term schedule lines. An item referenced
  by a schedule or issued invoice cannot be removed.
- **fee_schedule_lines** — id, school_id, term_id, scope_type
  (level/class/department), exactly one of academic_level_id/class_id/
  department_id, fee_item_id, amount_kobo (unsigned bigint integer kobo),
  is_required, timestamps. Matching scopes add; duplicate items resolve by
  class, then department, then level specificity. A permitted user may copy
  lines from an earlier Term to all scopes or one selected scope. Copy
  preserves amounts and required flags, skips existing target keys and missing
  scopes, never touches issued invoices, and is idempotent; one generic audit
  entry records source, target and counts.
- **invoices** — id, school_id, exactly one of student_id/candidate_id,
  term_fee_student_id (populated only for a term-fee invoice), term_id
  (nullable for admissions), purpose (display snapshot), kind (term_fee,
  part_payment, one_off, registration, offer_deposit), creation_source
  (legacy, bulk, single, automatic), amount_kobo (unsigned bigint integer
  kobo, NGN), due_date, status (Unpaid/Paid), created_by. The unique
  school/term/term_fee_student key prevents two term invoices under races;
  only `Active` Students are eligible for automatic/bulk invoicing.
- **invoice_lines** — id, school_id, invoice_id, fee_item_id (nullable),
  description and amount_kobo snapshots, is_required, timestamps. The invoice
  total equals its lines; later schedule changes do not alter these snapshots.
- **invoice_generation_runs** — id, school_id, term_id, scope_type, scope_id
  (nullable for whole school), due_date, students_in_scope, created_count,
  skipped_count, no_class_count, no_schedule_count, created_by, timestamps.
  Bulk creation processes eligible Students in chunks of 100.
- **payment_attempts** — id, school_id, invoice_id, reference (unique),
  amount_kobo (unsigned bigint integer kobo), initiated_by,
  authorization_url (nullable), status (Pending/Verified/Failed),
  created_at, updated_at. These are gateway initiation attempts, separate
  from settled `payments`; pending attempts never count as revenue.
- **payments** — id, school_id, invoice_id (unique: no double settlement),
  amount_kobo (unsigned bigint integer kobo), method (Online/Manual), gateway_reference (nullable,
  unique), confirmed_by (nullable for verified gateway), paid_at,
  receipt_number (nullable column, unique per school, generated on
  confirmation), created_at, updated_at. **Receipts are not a separate
  table:** the Bursar receipt listing is generated from settled `payments`
  joined to their invoices, using `receipt_number` as the receipt ID.
- **expenses** — id, school_id, date, category, amount_kobo (unsigned bigint
  integer kobo), vendor,
  receipt_file_path (nullable, private), approved_by (nullable), created_by
- **school_settings.result_access_payment_rule** — None or
  TermInvoicePaid, default TermInvoicePaid; checked by a server-side
  Payments service on every future Student/Parent result/attendance fetch.

## Notifications
- **notifications** — id, school_id, user_id, channel (In App for the MVP),
  title, body, url (nullable same-site relative path, maximum 2048 characters),
  read_at, created_at, updated_at; indexed by tenant/user/read state for the
  paginated inbox. URL values must start with one `/` and may not contain a
  scheme, `//`, a backslash, or a control character.
- **notification_logs** — id, school_id, notification_id, status (Sent,
  Failed), created_at —
  structured now so Phase 2 channels (SMS/WhatsApp) can plug in without a
  schema change

## Relationship Notes — Resolved

These were previously open questions. Both are now final. See
`ARCHITECTURE.md` §9 for the full reasoning.

- `students.candidate_id` is nullable **by design**: a student can arrive
  via the Candidate → Admission Offer → Convert pipeline (`candidate_id`
  set), or be enrolled directly by an Admin without an entrance exam
  (`candidate_id` null). Both are valid paths.
- `students`, `parents`, `staff`, and `candidates` are confirmed as
  separate tables, each linked to a shared `users` table via `user_id` for
  authentication — not a single `users` table with a `type` column.

## Tenancy Implementation Note

Every table marked tenant-owned above gets its `school_id` set and
enforced automatically via the `BelongsToTenant` trait described in
`ARCHITECTURE.md` §2.1 — models should not set `school_id` manually in
application code.
