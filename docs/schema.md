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
  admissions_form_target (drives the tenant-aware landing page),
  result_access_payment_rule, one_step_payment_approval,
  proprietor_comment_title (default `Proprietor's comment`); Paystack public key,
  encrypted secret key, separately stored secret last four, derived mode,
  changed_by (nullable same-school User) and changed_at. The secret is hidden
  from model serialization and encrypted with the application key. Registration
  settings are include-School/Section/Level/Year flags, separator (hyphen,
  slash, dot or none), school code, minimum zero-padding length (3–6; a larger
  sequence value grows and is never truncated), 2- or 4-digit calendar-year
  style, the next-number floor, locked movement policy and its lock time.
  Parts have the fixed order School, Section, Level, Year, Number. Format
  changes apply only to numbers issued afterward.
  Landing/admissions text additionally stores WhatsApp number, opening hours,
  key admission dates, admissions-reopen text, privacy contact email and last-
  updated date, and retention text for children who are not admitted.
- **users** — id, school_id, name, username (nullable, unique within a school;
  new values are normalized lower-case and checked case-insensitively), email
  (nullable, unique within a school), email_verified_at (nullable; recovery is
  enabled only after confirmation), password, must_change_password (default
  false), last_signed_in_at (nullable), status (`active` or `disabled`); roles are assigned through
  `user_roles`, not a duplicate `user_type` field. User-chosen passwords have
  a minimum of eight characters and are refused when they equal the username,
  temporary/current password or normalized school name, contain only digits,
  or appear in the bounded common-password list. Generated temporary passwords
  remain 12 characters. The last active School Admin cannot lose that role.
  Staff role/Section changes and password resets revoke database sessions and
  the remember token. Disabling a staff sign-in or recording a Staff HR exit
  also revokes both; re-enabling sign-in does not change employment status.
- **account_password_reset_tokens** — id, school_id, user_id, SHA-256 token
  hash (unique; plaintext is never stored), expires_at, used_at and timestamps.
  A new request marks older unused rows used. Links expire after the configured
  60 minutes and are single-use. Laravel's stock email-keyed
  `password_reset_tokens` table remains unused because it is not tenant-safe.
- **roles** — id, school_id, name (e.g. Admin, Principal, Teacher, Bursar,
  Parent, Student, Candidate)
- **permissions** — id, name (e.g. `approve_result`, `confirm_payment`,
  `view_staff_salary`, `view_all_sections`, `manage_section_subjects`,
  `manage_section_teacher_assignments`, `manage_section_levels`,
  `manage_section_arms`, `manage_section_result_settings`,
  `manage_payments`, `approve_payments`,
  `manage_payment_gateway`, `create_staff`, `manage_staff_access`,
  `view_sign_in_rules`, `view_activity_log`, `view_section_staff`,
  `record_section_discipline`, `view_students`, `create_students`,
  `manage_student_accounts`, `manage_registration_numbers`,
  `change_registration_numbers`, `view_parents`, `view_all_parents`,
  `manage_parent_accounts`, `manage_exam_sessions`)
- **role_permissions** — school_id, role_id, permission_id (pivot; role
  must belong to the same school)
- **user_roles** — school_id, user_id, role_id (pivot — supports a user
  holding more than one role; both records must belong to the same school)
- **Default role catalog** — `DefaultRoleCatalog` is the single source used
  by real-school bootstrap, demo seeding and the additive existing-role
  migration. It creates School Admin, Principal, Teacher, Bursar, Student and
  Parent roles. Defaults are:
  - School Admin: `view_dashboard`, full Academics/profile/Admissions/Results/
    CBT/Attendance/Assignments/E-Notes/Payments/Staff permissions,
    `view_all_sections`, `manage_promotions`, `create_staff`,
    `manage_staff_access`, `view_sign_in_rules`, `view_activity_log`, all four Student-management
    permissions, `change_registration_numbers`, `view_parents`,
    `view_all_parents`, `manage_parent_accounts` and `manage_exam_sessions`.
  - Principal: `view_dashboard`, `approve_results`,
    `add_principal_comment`, `manage_attendance`,
    `view_attendance_alerts`, `manage_section_classes`,
    `manage_section_levels`, `manage_section_arms`,
    `manage_section_subjects`, `manage_section_teacher_assignments`,
    `manage_section_result_settings`, `manage_promotions`, the Section-scoped
    `change_registration_numbers`, `view_section_staff` and
    `record_section_discipline`, Section-scoped read-only `view_parents` and
    Section-scoped `manage_exam_sessions` permissions.
  - Teacher: `view_dashboard`, `enter_results`, `mark_attendance`,
    `mark_assignments` and `upload_enotes`.
  - Bursar: `view_dashboard`, `manage_payments`, `manage_fee_schedule`,
    read-only `view_parents` and `view_all_parents`.
  - Student and Parent: no permission rows; portal access is resolved from
    their tenant-scoped Student or Parent/ward profile.
  The migration only adds missing defaults to roles that already exist and
  never removes school-managed grants. Staff-account assignment uses the rank
  School Admin > Principal (Head) > Bursar > Teacher; only School Admin receives
  the three staff-account permissions by default.
- **audit_logs** — id, school_id, user_id, actor_name (nullable snapshot),
  actor_role (nullable snapshot), module (nullable), action, description
  (nullable plain language), subject_type, subject_id,
  metadata (JSON), created_at — generic write-only audit trail; Attendance uses
  it for session-time changes, sessions-not-held additions/removals and
  form-teacher assignment/replacement (including the previous and new User);
  Result configuration uses it for template, grade-band and assessment-
  component edits/deletes; Staff HR uses `staff_employment_record_updated`
  with before/after role title, contract type and employment date; an
  Admission-requirements override uses `admission_checklist_overridden` with
  the written reason and outstanding-item count in metadata;
  incoming admissions uses `student_incoming_accepted` and
  `student_marked_not_continuing`, with the actor in `user_id`, the Student as
  subject, and the before/destination values in `metadata`. Every new row made
  through `AuditRecorder` stores the actor's current name and role snapshot;
  historical rows remain nullable and use readable action/module fallbacks.
  The Admin Activity log uses tenant/created-at and tenant/module/created-at
  indexes, paginates 25 rows newest-first, and never renders metadata.

## Academics
- **sections** — id, school_id, name (e.g. Primary School, Secondary
  School), head_title (e.g. Head Teacher, Principal), `learner_term`
  (`Pupil` or `Student`, default `Student`), classes_arranged_by
  (nullable User), classes_arranged_at (nullable timestamp),
  legacy registration_prefix and registration_code (nullable 1–6 upper-case
  letters/numbers used by the composable pupil-number format); tenant-owned and name
  unique within a school. Head assignment scopes what a user may see; it
  does not create a section-specific role.
- **section_user** — id, school_id, section_id, user_id; tenant-scoped
  many-to-many assignment of people to Sections, unique per Section/User
  pair. The current UI shows one head, while the data model permits a future
  vice principal without a schema change. For teacher pickers this direct link,
  a subject-teacher assignment in the Section, or a form-teacher arm in the
  Section marks the teacher as belonging to that Section.
  Staff-account rules assign exactly one Section to a Principal/Head, one or
  more Sections to a Teacher, and none to a School Admin or Bursar.
- **departments** — id, school_id, name (e.g. Science, Arts); optional
  school-defined Class tag catalog, unique within a school. If empty, no
  Department field, filter, or label is shown.
- **academic_levels** — id, school_id, section_id, name (Pre-Nursery,
  Nursery, Primary, Secondary), legacy registration_prefix and registration_code
  (nullable 1–6 upper-case letters/numbers), assessed_in_person (boolean,
  default false); name unique within a school. An in-person Level still uses an
  exam session but its applicants receive no Candidate login or online exam. Every configured
  Level belongs to exactly one Section, chosen on creation and immutable
  afterward; a Level assigned to the wrong Section is deleted and recreated.
  The migration temporarily permits null
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
  name, position (nullable until the Section's first arrangement); the database
  retains its same-Level unique key, while every application create/rename path
  additionally enforces a trimmed, case-insensitive school-wide name. Position
  is one school-wide, system-maintained
  sequence: parallel Department Classes on the same step share it, and nobody
  types or sees it. Department is a Class tag, never an Arm.
- **class_arms** — id, school_id, class_id, name (e.g. "A", "B"),
  form_teacher_user_id (nullable; current form teacher); name unique
  within a class. A form-teacher candidate is an active User who holds
  `mark_attendance` and belongs to the arm's Section by the shared teacher-
  Section rule. A Head sees only their Section's candidates; Admin sees those
  candidates first and then other active attendance teachers. Users with
  `manage_attendance` assign within their Section scope; assigning a replacement overwrites this current pointer and
  writes the previous/new User IDs to `audit_logs`. Historical attendance
  still records `marked_by`.
- **subjects** — id, school_id, section_id, name; every Subject belongs to
  exactly one same-school Section and its name is unique within that Section.
  The Section is chosen on creation and immutable afterward; a Subject assigned
  to the wrong Section is deleted and recreated.
  Existing data is prepared between the two schema migrations with
  `oakfield:migrate-subject-sections`: one-Section Subjects are assigned there,
  cross-Section Subjects are split and class-linked references are repointed,
  and unused Subjects are copied to every Section. The command supports a
  read-only `--dry-run` and requires `--actor=<user-id>` for its audited apply.
- **class_subjects** — id, school_id, class_id, subject_id (which subjects
  a class offers); pair unique within a school. The Subject must belong to the
  same Section as the Class; this is enforced by every write service.
- **teacher_subject_assignments** — id, school_id, user_id, class_id,
  subject_id (which teacher may enter a class's subject results); unique
  teacher/class/subject combination, linked to `class_subjects`. Assignments
  persist across Terms. The current management UI presents one Teacher per
  Class/Subject pair. Candidates must be active, hold a teaching permission,
  and, for a Head, belong to that Section; Admin may choose an active qualified
  teacher from the Other teachers group. Removing or replacing an assignment
  deletes only this authorization row and never a historical Result. Each
  change records actor, Class, Subject, previous teacher and new teacher in
  `audit_logs`.

Every Academics child and assignment carries `school_id` and a same-school
composite foreign key to each tenant-owned parent. This makes accidental
cross-school links invalid at the database layer as well as in Eloquent.

## Admissions / Candidates
- **applications** — id, school_id, public_reference, child names/date of birth/
  gender, applied_level_id, previous_school, guardian contact/address,
  normalized username plus nullable `active_username_key`, status (Awaiting
  payment/Registered/Expired), fee_amount_kobo, currency (NGN), hashed opaque
  browser token, consent timestamp/version, expiry/registration/check/email
  timestamps, confirmed_attempt_id, nullable exam_session_id,
  exam_notified_at, assessment outcome/note/actor/time and attention flags. The tenant/live-
  username unique key reserves a username until expiry or deletion. No User or
  Candidate exists at this stage. A matching unpaid, unexpired username may
  resume only for the same guardian email; saved Application details win.
- **exam_sessions** — id, school_id, name, starts_at, venue_note,
  is_in_person, nullable section_id, created_by and timestamps. Admin may use a
  school-wide or Section session; Heads are limited to assigned Sections.
  Applications retain a nullable reference to the session, so its date/time is
  never copied to each applicant. A session with assigned Applications cannot
  be deleted. Assignment/move/removal and session create/edit/delete actions
  write `audit_logs`; `exam_notified_at` records the latest successful session
  email.
- **application_payment_attempts** — id, school_id, application_id, nullable
  unique active_application_id, globally unique Paystack reference, amount_kobo,
  currency, authorization_url, status (Pending/Verified/Failed), last activity
  and late-success flag. These rows are read-only Application-fee entries in
  Bursar Payments and never participate in tuition balances.
  `active_application_id` identifies the one reusable checkout. A Pending
  checkout older than the configured 30 minutes may be released by setting
  that column null while the immutable reference remains available to callback,
  webhook, scheduled re-check and manual Check again.
- **school_settings admissions fields** — admissions_open,
  application_fee_enabled, application_fee_kobo and admissions_privacy_text,
  plus the landing/admissions text fields listed under Core.
- **candidates** — id, school_id, user_id (shared login), nullable unique
  application_id, applied_level_id,
  name, phone, status (Registered,
  Payment Pending, Payment Confirmed, Exam Scheduled, Exam Taken, Result
  Pending, Passed, Failed, Offer Extended, Offer Accepted, Offer Declined,
  Offer Lapsed, Converted to Student)
  Build 2 creates this row only when Admin issues exam-day credentials for a
  paid, session-assigned online-Level Application. Candidate passwords are
  transient plaintext in an in-memory PDF and stored only as hashes.
- **candidate_status_history** — id, school_id, candidate_id, from_status,
  to_status, changed_by (nullable for registration), reason, is_override,
  changed_at (audit trail for every transition and explicit overrides). An
  Admin conversion override requires a 10–500 character reason; the same
  reason is retained here and in the generic audit entry.
- **admission_checklist_items** — id, school_id, label (configurable list,
  e.g. "Documents submitted", "Interview completed")
- **candidate_checklist** — id, school_id, candidate_id, checklist_item_id,
  is_complete, completed_by, completed_at
- **admission_offers** — id, school_id, candidate_id, offered_by, offer_date,
  response_deadline, status (Pending, Accepted, Declined, Lapsed),
  deposit_required (bool), deposit_paid (bool)

## Users (Student / Parent / Staff)
- **students** — id, school_id, registration_number (nullable, upper-case,
  up to 64 characters; letters, numbers, hyphens, slashes and dots; unique
  within the school), user_id, class_arm_id, candidate_id
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
- **registration_number_sequences** — id, school_id, sequence_key (`school`
  for the school-wide counter or `prefix:<COMPOSED PREFIX>` for the per-prefix policy),
  next_number, timestamps; unique per school/key. Allocation locks this row in
  a transaction and relies on the Student unique constraint with retry.
- **student_registration_number_history** — id, school_id, student_id,
  old_number, new_number, sequence_value (nullable for a typed number),
  issued_year, section_code and level_code (the allocation snapshot used for
  later movement-policy decisions), changed_by, changed_at. `new_number` is
  unique per school, permanently
  reserving every issued number. Each change also writes `audit_logs`.
  Record payment searches the tenant-scoped current number and both historical
  number columns case-insensitively. The current number is displayed on report
  cards, receipts, invoice details and the one-time Student sign-in slip; a
  missing number renders no placeholder.
  Section codes are unique school-wide when used; Level codes are unique within
  a Section when the Section part is present, otherwise school-wide. Selected
  parts require every corresponding code. The full current number and every
  historical number remain school-wide unique even if two compositions could
  otherwise produce the same text.
- **student_imports** — id, school_id, owner_id, status, original_name,
  stored_path (nullable private CSV path), file_hash (SHA-256), row_count,
  ready_count, skipped_count, error_count, report (JSON without passwords),
  confirmed_at (nullable), expires_at and timestamps. Import records are
  tenant-owned and owner-scoped. A confirmed SHA-256 blocks that same file in
  the same school; unconfirmed/failed files and other schools do not. Possible
  same-normalized-name/class matches are retained as report warnings and need
  a server-checked acknowledgement before Confirm. Checked CSV files are
  deleted after confirm or after 24 hours by the hourly
  `oakfield:cleanup-student-imports` schedule; reports remain, but one-time
  credentials exist only in the first confirmation response. The fixed CSV
  columns are `full_name`, `class`, `arm`, `registration_number`, `username`,
  `email`, `parent_name`, `parent_username`, `parent_email`. Class resolves by
  its ordinary staff-facing name when unique, or by the displayed
  Section/Level/Class path when names repeat; Arm resolves separately and may
  be blank only when the class has one Arm. Section and Level are derived.
  The browser renders the response as transient A4 credential slips (eight
  per page); no credential spreadsheet or server-side credential file exists.
- **parents** — id, school_id, user_id (one parent profile per login)
- **parent_student** — id, school_id, parent_id, student_id (tenant-scoped
  pivot — supports multi-ward, unique parent/student pair)
  The Parents directory adds no new table: Admin sees and manages all tenant
  parents, a Head sees only parents and linked children in their Section, and
  Bursar has school-wide read-only access. Link/unlink changes only the
  `parent_student` pivot and writes an actor-snapshotted `audit_logs` row.
- **staff** — id, school_id, user_id, role_title, employment_date,
  contract_type, status (Active, Resigned, Terminated). Role title, contract
  type and employment date are editable under `manage_staff_records` and are
  audit-logged. Status is read-only on that form and changes only through an
  Exit record.
- **staff_salary_history** — id, school_id, staff_id, amount_kobo (NGN),
  effective_from, effective_to (nullable) — history table, not a single
  overwritten field; salary periods for a staff member must not overlap.
  Forms display and accept naira, then convert the validated decimal string to
  integer kobo without floating-point arithmetic.
- **staff_disciplinary_log** — id, school_id, staff_id, type (Query,
  Warning, Commendation), date, description, issued_by, resolution. Entries
  are append-only; a correction is recorded as a new entry. A Head may view
  and record entries only for current staff linked to any of their Sections
  through `section_user`, a class/subject teaching assignment, or a form-
  teacher arm. The shared Head Staff scope excludes Admin/broad-section users
  and every holder of the Head Staff permission, and returns 404 outside the
  scope. Head payloads allow-list profile, teaching and discipline fields and
  never load salary, documents, exits or editable employment details.
- **staff_documents** — id, school_id, staff_id, document_type, file_path
  (private storage), uploaded_at
- **staff_exit_records** — id, school_id, staff_id, exit_type (Resigned,
  Terminated, Sacked), exit_date, notice_period, notes. An exit is immediate,
  may not be future-dated, disables the linked User and revokes all sessions
  and remember tokens in the same transaction.
- **staff_sensitive_view_audit** — id, school_id, staff_id, viewed_by,
  data_type (Salary/Disciplinary), viewed_at; this is a dedicated table,
  separate from general `audit_logs`. Every sensitive-data read writes a
  row, including repeated views; it has no standard created_at/updated_at
  columns

## CBT
- **exam_types** — id, school_id, name, feeds_results (boolean, default false),
  active (boolean, default true). Admin manages the list; a type referenced by
  an exam is deactivated rather than deleted. The School exam type is backfilled
  or created with feeds_results enabled; other and legacy types remain off until
  Admin enables them.
- **exams** — id, school_id, exam_type_id, subject_id (nullable for
  entrance), class_id (nullable for entrance), term_id (nullable for
  entrance), assessment_component_id (nullable for entrance), title,
  duration (minutes), default_question_marks (whole-number mark used by a
  single-letter bulk-upload answer; default 1), scheduled_at, closes_at (nullable),
  closes_automatically (boolean, default true), status
  (Draft/Published/Closed),
  pass_mark (percentage, entrance only), result_release_type
  (Immediate/Scheduled/Manual), result_release_at, is_released,
  show_score, show_correct_answers, show_explanations, requires_approval
  (legacy, no longer enabled), approved_for_release_at (legacy), created_by.
  `manage_cbt` users with `view_all_sections` manage every exam; other holders
  manage only class-linked exams in their `section_user` Sections. Classless
  and Entrance exams are Admin-only. Only types with feeds_results enabled may convert
  submitted attempt scores into Results, and only through the previewed staff
  conversion action; Draft component scores are replaced while Submitted or
  Approved Results and pupils without submitted attempts are skipped. Entrance
  exams update Admissions outcome only on release.
- **exam_level** — school_id, exam_id, level_id; additive many-to-many scope
  for Entrance exams. A linked Entrance exam is available only to candidates
  applying to one of its Levels. No rows means the legacy school-wide scope.
- **questions** — id, school_id, exam_id, text, marks, explanation
  (nullable), position
- **question_options** — id, school_id, question_id, text, is_correct;
  one or more correct options, whole-number option marks and at least one wrong
  option per published question are validated by CBT publishing (not a portable
  database constraint). `marks` is zero for wrong options.
- **exam_submissions** — id, school_id, exam_id, candidate_id (nullable),
  student_id (nullable), attempt_number (default 1), started_at, submitted_at, raw_score,
  max_score, converted_score (nullable), status (In Progress/Submitted);
  candidates retain one attempt per exam; student attempts are unique by
  school, exam, pupil and attempt number
- **exam_resit_grants** — id, school_id, exam_id, student_id, granted_by,
  reason, opens_until, used_attempt_id (nullable), withdrawn_at (nullable),
  timestamps. One unused, unexpired grant is permitted per pupil/exam by the
  transaction-locked service; each grant creates exactly one separately
  numbered attempt and retains its audit/history row.
- **exam_answers** — id, school_id, exam_submission_id, question_id,
  selected_option_id (nullable), flagged (boolean, default false); one
  answer/flag state per question per attempt. `selected_option_id` is retained
  as the first/legacy selection for backward compatibility.
- **exam_answer_selections**: id, school_id, exam_answer_id,
  question_option_id; one row per selected option, unique by answer and option.
  Autosave replaces this set atomically.

Question-option marks are whole numbers: correct options carry at least one
mark and wrong options carry zero. A question's `marks` remains the derived sum
of its correct-option marks. Published questions require at least two options,
at least one correct option and at least one wrong option. One-correct-option
questions accept one answer; multi-correct questions accept up to that many
selections. Scoring sums selected correct-option marks without subtracting for
wrong selections.

Bulk Question CSV uses `Question`, `Option A` through `Option F`, and
`Correct Answer`. A lone letter uses `exams.default_question_marks`; a lone
`LETTER=mark` overrides it; multiple correct letters require an explicit
whole-number mark for each. The two earlier 13-column layouts (`Marks for A`
and `Option A Score`) remain import-compatible, but layouts cannot be mixed.

CBT questions are auto-marked multiple-choice questions only. Publishing moves
a Draft to Published. An exam counts as closed after the one-way staff End
action, or when `closes_automatically` is true and `closes_at` has passed;
time-derived closing is evaluated on every relevant request and does not depend
on the scheduler. For automatic closing, an attempt ends at the earlier of its
personal duration and the exam close time; stay-open exams use the personal
duration only. A granted additional attempt can start after either form of
closing and ends at the earlier of the normal personal duration and the grant's
`opens_until`; ending the exam does not cut that attempt short. The existing
30-second server grace applies to answer receipt.
Manual end, an automatic deadline, or a personal timer expiry submits and scores
all affected in-progress attempts idempotently from their saved answers. Staff
live progress is class-wide (all Arms), Active pupils only, and Section-scoped.
Release is controlled
directly by Immediate/Scheduled/Manual policy with no approval step. An exam can
be deleted only while Draft and only when it has no submissions. The
`view_cbt_results` permission is granted by default to School Admin, Principal
and Teacher: Admin sees school-wide results, Principal sees assigned Sections,
and Teacher sees only closed exams for persistent class/subject teaching pairs.
For student exams, live progress uses the latest numbered attempt and derives
Absent only when the exam counts as closed and the pupil has no attempt. Score
conversion and staff Results default to the latest Submitted attempt, so an
earlier Submitted score continues to count while a granted attempt is still in
progress. Per-question review remains tied to the exact attempt id.

## Result Management
- **assessment_components** — id, school_id, section_id, name (CA1, CA2,
  Assignment, Exam), max_score — defines **how a total is built**. The
  `(school_id, section_id)` same-school foreign key is indexed and the name is
  unique within the Section. Its Section is required, chosen at creation and
  immutable. Admin manages any Section; a Head with
  `manage_section_result_settings` manages only their assigned Section. It may
  be edited until a `result_item` uses it; deletion also requires that no
  template references it.
- **grading_schemes** — id, school_id, section_id, name — defines **what
  grade a total maps to**. The UI calls this a Grade scale. The
  `(school_id, section_id)` same-school foreign key is indexed and the name is
  unique within the Section. Its Section is required and immutable. One
  Section may own multiple scales. Admin manages any Section and a Head only
  their assigned Section. An unused scale may be edited/deleted; a Result under
  any template using it locks edits/deletion, and deletion also requires no
  template reference.
- **grade_bands** — id, grading_scheme_id, min_score, max_score, grade,
  remark; tenant-owned with school_id; bounds are inclusive whole-number
  percentages from 0 to 100. Adjacent bands meet as 20 then 21. Invalid ranges
  and overlaps are refused, but gaps are allowed while an Admin builds or
  corrects an incomplete scale one band at a time.
  Bands remain editable only while no Result uses a template under their
  scheme. A scale is complete only when its bands cover 0 through 100 with no
  gap or overlap; an incomplete scale is labelled with every uncovered range
  and cannot be used to submit a Result. Grade lookup rounds
  the percentage to the nearest whole number, with halves rounded up.
- **result_templates** — id, school_id, section_id, nullable
  academic_level_id, nullable class_id, assessment component set + grading
  scheme assignment, `scope_key`, nullable `active_scope_key`, nullable
  retired_at, nullable retired_by. A null Level and Class means all Levels in the Section;
  a Level with no Class is Level-wide; a Class is the most-specific override
  and must belong to its selected Level. `scope_key` preserves the historical
  scope (`section:{id}`, `level:{id}` or `class:{id}`); `active_scope_key`
  repeats it while active and becomes null on retirement. The unique
  `(school_id, active_scope_key)` index therefore allows only one active
  template per scope while permitting retired history. Resolution for a class
  arm is active Class, then active Level, then active Section-wide. The
  Section, Level, Class and retiring User use same-school composite foreign
  keys; Section is required and immutable.
  `show_subject_remarks`, `show_overall_total`, `show_average` and
  `show_position` booleans default true. The three summary switches control
  the report-card overall total, average/overall percentage and exact-Arm
  position consistently on staff web, PDF and Student/Parent portal cards;
  they remain editable after Results use the template. `show_subject_remarks`
  controls whether the card
  shows each subject's matching live `grade_bands.remark` (no teacher remark
  input). The Result snapshots `grade` and `remark`, but the printed subject-
  remark column resolves the current band from the template's Grade scale using
  the same rounded percentage as submission. A template may be edited/deleted
  only before any Result uses it. A used template may be retired only when no
  current-Term Result uses it; retirement is read-only, audited and frees its
  active scope for a replacement. A scope may change only before a Result uses
  the template. Its Grade scale and every component must belong to the same
  Section. Every Result retains its stored `result_template_id`; retirement or
  a newer/more-specific template never changes existing Results or report
  cards.
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

Existing populated databases use the staged Result-configuration ownership
migration: Migration A adds nullable Section keys; dry-run and then apply
`php artisan oakfield:migrate-result-sections --actor=<admin-user-id>` to
assign/split data and repoint templates, pivots, Result items and class-linked
CBT exams; Migration B refuses to make the keys required while any row remains
unassigned. The command is transactional, audit-marked and refuses a second
real run.

Result-template Section scope uses a separate mechanical two-stage migration:
Migration A adds a nullable `section_id`, makes `academic_level_id` nullable,
and backfills Section from each existing template's Level; Migration B refuses
to make `section_id` required while any template remains unassigned. No command
is needed because the backfill has one unambiguous source.
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
- **sections.principal_comment_mode / proprietor_comment_mode** — `single`
  (the current default) or `range`, independently selected for each Section.
- **comment_ranges** — id, school_id, section_id, comment_type (Principal or
  Proprietor), min_average, max_average, comment_text. Bounds are inclusive
  whole numbers from 0 to 100; ranges of the same type cannot overlap, while
  gaps are allowed. The approved-subject average is rounded to the nearest
  whole number, halves up, for matching. These Section/type ranges are reused
  across Terms and remain editable; changes therefore affect existing cards
  that do not have personalized text.
- **term_default_comments** — id, school_id, term_id, section_id, comment_type
  (Principal, Proprietor), comment_text — optional standard remark that
  supplies the effective remark at compile/render time whenever a student's
  personalized `result_cards.principal_comment` / `proprietor_comment` is
  null when that Section/type uses `single` mode. It can still be overridden
  per student afterward. In `range` mode, personalized text wins; otherwise
  the matching average range supplies the comment. A gap or a pupil with no
  approved Results supplies no range comment.
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
  legacy row, avoiding nullable-unique ambiguity in MySQL and SQLite. Today's
  Afternoon roll opens at the single named 12:00 Africa/Lagos boundary for form
  teachers; Morning remains open all day, and `manage_attendance` actors retain
  their existing bypass. The same-day-only rule is unchanged.
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
- **invoices** — id, school_id, parent_invoice_id (nullable self-link; populated
  only when a part-payment points to its parent term-fee Invoice), exactly one
  of student_id/candidate_id, registration_number_snapshot (nullable; the
  pupil number when the invoice was issued, retained after later movement),
  term_fee_student_id (populated only for a term-fee invoice), term_id
  (nullable for admissions), purpose (display snapshot), kind (term_fee,
  part_payment, one_off, registration, offer_deposit), creation_source
  (legacy, bulk, single, automatic, part_payment), notes (nullable),
  amount_kobo (unsigned bigint integer kobo, NGN), due_date, status
  (Unpaid/Paid/Voided), created_by, voided_at/voided_by/void_reason (nullable).
  The parent/status lookup is indexed and both the parent and voiding actor use
  same-school foreign keys. The unique
  school/term/term_fee_student key prevents two term invoices under races;
  only `Active` Students are eligible for automatic/bulk invoicing.
- **invoice_lines** — id, school_id, invoice_id, fee_item_id (nullable),
  description, line_type (charge/adjustment), signed amount_kobo snapshot,
  is_required, adjustment_reason (nullable), created_by (nullable same-school
  actor), timestamps. Original lines never change. An adjustment is a new
  signed line with a required reason; the Invoice total equals all its lines,
  and later schedule changes do not alter those snapshots.
- **invoice_generation_runs** — id, school_id, term_id, scope_type, scope_id
  (nullable for whole school), due_date, students_in_scope, created_count,
  skipped_count, no_class_count, no_schedule_count, created_by, timestamps.
  Bulk creation processes eligible Students in chunks of 100.
- **payment_attempts** — id, school_id, invoice_id, reference (unique),
  amount_kobo (unsigned bigint integer kobo), initiated_by,
  authorization_url (nullable), status (Pending/Verified/Failed),
  last_checked_at (nullable; enforces the 30-second manual recheck interval),
  created_at, updated_at. These are gateway initiation attempts, separate
  from settled `payments`; pending attempts never count as revenue. The
  school/status/created-at index supports the bounded scheduled recheck.
- **payments** — id, school_id, invoice_id (unique: no double settlement),
  amount_kobo (unsigned bigint integer kobo), method (Online/Manual), gateway_reference (nullable,
  unique), confirmed_by (nullable for verified gateway), paid_at,
  receipt_number (nullable column, unique per school, generated on
  confirmation), created_at, updated_at. **Receipts are not a separate
  table:** the Bursar receipt listing is generated from settled `payments`
  joined to their invoices, using `receipt_number` as the receipt ID.
- **manual_payment_submissions** — id, school_id, invoice_id, bank,
  teller_number (trimmed display value), active_teller_key (nullable normalized
  value), paid_on, amount_kobo, photo_path (nullable private-local path),
  recorded_by, status (Awaiting approval/Approved/Rejected), decided_by
  (nullable), decided_at (nullable), rejection_reason (nullable), timestamps.
  A unique `(school_id, active_teller_key)` index makes each non-rejected teller
  number school-wide once-only under races; rejection sets the key to null while
  retaining the record. Queue and invoice/status lookups are indexed. The final
  `payments` row and receipt are created only on approval.
- **Part-payment lifecycle** — only a term-fee Invoice may own linked
  part-payment Invoices. Each part remains an independently payable Invoice,
  so `payments.invoice_id` stays unique and each settled part has its own
  receipt. Parent-row locking caps settled plus open parts at the adjusted term
  total. Settled parts reduce the parent's balance; open parts reserve only
  allocatable space and do not reduce arrears. The parent becomes Paid exactly
  once when its direct settlement plus settled parts reaches its total. A paid
  part never independently unlocks Results or Attendance. Lifecycle changes
  use `audit_logs` as their history source.
- **expenses** — id, school_id, date, category, amount_kobo (unsigned bigint
  integer kobo), vendor,
  receipt_file_path (nullable, private), approved_by (nullable), created_by
- **school_settings.result_access_payment_rule** — None or
  TermInvoicePaid, default TermInvoicePaid; checked by a server-side
  Payments service on every future Student/Parent result/attendance fetch.
- **school_settings.one_step_payment_approval** — boolean, default false. Only
  a user with `approve_payments` may change it. When true, a newly recorded
  manual payment is approved and receipted immediately; existing waiting rows
  are unchanged.
- **Paystack gateway settings** — `manage_payment_gateway` is granted only to
  School Admin by default. Saved per-school keys take priority over environment
  fallbacks. The public/secret prefixes derive Test or Live mode and must match;
  the secret is write-only. Initialization, verification, availability checks
  and webhook HMAC verification all use the same saved-first resolver.

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
