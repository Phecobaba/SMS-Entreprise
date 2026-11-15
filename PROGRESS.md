# Progress Log

> **This file must be updated as work happens, not just at the end of a
> session.** If a build session stops abruptly (token limit, crash,
> handoff to another agent), this file is how the next agent picks up
> without re-deriving context from scratch. Read `ARCHITECTURE.md`,
> `PHASES.md`, and `docs/schema.md` first, then read this file for current
> state. See `CONTRIBUTING.md` for exactly how to pick up a task and what
> counts as done.

## How to Update This File

Every time you (the agent) complete a meaningful unit of work, move it from
**In Progress** to **Completed** per the Definition of Done in
`CONTRIBUTING.md`. If you make a decision not already documented elsewhere,
log it under **Decisions Log** with a one-line reason. Do not leave this
file stale — a new agent should be able to read this file alone and know
exactly what state the codebase is in.

---

## Project Status Snapshot

- **Stack confirmed**: Laravel (PHP) + Blade/Livewire/Alpine.js + MySQL,
  cPanel hosting.
- **Scope confirmed**: MVP Scope per `ROADMAP.md` (CBT, Result Management,
  Parent Portal, Payment full-suite; RBAC/Bursar/Dashboards supporting
  depth; Attendance, Assignments Mode A, E-Notes, Staff HR added;
  tenant-aware Landing Page).
- **Build order confirmed**: Phase 0 through Phase 5 per `PHASES.md`.
- **Tenancy mechanism resolved**: `BelongsToTenant` trait + Global Scope +
  `TenantContext` singleton — see `ARCHITECTURE.md` §2.1.
- **Schema design resolved**: table-per-role (not unified `users` table);
  `students.candidate_id` nullable by design — see `ARCHITECTURE.md` §9.
- **Result card comments resolved**: Proprietor = School Admin (no new
  role); Principal/Proprietor comments support personalized text plus either
  a Section/Term single default or reusable Section/type average ranges.
  Personalized text always wins — see the 2026-11-03 Decisions Log.
- **Timeline**: originally 12 weeks, **still not reconfirmed** against
  expanded scope — see `PHASES.md` "Timeline Status."
- **Code status as of this document**: Phases 0–3 are complete, with live-host
  proof deferred until cPanel is purchased. Phase 4 is complete, including
  the real Paystack sandbox transaction and provider-webhook proof. Phase 5
  is substantially built—Portals, Notifications, Student/Parent design work,
  performance hardening and the 2026-10-17 exit-gap closure are recorded
  below—but it is **not formally complete**; only the owner may mark it
  complete. Admin foundations A is built, and the remaining owner-ordered
  Admin refinement work is listed under **Next Up**.

## Current Phase

**Phase 5 substantially built — owner-directed Admin refinement is active.**
Phase 4 is complete, Admin foundations A is built, and the 2026-10-17 Phase 5
exit-gap closure is recorded below. Phase 5 is **not formally complete**;
only the owner may mark it complete.

`DemoDataSeeder` defines seven separate demo accounts (Admin, Teacher,
Principal, Student, Parent, Candidate, Bursar) for hands-on review. It
rebuilds them idempotently only in the local environment, using password
values from ignored `.env` keys. The demo Principal is assigned to Secondary
through `section_user`, the demo Teacher has a class/subject assignment, and
the demo Parent is linked to the demo Student. On 2026-11-04 the owner-authorised
unnumbered pupil and its login/dependent demo rows were removed from the
persistent development database; no pupil there now lacks a registration
number. Passwords are never stored in this repository.

## In Progress

- No task is currently in progress.

## Completed

- **Landing build — approved public Home, Apply, Status and Privacy design
  (completed 2026-11-15).** Replaced the plain public pages with the approved
  `docs/design/landing` v2 design through one shared shell, responsive generated
  WebP images, settings-backed optional contact/privacy/admissions copy, Home-only
  motion, absolute `APP_URL` SEO/share metadata, favicon and robots rules, plus
  module-safe Apache compression and caching. Builds 1, 1.1 and 2 behavior was
  retained. The PHP 8.2 suite passed 298 tests / 3,688 assertions; Pint, Vite and
  diff checks passed. Guarded QA at 1280px and 390px passed with no horizontal
  overflow, a one-line phone header, reduced-motion and no-JavaScript fallbacks,
  and about 382 KB above-fold / 600 KB full-scroll transfer. QA data and helpers
  were removed and the persistent development database fingerprint was unchanged.
  Definition of Done met; there was no migration.

- **Public admissions Build 2 — exam-day accounts, per-Level entrance exams,
  assessment outcomes and conversion (completed 2026-11-14).** Added Admin-only
  transactional Candidate account creation/reset with in-memory A4 credential
  PDFs, per-Level entrance-exam visibility, in-person Level settings,
  Section-scoped assessment outcomes, Candidates and Entrance exams navigation,
  Application-aware Student/Parent conversion, Website settings, and stale
  Application-payment replacement with late-reference reconciliation. The PHP
  8.2 suite passed 295 tests / 3,626 assertions; Pint, Vite and diff checks passed.
  Guarded browser QA passed at 1280px and 390px with no horizontal overflow or
  target below 44px; the generated PDF was raster-inspected and corrected so no
  field is clipped. QA data and helpers were removed, and the persistent
  development database fingerprint remained unchanged. Definition of Done met.

- **Public admissions Build 1.1 — submit recovery, Admissions navigation and
  exam sessions (completed 2026-11-14).** Added guarded same-child application
  recovery, the grouped Admissions navigation, counted Applications tabs,
  Section-scoped exam-session management and assignment, deferred session
  emails, and public session details. The owner confirmed that their demo Admin
  sign-in during the QA window caused the one observed persistent `users` row
  timestamp change; no Build 1.1 process touched tenant data there. The final
  PHP formatter and `git diff --check` pass, including the late 44px Edit-target
  correction. Definition of Done met.

- **Public admissions Build 1 — application, optional fee, status and Admin
  handling (completed 2026-11-14).** Added the public `/apply` flow, optional
  pre-account Paystack application fee, two-hour hashed-token status access and
  signed email link, privacy/consent, username reservation, seven-day expiry,
  idempotent payment finalisation, Admin settings and Applications management,
  and read-only Bursar fee rows. The retired `/admissions/register` route now
  redirects without creating a User or Candidate. The PHP 8.2 suite passed 281
  tests / 3,468 assertions in 301.77 seconds. Guarded browser QA passed at
  1280px and 390px with no horizontal overflow or tap target below 44px; the
  throwaway database and helpers were removed, and the persistent development
  database fingerprint remained unchanged. Definition of Done met.

- **Batch 6A — phone-header school name and admissions readiness report
  (completed 2026-11-13).** The authenticated phone header now shows the full
  school name in at most two compact lines without ellipsis. Guarded QA passed
  with long and short names at 320, 360, 390 and 768px; the required 320/390px
  checks had no horizontal overflow or target below 44px, and More plus the
  notification bell worked. The current public, Candidate, Admissions,
  payment-reuse and in-person-assessment paths were audited without building
  any Part B feature. The PHP 8.2 suite passed 273 tests / 3,367 assertions in
  797.28 seconds; Vite built successfully. Throwaway QA data, scripts and the
  browser profile were removed, and the development database fingerprint was
  unchanged. Definition of Done met.

- **Batch 5 small fixes — live-results bell attention, sidebar brand/icons,
  CBT bulk-upload format and Head permission checks (completed 2026-11-13).**
  The desktop sidebar no longer duplicates live Results attention; the bell
  combines live Results attention with stored unread notifications and the
  notification page lists both. The desktop brand shows the full school name,
  and Bulk subjects, Teacher assignments and Promotion have distinct icons.
  CBT question CSV uses the compact Question/Options A–F/Correct Answer
  layout, supports configured single-answer marks, explicit overrides and
  marked multiple answers, retains both earlier layouts without allowing them
  to be mixed, and provides three delete-marked examples plus Admin/Head help.
  The existing Principal `view_parents` default and dynamic Section dashboard
  tabs were confirmed and required no change. The full PHP 8.2 suite passed
  272 tests / 3,363 assertions in 739.85 seconds; the production Vite build and
  guarded 1280px/390px QA passed with no horizontal overflow or target below
  44px. All throwaway QA data, scripts, screenshots and browser profiles were
  deleted. The persistent development database SHA-256, size and modified time
  were unchanged. Definition of Done met.

- **Batch 4b — Parents directory, scoped read access, account actions and
  recovery-copy cleanup (completed 2026-11-11).** Admin now has a searchable,
  20-per-page Parents directory and detail with linked-child context and
  bulk-computed term-fee status; Admin-only reset/email/link/unlink actions
  reuse the one-time credential, session-revocation, assignment-link and audit
  paths. Heads are read-only within their Sections, Bursars are school-wide
  read-only, and every other default role is refused. `view_parents` and
  `manage_parent_accounts` are provisioned through the default-role catalog
  and additive migration. Signed-out recovery copy is non-enumerating. The
  full PHP 8.2 suite passed 269 tests / 3,313 assertions; final focused,
  catalog and Blade compilation coverage passed 9 tests / 121 assertions. Guarded QA at
  1280px and 390px passed page-two, three-child, reset/copy, email, link,
  unlink, Head, Bursar and recovery flows with no page overflow or target below
  44px. All QA artifacts were deleted and the persistent development database
  fingerprint was unchanged. The separate `view_all_parents` grant keeps
  school-wide Bursar access from widening a Head who also holds a payment
  permission. Definition of Done met.

- **Batch 4a — live result notifications, pending-Paystack safety, sign-in
  cleanup and multi-Section verification (completed 2026-11-10).** Teacher,
  Admin and Head result-attention links now use live role-scoped counts; recent
  and stale Pending Paystack attempts have the approved staff warnings; Sign in
  is concise; dashboard definitions use accessible tooltips; and incoming,
  promotion and Section terminology work with an Early Years → Primary →
  Secondary fixture. The PHP 8.2 suite passed 263 tests / 3,233 assertions,
  production assets built, and guarded desktop/phone QA passed without overflow
  or targets below 44px. The throwaway database, scripts and browser profiles
  were removed, and the persistent development database fingerprint was
  unchanged.

- **Batch 3b — Parent email confirmation and Paystack pending-attempt recovery
  (completed 2026-11-09).** Parent navigation now opens **My details**; changing
  email requires the current password and sends a signed, single-use 60-minute
  confirmation link. The address is immediately available for Paystack receipt
  delivery, while password recovery remains unavailable until confirmation.
  Existing addresses are backfilled as confirmed and school-provisioned
  addresses are confirmed on creation. An email-less Parent is prompted inside
  Pay online and continues without leaving the invoice flow. Payers can recheck
  only their own pending attempt; Payments staff can check any and create an
  audit entry. The 30-second per-attempt limit and one shared idempotent verifier
  cover callback, webhook, manual and five-minute scheduled checks. The
  scheduler processes at most 50 tenant-scoped attempts from 2 minutes through
  48 hours old and logs aggregate counts only. Refund/reversal/dispute handling
  remains unchanged and was report-only. The full PHP 8.2 suite passes at
  **258 tests / 3,182 assertions**; focused Pint and the production build pass.
  Guarded QA at 1280px and 390px passed My details, receipt-email prompting,
  payer/staff Check again, overflow and 44px targets. QA files/scripts/profile
  were removed, and the persistent development database fingerprint was
  unchanged.

- **Batch 3a: results authority, Section learner wording and Active-only Add
  student (completed 2026-11-09).** Only the Teacher assigned to the exact
  Subject/Class pair can enter, save or submit scores; Admin and Section-scoped
  Heads review Submitted Results read-only, approve or reject with a required
  reason, and the assigned Teacher sees that reason before editing and
  resubmitting. CBT conversion remains an explicit system write. Sections now
  choose Pupil or Student through `sections.learner_term`; existing
  Nursery/Primary names backfill to Pupil and other/new Sections use Student.
  One shared helper supplies contextual singular/plural wording, with Students
  retained for mixed or Admin-wide scope. Add student no longer offers Status
  and a forged status is ignored; direct creation is always Active. Guarded QA
  covered the results review/rejection flow and key Primary/Secondary wording
  at 1280px and 390px with no horizontal overflow; raw radio inputs were below
  44px but their enclosing labels are the 44px interaction targets. QA data,
  scripts, server and browser profile were removed, and the persistent
  development database fingerprint was unchanged. The full PHP 8.2 suite
  passes at **251 tests / 3,127 assertions**.

- **Bug fixes batch 1 (completed 2026-11-08).** Teacher score entry now
  preserves the current-Term and assigned-pair rules while every refusal is
  visible; the pair page has clear template, upload/preview, Apply and Cancel
  actions. Teachers without a form Arm no longer see Attendance actions or
  navigation, while attendance managers retain them. One-time credential
  controls copy labelled username/password lines with transient accessible
  feedback and a select-text fallback. Production-safe 403/404/419/429/500/503
  pages are in place; GET logout is harmless and GET requests to POST-only
  routes render 404. Email recovery now accepts every active role with an email
  and remains generic, deferred and non-queued. Staff-account selectors are
  permissioned, bounded and exclude Student, Parent and Candidate roles,
  including mixed-role portal accounts. No migration was required. Pint and
  the production build pass; the full PHP 8.2 suite passes at **245 tests /
  3,057 assertions**. Guarded QA at 1280px/390px passed the score, CSV,
  attendance-visibility, credential-copy and error states with no horizontal
  overflow or phone target below 44px. All QA data/scripts/profiles were
  deleted and the persistent development database fingerprint was unchanged.

- **CBT bulk question upload and guarded QA proof (completed 2026-11-08).**
  Scoped Admin/Heads can download the fixed BOM CSV, preview row/column errors
  without writes, and append an all-valid staged upload once to a Draft exam
  with no attempts; shared question validation, the 200-question cap, private
  expiry/cleanup, Section scope and one summary audit remain server-enforced.
  The QA launcher now starts `php -S` with an explicit absolute SQLite path and
  trusts it only after a stateless, non-production proof registered outside the
  `web` middleware group returns that exact path. The proof created no session
  and changed neither the QA hash nor session count; a deliberate path mismatch
  returned HTTP 409/`FAIL`, exited 1 and stopped its child. Browser checks at
  1280px and 390px passed template headings, row/column errors, clean preview,
  append/publish, pupil multiple-answer selection, cross-Section Head 404 and
  Teacher 403, with no overflow or target below 44px. QA data, staged files,
  scripts and the browser profile were deleted. The full PHP 8.2 suite passes
  at **240 tests / 2,953 assertions**; the persistent development database
  SHA-256, size and modified time were identical before and after.

- **Dev database hygiene after the QA isolation incident (completed
  2026-11-07).** The exact Demo Student chain recreated during the incident was
  removed from the persistent development database after a verified ignored
  SQLite backup was taken. Its audit entry, issued-number history and next
  registration counter were retained. Demo and Oakfield branding seeders are
  now create-only and leave existing school, branding, arrangement, account,
  role, pupil, candidate, fee and invoice values unchanged; the Demo Student
  chain is created only when the school has no pupils. QA and browser setup now
  goes through `qa:prepare`, which accepts only an absolute project
  `database/qa.sqlite` path, refuses production and the persistent development
  database, prints and verifies the resolved connection before migration or
  seeding, and can serve the same isolated process. Launcher verification
  returned HTTP 200 and logged the exact QA path; the throwaway file was
  deleted and the development database size/timestamp did not change. Focused
  checks pass at **4 tests / 31 assertions**; Pint and the full PHP 8.2 suite
  pass at **233 tests / 2,837 assertions**.

- **Phase 5 pagination and performance fixes (completed 2026-11-06; Phase 5
  remains owner-sign-off only).** Staff-account and Parent-account pickers now
  use permissioned, role-filtered searches requiring two characters and
  returning at most 20 rows. Fee items and schedule lines paginate independently
  at 20 rows. CBT live progress derives counts/status in SQL, paginates 25,
  fetches only current-page attempt/grant detail, and refreshes every 10 seconds
  while visible and not editing. Dashboard attendance dates use indexable ranges,
  the duplicate Student CBT question count was removed, and CBT creation now
  chooses the class before showing only its `class_subjects`. A 1,500-pupil,
  120-staff QA fixture measured Admin 39, Head 51, Bursar 15, Teacher 25,
  Student 60 and Parent 59 dashboard queries with no row-driven N+1; CBT live
  progress improved from 63.06 ms to 23.78 ms total database time and an
  attendance date lookup from 1,532.76 ms to 5.39 ms. The full suite passes at
  **224 tests / 2,765 assertions** and the production build passes. Headless
  1280px/390px checks passed Staff HR, Assignments, both Fee paginators, CBT's
  assigned/no-subject states and live-progress refresh/pause with no horizontal
  overflow or visible target below 44px. All QA files, scripts and profiles were
  deleted.

- **Promotion 5c — registration-number movement and Incoming allocation
  (completed 2026-11-05).** Promotion preview now shows each old → new number
  and calls out a clash adjustment. Promotion, single-pupil movement and
  Incoming acceptance use one policy-aware allocation path with stored
  sequence/year/code metadata, transaction-locked school/prefix counters,
  permanent history and actor-snapshotted audit entries. Placement and number
  changes commit atomically; missing destination codes refuse Incoming
  acceptance without partial state. Keep-whole, keep-digits, new-prefix,
  same-scope retention, rollback, idempotency and graduate/leaver retention are
  covered. Issued invoices retain their registration-number snapshot. The full
  PHP 8.2 suite passes at **207 tests / 2,614 assertions**; focused post-format
  checks pass at **27 tests / 197 assertions**. Isolated 1280px/390px browser
  checks passed promotion clash preview/apply and Incoming preview/acceptance
  with no horizontal overflow; phone controls had no target below 44px. The
  throwaway QA database and helper scripts were deleted.

- **Phase 6D-2 — Section-scoped Head Staff (completed 2026-11-04).** Heads
  now have a permission-gated Staff list and detail for the union of their
  assigned Sections. One reusable scope includes current staff through direct
  `section_user`, class/subject teaching assignments or form-teacher arms,
  while excluding Admin/broad-section accounts and every Head Staff permission
  holder. Lists are searchable and paginated 25 per page; guessed or otherwise
  out-of-scope ids return 404. The detail payload is an explicit allow-list of
  profile, Section, teaching and discipline data and never loads salary,
  private documents, exits or editable employment details. Heads can append
  Query, Warning and Commendation entries in scope; views write the sensitive-
  access audit and records force the signed-in Head as issuer plus a general
  audit snapshot. `view_section_staff` and `record_section_discipline` are
  Principal defaults delivered additively to existing roles. Focused checks
  pass at **6 tests / 52 assertions**; the full PHP 8.2 suite passes at
  **193 tests / 2,454 assertions**. Browser QA at 1280px and 390px confirmed
  list, search, detail, discipline form and out-of-scope 404 with no horizontal
  overflow or sub-44px target; the throwaway database, server, scripts and
  browser profile were removed. Pint and the production Vite build pass.

- **Phase 6D-1 — staff sign-in shutdown and Admin Activity log (completed
  2026-11-04).** Admins can disable or re-enable staff sign-in from Staff
  access; self-disable and disabling or exiting the last active School Admin
  are refused. Disable and immediate Staff HR exit revoke database sessions
  and remember tokens, while re-enable restores sign-in without changing the
  employment record. Exit dates are limited to today or earlier. The new
  Admin-only Activity log is newest-first, paginated 25 per page, rendered in
  Africa/Lagos time, and filterable by date, module and person; legacy entries
  without actor snapshots display “Unknown user”, and audit metadata is not
  exposed. The additive role catalog/migration grants `view_activity_log` only
  to School Admin. Focused and related trust checks pass; the full PHP 8.2
  suite passes at **187 tests / 2,393 assertions**. QA at 1280px and 390px
  confirmed the Staff access list and confirmation flows, Staff HR exit
  warning, and Activity filters have no horizontal overflow or sub-44px
  targets; the throwaway database, server, scripts and browser profile were
  removed. Pint and the production Vite build pass.

- **Composer advisory fixes (completed 2026-11-04).** Livewire was minimally
  updated from 4.4.7 to 4.4.8 and Symfony Mime from 7.4.19 to 7.4.22; no other
  dependency moved, the PHP platform remains pinned to 8.2.0, and no published
  Livewire assets required refreshing. `composer audit` is clean. Pint and the
  production build pass; the full PHP 8.2 suite passes: **182 tests / 2,317
  assertions**.

- **Teacher pair-scoped score CSV (completed 2026-11-04).** Each authenticated
  Teacher assignment now provides a BOM UTF-8 roster template bound by a plain
  Subject/Class/Term comment, with current registration numbers, reference-only
  names and the resolved assessment components. Existing Draft/Rejected scores
  are prefilled; blank score rows are skipped. Uploads receive a plain,
  all-or-nothing preview and a 30-minute single-use staged file bound to the
  same user, pair and Term. Apply revalidates current registration, active
  Class membership, template maxima and Result locks, then saves through
  `ResultWorkflow` in one transaction and writes one bulk audit entry. The
  legacy raw-ID import remains refused to Teachers. The sole unnumbered Demo
  Student and its explicitly demo finance/dependent chain were removed from
  the development database under the owner's deletion authority. Isolated
  browser QA covered the upload/error state at 1280px and 390px with no
  horizontal overflow or sub-44px controls; the QA database, staged uploads
  and browser process were removed. Pint and the production build pass; the
  full PHP 8.2 suite passes: **178 tests / 2,298 assertions**.

- **Teacher Results workspace (completed 2026-11-03).** Teachers now land on
  current-Term **Subject · Class** assignment cards with active-pupil and
  Draft/Submitted/Approved counts, then a paginated all-arm roster and a
  route-bound score-entry screen using the resolved template's real components.
  Form teachers additionally receive an exact-arm **My class** view of approved
  compiled cards; ordinary subject teachers and other-arm form teachers are
  refused server-side. Admin and Head Results workspaces are unchanged. The
  legacy raw-ID CSV import was hidden and refused to Teachers because it had no
  registration-number roster/preview contract; the pair-scoped workflow in the
  entry above supersedes that earlier gap. Isolated browser QA passed the landing, pair,
  score-entry and My class pages at 1280px and 390px with no horizontal overflow
  or sub-44px controls; all QA data and browser profiles were deleted. Focused
  Pint and the production build pass; the full PHP 8.2 suite passes:
  **174 tests / 2,250 assertions**.

- **Fixes batch A (completed 2026-11-03).** Today's Afternoon attendance now
  opens for form teachers at the shared 12:00 Africa/Lagos boundary, with the
  direct write and the roll UI enforcing the same rule while
  `manage_attendance` retains its existing bypass. Class create/rename and the
  app's demo-seeding path enforce trimmed, case-insensitive school-wide names.
  Failed Paystack initialisation returns the payer to the invoice page with a
  plain error, leaves the invoice unpaid and marks its attempt Failed; gateway
  connection checks distinguish missing keys, rejected credentials and network
  failure without exposing keys. Isolated browser QA at 1280px and 390px on
  the Attendance roll and Student invoice page passed with no horizontal
  overflow or sub-44px controls; QA data was deleted. Pint, the production
  build and the full PHP 8.2 suite pass: **168 tests / 2,177 assertions**.

- **Teacher access part 1 (completed 2026-11-03).** Compiled report-card
  show/PDF access and the embedded broadsheet now allow only the exact arm's
  form teacher or existing Admin/Head permissions within Section scope; a
  class-level subject assignment no longer grants access or card-list
  visibility. Results, E-Notes and Assignments share the **Subject · Class**
  label. Teacher E-Note upload/filter and Assignment creation expose only
  assigned pairs, with forged pairs still refused server-side. The Teacher
  dashboard lists every form arm and assigned pair instead of silently using
  the first. Isolated browser QA at 1280px and 390px passed on Dashboard,
  Results, E-Notes and Assignments with no horizontal overflow or sub-44px
  controls; QA data was deleted. Pint, the production build and the full PHP
  8.2 suite pass: **165 tests / 2,133 assertions**.

- **Recovery outcome logging (completed 2026-11-03).** Every valid
  forgot-password request now writes exactly one account-neutral outcome line:
  `queued_for_send`, `sent`, `send_failed`, `no_matching_account`,
  `account_has_no_email`, `not_eligible`, or `throttled`. Transport failures
  retain only the scrubbed exception class/message, while identifiers, email
  addresses, usernames, tokens and reset links never enter the line. Every
  branch returns the same generic redirect and browser message. Deployment
  guidance now explains how to read the outcome before testing mail delivery.
  Focused recovery tests pass (11 tests / 161 assertions); Pint and the full
  PHP 8.2 suite pass: **163 tests / 2,086 assertions**.

- **Batch 6C hand-testing corrections (completed 2026-11-03).** Student
  import now separates the staff-facing Class and Arm columns, derives
  Section/Level, accepts a blank Arm only for a one-Arm Class, and reports
  every Section / Level / Class path when a repeated Class name is ambiguous.
  The school-scoped Class/Arm table with Copy controls replaces the text
  download. Import, Add student, Student/Parent reset and Staff credentials
  share an A4 two-by-four slip layout with dashed cut lines and transient
  Print / Save as PDF only; the credential spreadsheet was removed. The
  import print-visibility fault was the old CSS selecting only
  `#credential-panel`. A 50-pupil isolated-QA run rendered all 50 slips and a
  seven-page PDF at 1280px/390px without overflow; Add student and Staff slips
  also passed, and QA data was deleted. Guest recovery eligibility, tenant
  resolution, throttling and deferred sending passed; the configured SMTP
  attempt reached the deferred callback but this tool environment blocked the
  outbound socket, while the array transport delivered. Production assets,
  Pint, Blade compilation and the full PHP 8.2 suite pass: **163 tests / 2,049
  assertions**.

- **Batch 6C duplicate/cap/cleanup follow-up (completed 2026-11-02).** A
  confirmed CSV hash is refused within its school while unconfirmed, failed
  and other-school files remain reusable. Same-normalized-name/class matches
  now identify the existing pupil and require a server-enforced **Create
  anyway** acknowledgement; exact duplicates alone remain skipped. Added the
  original canonical class-list download (replaced on 2026-11-03 by the
  embedded Class/Arm table), a real class in the template sample, and the
  runtime effective-cap formula driven by web `max_execution_time` and the
  host-measured `STUDENT_IMPORT_SECONDS_PER_ROW`. Expired source uploads are
  removed hourly through the registered Laravel scheduler. PHPunit uses cost
  4 for temporary credentials except one cost-10/12-character production
  guard; full-suite time fell from 293.50 to 240.77 seconds. Application
  E-Note/portal/Staff downloads now prove permission and tenant refusal;
  Livewire previews and Laravel local private storage prove signed bearer URL
  enforcement. Browser QA at 1280px/390px covered the hash refusal, warning
  and acknowledgement, the then-current class-list download and cap line with no overflow and
  no sub-44px phone target; QA data was deleted. Pint, production build, and
  the full PHP 8.2 suite pass: **159 tests / 2,013 assertions**.

- **Batch 6C: synchronous all-or-nothing Student import (completed
  2026-11-01; column contract superseded 2026-11-03).** Admins with
  `create_students` can download the fixed UTF-8 CSV template, check the whole file without creating accounts,
  review every row/column error, and confirm through the existing Add student
  service in one transaction. Confirmation revalidates, is idempotent, rolls
  back users, pupils, invoices and registration counters together, numbers in
  file order, shares Parents only by the approved username/email rules, skips
  exact duplicates only, and deletes the private source file after Confirm or
  24 hours. The retained tenant/owner-scoped report stores no passwords;
  credentials exist only in the first no-store response and browser-built
  print/download. The measured 50-row run completed in 13.00 seconds on PHP
  8.2; the 1 MB file limit and configurable 50-row cap are displayed and
  enforced. Browser QA at 1280px and 390px covered upload, errors, review,
  confirm, report, download, return and cap states with no horizontal overflow
  and no sub-44px phone controls; all QA files were deleted. Pint and the
  production build pass; full suite: **152 tests / 1,968 assertions**.

- **Account-recovery hardening mini-task (completed 2026-11-01).** Recovery
  email now uses Laravel's in-process deferred callback after the response,
  never a queued job. Forgot/reset pages send `no-referrer` and `no-store`;
  failed transport diagnostics retain only the exception class and scrubbed
  message while the browser keeps the generic response. Added the credential-
  safe `oakfield:mail-test` command and deployment checklist step. Parameterized
  named-route smoke coverage now uses real Payment, Result, Staff, Admission,
  Assignment, Student, Attendance and CBT records. Token-absence checks cover
  audit rows, notifications, session/flash, jobs and logs. Pint and the
  production build pass; full PHP 8.2 suite: **146 tests / 1,895 assertions**.

- **Payments approvals regression guards and Batch 6 account recovery
  (completed 2026-11-01).** Fixed the invalid two-statement inline Blade
  directive on the manual-payment approval queue. Added a database-free
  compiler/PHP parser check for every Blade view, a six-role smoke check for
  every named parameter-free GET route, and pending-payment rendering coverage
  for approvals, record payment and Invoice detail. Added non-enumerating,
  IP/identifier-throttled Admin email recovery with tenant-scoped hash-only
  single-use 60-minute tokens, synchronous non-log mail, shared password rules,
  session/remember-token revocation, audit and other-Admin notices. Student and
  Parent Account pages now expose only own/linked profile data and reuse the
  throttled current-password service. The console break-glass command resets
  only an Admin and prints its must-change temporary password once. Isolated QA
  verified the approval queue as Admin/Bursar and every recovery/portal state
  at 1280px/390px with no page overflow and no phone target below 44px; the
  throwaway database, profiles and screenshots were removed. Pint and Vite
  pass; full PHP 8.2 suite: **142 tests / 1,835 assertions**.

- **Batch 6 UI follow-up: navigation, Staff HR, Admission requirements
  override reason and mobile tap targets (completed 2026-11-01).** Admin
  payment destinations are grouped beneath Payments with permission-filtered
  children, phone More has nested groups and Back, and the fixed tab sets are
  unchanged. Staff account/employment labels and links now agree; employment
  role title, contract type and date are editable and audited while status
  remains Exit-only; salary uses exact naira-to-integer-kobo conversion. D15
  requires a 10–500 character reason, retains it in Candidate history and the
  generic audit, and explains that unticked items remain outstanding. The
  demo-number command now makes dry-run writes transactional and rolled back;
  Livewire is pinned to installed major 4 (4.4.7). Reissued Batch 6 design
  status text was reconciled without changing the two unrelated design briefs.
  Isolated Admin/Head browser QA at 1280px/390px and sidebar checks at
  1366×650/720/768 passed with no page overflow and no effective target below
  44px; brand and Sign out stayed pinned while navigation scrolled. QA data,
  screenshots and browser profiles were removed. Composer validation, Pint,
  Blade compilation and Vite pass; full PHP 8.2 suite: **132 tests / 1,420
  assertions**.

- **Batch 6B follow-up: registration-number visibility and payment search
  (completed 2026-10-31)** — The current number now appears, when present, on
  staff and portal report cards, the printable card, staff Invoice detail,
  Student/Parent Fees and receipt history, Record payment results and the
  one-time Student credentials slip. Record payment searches name, username,
  current number and number history case-insensitively under tenant scope.
  Composer is pinned to platform PHP 8.2.0. Added direct coverage for prefix
  validation, typed-number permission, audited Student name/email/username
  edits (including Set username), document rendering, historical-number lookup
  and cross-school isolation. Isolated QA at 1280px/390px found no page-level
  overflow and displayed the new number on every requested page; existing
  compact links/form controls remain below 44px on Invoice detail and the
  staff report-card page and were not restyled in this bounded follow-up. The
  throwaway database/profile were removed. Composer validation passes with
  only the existing unbounded-Livewire warning; Pint passes; full PHP 8.2
  suite: **130 tests / 1,390 assertions**.

- **Batch 6, part B: Students and registration numbers (completed 2026-10-31)**
  — Added school/Section/Level prefix settings, minimum zero-padding, optional
  issue year, locked movement policy, concurrency-safe sequence rows and
  permanently reserved number history. Candidate conversion and the demo
  seeder allocate through the shared engine. Admin now has paginated Students,
  Add student, one-time Student/Parent credentials, Student identity/sign-in/
  number management and registration-number settings; Heads may change only a
  current pupil in their Section, while Awaiting admission remains Admin-only.
  Existing Parent reuse creates no credentials, all resets revoke sessions,
  and D15 is labelled Admission requirements with the existing logged override.
  Browser QA on a deleted throwaway SQLite database passed every requested page
  at 1280px and 390px with no overflow, no measured action below 44px, and no
  credential page in Back history. Pint, Blade compilation and Vite pass; full
  PHP 8.2 suite: **127 tests / 1,357 assertions**.

- **Batch 6 part A follow-up and documentation tidy (completed 2026-10-31)**
  — Registered the mandatory password-change middleware as Livewire-persistent
  and proved a flagged user is redirected on a real update request. Added one
  shared eight-character password policy for set-password, staff Account and
  bootstrap; generated credentials remain 12 characters. Role/Section changes
  now revoke the target's database sessions and remember token, and the last
  active Admin cannot be demoted. The plain-POST credentials response remains
  outside Livewire state, uses no-store, clears the password on page exit and
  replaces its history entry on Done. Remote deployment now fails before
  migration unless the session driver is database. Owner decisions, superseded
  rows, Next Up and resolved Blocker wording are current. Twenty-hash CLI loops
  measured bcrypt cost 10 at mean 0.148s (0.118–0.236) and cost 12 at mean
  0.563s (0.439–0.905) on PHP 8.3.32 without Xdebug or OPcache. Pint, Blade
  compilation and Vite pass; full suite: **119 tests / 1,288 assertions**.

- **Batch 6, part A: account foundations, sign-in, staff accounts and the
  staff Account page (completed 2026-10-27)** — Added Admin-only staff account
  creation/access permissions, server-enforced role rank and Section rules,
  school-wide lower-case usernames with case-insensitive sign-in, one-time
  no-store credentials, configurable lower-cost temporary hashes, mandatory
  first-password middleware, reset/session revocation and a shared throttled
  change-own-password service. Admin can add staff, reset another account and
  change its role/Section; every staff role has an Account page, and Sign-in
  rules includes a non-reserving username checker. Generic audit rows now
  snapshot actor id/name/role without rewriting older rows. Existing Staff HR
  remains the separate employment/salary/document workflow. The three design
  backups contained no newer material and were moved outside the repository.
  Browser QA on a throwaway SQLite database passed Login, Add staff, one-time
  credentials/print, Staff access, Account, Sign-in rules and Set password at
  1280px and 390px with no horizontal overflow or effective target below
  44px; all QA data/artifacts were removed. Temporary bcrypt cost 10 averaged
  0.219 seconds/hash and normal cost 12 averaged 0.484 seconds/hash on this
  machine. Pint, Blade compilation and Vite pass; full suite: **116 tests /
  1,267 assertions**.

- **Retire and replace for Result templates (owner decision 2026-10-26;
  completed 2026-10-27)** — Used templates can now be retired between Terms
  by an Admin or a Section-scoped Head. Retirement retains the historical
  `scope_key`, nulls a separately unique `active_scope_key`, records who/when
  and the Result count in `audit_logs`, and frees the scope for one active
  replacement. Current-Term use blocks retirement with a counted message;
  unused templates remain deletable instead. New Results ignore retired
  templates while existing drafts, submissions and report cards keep their
  stored template. Active configuration shows retirement eligibility, a
  confirmation sheet and broader-template pupil warnings; retired templates
  appear read-only in a collapsed list. Restore remains deferred. Browser QA
  on a throwaway database passed the current-term refusal, eligible sheet and
  Retired list at 1280px and 390px with no horizontal overflow or effective
  target below 44px; all QA artifacts were deleted. Production assets, Pint,
  Blade compilation and the full suite pass: **110 tests / 1,177 assertions**.

- **Section-wide result templates (owner decision 2026-10-26)** — Result
  templates now belong to an immutable Section and may cover every Level in
  it, one Level, or one class in that Level. A single resolver applies the
  class → Level → Section-wide precedence for entry and CSV creation, while
  submission and report cards continue through the Result's stored template
  so later overrides never rewrite history. Duplicate scopes, cross-Section
  configuration and used-template scope changes are refused server-side; a
  missing template produces the agreed plain-language entry message. The
  two-stage migration mechanically backfills `section_id` from each existing
  template's Level, then requires it; no migration command was needed. Schema,
  decision log, form/list scope labels and conditional class selection are
  updated without redesigning the page. Browser QA on a throwaway database at
  1280px and 390px found no horizontal overflow or effective target below
  44px; the database and browser artifacts were removed. Production assets,
  Blade compilation, Pint and the full suite pass: **107 tests / 1,157
  assertions**.

- **Owner-testing redirect and whole-number grade-band fixes (2026-10-26)**
  — successful saves and deletes now redirect to an explicit list, parent,
  detail, or originating Attendance page with a status message instead of
  relying on browser history; the deleted Grade scale/Band 404 is covered by
  redirect-target tests for Grade scales, Bands, Components and Templates.
  Grade-band bounds render and pre-fill as whole numbers, numerically whole
  input such as `60.00` is accepted and stored as `60`, and fractional bounds
  remain invalid. Focused Results tests pass (4 tests / 72 assertions); Pint
  and the full suite pass: **103 tests / 1,142 assertions**.

- **Section ownership of Result configuration (2026-10-26)** — Grade scales
  and assessment components now belong to one immutable Section. Result
  templates enforce one Section across their Level, Grade scale and every
  component. Admin retains school-wide `manage_result_settings`; Heads receive
  `manage_section_result_settings` and can manage only their assigned Section,
  with a locked state when none is assigned. The staged migration adds nullable
  ownership, the audited `oakfield:migrate-result-sections` command assigns or
  splits existing configuration while repointing templates, Result items and
  CBT exams without changing marks, and the final migration requires ownership.
  The local database was backed up, then Migration A, dry run, real command and
  Migration B were completed in order. Browser QA covered Admin, assigned Head,
  unassigned Head and result-locked states at 1280px and 390px; no page-level
  horizontal overflow or effective target below 44px was found, and an internal
  desktop-sidebar x-scrollbar was removed. The throwaway database, screenshots
  and browser profiles were deleted. Production assets build in 5.92 seconds;
  Pint and the full suite pass: **103 tests / 1,125 assertions**.

- **Owner-testing fixes: grade scales and Section-scoped teacher assignments
  (2026-10-26)** — Grading schemes are now shown as Grade scales and have
  audited Admin-only edit/delete actions with result/template locks. Bands use
  inclusive whole-number bounds, must be adjacent without overlap, display
  coverage/incomplete gaps, and all grade/remark lookup uses half-up
  whole-number rounding; incomplete scales cannot submit Results. Active,
  qualified teacher candidates are resolved consistently from `section_user`,
  subject assignments and form-teacher arms. Heads are restricted to their
  Section; Admin sees that Section first and qualified Other teachers second;
  inactive and unqualified users are rejected server-side. Head/Admin class
  assignment review now includes each arm's form teacher plus subject teachers,
  supports audited removal/replacement, previews entered-Result counts, and
  preserves those Results. Empty-roster CSV import is unavailable with an
  explanation. Existing non-integer data was not rewritten. Design-system QA
  at 1280px and 390px passed without horizontal overflow or effective tap
  targets below 44px, and the throwaway QA database/profile were removed.
  Pint and the full suite pass: **99 tests / 1,084 assertions**.

- **Result configuration locks and form-teacher assignment correction
  (2026-10-25)** — Admin-only `manage_result_settings` edit/delete routes and
  existing edit-sheet/delete-confirmation patterns now cover Result templates,
  grade bands and assessment components. Result-backed configuration returns
  the required counted lock message on direct requests; components also retain
  template-reference protection, and band edits/deletes preserve a contiguous,
  non-overlapping scale. Every successful edit/delete is audit-logged and the
  existing subject-remarks toggle remains available. Form-teacher assignment
  now requires `manage_attendance`, not `mark_attendance`: Admin acts across
  Sections, Heads act only in assigned Sections, candidates still require
  `mark_attendance`, and replacements record previous/new teachers in
  `audit_logs`. Marking remains limited to Admin or the arm's current form
  teacher. Focused direct-request coverage includes used/unused configuration,
  gap/overlap refusal, Section scope, assignment permission/audit, non-form and
  replaced-teacher refusal, and Head marking refusal. Schema and deferred-
  polish notes are current; Pint, Blade rendering and the full suite pass:
  **96 tests / 1,036 assertions**.

- **Default role catalog and Phase 5 dashboard remeasurement (2026-10-25)**
  — `DefaultRoleCatalog` now supplies the same additive defaults to real-school
  bootstrap, demo seeding and the existing-role migration for School Admin,
  Principal, Teacher, Bursar, Student and Parent. Student/Parent remain
  profile-driven with no permissions. Non-demo HTTP coverage proves the Head
  can open Dashboard and approve a submitted Result, the Teacher can open
  Dashboard and enter/submit a Result, and the Bursar can open Dashboard and
  record a teller payment; none receives Admin-only permissions. The migration
  writes nothing before bootstrap, adds missing defaults to roles that exist
  and preserves a custom grant. Read-only local HTTP measurements are Admin
  38, Head 48, Teacher 23, Bursar 18, Student 52 and Parent 57 queries. Student
  is +8 and Parent +14 versus the earlier record; the excess is fixed Batch 5
  Invoice/Payment/part-payment relationship and ownership checking, while the
  ward-growth guard remains constant, so no N+1 pattern was found. No query
  optimization was made. Role ranks/assignability remain deferred to Batch 6.
  Pint passes; full suite: **93 tests / 982 assertions**.

- **Bursar default payment permissions and Phase 5 status refresh
  (2026-10-25)** — Real-school bootstrap now creates a Bursar role with
  `manage_payments` and `manage_fee_schedule`; migration `2026_10_25_000100`
  backfills those grants onto every existing Bursar role. Neither path grants
  `approve_payments` or `manage_payment_gateway`. Focused coverage proves both
  new-school and existing-role behavior (2 tests / 8 assertions). The Level
  nullability launch step and generic Session/Term delete-sheet polish are
  recorded. The Phase 5 exit evidence was refreshed against Batch 5 without
  changing its formal status. Pint passes; full suite: **93 tests / 946
  assertions**.

- **Academic setup front-end alignment and Batch 5 follow-up verification
  (2026-10-24)** — Admin Levels and Subjects now match the re-issued Section-
  ownership references, including required create-time Section selection,
  immutable read-only edit labels, Section-named delete/error copy, all-Section
  Subject filtering and per-row tags. Head variants are fixed to the assigned
  Section with matching create/edit/locked copy. Sections shows read-only Level
  ownership plus class/active-student counts and a Manage levels link; Sessions
  are grouped with their Terms and current/future/past labels. Academic
  navigation and Promotion access wording are aligned. Added the two-way
  Paystack mode-mismatch and no-credential webhook assertions; the existing
  no-key test confirms the Bursar Invoice detail state. QA at 1280px/390px had
  no horizontal overflow or sub-44px visible target across the requested
  screens/states; the throwaway database/profile were deleted. The ordinary
  Head Staff-link gap is recorded below. Blade cache, Pint and Vite pass; full
  suite: **91 tests / 938 assertions**. Phase 5 remains not formally complete.

- **Batch 5 part 4: Paystack gateway settings (2026-10-24)** — Added the
  Admin-only `manage_payment_gateway` permission and per-school saved public
  key, encrypted write-only secret, last-four mask, derived Test/Live mode and
  changed-by/time metadata. `PaystackCredentials` is now the single
  saved-first/environment-fallback resolver for availability, initialization,
  verification, connection testing and webhook HMAC verification. Key changes
  require the Admin's current password, are rate-limited, audited without
  values and notify other Admins; Bursars see only the locked state. Missing or
  unreadable keys remove every payer/Bursar Pay control and refuse initiation
  server-side while manual teller payments remain available. The settings page
  includes the real webhook URL, encryption recovery warning and a timeout-
  bounded read-only `GET /transaction?perPage=1&page=1` connection check.
  Official Paystack documentation confirmed the API Keys & Webhooks setting.
  The throwaway QA database was deleted after the environment-key state passed
  1280px/390px comparison with no horizontal overflow or sub-44px interactive
  target; the deliberate difference from the reference is an inline plain POST
  password field rather than its scripted confirmation sheet. Pint and Vite
  pass; full suite: **91 tests / 924 assertions**. Phase 5 remains not formally
  complete.

- **Batch 5 part 3: invoice lifecycle and payer Fees pages (2026-10-23)** —
  Added same-school linked part-payment Invoices, separate settlement and
  receipts, signed reasoned adjustment lines, guarded part/whole-Invoice void,
  and row-locked parent settlement through one `InvoiceLifecycle` service.
  Open parts reserve the allocatable balance and block direct parent payment;
  settled parts reduce all-Term arrears once, while only final parent-Invoice
  settlement unlocks Results and Attendance. Bursar/Admin Invoice detail now
  shows lines, progress, parts, receipts and audit history; paginated Student
  and Parent Fees pages show expandable lines, part states, attempts, manual
  approval states and payer-owned Pay actions. Part-2 approval notifications
  now have explicit linked-Parent coverage, and record/approve/reject/automatic
  approval audit evidence is asserted. No permission was added:
  `manage_fee_schedule` remains the invoicing/lifecycle permission,
  `manage_payments` collects money, and `approve_payments` decides queued bank
  payments. Schema and design-system guidance are current. The isolated QA
  database was deleted after 1280px/390px checks of Invoice detail, the open
  create-part form and both payer pages found no overflow or sub-44px target.
  Blade compilation, Pint and Vite pass; full suite: **86 tests / 858
  assertions**. Phase 5 remains not formally complete.

- **Documentation tidy (2026-10-23)** — Aligned Graduated/Awaiting portal
  wording and Batch 5 references, replaced the missing backend-handoff pointer,
  reordered the real remaining work, marked superseded Subject pauses without
  deleting their history, and recorded outstanding academic/payment visual
  comparisons. Reference HTML was deliberately left unchanged for the design
  agent. Documentation only; no code, migrations or tests changed or ran.

- **Immutable Level/Subject Sections and Head Level creation (2026-10-23)** —
  Level and Subject Sections are now selected only at creation and are
  immutable afterward for both Admin and Heads. The Admin-only Level
  reassignment route, controller/service path and Sections-screen control were
  removed, as were the Admin Section selectors on Level and Subject edit
  sheets. Direct edit requests with a different Section are refused. Heads
  create Levels and Subjects only in their assigned Section through the
  existing `manage_section_levels` and `manage_section_subjects` permissions;
  Admin selects any same-school Section at creation. Sessions, Terms, existing
  data and delete blockers are unchanged. Schema and design specs record the
  owner decision and the earlier Subject-move allowance remains in the
  decision log marked superseded. Focused tests pass (**6 tests / 111
  assertions**); Pint and Blade compilation pass; the full suite passes (**80
  tests / 815 assertions**). Phase 5 remains not formally complete.

- **Local environment recovery and QA database isolation (2026-10-23)** â€”
  Preserved the contaminated SQLite file outside the repository, rebuilt the
  permitted local development database through every migration (including
  manual payments and both Subject stages), and restored school ID 1 with an
  idempotent local-only `DemoDataSeeder`. The seven `.env`-password demo
  accounts, Oakfield branding, two Sections, arranged Classes/Arms,
  Section-owned Subjects, current Session/Term, Teacher assignment,
  Student/Parent/Candidate profiles, fee schedule and term Invoice are present.
  `tests/TestCase.php` now rejects every database except SQLite in-memory and
  PHPUnit forces that configuration; the persistent-database probe fails fast
  before `RefreshDatabase`. Browser/QA guidance now requires a separate
  gitignored throwaway SQLite file. The Windows public-storage junction was
  created. `/`, `/login`, the logo, all six dashboard landings and the
  Candidate admission landing pass through the running app. Pint passes and
  the exact full suite passes (**80 tests / 807 assertions**).

- **Batch 5 part 2: manual bank-teller payments and one-step approval
  (2026-10-23)** — Replaced immediate manual settlement with tenant-scoped
  `manual_payment_submissions`: bank, normalized race-safe teller key, paid
  date, exact integer-kobo balance, optional private JPG/PNG/PDF evidence,
  recorder, decision and rejection reason. `manage_payments` records while the
  new Admin-only `approve_payments` permission approves/rejects and changes the
  default-off `school_settings.one_step_payment_approval` control. Approval is
  row-locked and creates one settlement/receipt; rejection preserves the log,
  releases the teller key and notifies the recorder. One-step changes are
  audited and notify other Admins without changing existing queued rows. The
  Student/Parent Fees page shows the approved awaiting copy and recorded
  method/date/amount, exposes no bank/teller/photo, and hides Pay; the Bursar
  dashboard now links its live Awaiting approval count. Teller evidence is
  stored below `storage/app/private/manual-payment-slips` with random names,
  content-checked JPG/PNG/PDF only and a 3 MB limit, and is served only through
  the authenticated queue-viewer route. Focused coverage: **7 tests / 58
  assertions**; the full suite passes (**80 tests / 807 assertions**). Blade,
  Pint and the production Vite build pass. Phase 5 is not marked complete.

- **Subject ownership by Section (2026-10-23)** — Subjects have a mandatory
  same-school Section and are unique per School/Section. The two-stage
  migration and audited `oakfield:migrate-subject-sections` command provide a
  read-only dry run, deterministic splitting/copying, per-module history
  repointing and idempotent re-runs. Admin manages any Section; Heads use
  `manage_section_subjects` for both bulk Subject assignment and Subject
  create/edit/delete within their assigned Section. Direct, bulk and Teacher
  assignment paths reject cross-Section Subjects. The Subjects screen matches
  the approved reference for Admin and Head at 1360px and 390px with no
  horizontal overflow. Tailwind scanning is restricted to application source
  folders; the production build completes in 4.54s with no warnings. Focused
  tests pass (3 tests / 47 assertions), Blade and Pint pass, and the full suite
  passes (**73 tests / 752 assertions**).

- **Batch 5 part 1, 7a calendar fallbacks (2026-10-23)** — Added central
  `AcademicCalendar::termForInvoicing()` and `termForSummary()` fallbacks.
  Automatic enrolment invoicing now uses the earliest upcoming Term during a
  gap and remains silent and idempotent when no applicable schedule or Term
  exists. The Bursar dashboard uses and labels the most recently ended Term
  during a gap, while the Not invoiced list shares the calendar selection and
  still lists pupils when no future Term exists. The current-Term overlap rule,
  Student portal label, all-Term arrears, Parent status pill and deletion
  protection are unchanged. Focused tests pass (15 tests / 67 assertions);
  Pint and the full suite pass (**70 tests / 705 assertions**).

- **Batch 5 part 1, 7a gap closure (2026-10-20)** — Fee schedule create/update
  now accepts comma-formatted Naira with up to two decimal places, converts it
  exactly to integer kobo and renders the saved two-decimal value. Bulk Invoice
  generation coverage now proves that no-Class and no-schedule Students are
  skipped and counted separately. The Parent “Fees due” pill now derives from
  the same batched all-Term term-fee balance calculation as portal outstanding,
  includes arrears and excludes one-off Invoices while remaining status-only.
  The remaining manual-payment, teller reuse, adjustment, void and one-step
  approval owner decisions are logged below and the resolved payment question
  is closed. Focused tests pass (12 tests / 53 assertions); Pint and the exact
  full suite pass (**67 tests / 691 assertions**).

- **Batch 5 part 1, 7a arrears/current-Term remainder (2026-10-20)** — Portal
  outstanding now sums the non-negative balance of every term-fee Invoice for
  the Student exactly once, subtracting recorded Payments while excluding
  one-off and every other Invoice kind; paid term-fee Invoices contribute zero.
  Result access remains scoped only to the viewed Term's paid term-fee Invoice.
  `AcademicCalendar::currentTerm()` is the single date-derived resolver used by
  the Bursar summary, automatic invoicing and Student portal context; overlapping
  ranges resolve to the containing Term with the latest start date, while delete
  protection continues to reject every date-containing Term. No screens changed.
  Focused tests pass (14 tests / 100 assertions); Pint and the full suite pass
  (**66 tests / 682 assertions**).

- **Academic setup safeguards and fee-schedule copy (2026-10-20)** — Added
  capped Admin/Head Level management, Admin-only Session/Term management and
  editable/deletable Class Arm chips. `manage_section_levels` and
  `manage_section_arms` are granted to existing Principal roles; every ID is
  rechecked against the Head's assigned Section, and the no-Section state is
  locked. One Academics guard composes module-owned Student, Result,
  Attendance, Assignment, CBT and Payment usage counts, returning counted
  plain-language blockers before any history can be deleted. Current
  date-containing Sessions/Terms are protected; unused Sessions cascade only
  their unused Terms; all edits/deletes are audited and renaming/re-dating
  leaves history unchanged. Fee schedules now copy unchanged prices from an
  earlier Term to all scopes or one scope through one shared preview/apply
  plan, skipping existing/missing scopes and never touching issued invoices.
  The Admin navigation now reaches Bulk subjects, Teacher assignments and
  Promotion, while fixed phone tabs are unchanged. Subjects remain deliberately
  paused because their present schema is school-wide and the owner-required
  existing-row Section mapping is not known; the screen states that limitation
  instead of silently reshaping data. **Superseded 2026-10-23:** the Subject
  ownership migration and Section-scoped management work resolved this pause.
  The requested Levels/Subjects/Sessions
  reference HTML files were not present, so the delivered shared Admin patterns
  were reused. New focused coverage passes (12 tests / 90 assertions); Pint,
  Blade compilation, Vite production build and the exact full suite pass
  (**64 tests / 672 assertions**).

- **Batch 5 part 1: fee schedules and invoicing (2026-10-19)** — Added the
  tenant-scoped fee-item catalog, Level/Class/Department term schedules,
  required/extras rules, invoice-line snapshots, explicit invoice kinds and
  creation sources, race-safe one-term-invoice uniqueness, chunked previewed
  bulk generation with run logs, paginated not-invoiced/single invoicing, and
  automatic invoices on candidate conversion and Incoming acceptance when a
  matching current-term schedule exists. Matching scopes add while duplicate
  items resolve Class over Department over Level; Students without a Class or
  schedule are counted and skipped. New `manage_fee_schedule` access is
  granted to existing School Admin/Bursar roles and to bootstrapped Admins;
  Admin and Bursar navigation now reaches Fee schedule, Generate invoices and
  Not invoiced. Result/Attendance payment gates now use only the relevant
  term-fee kind, while portal outstanding is the sum of every term-fee Invoice
  balance across all Terms and excludes one-off charges. The Bursar Not
  invoiced card and actions now use real data; Awaiting approval remains for
  the later manual-approval batch. Fee schedule, Generate invoices and Not
  invoiced passed 1280px/390px browser checks with no horizontal overflow and
  no phone target below 44px. The shared desktop Notifications and Sign out
  shell controls remain 38px/40px high, an existing shell deviation; the QA
  database rows, route, screenshots, profile and server were removed. Schema
  docs, migration, Pint, Blade cache, Vite build and the full suite pass (**60
  tests / 611 assertions**). Phase 5 is not marked complete.

- **Admin Attendance navigation regression and Graduated attendance policy
  documentation (2026-10-19)** — Restored separate permission-filtered Admin
  links for Attendance rolls and Alerts without changing any role's fixed
  phone tabs or the Head navigation. Added one focused regression test covering
  both Admin links and the Teacher Attendance link. Recorded that Graduated
  pupils and their parents retain read-only, payment-gated Attendance for the
  last 30 recorded school days, while Awaiting admission remains limited to
  report cards and receipts; term-based Attendance history remains post-MVP.
  Pint and Blade compilation pass; focused test: **1 test / 3 assertions**;
  full suite: **52 tests / 582 assertions**.

- **Bulk Subject and Teacher assignment (2026-10-18)** — Added Admin
  Section-filtered and Head fixed-Section workflows for the five-step,
  add-only Subject wizard and By teacher/By class Teacher assignments. Both
  use shared server plans for preview/apply counts, validate every Class and
  Level against the actor's Section, ignore non-offered Class/Subject pairs,
  keep the teacher picker school-wide, and preserve Results on removal. New
  `manage_section_subjects` and `manage_section_teacher_assignments`
  permissions are migration-granted to Principal roles; Admin retains
  `manage_academics` and `manage_result_settings`, and heads still cannot open
  Results configuration. The 20-plus-subject matrix has a labelled internal
  desktop scroller, sticky header and frozen Class column, then becomes
  per-Class offered-Subject cards on phone. Admin and Head browser checks at
  1280px/390px pass with no horizontal page overflow and no measured page
  target below 44px; temporary QA data was removed. Migration, Blade compile,
  Vite build, Pint, focused tests (5 tests / 31 assertions), and the full suite
  pass (51 tests / 579 assertions). Whether a replacement Teacher should be
  swapped in one save remains an owner question; current behavior requires an
  explicit remove-and-save first.

- **Promotion revision 5b: Incoming from Primary (2026-10-18)** — Added the
  second Promotion tab for Admin and receiving-Section heads, with a
  server-scoped, 20-row paginated Awaiting admission list; same-Department
  Class and same-name/first-Arm suggestions; editable destinations; shared
  preview/apply plans; idempotent acceptance; selected and all-remaining not
  continuing; outcome reports; and generic audit entries. Acceptance preserves
  the current registration number when that column exists, creates no invoice,
  and moves the pupil to Active; not continuing moves only still-waiting pupils
  to Graduated. Both retain historical Results, Attendance and invoices, and
  Graduated pupils can read their past attendance subject to the existing
  payment gate. The current schema has no `students.registration_number`
  column, so the list correctly omits that field and 5c was not built. The
  Primary head is denied the tab/direct route; destination Section, Arm and
  pupil scope are revalidated server-side. Manual Admin cross-Section catalog
  authorization passed. At 1280px and 390px the list had no horizontal
  overflow; all page actions and all phone targets were at least 44px; review
  and acceptance completed end to end. Existing desktop pointer-only shell
  links remain 38–40px high and are recorded as a deviation, outside this
  screen's controls. Temporary QA users/data/tools were removed. `npm run
  build`, Pint on touched PHP, Blade compilation, and the full suite pass: 46
  tests / 548 assertions.

- **Re-issued Admin/Head dashboards, Bursar dashboard, and shared shell/navigation
  (2026-10-18)** — Verify-first confirmed that the old staff dashboard was
  assembled by `DashboardController` / `DashboardOverview` into one shared
  Blade view, the attendance work had already added Morning/Afternoon figures
  to Admin/Teacher and roster data to Teacher, `SectionAccess` already supplied
  the server-side Section scopes/assertions, and active operational Students
  were exposed through `Student::active()`. The reissue now provides Admin
  All/Section views, a fixed-Section Head view, a whole-school current-term
  Bursar view, Submitted-only result queues, distinct teacher counts, active
  Student counts, and shared AttendanceService Morning/Afternoon percentages,
  deadline alerts, Not held notices, and settings links. A Head without a
  Section now gets the locked state on both Dashboard and Attendance alerts.
  The permission-and-route-driven shell has grouped Admin/Head navigation,
  role phone tabs, and an independently scrolling link region with pinned brand
  and Sign out. Head Bulk subjects and Teacher assignments links remain absent
  because those pages/routes do not exist; Bursar Awaiting approval and Not
  invoiced cards/actions remain absent pending batch 5. Dashboard styles live
  only in `resources/css/app.css`; `docs/design-system.md` records the patterns.
  A fixed-query HTTP probe measured Admin **36**, Head **48**, and Bursar **11**
  queries both before and after increasing the fixture from two to four
  Sections. The existing Parent ward-growth query test remains green. Browser
  checks at 1280px and 390px found no horizontal overflow and no visible phone
  target below 44px; 1366×650/720/768 checks confirmed `overflow-y: auto` on
  the sidebar links with brand and Sign out pinned. Reference differences are
  limited to the explicitly deferred batch-5 Bursar content, route-gated Head
  links, real empty/data states, and native shared controls rather than static
  sample content. Temporary QA accounts, screenshots, server, and Edge profiles
  were removed. No schema change was needed. Pint, Blade cache, and Vite build
  pass; focused dashboard/core tests pass (**5 tests / 50 assertions**) and the
  exact full suite passes (**42 tests / 511 assertions**). Phase 5 remains not
  formally complete.

- **Promotion revision 5a: Student status, explicit active scope, promotion
  engine and Admin/Head Promotion screens (2026-10-18)** — Students now have
  `Active`, `Awaiting admission` and `Graduated` states, with retained source
  Arm/time data for Awaiting admission and no global status scope. Operational
  rosters, Class counts, Attendance and dashboard Student figures explicitly
  use `active()`; ownership, wards, result cards, receipts and payment history
  remain status-unfiltered. Awaiting-admission portal access is limited to past
  Results and paid Receipts, with no exams, assignments, Attendance, E-Notes
  or Pay controls. Graduated pupils and their parents additionally retain
  read-only, payment-gated Attendance for the last 30 recorded school days.
  `manage_promotions` is granted to School Admin and Principal and
  all source/destination IDs are revalidated against Section scope. The shared
  transactional plan powers preview and apply, supports per-row/bulk Class and
  Arm choices, Stay, stale-placement skips, idempotent reruns, audit records,
  final Awaiting/Graduated classification, and a separately validated
  single-Student move. Promotion remains locked until every Section has a
  saved arrangement. Admin/Head blocked, normal and final states passed
  1280px/390px browser checks with no horizontal overflow and no Promotion
  content target below 44px. Reference differences: Incoming is deliberately
  absent until 5b; live selectors use the established native fields rather
  than copying static sample rows; restricted portal pages use the existing
  neutral no-access pattern because no dedicated design exists. Temporary QA
  users, Students, Arms, screenshots, server and browser profiles were removed.
  Migration, Pint, Blade cache and Vite build pass; focused tests pass (**5
  tests / 38 assertions**) and the exact full suite passes (**38 tests / 484
  assertions**).

- **Class ordering, scoped Class management, Arrange screens and notification
  destinations (2026-10-18)** — Classes now have one school-wide numeric
  `position`, while the UI exposes only relative placement and whole-step
  movement. Parallel Department classes share a step and move together. One
  transactional Academics service owns create/edit/re-level/move/delete and
  first-time arrangement; every mutation is tenant- and Section-scoped and
  audited. Admin may manage every Section; Heads receive
  `manage_section_classes` and may manage only assigned Sections, including
  when request IDs are edited. Per-Section `classes_arranged_by` / `_at`
  markers are written only by Save, and the natural-sort guess remains form
  state until then. Class deletion consults module-owned Student, Result,
  Attendance and Invoice usage services and names every blocking history type.
  `notifications.url` is nullable, validates a bounded single-slash same-site
  path, and links inbox titles only when present; existing notification events
  remain text-only. Schema/design docs and the verify-first Foundations record
  are synchronized. Browser checks covered Admin Classes/Arrange and Head
  Classes/Arrange at 1280px and 390px: no horizontal overflow; all visible
  phone actions were at least 44px. Reference differences are limited to live
  one-Section data (so the Admin switcher is correctly absent), withholding
  list move controls until arrangement is saved, and omitting the unavailable
  Promotion follow-on route. Temporary users, browser profile, helper and
  server were removed. Focused tests: **4 tests / 66 assertions**; exact full
  suite: **33 tests / 446 assertions**. Pint, Blade cache and Vite production
  build pass.

- **Attendance confirmation-impact follow-up (2026-10-18)** — both existing
  inline “session not held” entry points now use one AttendanceService impact
  calculation for preview and apply: open alerts cleared, percentage exclusion,
  and already-marked students per selected session (shown only when non-zero).
  Alert-list previews are batched rather than queried per row. The existing
  trust-sensitive test now proves preview and applied counts are identical.
  A temporary isolated browser fixture verified the alert-row action end to end
  at 1280px and 390px: 155×44px action, panel opens with real counts, no
  horizontal overflow, and the applied report matches; the fixture was removed.
  Deferred visual polish and the off-hours attendance deployment warning are
  recorded below. Focused suite: **7 tests / 50 assertions**; full suite:
  **29 tests / 380 assertions**. Pint, Blade cache, and Vite build pass.

- **Twice-daily Attendance sessions (2026-10-18)** — Morning/Afternoon is
  stored on records and alerts while legacy null sessions remain whole-day
  and count twice. Marking and locks are per session; the posted tab is
  authoritative. Section alert times default to 08:00/14:00 WAT and every
  change is written to the new generic `audit_logs`; Heads use
  `manage_attendance` within assigned Sections and Admin may use any Section.
  The five-minute tenant-aware scheduler honours each Section/session due
  time, weekends and sessions-not-held, with idempotent alerts that resolve
  on a full roll or not-held declaration. Admin/Head alert and settings
  screens implement Ready rows 22–25, including preview/confirm/report flows;
  Student/Parent pages and dashboard summaries use the re-issued session
  designs and last 30 weekday school days. The teacher page uses a minimal
  existing-component tab because no teacher reference exists: its WAT clock
  selects only the opening tab, while the submitted tab controls the write.
  Trust-sensitive coverage passes (7 tests / 43 assertions). Final verification:
  29 tests / 373 assertions, Pint clean, Blade cache successful, and Vite
  production build successful.

- **Phase 5 exit-gap closure (2026-10-17)** — the default CLI
  `php artisan test` path now has GD and SQLite enabled and README Local Setup
  records both required extensions. `PortalContext` is request-scoped and
  request-cached across the dashboard controller, navigation and shell;
  students, class/section data and fee-due state are eager-loaded or batched
  outside the ward loop. The regression fixture is flat at 42 queries for one
  and three wards. Local demo dashboard measurements are Student 44, Parent
  43, Admin 26, Head 39 and Bursar 18 queries. Sections and Levels remain
  unpaginated with 100-row caps; Exam Types are user-creatable only by
  `manage_cbt`, restricted server-side to six supported names, and defensively
  capped at 100 rows. The system-wide list rule in `ARCHITECTURE.md` and the
  Phase 5 exit wording now distinguish growing activity lists from bounded
  structural lists. Oakfield's logo path and colours are seeded in
  `school_settings`, while code fallbacks are neutral; the local values are
  `branding/oakfield-logo.jpg`, `#2E4F56` and `#F5E58A`. Demo Principal is
  assigned as head of the new Secondary School Section and Junior Secondary
  is mapped to it. Pint passes; exact full command result: **23 tests / 354
  assertions, all passing**. This closes the requested gaps but does **not**
  mark Phase 5 formally complete.

- **Admin foundations A — Sections, Departments and scoped Classes UI
  (2026-10-17)** — the existing tenant-scoped backend now has the Ready Admin
  screens for Sections/Levels, optional Departments, editable Classes and the
  head's read-only Classes list. Section heads use tenant-scoped many-to-many
  `section_user` assignments; the current UI shows one head. Section selection
  is server-validated and session-remembered, department fields disappear when
  unused, in-use Departments cannot be deleted, and Admin/head phone navigation
  follows the approved order. Manual Edge checks covered all four variants at
  1280px and true 390 CSS-pixel width, including the All/Section switcher,
  pinned Sign out, 44px targets and no page-level horizontal overflow; the
  tabs intentionally scroll within their own strip. The Class order control is
  intentionally absent because its semantics remain an owner-blocked Promotion
  decision. Local migration, Pint, Blade cache and Vite build pass. The scoped
  trust test passes (1 test / 22 assertions); the full PHP 8.2/GD suite passes
  (22 tests / 350 assertions). Temporary visual-QA accounts, records, helper,
  server and screenshots were removed.

- **Student design batch 2 and Parent design batch 3 (2026-10-11)** —
  completed Ready rows 4–22 and the three Student re-issues. Student sign-in,
  Fees, Results, own-only report card, CBT result, Attendance, Assignments,
  E-Notes, Notifications and Profile use the shared portal UI. Parent ward
  selection is server-validated on every request and carried through links;
  dashboard, Results/card, Attendance, Assignments, Fees, Notifications and
  Profile are selected-child-only. Linked Parents can pay only their own
  wards' open invoices; the return trip reaches their Fees page, and a
  redirect alone never settles payment. New Parent notification text names
  the child. Report-card head labels use the configured Section title.
  `docs/schema.md` and `docs/payments.md` are synchronized. Local migration,
  Blade cache, production asset build and the trust-sensitive suite pass
  (22 tests / 340 assertions). Manual 1280px and 390px browser checks covered
  every Student and Parent route, ward switch, Pay sheet, and locked and
  populated report-card states; no horizontal overflow was found. Temporary
  preview records were removed. See the visual-deviation record below.

- **Student reference rollout / Phase 5 pull-forward (2026-10-09)** — the
  shared shell now uses the approved Student navigation (Dashboard, Exams,
  Results, Assignments, Attendance, Fees, E-Notes), the matching phone tabs
  (Home, Exams, Results, Work, More), inline Tabler icons, bell counts,
  notification ticker, shared sheets, notices, metrics, tables, empty states,
  and contrast/touch tokens in the sole `resources/css/app.css`. The Student
  dashboard, grouped exams list/start sheet, and focus-mode exam-taking screen
  replace the old views. Exam attempts now autosave answers and independent
  “Check later” flags, retry from local device storage, resume from server
  state, use a server-computed monotonic countdown, and submit saved answers
  after timeout without exposing `is_correct`. `exams.closes_at`, nullable
  answer selections, and answer flags are migrated and documented. The four
  requested trust cases pass alongside prior CBT coverage; full PHP 8.2/GD
  suite: **16 tests / 265 assertions**. Pint, Blade compilation, local
  migration, and Vite production build pass. Live headless-browser checks at
  1280px and 390px found no horizontal overflow; every visible phone target
  was at least 44px and the phone nav order matched the reference. The only
  deliberate differences are the approved dashboard-anchor placeholders for
  undesigned destinations and live/empty tenant data instead of mockup data.
  The supplied reference files themselves had no remaining phone-nav or
  phone-target conflict after applying `DESIGN_AMENDMENTS.md`.

- **Phase 5 Portals, Notifications & Hardening (2026-10-09)** — the shared
  dashboard now detects Student/Parent profiles without hardcoded roles.
  Students are scoped to self; Parents can switch only among linked wards.
  Results, payment status/actions, payment-gated attendance, and assignment
  status are visible together within one dashboard and represented in the
  profile-driven desktop/mobile navigation. Tenant-scoped in-app
  notifications have a paginated inbox/read state/log and are emitted for
  assignments, approved results, and confirmed payments to students and
  linked parents. Salary and disciplinary lists were brought under
  pagination; notification and portal lookups use tenant/FK indexes,
  bounded queries, batching, and eager loading. A temporary two-ward HTTP
  smoke fixture verified the complete dashboard/inbox and a constant
  40-query dashboard render, then was removed under the minimal-test rule.
  Local migration, Pint, Blade cache, Vite production build, and the full
  PHP 8.2/GD trust suite pass (11 tests / 251 assertions).

- **Client-supplied dashboard visual rollout (2026-10-05)** — Admin desktop,
  Teacher desktop/mobile, and the shared permission-driven shell use the
  supplied teal/cream reference layout and real Oakfield logo. Public
  homepage and print profile use the logo with uploaded-logo precedence.
  Tenant-scoped module services provide real counts/roster/approvals, not
  mockup data; incomplete attendance is not shown as a full percentage.
  Teacher dashboard RBAC gap was closed with scoped permission migrations.
  Desktop/mobile static browser previews were visually checked, and PHP
  8.2/GD tests (11 / 251), Pint, Blade cache, and Vite build passed.
  Handoff details: `docs/dashboard-visual-rollout.md` and
  `docs/design-system.md`.

- **Phase 4 Bursar/Payments** — tenant-scoped invoices, separate pending
  gateway attempts, confirmed payments/receipts, expenses, Paystack
  initialize/verify/signed webhook, Bursar screens, invoice-backed
  Admissions confirmation, and server-side Student/Parent result payment
  gating are complete. A real ₦50 Paystack TEST transaction succeeded;
  after temporary tunnel 502s, Paystack redelivered its actual
  `charge.success` event. Provider status was Delivered/HTTP 200, the local
  webhook recorded `newly_settled: true`, invoice #2 became Paid, and
  online receipt `OAK-1-00000002` rendered on Bursar without manual
  matching. The earlier failed test invoice and its pending attempts were
  removed; successful proof records remain. Full PHP 8.2/GD suite passes
  (11 tests / 249 assertions), as do Pint, Blade cache, and Vite build.
- **Design-system rollout across existing screens and Result Card** — shared
  exact color tokens, typography, buttons, status pills, dense tables, and
  responsive shell cover the built Phase 0–4 screens; the desktop sidebar,
  header, and mobile tabs use `@persist`, while internal links use
  `wire:navigate` and the Livewire progress bar is teal. The Bursar expense
  list is now a table. The branded PDF passes its 20-subject one-page A4
  guard with remarks on/off. Public mobile sign-in and homepage were
  visually checked; Blade cache, production Vite build, and full PHP 8.2
  suite with GD pass (11 tests / 249 assertions).
- **Payments/Staff HR schema sync** — `docs/schema.md` now explicitly
  reflects the implemented `payment_attempts`, unsigned integer kobo
  fields, payment-row-backed receipts (no receipts table), and dedicated
  `staff_sensitive_view_audit`. `AGENTS.md` now references
  `docs/payments.md`; checked against both module migrations.
- **Laravel project scaffold** — Laravel 12.69.3 runs on PHP 8.2.12 from
  the repository root; Composer dependencies are locked, baseline tests
  pass (2 tests / 2 assertions), npm dependencies audit clean, and the
  Vite production build succeeds.
- **Core tables and migrations** — `schools`, `school_settings`, `users`,
  `roles`, `permissions`, `role_permissions`, and `user_roles` are defined
  in the Core module. Same-school pivot foreign keys and school-scoped user
  email uniqueness are in place; `migrate:fresh` passed on the verified-empty
  local SQLite database. `docs/schema.md` reflects the implementation.
- **Tenant foundation** — `TenantContext` is a container singleton;
  `ResolveTenant` sets the configured single-school ID before HTTP auth;
  `BelongsToTenant` scopes queries, sets `school_id` on create, and rejects
  cross-school updates/deletes. It is applied to Core `User`, `Role`, and
  `SchoolSetting` models. A two-school isolation test passes.
- **Login/auth scaffolding** — school-scoped sign-in, session regeneration,
  sign-out, and a permission-gated dashboard route are implemented. An
  interactive CLI command provisions the first school administrator.
  Authentication and permission test passes, including a same-email user
  in another school, denied access without `view_dashboard`, and allowed
  access after assignment; full suite: 4 tests / 31 assertions.
- **Local deployment proof** — the actual SSH archive/atomic-release script
  completed two successful releases on an isolated Alpine WSL stand-in
  (`20261003061624` and `20261003061834`), then `rollback.sh` switched
  `current` back to the first. `/up` returned HTTP 200 both before and
  after rollback. The target runs PHP 8.2.26, Apache, MariaDB, and a
  single cron daemon; a timed cron invocation wrote the Laravel scheduler
  log. No persistent Node/PHP CLI worker is used (`QUEUE_CONNECTION=sync`).
  Checked release code modes 755/644, private shared `.env` 640, and
  group-writable shared storage 2770. Composer validation, 4 tests / 31
  assertions, clean npm install, and Vite production build passed locally.
  Docker image extraction failed on this Windows host, so WSL was used.
  The local proof is not proof of the future live cPanel host.
- **Phase 0 exit verification** — two-school isolation test passes,
  `view_dashboard` permission gates the real `/dashboard` route, and the
  local release/rollback, health, cron, PHP, and permissions checks above
  satisfy all three Phase 0 exit criteria in `PHASES.md`.
- **Academics foundation and configuration UI** — all seven Phase 1
  Academics structures are migrated and modeled with tenant scopes and
  same-school foreign keys. `academic_sessions` avoids Laravel's HTTP
  `sessions` table. The `manage_academics` gate protects eight configuration
  routes, and the dashboard links to one admin page with paginated lists
  and forms for levels, sessions, terms, classes, arms, subjects, and class
  subject assignments. A local authenticated HTTP walk created and listed
  all seven records; Blade compilation, the 4-test suite (31 assertions),
  Vite build, and MySQL migration on the WSL deployment target passed.
  Existing admins can run `oakfield:grant-academic-access`; new admins get
  the permission at bootstrap.
- **Tenant-aware Landing Page** — `/` now renders the school name from
  `schools` and logo, colors, motto, about text, contact information, and
  admissions link from `school_settings` through a Core profile service.
  A separately permission-gated school-profile editor updates those fields;
  the admissions link accepts only safe same-site paths or HTTPS URLs.
  Authenticated HTTP editing and public rendering were verified with local
  sample values; an unsafe `javascript:` link was rejected. The updated
  homepage check and full suite pass (4 tests / 33 assertions), and the
  production Vite build passes. Logo upload uses Laravel's validated public
  storage path; no real school logo has been supplied yet.
- **Admissions/Candidates pipeline** — tenant-scoped candidates and full
  status history, public registration with a shared login, staff-recorded
  payment confirmation (explicit Phase 4 stub), manual Passed/Failed entrance
  result (explicit Phase 2 CBT stub), configurable checklist, offers with
  candidate acceptance/decline and cron-driven lapse, and conversion to a
  student with class-arm placement. Conversion rejects an incomplete or empty
  checklist unless an administrator has the distinct override permission and
  supplies a reason; the override event records actor and reason. Staff and
  candidate HTTP endpoints, checklist updates, and both conversion paths
  passed the required test, including all eight happy-path status events.
  Public HTTP registration reached the Payment Pending candidate page.
  Candidate and staff views render; both candidate and checklist lists are
  paginated. New admins get admissions permissions at bootstrap, and the
  existing local admin grant command ran successfully. SQLite and a clean
  MariaDB migration passed; the isolated MariaDB verification database was
  removed afterwards. Full suite: 5 tests / 69 assertions; Blade cache and
  scheduler listing passed. No real gateway or CBT integration is claimed.
- **Phase 1 exit verification** — Academics class/arm/subject UI was already
  verified, the Landing Page remains sourced from school data, and the
  candidate registration → manual payment confirmation → manual exam result
  → offer → candidate acceptance → checklist-gated conversion path now runs
  through HTTP/service checks with every status transition recorded. The
  required checklist test is green. Phase 2 began after this verification.
- **Result Management and Printable Result Card** — tenant-scoped grading,
  templates, score entry/CSV import, teacher assignment enforcement,
  submit/approve/reject audit and lock, approval-triggered card creation,
  approved-only broadsheet/position, term defaults and personalized comments
  with distinct permissions and audit, and branded A4 PDF are implemented.
  HTTP result entry, positive CSV import, review, and card flows plus direct
  approved-edit/CSV bypass denial pass. A 20-subject Secondary fixture with
  CA1/CA2/Assignment/Exam,
  school logo, and 300-character Principal/Proprietor defaults rendered as
  exactly one A4 page through the PDF route. The focused test passes
  (1 test / 43 assertions). XAMPP PHP 8.2
  has GD installed but disabled in its ini, so the PDF test runs via
  `php -d extension=gd vendor/phpunit/phpunit/phpunit`; production PHP must
  also enable GD for PNG logos.
- **CBT** — tenant-scoped exam types, exams, questions/options,
  attempts/answers, permission- and Section-scoped authoring and publish UI,
  candidate/student
  sitting UI with a server-enforced timer, automatic marking, 40/50→56/70
  Results component conversion, Immediate/Scheduled/Manual release policies,
  direct Head/Admin release, server-side show-score/answer flags,
  and verified entrance outcome integration with Admissions are built.
  Scheduled entrance outcomes sync through the existing cron scheduler; the
  Phase 1 manual outcome fallback is rejected when a published entrance CBT
  exists. CBT tests cover HTTP staff creation, questions, publish, student
  and candidate attempts, result release, direct unauthorized access,
  score conversion, and Admissions status history. The local School Admin
  has been granted CBT access.
- **Phase 2 exit verification** — all required trust tests pass: result
  approval lock, CBT scoring/conversion, and candidate checklist conversion.
  The Results HTTP cycle covers teacher entry and CSV upload → submit →
  principal approval → card creation and approved-only broadsheet/position.
  The CBT HTTP cycle covers staff authoring → student/candidate sitting →
  auto-marking → Manual/Scheduled/Immediate release enforcement → Results
  or Admissions integration. Direct approved-result edits and unreleased
  CBT result fetches fail. A logo-bearing, 20-subject Secondary card with
  four components and long Principal/Proprietor comments passed the runtime
  one-page A4 PDF guard. Full PHP 8.2 suite: **8 tests / 152 assertions**;
  Blade cache, Pint, Composer validation, production Vite build, and
  scheduler listing pass. All migrations passed on both the local SQLite
  database and a clean isolated MariaDB 10.11 database; the latter was
  removed after verification. PHP 8.2 GD is installed in the local WSL
  stand-in, and remote deployment now checks GD before release activation.
- **Phase 3 Attendance** — tenant-scoped daily records and alerts, form-
  teacher assignment, full-roster one-page marking with Present defaults,
  same-day WAT edits and admin lock, and a weekday 17:00 WAT cron alert
  visible to Admin/Principal are built. Direct HTTP checks cover teacher
  scope, complete roster, same-day rejection, admin lock rejection, and
  visible alerts. Required trust test passes (1 test / 21 assertions); full
  PHP 8.2 suite passes (9 tests / 173 assertions). Blade compilation,
  scheduler registration, local SQLite migration, and Pint pass. The
  attendance page is reachable directly from teacher login.
- **Phase 3 Assignments (Mode A)** — tenant-scoped assignment, roster-wide
  tick, parent profile, and parent/ward tables are built. A teacher assigned
  to the class/subject can create an assignment and tick each student's
  Submitted/Not Submitted/Late status; an Admin can create/link a parent
  account. Student and Parent status pages query fresh rows on each request.
  A focused HTTP walk-through verified admin screen → parent creation →
  teacher creation/tick → immediate Student and Parent visibility (12
  assertions); the temporary smoke fixture was removed per the project's
  minimal-test policy. Local SQLite migration, routes, Blade compilation,
  Pint, and the 9-test / 173-assertion trust suite pass. Existing local
  admin/Teacher permissions were granted with the new CLI command.
- **Phase 3 E-Notes** — tenant-scoped notes with either a private PDF/Word/
  PowerPoint file or HTTPS link, Admin/subject-scoped Teacher upload, class/
  subject filters, and authorized private download are built. A focused
  HTTP walk-through verified unassigned-subject direct POST denial, assigned
  Teacher link upload and filtered listing, Admin file upload/download,
  and denied Teacher/unauthorized-account file fetch (11 assertions). The
  temporary low-risk smoke fixture was removed per the minimal-test policy.
  Local permissions granted, SQLite and isolated MariaDB 10.11 migrations,
  Blade compilation, Pint, production Vite build, and the full trust suite
  pass.
- **Phase 3 exit verification** — full-class attendance is a single roster
  page with Present defaults and one save action, reachable from teacher
  login; same-day and lock rules pass direct-request tests. The weekday
  17:00 WAT scheduler generates unresolved alerts for incomplete rosters;
  Admin and Principal can see them without marking access (verified by
  HTTP assertions). An assignment tick was verified immediately on both
  Student and Parent status pages without cache. A Teacher uploaded an
  assigned-subject E-Note, while an unassigned-subject direct POST failed.
  All four `PHASES.md` exit criteria are met. Final PHP 8.2 suite:
  **9 tests / 176 assertions**; Pint, Blade cache, scheduler listing, local
  SQLite/MariaDB migrations, and Vite production build pass. The isolated
  MariaDB verification database was removed after checking it.
- **Pre-Phase 4 class-teacher-comment correction** — the server-side
  `add_class_teacher_comment` gate now requires `enter_results` and an exact
  match to the report card's class arm `form_teacher_user_id`; subject
  assignments alone no longer suffice. The form teacher can view the card
  even without a subject assignment, and only sees the edit form when
  authorized. The existing Results trust test now checks a subject teacher
  direct POST is forbidden, the actual form teacher can save, and the
  comment audit records the right actor (focused test: 1 / 51 assertions).
- **Pre-Phase 4 subject-remark toggle** — `result_templates` now has
  `show_subject_remarks` defaulting on, with create and later on/off
  controls. Card HTML/PDF resolve each approved subject's remark from its
  matching `grade_bands.remark` and show no remark column when off; no
  teacher remark input was added. The focused Results test checks default
  on, both matching A/F band remarks rather than a stale result snapshot,
  off behavior, and HTTP PDF page guards for both states against the full
  20-subject Secondary card with CA1/CA2/Assignment/Exam, logo, and 300-
  character Principal/Proprietor comments. Both PDF states returned 200
  through the single-page A4 guard (focused test: 1 / 74 assertions).
  Local SQLite migration, Blade cache, Pint, and full PHP 8.2 suite pass
  (9 tests / 206 assertions).
- **Phase 4 Staff HR** — staff records, non-overwriting salary history,
  disciplinary entries, private documents, exit records, the three-way
  permission split, and per-view salary/disciplinary audit are built.
  Existing school-admin permissions were migrated. Local migration and
  Blade compilation pass; the focused test verifies two salary entries,
  permission denial, repeated-view audits, document access, and exit
  status (1 test / 23 assertions). Full suite: 11 tests / 249 assertions;
  Pint and Blade cache pass.

- **Report-card comment modes and summary controls** — Principal and
  Proprietor comments support independent Section-level single-default or
  average-range modes, while typed pupil comments retain precedence. Admin can
  set the printed Proprietor title; Heads configure Principal ranges only in
  their assigned Section. Result templates control overall total,
  average/overall percentage and exact-Arm position on staff web, PDF and
  portal cards; all three switches default on and remain editable after use.
  Focused coverage: 3 tests / 33 assertions. Definition of Done: full PHP 8.2
  suite 171 tests / 2,210 assertions, Pint, production asset build and isolated
  1280px/390px browser checks passed; both staff and portal PDFs returned 200.

- **Registration number format (completed 2026-11-04)** — The Admin page now
  follows the four-step composition reference with a live preview, fixed
  School / Section / Level / Year / Number order, retained codes, separator,
  year style, next-number floor, generated movement examples, issued-number
  warning and locked policy. One shared builder drives preview/allocation;
  migration `2026_11_04_000100` maps every old effective prefix to a Level code
  without rewriting issued numbers and widens full values/sequence keys. All
  designed separators and two-digit years remain supported across manual/import
  validation, search, CSV and printed views. Focused coverage passes (14 tests /
  100 assertions); full PHP 8.2 suite passes (182 tests / 2,317 assertions),
  targeted Pint and the production build pass. Isolated normal, numbers-issued
  and missing-code browser states pass at 1280px/390px with no overflow or tap
  target below 44px; the QA database/profile were deleted.

- **CBT scoping (completed 2026-11-04)** — one reusable scope now protects
  every CBT management list and id: Admin is school-wide, Heads manage exams
  linked to classes in any assigned Section, and classless/Entrance CBT remains
  Admin-only. Draft-only, zero-attempt deletion, edit/question/publish/release/
  close/attempt routes, and Section-scoped pickers are server-enforced. The
  release-approval step was retired; CBT remains auto-marked only. New
  `view_cbt_results` pages give Teachers closed-exam read-only scores and
  per-question answers for assigned Subject/Class pairs, Heads Section-wide
  access at any state, and Admin school-wide access. The additive role catalog
  migration grants Principal management/results and Teacher results without
  widening Bursar or removing custom permissions. Focused CBT/catalog coverage
  passes (15 tests / 182 assertions); the full PHP 8.2 suite passes (199 tests /
  2,537 assertions), Pint and the production build pass. Isolated Head,
  Teacher and Admin browser checks pass at 1280px/390px with scoped 404s, the
  deletion refusal, no horizontal overflow and no target below 44px; the QA
  database/profile were deleted.

- **CBT closing and Results conversion follow-up (completed 2026-11-05)** —
  every exam now chooses automatic end-time closing or staff-ended closing;
  one request-time rule protects pupil starts and Teacher result visibility,
  while an in-progress pupil retains the existing per-attempt timer. Scoped
  Heads/Admin can end early through a confirmed, audited action. Only School
  exam scores may be previewed and explicitly converted into the linked Draft
  Results component: Draft values are replaced, locked/non-Draft Results and
  pupils without submitted attempts are listed and skipped, and one generic
  audit retains counts and previous values. Focused CBT coverage passes (17
  tests / 191 assertions); the full PHP 8.2 suite passes (203 tests / 2,592
  assertions). Isolated 1280px/390px browser checks pass for the form, End
  confirmation, conversion summary and Mock state with no overflow or target
  below 44px; all QA data and profiles were deleted.

- **CBT 2 (completed 2026-11-05)** — automatic-close attempts now end at
  the earlier of personal duration and exam deadline, while stay-open exams
  retain the personal timer. One idempotent request-time lifecycle scores saved
  answers for personal expiry, automatic closing, staff End and pre-conversion;
  End reports and audits the submitted count. Scoped Heads/Admin have paginated
  Active-pupil live progress across every Arm, and Teachers are refused. Admin
  manages active/Feeds Results exam-type settings; conversion no longer depends
  on the type name, used types cannot be deleted, and type changes are audited.
  Focused CBT coverage passes (23 tests / 224 assertions); the full PHP 8.2
  suite passes (213 tests / 2,653 assertions).
  QA browser checks at 1280px/390px pass for live progress, End count, type
  list/form and the feeds-off state, with no overflow or target below 44px;
  `database/qa.sqlite` and temporary QA scripts were deleted.

- **CBT resit (completed 2026-11-06)** — scoped Heads and Admin can grant or
  withdraw one unused additional attempt for one pupil, with a required reason
  and Africa/Lagos deadline. Attempts are separate and numbered; grant and
  withdrawal are audited; used grants cannot be withdrawn; the latest submitted
  attempt feeds Results while a still-writing resit leaves the earlier submitted
  score in force. Progress derives Absent for closed exams with no attempt and
  exposes All, Not started, Writing, Submitted and Absent filters. The focused
  resit suite passed 7 tests / 66 assertions, the full suite passed 220 tests /
  2,719 assertions, and Admin/Student checks at 1280px and 390px found no
  horizontal overflow or tap target below 44px; all QA data and profiles were
  deleted.

- **CBT multiple-answer scoring (completed 2026-11-07)** — correct options now
  carry whole-number marks and one answer state owns an atomically replaced set
  of selected options. Single-answer questions remain radio choices;
  multi-answer questions allow up to the correct-option count, award partial
  credit without subtraction, restore saved ticks, and serialize rapid
  autosaves. Submission, expiry and forced end use the same scorer; staff and
  pupil reviews show selected/correct options and earned/available marks.
  Questions lock after any attempt begins. The additive migration preserves
  legacy weighted questions and copies legacy selections. Focused trust tests
  pass 5 tests / 41 assertions; the full PHP 8.2 suite passed 229 tests / 2,806
  assertions. Browser checks at 1280px and 390px passed authoring, validation,
  selection limit, refresh restore and both reviews with no overflow or target
  below 44px; the throwaway database, scripts and browser profile were removed.

## In Progress
- Bug fixes batch 2: implementation and automated verification are complete,
  including bounded 200-row preview queries, handled Paystack failures,
  Teacher draft/submit behavior, readable controls and UTF-8 source checks.
  Guarded QA passed every behavior/layout check, but the unaccelerated Windows
  CLI server still took **11.267 seconds** for the 200-row preview although the
  preview service itself measured **0.188 seconds** (27 queries, about 15–18 ms
  in the database). Keep this open until the owner enables OPcache or uses a
  concurrent local server and confirms the full request is below two seconds.
- Grade band editing fix: allow incomplete scales to be built and corrected
  incrementally while retaining range, overlap, result-lock and submission
  completeness enforcement.

## Next Up (see `PHASES.md`)

1. Batch 6D remainder: staff ticker events.
2. Final full-test pass.

## Landing page and admissions (planned)

### Landing design decisions (2026-11-14)

1. The public pages are built from `docs/design/landing`, with Build 1, 1.1 and 2 behaviour and tests intact.
2. Anything the school has not supplied comes from Website settings and the school profile; empty values hide their line or block and live pages never show supply/placeholder/confirm markers.
3. The Leadership section is hidden entirely for now; its Blade partial exists but is not included.
4. Photos are generated once as responsive WebP files at about 600px and 1200px, with a 1920px hero variant when the original supports it; source JPG files are not served publicly.
5. `public/.htaccess` adds module-safe compression, long caching for images and built assets, and short caching for pages; no CDN is used.
6. Public SEO includes title, description, canonical, Open Graph and Twitter metadata built from `APP_URL`, plus a favicon and `robots.txt`.
7. The design's `home.js` loads on Home only; Apply, Status and Privacy have no animation script.
8. Application status keeps the Build 1 eight-second refresh bounded at 176 seconds; only its styling changes.
9. Header and footer use one configured logo path; the 447px reference remains until the SVG arrives.
10. The admissions hall photo is cropped lower so desks, rather than the ceiling, remain prominent.

1. Registration is one public parent/guardian Application per child, with no
   document upload. It creates neither a User nor Candidate account in Build 1.
2. Admin switches admissions open or closed; a closed public page shows only
   **Admissions are closed** and refuses submission.
3. Admin switches the application fee on or off and stores its naira amount as
   integer kobo. Fee-on Applications go to Paystack; fee-off Applications are
   Registered immediately.
4. Application statuses are **Awaiting payment**, **Registered** and
   **Expired**. Registered means the school has the Application and fee; login
   details are issued later at school on exam day.
5. The Application is saved before Paystack. The submitting browser receives a
   two-hour absolute, non-renewing, HttpOnly, SameSite=Lax `/apply` cookie,
   Secure per configuration, containing only an opaque token whose hash is
   stored. It is cleared by **Not you? Start a new application**. A signed link
   valid until unpaid expiry provides status-only access without the cookie.
6. Status shows only the child's first name and state. Awaiting payment reuses
   one reference after verifying; Still confirming says not to pay again and
   offers a 30-second-limited Check again; Registered explains that exam details
   follow and credentials are handed out at school; Failed is shown only when
   Paystack reports failure; Expired offers a new Application. Unconfirmed pages
   refresh every 5–10 seconds for up to three minutes.
7. Callback, webhook, Check again, scheduled re-check and Admin manual payment
   use one row-locked idempotent finalizer. It verifies reference, NGN and amount,
   registers once and sends one confirmation; late older success is retained and
   flagged for Admin.
8. The required parent-chosen username uses existing normalization and format,
   is unique across Users and live Applications, refuses staff/system-like
   names with a generic rate-limited message, and is released on expiry/delete.
9. Unpaid Applications expire after seven days. A daily purge deletes those
   without unresolved or last-48-hour payment activity and logs counts only.
10. Consent is required and versioned. `/privacy` uses Admin-edited text with a
    clearly marked default placeholder. The public form has a honeypot and
    throttle.
11. Bursar Payments shows read-only Application-fee attempt reference, amount,
    status and date; it never enters tuition arrears or portal Fees.
12. Admin has a 20-row paginated searchable/filterable Applications list and a
    detail with reasoned audited manual payment, email resend and guarded delete.
13. `/admissions/register` redirects to `/apply`; the existing Candidate
    pipeline remains untouched for the later account-creation build.
14. Submit validates and saves the Application, queues its status cookie, then
    calls Paystack. Application email is deferred until after the response;
    scrubbed mail failures never change the Application status or response.
15. A matching unpaid, unexpired Application resumes only when the guardian
    email also matches case-insensitively: saved details win, no duplicate is
    created, and status says **You already started this application. Continue.**
    Different-email, paid and expired matches retain the unavailable-username
    response.
16. Admin navigation groups **Applications**, **Exam sessions** and
    **Admission settings** beneath **Admissions**. Candidates and Entrance
    exams remain outside this Build 1.1 group pending Build 2.
17. Applications provides counted **All**, **Paid**, **Unpaid**,
    **Awaiting date** and **Expired** tabs. Registered is Paid; Awaiting
    payment/Still confirming are Unpaid; Awaiting date is paid without a
    session.
18. Exam sessions have a name, date/time, venue note and in-person flag. Admin
    may make one school-wide or Section-specific; a Head is limited to their
    assigned Section and sees its Applications read-only.
19. Assigning or moving an applicant sends session details once after the
    response. Editing date/time/venue asks whether to email every assigned
    applicant (default yes); removing an applicant sends no email.
20. A paid applicant's cookie or signed status page shows only session name,
    date, time and venue in addition to the existing first-name/status view;
    an unassigned applicant retains the existing exam-date message.
21. Applicants get **no login until exam day**. Admin creates accounts per exam
    session and prints one-time cut-out credential slips; credentials are never
    emailed.
22. A candidate slip shows the school, child, session details, username,
    one-time readable password, sign-in address and a short instruction. The
    password has no look-alike characters, is stored only as a hash and appears
    only in that in-memory PDF.
23. Candidates are **not** forced to change their password.
24. Account creation uses the username chosen on the application. A username
    clash skips that applicant with a reason while the remaining selected
    applicants continue.
25. Entrance exams link to one or more Levels. Candidates see exams for their
    applied Level only on their session date and inside the exam live window;
    an exam with no linked Level remains visible to all candidates with a clear
    warning.
26. Levels may be marked **Assessed in person**. Those applicants still receive
    sessions but receive no candidate account or online entrance exam.
27. A Head for the applicant's Section or an Admin records Recommended, Not
    recommended or Undecided plus an optional note of at most 500 characters;
    the online score is read-only and Heads cannot manage accounts, payments or
    deletion.
28. Conversion keeps the candidate login for the Student and requires one
    password change. The application guardian is linked to an existing Parent
    with the same email or receives a new unconfirmed Parent account and a
    one-time cut-out slip; credentials are never emailed.
29. An application payment attempt older than the configured 30 minutes is
    verified once, then released and replaced if still unpaid. A later success
    for the old reference still finalises exactly once without double credit.
30. **Website settings** is one plain Admin page for landing-page contact and
    admissions text. Leadership names and portraits remain part of the later
    landing-page build.

## Deferred polish (attendance)

- Assign form teacher lives only on the Daily attendance page; consider adding
  it to the Classes screen.
- Attendance alerts lack the Admin Section switcher and the Head Section
  badge.
- Alerts lack the Today/Earlier date pill.
- Confirmations are an inline panel, not the reference modal or mobile bottom
  sheet with a loading state.
- Sessions & times lacks “Last changed by ... - date, time”.
- `docs/design-system.md` has no attendance-session guidance and
  `docs/schema.md` does not list `audit_logs` indexes.

## Deferred polish (promotion)

- No test proves the Admin can promote from a Class in the other Section;
  check this manually.
- No test covers “unchanged by any move” for an ordinary Class-to-Class move.
- The Preview promotion button is enabled while a destination still says
  “Choose a class”; the server rejects the incomplete destination.

## Deferred polish (academic setup)

- Restore a retired Result template.
- Duplicate a template as a starting point for a new one.
- Refine the Head Result configuration screen against the design agent's Head
  variant when that reference is delivered.
- Session and Term delete sheets remain generic until the server returns the
  relevant blocking counts.

## Deferred polish (fees and invoicing)

- Batch 5 part 2 had no desktop/phone browser comparison.
- Payment-gateway password confirmation uses an accessible inline field, not
  the reference desktop confirmation sheet/mobile bottom sheet.
- Invoice lifecycle confirmations use accessible inline disclosure panels;
  matching the reference desktop modal and phone bottom-sheet/loading treatment
  is deferred because it is not required for the production rules.
- Custom per-student amounts for schedule extras remain undecided; part 1 uses
  the schedule amount exactly.
- Fee-item/schedule-line creation and per-student invoicing use responsive
  inline cards instead of the references' desktop sheets/mobile bottom sheets;
  the preview, validation and live-total behavior is present.

## Deferred polish (accounts)

- Discipline entries are append-only. A mistaken entry cannot be edited or
  deleted; its correction is recorded as a new entry.

## Student Design Batch 2 Verification Gate (2026-10-10)

No application code was changed before this check.

### Decisions needed: stated current behaviour

1. **Confirmed — report-card privacy exposure.** The portal endpoint uses
   `ResultCardController::myResult`, supplies the whole class broadsheet, and
   renders the shared staff card containing every classmate's name, subject
   count, and average (`app/Modules/Results/Http/Controllers/ResultCardController.php:19-28`;
   `resources/views/results/card.blade.php:5-11`). Parents reach that same
   `results.mine.show` route from their dashboard, and the payment-access
   service explicitly accepts a linked ward (`resources/views/core/dashboard.blade.php:91-112`;
   `app/Modules/Payments/Services/ResultPaymentAccess.php:15-20`).
2. **Confirmed — Excused currently lowers attendance percentage.**
   `portalSummary()` counts every one of the 30 records in `$marked`, while
   only Present and Late enter `$present`; its percentage divides present by
   marked and returns `null` only when there are no records
   (`app/Modules/Attendance/Services/AttendanceService.php:153-164`).
3. **Confirmed — notifications have no URL or type.** The notifications
   migration contains channel/title/body/read time only, the model fillable
   list has no URL, and `send()` accepts only recipients, title, and body
   (`app/Modules/Notifications/Database/Migrations/2026_10_09_000100_create_notifications_tables.php:11-22`;
   `app/Modules/Notifications/Models/Notification.php:8-16`;
   `app/Modules/Notifications/Services/InAppNotifications.php:12-27`).
4. **Different — payer scope.** A Student can pay their own invoice, and the
   attempt already records `initiated_by` (`app/Modules/Payments/Services/PaymentLedger.php:65-83`;
   `app/Modules/Payments/Database/Migrations/2026_10_04_000900_create_payments_tables.php:35-45`).
   However, contrary to the batch-2 statement that a linked Parent can also
   pay, `BillingRecipients::owns()` recognizes only the candidate or Student
   user ID; it never checks the parent-to-ward link
   (`app/Modules/Admissions/Services/BillingRecipients.php:25-29`).
   `beginPaystack()` relies on that helper (`PaymentLedger.php:74-75`), so a
   linked Parent without `manage_payments` is rejected. This is a
   scope-changing difference and triggers the requested stop.

### New backend work A–E: stated current behaviour

A. **Confirmed — standalone Fees and Results lists do not exist.** The
   authenticated portal routes include only invoice payment and a single
   `/my-results/{card}` endpoint; there is no `/my-fees` or `/my-results`
   index (`routes/web.php:34-50`). `PaymentLedger::portalSummary()` limits
   invoices to five and `ResultCards::portalSummary()` limits cards to five
   (`app/Modules/Payments/Services/PaymentLedger.php:31-43`;
   `app/Modules/Results/Services/ResultCards.php:148-168`).
B. **Confirmed — locked portal data currently fails before rendering.**
   `myResult()` calls payment authorization before loading its view, and
   attendance `portalSummary()` does the same; both authorization paths throw
   rather than return a locked payload
   (`app/Modules/Results/Http/Controllers/ResultCardController.php:19-28`;
   `app/Modules/Attendance/Services/AttendanceService.php:153-164`;
   `app/Modules/Payments/Services/ResultPaymentAccess.php:15-44`).
C. **Confirmed — unreleased exam results are a bare 403 and released answer
   rows omit the Student's selection.** Ownership is checked first, then
   unreleased/non-submitted attempts abort; released rows contain question,
   optional correct answer, and optional explanation only
   (`app/Modules/CBT/Services/ExamWorkflow.php:274-296`).
D. **Confirmed — Student E-Notes access does not exist.** All E-Notes routes
   require `upload_enotes`, while listing and item access repeat that staff
   permission check (`routes/web.php:131-135`;
   `app/Modules/ENotes/Services/ENoteService.php:45-76`).
E. **Confirmed — Profile and password-change endpoints do not exist.** The
   authenticated portal route group has no `/my-profile` GET or password PUT
   (`routes/web.php:34-51`), and `AppNavigation` has no Student profile page
   destination (`app/Modules/Core/Services/AppNavigation.php:41-50`).

Additional confirmed batch-2 baseline: `payments.verify` verifies through
the ledger and then always redirects to the public `home` route
(`app/Modules/Payments/Http/Controllers/PaymentController.php:54-59`); the
redirect itself does not directly update an invoice, while settlement occurs
inside verified ledger processing (`app/Modules/Payments/Services/PaymentLedger.php:98-143`).

## Parent Batch 3 Verification (2026-10-11)

Only the four requested Parent-specific checks were repeated; the Student
batch-2 findings above remain the baseline.

1. **Confirmed — ward choice today is dashboard-only.** Dashboard passes the
   optional `student` query parameter into `PortalOverview`, which chooses
   the first ward by default and rejects an unlinked id
   (`app/Modules/Core/Http/Controllers/DashboardController.php:18`;
   `app/Modules/Portals/Services/PortalOverview.php:21-31`). Other Parent
   pages have no selected-ward propagation yet.
2. **Confirmed — assignments.parent mixes wards.** `forParent()` collects
   every linked student id and paginates all of their submission rows together
   (`app/Modules/Assignments/Services/AssignmentService.php:121-128`).
3. **Confirmed — Parent notification fan-out uses `userIdsForStudents`.**
   It looks up parent profiles for the supplied student ids and returns their
   user ids (`app/Modules/Assignments/Services/ParentAccountDirectory.php:32-37`);
   assignment creation invokes it for the roster
   (`app/Modules/Assignments/Services/AssignmentService.php:36-44`).
4. **Confirmed — payments.verify returns to the public home.** It calls
   `verifyPaystack()` then redirects to `home`
   (`app/Modules/Payments/Http/Controllers/PaymentController.php:54-59`).

## Student/Parent Design Review (2026-10-11)

- At 1280px and 390px, manually opened Student dashboard, Fees, Results,
  locked card, Attendance, Assignments, E-Notes, Notifications and Profile;
  Parent dashboard, Fees, Results, Attendance, Assignments, Notifications,
  Profile, and a populated card. All had document width at or below the
  viewport width. The Parent ward dialog switched between two linked wards;
  its Fees Pay sheet opened for a real unpaid invoice. The locked card
  exposed no score data. The populated card rendered its own subject and
  comments at both widths. An initial PDF-link contrast defect found in the
  preview was corrected in `resources/css/app.css` and the assets rebuilt.
- Reference deviations: screens with no existing school records show live
  empty states instead of design samples; optional profile password-change
  card was omitted; invoice line items, linked part-payment blocks and
  manual-payment “Awaiting approval” are hidden because Admin-foundations
  C/D data and approval flow do not exist yet. No second stylesheet or
  design-only State bar was shipped. No unresolved visual overflow remains.

## Phase 5 Exit Criteria Refresh (2026-10-25)

- **Parent/Student UX — Met:** the combined dashboard still exposes latest
  Results, Attendance and payment state, including the Batch 5 all-Term balance
  and status-only Parent fee indicator.
- **Pagination — Met:** the earlier full list audit remains valid; Batch 5's
  activity-growing Invoice, payment, approval, payer Fees and not-invoiced
  lists are paginated. Bounded structural catalogs retain documented caps.
- **No core-dashboard N+1 — Met:** one read-only HTTP method measured Admin 38,
  Head 48, Teacher 23, Bursar 18, Student 52 and Parent 57 queries. The existing
  automated guard remains constant from one to three wards. The Student/Parent
  increase is a fixed Batch 5 payment relationship/ownership cost rather than
  growth per ward; no optimization was made in this task.
- **Trust-sensitive tests — Met:** the full suite passes at **93 tests / 982
  assertions**.

This evidence refresh does **not** mark Phase 5 formally complete; only the
owner may do that.

## Phase 5 Exit Criteria Check (2026-10-09)

- **Parent/Student UX:** a temporary two-ward HTTP fixture verified a Parent
  can switch wards and see the selected child's latest result term,
  attendance status, tuition invoice, and assignment from `/dashboard`;
  the same composition scopes Student accounts to self. The fixture was
  removed after verification.
- **Pagination:** every standalone list view is paginated. The final audit
  corrected salary and disciplinary history; remaining unpaginated
  collections are bounded dashboard details, complete rosters/exam
  questions needed for one workflow, or form option sets rather than list
  views.
- **N+1/query inspection:** the two-ward portal HTTP render completed in 40
  queries with ward/student/class data batch-loaded and module summaries
  eager-loaded or bounded; RBAC permissions are eager-loaded once for the
  dashboard request. Core admin/teacher dashboards retain their existing
  batched label/user lookups.
- **Trust-sensitive suite:** all required tests pass under PHP 8.2 with GD:
  **11 tests / 251 assertions**. Pint, Blade cache, local SQLite migration,
  and the Vite production build also pass.

Phase 4 exit criteria are verified below for historical handoff context.

## Phase 4 Exit Criteria Check (2026-10-04)

- **Payment confirmation drives result-access gating:** verified by the
  direct Student HTML/PDF denial-before-payment and access-after-payment
  assertions in `ResultApprovalLockTest`.
- **Gateway payment auto-reconciles and appears to Bursar:** verified with
  Paystack TEST attempt #3 for ₦50. Paystack reported success; its real
  `charge.success` event was resent after temporary-tunnel 502s and then
  marked Delivered/HTTP 200. The local webhook log at 21:50:24 recorded
  payment #1 with `newly_settled: true`; invoice #2 was Paid, with online
  receipt `OAK-1-00000002` present in the Bursar view. No manual invoice
  matching or local fake settled this payment. The first-attempt transport
  failure remains a limitation of temporary local tunneling, not proof of
  live-host reliability.
- **Salary progression:** verified with two dated salary entries and the
  prior period's end date in `StaffSensitiveAccessTest`.
- **Sensitive-view audit:** verified for two salary views and one
  disciplinary view in `StaffSensitiveAccessTest`.

**Phase 4 is complete.**

## Decisions Log

| 2026-11-13 | **Phone and tablet header school name:** show the full school name, wrapping to at most two compact lines or reducing its size as needed; do not truncate it, overflow the viewport or reduce any control below 44px. | Owner decision: Batch 6A Part A |

| 2026-11-11 | **Batch 5 small fixes:** live Results attention no longer occupies the desktop sidebar; dashboard and phone content banners remain. The bell count is the live Results count plus stored unread notifications, is hidden at zero, and its page lists both sources with links. The sidebar brand shows the full school name, while Bulk subjects, Teacher assignments and Promotion use distinct book, checklist and upward-arrow icons. CBT Question CSV now uses `Question`, Options A–F and `Correct Answer`; a lone letter uses the exam's configured single-answer upload mark, a lone `LETTER=mark` overrides it, and multiple letters require a whole-number mark for each. Comma/semicolon, case and spacing variants are accepted; invalid, duplicate or empty-option letters are refused. Both earlier 13-column formats remain readable but cannot be mixed. The template contains three delete-marked examples, preview/apply remains all-or-nothing with the 200-question cap and the Admin/Head page includes a short How to use disclosure. | Replaces the Batch 4a desktop-sidebar placement and the 2026-11-07 fixed-layout CSV decision without adding notification persistence or changing partial scoring. | Owner decisions: Batch 5 small |

| 2026-11-11 | **Parents directory and account actions:** Admin gets a paginated Parents menu page searchable by parent or child, with each linked child's contextual learner word and all-term term-fee status. Admin alone may reset a Parent password, save an immediately confirmed unique email, and link/unlink children; each action is actor-snapshotted in the audit log and unlinking deletes only the pivot. Heads have Section-scoped read-only access with out-of-scope records returning 404; Bursars have school-wide read-only access. `view_parents` is granted to Admin, Principal and Bursar, `view_all_parents` only to Admin and Bursar, and `manage_parent_accounts` only to Admin, through the additive default-role catalog migration. Signed-out recovery pages disclose neither account/email existence nor eligibility and use only the generic confirmation “If an account matches, a reset link has been sent.” | Gives the office one bounded family directory while preserving child ownership, Section boundaries, one-time credentials and recovery privacy. | Owner decisions: Batch 4b |

| 2026-11-10 | **Batch 4a live Results attention:** notifications are derived from current Result rows, store no notification/read state, and stay hidden at zero. Teachers see Rejected Results only for assigned Subject/Class pairs; Admin sees Submitted Results school-wide; Heads see Submitted Results only in assigned Sections. The same bold link appears on dashboards and above navigation on every authenticated page. One item opens the Result; several open the filtered Rejected list; approvers open the existing Submitted list. | Gives each role an immediate, authorised correction/approval queue without another persistence model or redesigning Results. | Owner decisions: Batch 4a |
| 2026-11-10 | **Batch 4a pending Paystack safety:** recording a manual payment shows a warning while a Paystack attempt less than 48 hours old remains Pending. An attempt still Pending after 48 hours is labelled **Not confirmed** for staff, leaves the invoice unchanged and retains **Check again**; no cancel action is added and the scheduled recheck window is unchanged. Settlement remains one-payment-per-invoice and idempotent. | Makes a possible in-flight payment visible without inventing a provider state or allowing double credit. | Owner decisions: Batch 4a |
| 2026-11-10 | **Batch 4a sign-in and help:** the two username/account-office hints are removed from Sign in without replacement and authentication remains non-enumerating. Accessible hover/focus/tap tooltips are limited to genuinely unclear Admin/Head dashboard labels and never appear to signed-out visitors. | Keeps public authentication terse while preserving contextual help where staff figures need definition. | Owner decisions: Batch 4a |
| 2026-11-10 | **Batch 4a Sections:** a school may have any number of Sections and Heads; incoming/promotion labels derive the previous Section rather than naming Primary, Section learner terminology is selected on creation, and each Section retains its configurable Head title. | Keeps the existing global class order, Section scope and registration codes usable for Early Years → Primary → Secondary and later structures. | Owner decisions: Batch 4a |

| 2026-11-09 | A Parent may save an email from My details only after entering the current password. The new address is immediately usable for Paystack receipts, but password recovery stays disabled until the Parent uses the signed, single-use 60-minute confirmation link. | Separates receipt delivery from the stronger recovery trust boundary. | Batch 3b |
| 2026-11-09 | Existing email addresses are backfilled as confirmed; email addresses set by school staff are confirmed immediately. | Avoids unexpectedly removing recovery access from existing and school-provisioned accounts. | Batch 3b |
| 2026-11-09 | A payer may recheck only their own pending Paystack attempt; `manage_payments` users may recheck any attempt and every staff check is audit-logged. Manual rechecks are limited to once per attempt every 30 seconds. | Gives payers and finance staff visible recovery from delayed callbacks without exposing another payer's transaction. | Batch 3b |
| 2026-11-09 | Callback, webhook, manual Check again and the scheduler use one idempotent Paystack verification routine. | Keeps settlement validation and race handling identical across all entry points. | Batch 3b |
| 2026-11-09 | The scheduler rechecks Pending Paystack attempts from 2 minutes through 48 hours old every five minutes, at most 50 per run, tenant by tenant; logs contain aggregate counts only. | Recovers missed callbacks within cPanel limits without leaking references or producing unbounded provider traffic. | Batch 3b |
| 2026-11-09 | Paystack refunds, reversals and disputes remain report-only for this batch; no refund state transition is built. | The owner requested visibility analysis without expanding settlement behavior. | Batch 3b |

| Date | Decision | Reason | Source file |
|------|----------|--------|-------------|
| 2026-11-09 | **Batch 3a Results authority:** only the Teacher assigned to the exact Subject/Class pair may enter, save or submit scores. Admin and Head are read-only reviewers of Submitted Results (Admin school-wide; Head in assigned Sections), may approve or reject, and rejection requires a short reason that the Teacher sees before editing and resubmitting. CBT-to-Results conversion remains a scoped system action. | Separates human score ownership from approval authority even when an Admin holds every permission, while preserving the existing auditable CBT conversion. | Owner decisions: Batch 3a |
| 2026-11-09 | **Section learner terminology:** every Section chooses `Pupil` or `Student`; existing Nursery/Primary names backfill to Pupil, all others and new Sections default to Student. One shared helper derives singular/plural and case from a Section, Class, Arm, Student or uniform scope; mixed/no-context and Admin-wide pages use Students. Internal Student roles, permissions, tables, routes and registration-number wording do not change. | Lets school-facing language follow the learner's Section without renaming stable technical identifiers or making mixed-scope pages ambiguous. | Owner decisions: Batch 3a |
| 2026-11-09 | **New Student status:** Add student has no Status control, ignores a forged posted status and always creates Active. Existing import, admission, promotion and leaving workflows retain their own status transitions. | Prevents account creation from bypassing the established lifecycle while leaving later status changes in their dedicated workflows. | Owner decisions: Batch 3a |
| 2026-11-08 | **Bug fixes batch 2:** external HTTP calls use about a 5-second connect timeout and 15-second total timeout and fail gracefully; PHP's execution limit is not raised. Teacher **Submit for approval** saves the entered scores and submits them atomically; Save draft remains, saved drafts reload, and buttons retain readable contrast. | Prevents one slow provider request from exhausting a shared-host request or blocking the single-worker development server, while making the Teacher score workflow reliable without redesigning it. | Owner decisions: bug fixes batch 2 |
| 2026-11-08 | **Bug fixes batch 1:** a refused Teacher score save always explains why; error pages are friendly and never show a stack trace; GET requests to POST-only routes never sign anyone out; password reset by email is available to every active role with an email address; and staff selectors are staff-only, bounded and permissioned. | Restores the seven hand-testing paths without weakening current-Term, assignment, CSRF, tenant or role boundaries. | Owner decisions: bug fixes batch 1 |
| 2026-11-08 | **Live QA database proof:** a QA server is trusted only after the launcher requests a QA-only, stateless endpoint registered outside the `web` middleware group and proves the exact absolute `database/qa.sqlite` path. The proof has no session, cookies, CSRF, authentication or database write and exists only for a marked non-production QA run. No login, fixture access or browser check starts before it passes; missing or mismatched proof stops the child. Every QA session records and compares the persistent development database SHA-256 hash, size and modified time; any difference is investigated and reported as an incident. | Prevents a child server from silently re-reading `.env` or a proof request itself from starting a database session, while detecting any persistent development-database change. | Owner decisions: finish CBT bulk upload QA isolation |
| 2026-11-07 | **CBT question CSV upload:** Admin and Heads within their own Section may append up to 200 plain-text questions to a Draft exam with no attempts. The fixed 13-column CSV uses blank marks for wrong options and whole-number marks of at least 1 for correct options; one marked option is single-answer and multiple marked options set the pupil selection limit. Preview is all-or-nothing, stages privately for 30 minutes, applies once, preserves formula-leading characters as text, appends after existing questions and writes one actor-snapshotted audit entry. Teachers remain forbidden. | Gives schools a controlled bulk-authoring path without weakening CBT scope, question validation or attempt locks. | Owner decision: CBT bulk question upload |
| 2026-11-07 | **Dev/QA isolation hygiene:** delete only the Demo Student dataset recreated by the QA incident while retaining its audit and registration-number reservation; never lower the registration counter. Demo seeding is create-only and creates the pupil/parent/invoice set only when no pupils exist. Browser QA starts through guarded `qa:prepare` with an explicit absolute project `database/qa.sqlite` path; it refuses production and the persistent dev database. Never migrate or seed `database/database.sqlite` for QA. | Preserves owner data and permanently prevents an omitted or unresolved QA database override from falling back to the development database. | Owner decisions: dev hygiene after QA isolation incident |
| 2026-11-06 | **CBT multiple-answer scoring:** a question has at least two options, at least one correct and one wrong; every correct option has a whole-number mark of at least 1 and wrong options have zero. One-correct questions remain single-answer; multi-correct questions allow up to the number of correct options, award the sum of selected correct-option marks with no penalty, and count as answered after one selection. Question and exam totals are sums of correct-option marks. Autosave replaces the selection set; staff/pupil reviews show selected/correct options and earned/available marks. Questions, options and marks lock after any attempt starts. Existing weighted questions and stored scores are preserved, Results conversion remains percentage-based and Draft/Feeds-Results gated, and Teachers still do not manage CBT. | Adds partial-credit multiple choice without manual marking, negative marking, per-exam settings or weakened lifecycle/scope rules. | Owner decisions: CBT multiple answers |
| 2026-11-06 | Phase 5 hardening uses bounded account search (minimum two characters, maximum 20), independent 20-row Fee item and schedule-line pagination, SQL-backed CBT live progress with a 10-second polling refresh, index-friendly exact attendance dates, and class-first CBT Subject choices backed by `class_subjects`. Whole-roster pages have an expected review threshold of 100 pupils per Arm but no enforced enrolment cap. | Closes the fresh pagination/performance findings without queues, websockets, packages or a new design. | Owner decisions: Phase 5 fixes |
| 2026-11-06 | Accepted Phase 5 watch items are repeated Head/Student/Parent scope lookups; Results-configuration dropdowns; CBT question lists and attempt histories (review and enforce the expected 200-question maximum with bulk upload); Teacher CSV and Teacher Results summary for Arms approaching 100 pupils; and Invoice-detail adjustments. | These are bounded or fixed-cost today; review them at the stated scale instead of expanding this fix pass. | Owner decisions: Phase 5 fixes |
| 2026-11-05 | **CBT additional attempts:** “Give another attempt” is the only resit/special-access mechanism for illness, absence, technical faults or late arrival. A scoped Head/Admin may give one class-exam pupil one future Africa/Lagos deadline with a required reason; Teachers cannot and Entrance/Draft exams are excluded. One unused grant at a time creates one separately numbered attempt and may be repeated over time; unused grants may be withdrawn, while used grants may not. Grants work after closing without reopening the exam, and their attempts end at the earlier of normal duration or the grant deadline in both closing modes. Prior attempts/answers remain immutable; the latest Submitted attempt counts (including Results conversion), while a still-writing resit leaves the prior Submitted score in force. Absent is derived for a closed exam with no attempt. Grant/withdrawal actions retain actor snapshots and reasons. | Provides one auditable exception path without per-exam resit settings, stored attendance states or weakened Teacher/Section scope. | Owner decisions: CBT resit |
| 2026-11-05 | **Promotion 5c registration movement:** the locked school policy applies automatically with no batch/pupil override. A number changes only when an enabled Section/Level part changes: keep-whole retains it; keep-digits rebuilds from the stored sequence (never parsed text); new-prefix takes the next transaction-locked prefix sequence. A clash takes the next free destination-prefix value and is previewed/audited. Preview shows old → new; apply changes placement and number atomically. Incoming acceptance uses the same builder/counters and refuses missing destination codes atomically. Graduates/leavers retain their number and promotion authority is unchanged. Historical numbers remain reserved/audited; issued invoices retain their number snapshot; old-number search is not expanded by this task. | Makes promotion and Incoming acceptance obey the already locked format while keeping identifiers recoverable and race-safe. | Owner decisions: Promotion 5c; `docs/design/admin/specs/registration-movement-preview.md` |
| 2026-11-04 | **Superseded in part 2026-11-05:** every exam defaults to “Close automatically at the end time” and may instead stay open until a scoped Head or Admin ends it. Ending is one-way and may happen early in either mode. Effective closing is the explicit Closed state or an elapsed end time in automatic mode, evaluated whenever the exam is read. The statement that an automatic-close attempt keeps its full personal duration was replaced by the deadline-cap decision above. | Makes closing reliable without depending on `schedule:run`; the later decision closes the late-starter gap. | Owner decisions: CBT closing/conversion follow-up; CBT 2 |
| 2026-11-05 | **CBT timer and finalization:** an automatic-close exam caps each attempt at the earlier of personal duration and exam end; stay-open exams do not. Manual end, automatic deadline and personal expiry submit and score saved answers through one idempotent request-time routine, with no required job and the existing 30-second server grace retained. Scoped Heads/Admin get plain-refresh live progress for Active pupils in all Arms of the exam Class; Teachers remain forbidden. | Ensures late starters cannot write past the exam deadline and saved work is scored even when the pupil never presses Submit. | Owner decisions: CBT 2 |
| 2026-11-05 | **CBT exam types and Results:** `exam_types.feeds_results`, not a type name, controls conversion. School exam is enabled; all other and legacy types default off. Admin alone creates/edits type name, active state and the setting; referenced types are deactivated instead of deleted. End exam, conversion and type changes retain actor-snapshotted audits. Pupil CBT screens are inventory-only in this task. | Removes fragile name-based behavior while keeping legacy Internal exams out of Results unless Admin explicitly opts in. | Owner decisions: CBT 2 |
| 2026-11-04 | **Superseded in part 2026-11-05:** conversion remains a scoped, previewed action that replaces Draft component scores, skips Submitted/Approved/non-Draft Results and pupils without submitted attempts, never approves, and writes one actor-snapshotted audit. The exact-name “School exam only” gate was replaced by `exam_types.feeds_results` above. | Preserves the Results workflow while recording the later configurable eligibility rule. | Owner decisions: CBT closing/conversion follow-up; CBT 2 |
| 2026-11-04 | **CBT management scope:** Teachers never manage CBT. `manage_cbt` gives Heads full create/edit/question/publish/release/close/guarded-delete access only for class-linked exams in their assigned Section(s); `view_all_sections` gives Admin school-wide access. Classless and Entrance CBT remain Admin-only, and a manually granted holder with no Section sees nothing. Default Principal roles gain `manage_cbt` additively; Teacher and Bursar do not. | One reusable server-side scope protects lists, forms and every id-based management action without relying on customisable role names. | Owner decisions: CBT scoping |
| 2026-11-04 | **CBT lifecycle:** there is no release-approval step and no manual/theory marking. CBT remains auto-marked multiple choice. Heads and Admin publish and release directly. Only a Draft with zero attempts may be deleted; Published or Closed exams and any exam with attempts are retained. | Keeps the current automatic scoring model while making destructive behavior explicit and server-enforced. | Owner decisions: CBT scoping |
| 2026-11-04 | **CBT result visibility:** `view_cbt_results` is granted by default to School Admin, Principal and Teacher. Teachers see read-only pupil scores and per-question answers only for closed exams in their assigned Subject/Class pairs; Heads see assigned Sections at any state; Admin sees school-wide. Form-teacher class-wide rights do not widen CBT access. | Reuses persistent teaching assignments and Section ownership for a least-privilege results view. | Owner decisions: CBT scoping |
| 2026-11-04 | Normal Results keep their existing visibility and approval workflow: Teacher score entry is assigned-pair scoped, compiled cards/broadsheets are exact-Arm form-teacher scoped, and Head approval/card queues are Section-scoped; Admin remains school-wide. | Verification found no cross-pair or cross-Section exposure requiring a Results code change. | Owner decision and verification: CBT scoping |
| 2026-11-04 | **Phase 6D-2:** a Head sees the union of current staff linked to any assigned Section by direct `section_user`, a subject/class teaching assignment, or form-teacher arm. Admins, `view_all_sections` holders and all Head Staff permission holders are excluded. `view_section_staff` permits the allow-listed profile/teaching/discipline pages; `record_section_discipline` permits append-only Query, Warning and Commendation entries in scope. Out-of-scope ids return 404. Heads never receive salary, private documents, exit data or editable employment fields. | Resolves the Head Staff multi-Section and cross-Section rules with one reusable server-side scope while keeping sensitive HR data unavailable. | Owner decisions: Phase 6D-2 |
| 2026-11-04 | **Phase 6D-1:** an Admin may disable or re-enable staff sign-in without a reason; self-disable and disabling/exiting the last active Admin are refused. Disable and immediate, non-future Staff HR exit revoke all database sessions and remember tokens. Re-enable restores sign-in only. Every action stores the actor name/role snapshot. The Admin-only `view_activity_log` page is newest-first, 25 per page, shows Africa/Lagos time, actor, module and plain description, supports date/module/person filters, renders older missing snapshots as “Unknown user”, and provides no export, retention or deletion. | Makes account shutdown immediate and auditable while preventing school lockout and keeping employment state distinct from sign-in state. | Owner decisions: Phase 6D-1 |
| 2026-11-04 | Dependency security updates use the smallest version bumps within the current Composer constraints. The platform remains pinned to PHP 8.2.0, and `composer audit` must be clean before go-live. | Removes known advisories without widening the runtime or framework upgrade scope. | Owner decision: Composer advisory fixes |
| 2026-11-04 | Registration numbers are composed in fixed School / Section / Level / Year / Number order from any selected non-number parts; Number is always last. Separators are hyphen, slash, dot or none; padding is 3–6 and may grow; Year is the 2- or 4-digit Africa/Lagos calendar year at issue. School codes are 2–6 alphanumeric characters and Section/Level codes 1–6, upper-case and required when their part is active. Section codes are unique school-wide; Level codes are unique within Section, or school-wide when Section is absent. Issued and historical numbers never change or become reusable. The three movement policies and their lock remain; a policy has no effect without Section/Level. Next number may not be at or below the highest issued sequence. | Replaces fallback prefixes with one preview/allocation composition rule while preserving the existing school-wide or per-prefix transaction-locked counters and full-number uniqueness. Current consumers safely support every designed separator and the 2-digit year, so no option is dropped. | Owner decisions: Registration number format |
| 2026-11-04 | The owner-authorised Demo Student without a registration number, its login and only its dependent demo rows are deleted from the development database. No real pupil may exist without a registration number, so no unnumbered-pupil CSV fallback is designed. | Makes current registration number the sole safe pupil identity for Teacher score CSV without introducing name/internal-ID matching. | Owner decisions: Teacher pair-scoped score CSV |
| 2026-11-04 | Teacher score CSV identity is the current registration number; Subject/Class and current Term come from the route/page. The name column is reference-only. Line one must exactly name the pair and Term without IDs. A mismatch is refused, all validation is all-or-nothing, completely blank score rows are skipped, partial score rows are errors, and pupils omitted from the file remain untouched. | Prevents stale/wrong-context uploads and preserves the existing assignment, component-maximum and Result-lock rules through preview and apply. | Owner decisions: Teacher pair-scoped score CSV |
| 2026-11-03 | The Teacher Results workspace is current-Term only: every persistent teaching assignment is one **Subject · Class** pair covering all arms and all active pupils in that Class; there is no pupil-level elective filter. A form teacher separately sees approved compiled cards only for each exact arm assigned to them. Admin and Head workspaces retain their existing Section-scoped queues. | Matches the approved Teacher information architecture while keeping every selector and route derived from authenticated assignments. | Owner decisions: Teacher Results screens |
| 2026-11-03 | The proposed registration-number CSV workflow is not shipped yet. The existing importer accepts raw student/subject/term/component IDs, applies without a preview, and has no pair-bound generated roster; the dev database also has one pupil without a number. Teacher access to that legacy importer is server-refused while individual current-Term entry remains available. | The design explicitly requires the CSV path to be withheld when safe registration-number resolution, preview and complete roster numbering cannot be confirmed. | Owner decisions and backend verification: Teacher Results screens |
| 2026-11-03 | Principal and Proprietor report-card comments independently use either the existing Section/Term single default or reusable Section/type whole-number average ranges; typed pupil text always wins. Range lookup rounds the approved-subject average half-up, permits gaps and returns no range comment when no range or no approved Result matches. Admin configures Proprietor ranges and a school-wide printed title; Heads configure Principal ranges only in their Section. | Adds controlled automatic wording without changing class-teacher comments or rewriting stored personalized comments. Range edits intentionally affect existing non-personalized cards just as default-comment edits do. | Owner decisions: report-card comment modes and summary switches |
| 2026-11-03 | Result templates default to showing overall total, average/overall percentage and exact-Arm position; each display switch remains editable after Results use the template and applies consistently to staff web, PDF and portal cards. The embedded broadsheet and its exact-Arm competition ranking are unchanged. | Lets each Section control card summary presentation while preserving historical template selection and the established ranking calculation. | Owner decisions: report-card comment modes and summary switches |
| 2026-11-03 | Today's Afternoon attendance opens at 12:00 Africa/Lagos for ordinary form-teacher marking; Morning remains open all day. The server enforces the boundary before writing, `manage_attendance` retains its existing bypass, and past/future-date behavior is unchanged. The noon boundary is one named value, not a setting. | Keeps the visible tab state and direct POST trust rule aligned without changing the established attendance date or management rules. | Owner decisions: Fixes batch A |
| 2026-11-03 | Class names are trimmed and case-insensitively unique across the school on every application create/rename path; existing per-Level validation remains and no schema migration is added. | A Class name alone identifies its Section/Level context in current teacher-facing labels. | Owner decisions: Fixes batch A |
| 2026-11-03 | Paystack initialisation and connection-test failures always return a plain reason. Initialisation failures leave invoices unpaid, convert the attempt from Pending to Failed and log only category, HTTP status and Paystack's response message; connection checks distinguish missing keys, rejected keys and network failure. Saved keys are trimmed. | Makes payer and Admin failures actionable without weakening secret handling, webhook verification or settlement rules. | Owner decisions: Fixes batch A |
| 2026-11-03 | Compiled report cards and their embedded broadsheets are visible to the exact arm's form teacher, plus existing Admin/Head permissions within Section scope; a subject assignment alone grants no access. Teacher E-Note and Assignment forms use only assigned class/subject pairs, and teacher-facing pair labels use **Subject · Class** while an arm is appended only where the arm itself matters. Subject teachers cover every arm of their assigned Class. | Closes the class-wide report-card trust leak and gives teachers one consistent, scoped teaching context. | Owner decisions: Teacher access part 1 |
| 2026-11-03 | **Superseded in part 2026-11-04:** pupil-to-subject enrolment remains deferred and every pupil is treated as taking every subject offered by the Class. The statement that CBT scoping was unchanged/held was superseded by the CBT Section and assigned-pair scoping work above. | Retains the class-wide pupil/subject limitation while recording the later CBT authorization build. | Owner decisions: Teacher access part 1; CBT scoping |
| 2026-11-03 | Grade bands may be added, edited and deleted while a Grade scale is incomplete; only non-whole/out-of-range bounds, reversed ranges and overlaps are refused per operation. A scale becomes complete only when bands cover every whole mark from 0 through 100. Incomplete scales continue to block Result submission and name every uncovered range. Legacy decimal bands remain editable/deletable for correction, and any Result still locks the scale and its bands. | Allows an Admin to build or repair a scale incrementally without weakening grading completeness or historical locks. | Owner decision: Grade band editing fix |
| 2026-11-02 | Student import uses nine columns: `full_name`, `class`, `arm`, `registration_number`, `username`, `email`, `parent_name`, `parent_username`, `parent_email`. Section and Level are derived. A unique ordinary Class name resolves directly; a repeated name requires one of the listed Section / Level / Class paths. Arm is separate and may be blank only for a one-Arm class. The upload page embeds a school-scoped Class/Arm table with Copy controls and has no text download. | Matches the terms staff use while making genuine naming ambiguity explicit and server-enforced. | Owner corrections after Batch 6C hand-testing |
| 2026-11-02 | **Supersedes the credential CSV/download wording below:** all one-time Student, new-Parent and Staff credentials use one transient A4 print layout, eight slips per page, with dashed cut lines and one **Print / Save as PDF** action. Credentials remain only in the first no-store response and are never downloaded as a spreadsheet or stored. | Fixes blank import printing and makes the one-time handoff usable without creating another plaintext credential artifact. | Owner corrections after Batch 6C hand-testing |
| 2026-11-02 | Student import only creates pupils; corrections happen on the Student page and CSV never updates an existing pupil. A confirmed file's SHA-256 is refused within that school. A non-exact normalized-name/class match warns with the existing identity and requires a server-checked **Create anyway** acknowledgement. | Prevents accidental repeat creation without treating a plausible namesake as an automatic duplicate or introducing an update-by-file workflow. | Owner decisions for Batch 6C follow-up |
| 2026-11-02 | **Superseded in part 2026-11-02:** the import page provided a canonical class-label text download. The newer Class/Arm table replaces that download. The effective synchronous-cap rule and real template sample remain unchanged. | Preserves the cap decision while recording the hand-tested class-list correction. | Owner decisions for Batch 6C follow-up; newer correction above |
| 2026-11-02 | Production hashing remains bcrypt with temporary cost 10 and 12-character generated passwords. PHPunit uses minimum bcrypt cost except one explicit real-cost timing guard. | Keeps production credential strength unchanged while avoiding repeated production-cost hashing across the test suite. | Owner decisions for Batch 6C follow-up |
| 2026-11-01 | Student import is Admin-only under `create_students`, synchronous and all-or-nothing. Check creates no pupil accounts; Confirm revalidates and runs every Add-student operation in one transaction, is safe to repeat, numbers pupils in file order and keeps passwords out of queues and storage. The configurable initial cap is 50 rows and is shown and enforced. | Preserves the audited single-pupil workflow and makes rollback, duplicate-tab behavior and cPanel resource use predictable. | Owner decisions for Batch 6C |
| 2026-11-01 | **Superseded in part 2026-11-02:** the original Student import CSV had eight columns without a separate Arm. The newer nine-column decision above replaces the column contract. Exact-duplicate and Parent matching rules remain unchanged. | Retains the duplicate/family decisions while recording the corrected Class/Arm contract. | Owner decisions for Batch 6C; newer correction above |
| 2026-11-01 | **Superseded in part 2026-11-02:** Import credentials are shown only in the first Confirm response. The credential CSV download was removed; the browser now offers only the shared A4 Print / Save as PDF layout. Leaving the page retains the count/error report but permanently removes credential access. | Keeps temporary secrets transient without creating a downloadable plaintext credential spreadsheet. | Owner decisions for Batch 6C; newer correction above |
| 2026-11-01 | Email-backed accounts support tenant-scoped self-service recovery, initially enabled only for accounts holding the configured Admin-only `view_all_sections` permission. Responses are non-enumerating and throttled by IP and normalized identifier. Reset links are hash-only, single-use, synchronously mailed, invalidate older links and expire after 60 configured minutes; the log mail transport never receives them. | Delivers recovery without leaking account existence or secrets and keeps the rollout controlled by one configuration value. | Owner decisions for Batch 6 account recovery |
| 2026-11-01 | A self-service reset clears `must_change_password`, revokes sessions and remember tokens, audits the account as actor, confirms by email and notifies the other Admins without a password or token. Student and Parent Account pages use the shared current-password flow and show only own/linked profile data. | Reuses the established password and tenant-ownership services consistently across staff and portals. | Owner decisions for Batch 6 account recovery |
| 2026-11-01 | `oakfield:reset-admin-password {identifier}` is the console break-glass path: it refuses non-Admins, prints one temporary password once, requires first-login replacement, revokes sessions and writes a Console actor snapshot. | Keeps recovery possible when email is unavailable without retaining or transmitting a plaintext secret. | Owner decisions for Batch 6 account recovery |
| 2026-11-01 | Admin navigation groups its six payment destinations beneath Payments with permission-filtered children; Students remains in Daily ops and Registration numbers in Administration. Phone More uses nested groups with a Back control and hides unauthorized destinations. | Keeps related payment work together without changing permissions or the fixed phone tabs. | Owner decisions for Batch 6 UI follow-up |
| 2026-11-01 | “Add staff” is “Create staff account”; Staff HR uses “Add employment record”, and account creation and Staff access link to the preselected employment form. Salary is entered/displayed in naira and stored as integer kobo. Employment role title, contract type and date are editable and audited; status changes only through Exit record. | Separates login creation from employment records, preserves exact money storage and keeps employment exits explicit. | Owner decisions for Batch 6 UI follow-up |
| 2026-11-01 | An incomplete Admission requirements conversion requires a written 10–500 character Admin reason. The reason and outstanding count are retained in the audit trail, and Candidate history states that outstanding items remain unticked and conversion was approved by override. | Makes the D15 exception attributable and visible instead of silently bypassing incomplete requirements. | Owner decisions for Batch 6 UI follow-up |
| 2026-11-01 | Every control touched by the Batch 6 UI follow-up has a minimum 44px phone target; Admin payment navigation children are hidden rather than disabled when permission is absent. | Applies the approved mobile-access and permission-disclosure rule consistently. | Owner decisions for Batch 6 UI follow-up |
| 2026-10-31 | A pupil's current registration number appears on report cards, receipts, invoice detail and one-time sign-in slips. Record payment finds a pupil by the current number or any number in registration history, case-insensitively and within the school. | Keeps the identifier visible at the points where staff and families reconcile identity and preserves lookup after a number change. | Owner decision for the Batch 6B follow-up |
| 2026-10-31 | **Superseded in part 2026-11-04:** Registration-number prefixes may be configured at School, Section and Level and resolve Level → Section → School. The composable-format decision above replaces that fallback rule. Number width remains minimum zero-padding only, format changes still affect only future issues, the year remains the calendar year at creation, and a Graduated pupil keeps the last number. | Defines stable, non-semantic identifiers that do not break when the sequence grows or the pupil moves. | Owner decisions and amendment for Batch 6 part B |
| 2026-10-31 | The locked move-prefix choice is: keep digits/change prefix (Oakfield default), issue from the destination prefix's counter, or keep the full number. Choices 1 and 3 share one school counter; choice 2 uses one counter per prefix. Issued and historical numbers are never reused; Admin may advance but not rewind a counter. Prefix application during Promotion remains 5c. | Makes concurrency, movement and historical lookup deterministic without prematurely changing Promotion. | Owner decisions for Batch 6 part B |
| 2026-10-31 | Admin may change any registration number; a Head may change only a current pupil in their Section; Awaiting-admission changes are Admin-only until Incoming/Promotion 5c. Every change writes registration history and the audit trail. | Keeps the mutable identifier recoverable and Section-scoped while the receiving-Section workflow is not yet available. | Owner decisions for Batch 6 part B |
| 2026-10-31 | Admin-created and Admin-reset Student/Parent accounts receive one-time credentials and must set a password at first sign-in. Converted Candidates retain their chosen login and are not forced. One Parent family login may link to multiple pupils; linking an existing Parent creates no credentials. | Reuses the account foundation without replacing a Candidate's known secret or multiplying family accounts. | Owner decisions for Batch 6 part B |
| 2026-10-31 | Batch 6B delivers the Admin Students list, one-at-a-time Add student and one Student identity/sign-in/number page. Class movement stays in Promotion/Classes; import is 6C; Graduated/Awaiting portal behavior is reported but not expanded. D15 is now labelled “Admission requirements” and conversion still requires every item unless Admin records an override reason. | Keeps the part within its approved screen and workflow boundary. | Owner decisions for Batch 6 part B |
| 2026-10-31 | Minimum password length is eight for every account. One shared rule rejects the username, temporary/current password, normalized school name, all-digit values and a bounded common-password list; it imposes no symbol or mixed-case rule. Generated temporary passwords remain 12 characters. Candidate and Parent creation adopt the shared rule in 6B. | Keeps one understandable policy across staff and future Student/Parent account flows without weakening one-time credential handling. | Owner decisions for Batch 6 part A follow-up |
| 2026-10-31 | The last active School Admin cannot be demoted, disabled or removed through any path. Admins will be able to disable/re-enable logins and a Staff HR exit will disable sign-in in 6D; neither is built in this follow-up. Sole-Admin recovery will use the later self-service forgot-password flow. | Prevents an ordinary access change from leaving the school without an administrator while keeping 6D and recovery work bounded. | Owner decisions for Batch 6 part A follow-up |
| 2026-10-31 | Build order is 6B Students/registration numbers, 6C import, 6D Head Staff/Activity log/disable sign-in, then CBT bulk upload and guarded delete. | Replaces the previous Next Up ordering with the owner's delivery sequence. | Owner decisions for Batch 6 part A follow-up |
| 2026-10-31 | The 6C import row cap will be derived from measured password-hash time and the server execution limit and shown on the import page; no cap is chosen during part A. | Avoids guessing a synchronous workload limit before the import exists or the target host is measured. | Owner decisions for Batch 6 part A follow-up |
| 2026-10-31 | A Parent has one family login, using their own username or email, with a child switcher. | Keeps authentication tied to the adult rather than creating one login per child. | Owner decisions for Batch 6 |
| 2026-10-31 | Import is synchronous and all-or-nothing; temporary passwords are never queued; the browser builds the download and it is never stored. “Skipped” means an exact duplicate only. | Prevents partial enrolment and avoids retaining plaintext credential files on the server. | Owner decisions for Batch 6 |
| 2026-10-31 | Discipline entries are append-only; a correction is a new entry. A teacher belonging to both Sections is visible to both Heads with every discipline entry. | Preserves HR history while making dual-Section oversight explicit. | Owner decisions for Batch 6 |
| 2026-10-31 | Students and Parents change their own password through the shared service. Self-service forgot-password is wanted for accounts with an email; accounts without one ask the school office. | Replaces the earlier omission/deferred-recovery wording with the approved recovery boundary. | Owner decisions for Batch 6 |
| 2026-10-31 | **Superseded in part 2026-11-04:** Registration-number prefixes may be set at School, Section and Level; resolution is Level, then Section, then School. The composable-format decision above replaces that fallback rule. The three locked movement choices, permanent no-reuse rule and next-number floor remain. | Replaces the earlier Secondary-renumbering and Section-prefix-only decisions with one deterministic numbering policy. | Owner decisions for Batch 6 |
| 2026-10-27 | Staff role order is School Admin > Principal (Head) > Bursar > Teacher. Only Admin manages staff accounts; a changer cannot grant above their rank, and only Admin may grant unranked custom roles. A Head has exactly one Section, a Teacher one or more, and Admin/Bursar none. New usernames are 3–30 lower-case permitted characters, contain no `@`, and are school-wide case-insensitive; existing accounts are not backfilled. New generic audit rows snapshot actor id/name/role. Temporary passwords use a configurable lower hash cost while self-chosen passwords use the normal cost. | Establishes one reusable, server-enforced account foundation for the remaining Batch 6 work without changing existing email-only accounts. | Owner decisions for Batch 6 part A |
| 2026-10-26 | A used Result template may be retired only between Terms, remains read-only historical configuration, and releases its scope for one active replacement. New Results ignore retired templates; existing Results retain them for editing, submission and report cards. There is no restore action yet. | Lets a school change configuration over time without rewriting historical Results or creating duplicate active scope choices. | Owner decision: Retire and replace Result templates |
| 2026-10-26 | A Result template applies Section-wide, to one Level, or to one Class within a Level. Class wins over Level, which wins over Section-wide; at most one **active** template exists per scope. Results retain their stored template, so later or more-specific templates never alter existing Results or report cards. **The original all-time uniqueness wording is superseded by the Retire-and-replace decision above.** | Gives each Section a reusable default with narrow overrides while preserving historical scoring and printing. | Owner decision: Section-wide result templates |
| 2026-10-26 | Every successful save/delete redirects to an explicit stable list, parent, detail or originating page with a status message; Grade-band bounds accept numerically whole input and are cast/rendered as integers while fractional values remain invalid. | Prevents redirects back to method-only or deleted-resource URLs and removes misleading `.00` padding without changing score precision. | Owner-testing redirect and whole-number grade-band fixes |
| 2026-10-26 | Assessment components and Grade scales belong to exactly one immutable Section; a wrong item is deleted and recreated. A template's Level, Grade scale and every component must share a Section. Heads manage only their assigned Section through `manage_section_result_settings`; Admin retains school-wide `manage_result_settings`. | Allows Primary, Nursery/Kindergarten and Secondary configuration to differ without cross-Section mixing or granting Heads the broader Admin Results permission. | Owner decision: Section ownership of result configuration |
| 2026-10-26 | Grade scales, bands, templates and components may be edited/deleted by `manage_result_settings` only until a Result uses them. Grade-band bounds are inclusive whole numbers; adjacent bands meet at 20/21, grading rounds percentages to the nearest whole number with halves up, and an incomplete scale blocks submission with its uncovered marks named. **The Admin-only permission wording is superseded by the later 2026-10-26 Section-ownership decision above; the locks and whole-number rules remain current.** | Makes every attainable percentage grade consistently and preserves historical configuration once used. | Owner testing fixes |
| 2026-10-26 | A teacher belongs to a Section through `section_user`, a subject assignment in that Section, or a form-teacher arm there. Head pickers allow only active, qualified teachers in their Section; Admin sees those first and may choose qualified Other teachers. A dual-Section teacher appears in both. | Gives one server-side rule to every form- and subject-teacher picker without adding staff Sections before Batch 6. | Owner testing fixes |
| 2026-10-26 | A subject-teacher assignment may be replaced in one reviewed save. The preview names the count of entered Results affected; removal/replacement never deletes those Results and every change is audited with previous/new teacher IDs. | Resolves the former remove-and-save-first open question and keeps historical marks independent of current teaching authorization. | Owner testing fixes |
| 2026-10-25 | Result templates, grade bands and assessment components may be edited or deleted by `manage_result_settings` only until a Result uses them; used configuration is locked and a new template is created for later Terms. Component deletion also requires no template reference, and band changes must preserve a gap-free, non-overlapping scale. **Superseded in part by the 2026-10-26 whole-number Grade scale decision above.** | Preserves the configuration that produced historical scores and the live grade-band subject remarks while allowing correction before use. | Owner result-configuration and form-teacher fix task |
| 2026-10-25 | One additive `DefaultRoleCatalog` defines real and demo defaults for School Admin, Principal, Teacher, Bursar, Student and Parent. Student/Parent portal access remains profile-driven with no permissions. Existing-role migration adds missing defaults and removes nothing; role rank/assignability waits for Batch 6. | Prevents demo-only staff access and gives Batch 6 one reusable role list without prematurely defining delegation hierarchy. | Owner role-provisioning task |
| 2026-10-24 | Paystack credentials resolve from encrypted per-school saved keys first and matching environment keys second. `manage_payment_gateway` is Admin-only; Bursars see a locked state. An unreadable saved secret disables online payment and shows the Admin “The saved keys can't be read. Please enter them again.” while payers receive only the ordinary not-set-up message. | Keeps one credential source for initialize, verify and webhook HMAC, prevents silent fallback after an application-key change, and never exposes a secret. | Batch 5 part 4 implementation |
| 2026-10-23 | A Bursar may add a signed Invoice adjustment only with a required reason and audit entry, never reducing the total to zero or below settled plus open part-payments. A term Invoice with a settled part-payment cannot be voided, with no override. While any linked part-payment is open, direct payment of the whole remaining parent Invoice is blocked server-side; only the linked open part may be paid until every part is settled or voided. | Keeps corrections auditable, preserves settled money, and prevents concurrent whole-plus-part collection from overpaying a term Invoice. | Owner decision for Batch 5 part 3 |
| 2026-10-23 | Reuse `manage_fee_schedule` as the invoicing permission for Invoice detail, linked part-payment creation/void, adjustments and whole-Invoice void; keep `manage_payments` for collecting money and `approve_payments` for maker/checker decisions. Store lifecycle history in existing `audit_logs` rather than a second history table. | Preserves the existing Admin/Bursar responsibility split without adding an overlapping permission or duplicate audit source. | Batch 5 part 3 implementation |
| 2026-10-23 | Tests use SQLite in-memory only and fail fast otherwise. Browser/manual QA uses a separate gitignored throwaway SQLite file such as `database/qa.sqlite`, which is deleted after the check; QA must never refresh or seed `database/database.sqlite`. | The persistent local database was replaced by Subject-screen QA fixture data (school ID 9 and two QA users). The exact runner was not recoverable, but allowing QA to share the dev file was the contamination path. | Local environment diagnosis and recovery task |
| 2026-10-23 | Manual bank payments must equal the Invoice balance exactly and use a separate submission until approved; non-rejected normalized teller numbers are school-wide once-only, while rejection retains the log and releases the number for reuse. One-step approval lives in `school_settings`, is Admin-only, defaults off, and notifies every other Admin whenever changed. | Preserves maker/checker control, prevents duplicate settlement under races, keeps rejected evidence auditable and makes removal of the approval control visible to peer Admins. | Owner decision for Batch 5 part 2 |
| 2026-10-20 | Manual payments must settle the exact Invoice balance; partial manual payments are not allowed and must use the linked part-payment flow. Rejecting a manual payment releases its teller number for the Bursar to record again. | Keeps manual settlement deterministic while allowing rejected evidence to be corrected without permanently consuming the teller reference. | Owner decision for Batch 5 payment work |
| 2026-10-20 | Bursars may add Invoice adjustments only with a required reason; an adjustment may never reduce the Invoice total to zero or below the sum of settled and open part-payments. | Preserves an auditable reason and prevents an adjustment from invalidating money already settled or committed. | Owner decision for Batch 5 payment work |
| 2026-10-20 | A term Invoice with any settled part-payment cannot be voided, with no Admin override. | Settled financial history is immutable. | Owner decision for Batch 5 payment work |
| 2026-10-20 | Optional one-step manual-payment approval is Admin-only and disabled by default. | Keeps the normal maker/checker control while making the explicitly approved exception narrow and opt-in. | Owner decision for Batch 5 payment work |
| 2026-10-20 | The Parent “Fees due” status pill uses the same all-Term `termFeeOutstandingKobo()` balance as the portal: it appears only when term-fee arrears exceed zero and never exposes an amount; one-off Invoices do not trigger it. | Keeps the compact Parent status consistent with the detailed ledger without leaking amounts in the child switcher. | Owner decision for Batch 5 part 1, 7a gap closure |
| 2026-10-20 | Portal outstanding is the sum of the balance of every Student term-fee Invoice across all Terms, counting each Invoice once, subtracting all recorded Payments and treating paid Invoices as zero; one-off and other Invoice kinds remain excluded. Result locking remains isolated to the viewed Term's term-fee Invoice. | Shows real arrears without allowing an earlier debt to lock a later paid Term or unrelated charges to affect the result gate. | Owner decision for Batch 5 part 1, 7a remainder |
| 2026-10-20 | Resolve the current Term centrally as the date-containing Term with the latest start date in Africa/Lagos. Payment summary, automatic invoicing and Student portal context use that resolver; deletion deliberately continues protecting every date-containing Term. | Makes overlapping Term behaviour deterministic without weakening history protection. | Owner decision for Batch 5 part 1, 7a remainder |
| 2026-10-23 | When no Term contains today, automatic invoicing uses the earliest future Term; if none exists it creates nothing and the pupil remains Not invoiced. The Bursar summary instead uses the most recently ended Term and labels it; if none has ended it shows zero figures. A date-containing Term with the latest start date still wins during overlaps, every date-containing Term remains deletion-protected, and portal arrears and the Parent “Fees due” pill remain all-Term calculations. | Gives enrolment and Bursar reporting deliberate but different gap-between-Term behaviours while keeping all date selection in `AcademicCalendar`. | Owner decision for Batch 5 part 1, 7a follow-up |
| 2026-10-23 | **Superseded later on 2026-10-23:** Every Subject belongs to exactly one Section and names are unique per School and Section. Existing one-Section Subjects are assigned there; cross-Section Subjects are split per Section and every class-linked reference is repointed by the Class's Section; unused Subjects are copied into every Section. Subject creation requires a Section, Heads manage only their assigned Section, Admin may manage any Section, and only Admin may move an unattached Subject between Sections. The move allowance is superseded; the historical migration rules remain valid. | Removes shared Subjects while preserving class assignments, teacher assignments, scores and history. | Owner decision for Subject ownership by Section |
| 2026-10-23 | A Level or Subject's Section is selected at creation and is immutable afterward; a wrong record is deleted and recreated. Heads create, edit and delete Levels and Subjects only in their assigned Section through the existing `manage_section_levels` and `manage_section_subjects` permissions; Admin acts in any Section. Sessions and Terms remain Admin-only. | Removes ambiguous ownership changes and keeps every runtime mutation Section-scoped without broadening Head access to school-wide academic setup. | Owner decision for immutable Level/Subject Sections |
| 2026-10-23 | `manage_section_subjects` covers both bulk Subject assignment and Subject create/edit/delete for Heads within their assigned Section. | Keeps one narrowly scoped Head permission for all Section-owned Subject management without granting school-wide Academics access. | Subject ownership close-out |
| 2026-10-20 | Admin may edit/delete Levels and Class Arms in any Section; Heads use `manage_section_levels` and `manage_section_arms` only in assigned Sections. Sessions and Terms remain Admin-only; current date-containing Sessions/Terms cannot be deleted; date ranges may overlap but end must be after start; Levels have no independent order. Fee schedule copy preserves prices with no uplift. | Owner decisions for the academic setup small-items pass | `docs/design/admin/specs/academic-setup.md`; owner task dated 2026-10-18 |
| 2026-10-20 | **Superseded 2026-10-23 by the completed Subject ownership work:** Pause Subject ownership/edit/delete work until every existing school-wide Subject has an explicit Section assignment. The smallest safe change is a nullable `subjects.section_id` same-school foreign key, an explicit owner-approved assignment for every existing row, then a non-null constraint and Section-scoped uniqueness; do not infer ownership from current Class assignments because a Subject may currently be attached across Sections. | The current schema stores Subjects with only `school_id` and `name`, while the owner has now decided every Subject belongs to exactly one Section; silently assigning existing data would reshape live ownership. | `app/Modules/Academics/Database/Migrations/2026_10_03_000100_create_academics_tables.php`; owner task dated 2026-10-18 |
| 2026-10-19 | Fee schedule lines add across matching Level, Class and Department scopes; when the same fee item matches more than once, the most specific Class, then Department, then Level line wins; required lines are always invoiced and optional extras are selected explicitly | Makes overlapping schedules deterministic without double-charging the same fee item | `docs/design/admin/specs/fee-schedule.md`; owner decision for Batch 5 |
| 2026-10-19 | An in-use fee item cannot be removed; copying a previous schedule is deferred; active Students without a Class are skipped and counted during invoice generation | Preserves invoice snapshots and keeps bulk generation honest without inventing an automatic placement or copy workflow | `docs/design/admin/specs/fee-schedule.md`; `docs/design/admin/specs/invoices-generate.md`; owner decision for Batch 5 |
| 2026-10-18 | Name the Head permissions `manage_section_subjects` and `manage_section_teacher_assignments`; keep Admin access on `manage_academics` and `manage_result_settings`; reject a conflicting second-Teacher assignment until the existing assignment is explicitly removed | Gives heads only the two Section-scoped workflows without exposing Results configuration, while avoiding an undocumented silent replacement | `docs/design/admin/specs/bulk-subjects.md`; `docs/design/admin/specs/teacher-assignments.md` |
| 2026-10-18 | Drive the Bursar dashboard from the Academic Term containing today's Africa/Lagos date; show a true empty/zero state when no current Term exists instead of falling back to historical money | Keeps the whole-school figures and recent-payment list aligned with the reference's “this term” wording without inventing a term selector | `docs/design/admin/specs/bursar-dashboard.md` |
| 2026-10-18 | Name the Section-scoped promotion permission `manage_promotions`; keep Student status filtering explicit through `Student::active()` and `StudentDirectory` rather than a global scope; retain the last Class Arm plus `awaiting_from_class_arm_id` / `awaiting_admission_at` for cross-Section waiting history | Enforces edited-ID scope checks without role names, keeps historical portal/payment ownership resolvable, and supplies the source/waiting data required by Incoming 5b | `docs/design/admin/specs/promotion.md`; `docs/design/admin/specs/incoming-admissions.md` |
| 2026-10-18 | Name the Head-only Class permission `manage_section_classes`; store the one-time per-Section marker as `classes_arranged_by` / `classes_arranged_at`; when Sections have no stored order, place the first Class of an empty Section at the end of the school sequence and audit that choice | Keeps authorization permission-based, records who trusted the proposed order, and follows the owner's explicit fallback without inventing Section order data | `docs/design/admin/specs/academic-classes.md`; `docs/design/admin/specs/classes-arrange.md` |
| 2026-10-18 | Define an Attendance school day as a weekday on which the Student has at least one marked session; show at most the latest 30 such dates | This is the smallest deterministic rule without inventing a school calendar | `docs/design/ATTENDANCE_TWICE_DAILY.md` |
| 2026-10-18 | Reuse `manage_attendance` for session settings and not-held changes, with `view_all_sections` allowing any Section and other managers limited to assigned Sections | Preserves permission-based authorization while enforcing the approved Head/Admin scope on every write | `docs/design/admin/specs/attendance-sessions.md` |
| 2026-10-18 | Keep legacy null-session attendance rows unchanged and refuse a new split-session write for the same Student/date; all new application writes require Morning or Afternoon | Prevents MySQL/SQLite nullable-unique semantics from permitting ambiguous legacy-plus-session duplicates without a destructive backfill | `docs/design/ATTENDANCE_TWICE_DAILY.md` |
| 2026-10-18 | Interpret the 00:15 expired-offer schedule in Africa/Lagos, while keeping application and stored timestamps in UTC | Makes the existing school-day deadline explicit alongside every other scheduler timezone | `routes/console.php` |
| 2026-10-18 | Store classes in one school-wide position sequence; users choose “comes after” or “same step as” for parallel department classes and use Move up/Move down, while positions renumber automatically; two classes with the same department tag (including none) never share a position, each Section stays contiguous, and Arms are not ordered | Removes typed order numbers while preserving an unambiguous promotion sequence and parallel department steps | `docs/design/admin/specs/academic-classes.md` (supersedes its numeric-order interaction) |
| 2026-10-17 | Heads may perform bulk Subject assignment, Teacher assignment, and create/edit/delete Classes within their own Section; Admin may act in any Section, with scope enforced server-side | Replaces the earlier Admin-only/read-only design decision with owner-confirmed Section-scoped write access | `docs/design/admin/CODEX_BRIEF.md`; `docs/design/admin/specs/academic-classes.md`; `docs/design/admin/specs/bulk-subjects.md`; `docs/design/admin/specs/teacher-assignments.md` |
| 2026-10-17 | Run scheduled tasks in Africa/Lagos while keeping stored timestamps in UTC | School deadlines use local civil time without changing the database timestamp standard | `docs/design/admin/specs/attendance-sessions.md` |
| 2026-10-17 | Add a nullable same-site relative `url` column to notifications | Enables safe notification deep links without allowing cross-site targets | `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` §D13 |
| 2026-10-17 | **Superseded 2026-10-31:** Keep a pupil's registration number by default; optionally assign a new Secondary number at acceptance; enforce school-wide uniqueness, preserve old numbers as searchable history, and log every change; afterward Admin or the head of the pupil's current Section may change it. The current Batch 6 prefix-resolution, locked move-choice and Admin sequence rules replace this wording. | Preserves the historical decision while pointing to the newer owner-approved registration-number policy. | `docs/design/admin/specs/incoming-admissions.md`; owner Batch 6 decision |
| 2026-10-11 / 2026-10-17 | Use one school-wide Class sequence for promotion; default to the next Class by order in the same Department when possible and the same Arm name or first Arm; the last Class of the school moves Students to Graduated with history retained, no undo and a separate single-Student move; Heads promote only within their own Section and the server refuses out-of-scope moves; the last Class of a Section followed by another Section moves pupils immediately to Awaiting admission, removing them from that Section's lists, rosters, counts and automatic invoicing until the Secondary Head or Admin accepts them into an editable Class/Arm or marks them not continuing (Graduated); the flow is manual only with no scheduled lapse or Session/Term link | Consolidates the owner-approved promotion, cross-Section handoff, scope and defaults without inventing unresolved edge cases | `docs/design/admin/specs/promotion.md`; `docs/design/admin/specs/incoming-admissions.md` |
| 2026-10-19 | Graduated pupils, and parents viewing a Graduated pupil, retain read-only, payment-gated Attendance for the last 30 recorded school days alongside past report cards and paid receipts; Awaiting-admission pupils remain restricted to past report cards and receipts | Records the owner's explicit widening of the earlier Graduated portal-access decision while keeping Awaiting admission restricted |
| 2026-10-12 | Take Attendance in Morning and Afternoon sessions; null legacy rows count as both with no backfill; each Section's Head sets its alert times (Admin any Section, every change logged), defaulting to Morning 08:00 and Afternoon 14:00; the page defaults by Africa/Lagos noon but saves the chosen tab; Heads/Admin may mark a whole Section session not held for a date, suppressing its alert and excluding it from percentages; Excused is excluded, there is no whole-day shortcut or school calendar, and the alert job runs every five minutes | Confirms the complete twice-daily Attendance behavior and closes the Morning-time and Excused-percentage decisions | `docs/design/ATTENDANCE_TWICE_DAILY.md`; `docs/design/admin/specs/attendance-sessions.md` |
| 2026-10-17 | Store Section head assignments in tenant-scoped `section_user`, while showing one head per Section in the current UI | Owner-approved Admin Batch 4 design requires many-to-many assignments so a future vice principal does not require a schema change |
| 2026-10-11 | Portal report cards expose only the learner's own average and position, never the class broadsheet; locked HTML contains no scores, positions, subjects or percentages | The approved Student/Parent designs and privacy requirement supersede the old shared staff view |
| 2026-10-11 | Exclude Excused days from the Student/Parent attendance percentage denominator; Late still counts as present | Excused is not an absence and the old denominator incorrectly lowered attendance for an excusal |
| 2026-10-11 | **Superseded:** keep notifications without a `url` or type field and without deep links for this portal batch | Superseded by the 2026-10-17 decision to add a nullable same-site relative `url`; no notification type field was approved |
| 2026-10-11 | **Superseded 2026-10-31:** Omit optional Student/Parent self-service change-password card for this release; school-office reset remains the sign-in guidance. Students and Parents will instead change their own password through the shared service; email-backed accounts receive the later forgot-password flow. | Preserves the earlier portal-batch decision while recording the newer Batch 6 owner direction. |
| 2026-10-11 | Linked Parents may pay open invoices for their own children; extend and test `BillingRecipients::owns()` without weakening its existing owner checks | Owner resolved the Student batch-2 payer-scope difference; see `docs/design/student/CODEX_BRIEF.md` Batch 3 |
| 2026-10-11 | Carry validated `?student={id}` on every Parent link, default to the first linked child, optionally remember the last choice in session, and replace the auto-submit select with the ward bar/dialog (none for one child) | Owner decision; every request must verify `isWard`; see `docs/design/student/specs/parent-ward.md` |
| 2026-10-11 | Show a status-only “Fees due” switcher pill only for an own child with a real actionable unpaid invoice, never an amount | Owner decision; see `docs/design/student/specs/parent-ward.md` |
| 2026-10-11 | Prefix only the Parent copy of new assignment/result/payment notices with the child's first name; add no `student_id` column or child-tag pill and leave existing rows unchanged | Owner decision; see `docs/design/student/specs/parent-notifications.md` |
| 2026-10-11 | Parent Results, Attendance, Assignments and Fees remain selected-child-only; no all-children assignments/fees view and no Parent phone number | Owner decision; see `docs/design/student/CODEX_BRIEF.md` Batch 3 |
| 2026-10-11 | Keep `payments.invoice_id` unique; term-fee part-payments use linked invoices under Admin brief D10, not multiple settlements of one invoice | Owner decision; see `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` §D10 |
| 2026-10-10 | D1 — add tenant-owned Sections, assign every Level to one Section, assign heads to Sections without section-specific roles, and scope the listed operational data with `view_all_sections` as the cross-section permission | Owner decision; full rules and the unresolved Staff-list scope are authoritative in `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` §D1 |
| 2026-10-10 | D2 — support optional school-defined Departments as Class tags, never Arms, and preserve/default department during promotion choices | Owner decision; see `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` §D2 |
| 2026-10-10 | D3 — bulk subject assignment is level/optional-department/class/subject scoped, previewed, idempotent, and removal preserves historical Results | Owner decision; see `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` §D3 |
| 2026-10-10 | D4 — bulk teacher assignment works by Teacher or by Class, only for offered Class/Subject pairs, with preview/idempotency and history-preserving removal | Owner decision; see `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` §D4 |
| 2026-10-10 | D5 — dynamic forms hide irrelevant inputs while the server also clears/ignores them and validates only relevant fields | Owner decision; see `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` §D5 |
| 2026-10-10 | **Superseded in part 2026-10-31:** D6 — Admin creates Staff/Student identity and record together; school-unique nullable usernames support login; temporary passwords require first-login change; authorized Admin reset was to replace deferred self-service recovery; role assignment is bounded. Admin reset remains, but it no longer replaces recovery: email-backed accounts will receive self-service forgot-password. | Owner decision; see `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` §D6 and the newer Batch 6 recovery decision. |
| 2026-10-10 | D7 — support single/CSV Student enrolment, concurrency-safe school-unique registration-number formats with optional section prefixes and manual override, Guardian linking, automatic scheduled-fee invoicing, and a Bursar Not-invoiced list | Owner decision; see `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` §D7 |
| 2026-10-10 | D8 — add permissioned Fee Items/Schedules, required versus optional lines, immutable issued Invoice lines, logged corrections, idempotent bulk generation, one term Invoice per Student, and naira-only presentation | Owner decision; see `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` §D8; the cross-sibling Parent fees view remains open |
| 2026-10-10 | D9 — manual bank-teller Payments capture bank/teller/date/amount/photo, enforce globally single-use teller numbers, and require Admin approval unless a school setting enables single-step settlement | Owner decision; see `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` §D9 |
| 2026-10-10 | D10 — on-request linked exact-amount part-payment Invoices apply only to term fees, obey cap/void/audit rules, carry separate receipts, settle the term Invoice cumulatively, and never independently unlock gated data or double-count outstanding | Owner decision; see `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` §D10 |
| 2026-10-10 | D11 — tenant Paystack settings require `manage_payment_gateway`, re-authentication, encrypted write-only Secrets, environment fallback, mode/prefix validation, safe testing, audit/notification, saved-secret webhook verification, and a manual-only empty state | Owner decision; see `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` §D11 |
| 2026-10-10 | D12 — provide a permissioned, paginated, filterable, read-only Activity Log over existing audit data with tighter access for sensitive views | Owner decision; see `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` §D12 |
| 2026-10-10 | D13 — show the notification ticker for every role and notify the scoped head on Result submission, the Teacher on approval/rejection, and Admin on Offer acceptance, using notification URLs | Owner decision; see `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` §D13 |
| 2026-10-10 | D14 — CBT supports all-or-nothing previewed UTF-8 CSV Question import with template and blocks single/bulk Question or Exam deletion after Submissions exist | Owner decision; see `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` §D14 |
| 2026-10-10 | D15 — relabel “Admission checklist items” as “Admission requirements” and explain that all must be ticked before conversion unless an Admin makes a logged override | Owner decision; see `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` §D15 |
| 2026-10-09 | Pull the Ready-for-review Student component, dashboard, exams, and exam-taking references into the completed Phase 5 portal work as one ordered refinement batch | The client supplied approved Student references after the generic portal was completed; recording the pull-forward preserves the phase history without treating undesigned pages as complete |
| 2026-10-09 | Keep undesigned Student Results, Attendance, Fees, E-Notes, Notifications, and Profile destinations on existing dashboard anchors until their reference files exist | `docs/design/student/INDEX.md` explicitly forbids inventing rows 4–12 while requiring the complete approved navigation order |
| 2026-10-09 | Use one shared shell/component implementation and the existing `resources/css/app.css` for the Student rollout | Prevents a second styling pass or duplicate role stylesheet and keeps the supplied HTML as a visual reference rather than application code |
| 2026-10-09 | Treat the server deadline and saved attempt answers as authoritative for resumed and late CBT submissions; device time is display-only | Preserves the existing anti-extra-time rule while enabling safe autosave, refresh recovery, and late scoring from answers accepted inside the grace window |
| 2026-10-09 | Treat the latest issued Tuition invoice as the attendance gate when the school uses `TermInvoicePaid` | Attendance records do not carry a term ID; using the latest tuition obligation preserves the server-side payment gate without inventing an undocumented attendance-term relationship |
| 2026-10-09 | Send in-app assignment, result-approval, and payment-confirmation notifications to the Student and every linked Parent | Gives both portal audiences useful notices while keeping delivery synchronous and cPanel-safe; the notification log remains ready for later channels |
| 2026-10-09 | Allow `/dashboard` to authenticated Student/Parent profiles while retaining `view_dashboard` for other users | Portal identities are authorized by their tenant-scoped profile/ward ownership; generic accounts still fail the existing permission gate |
| 2026-10-05 | Show today's attendance percentage only after the relevant enrolled roster is fully marked | A percentage based on a partial roll would make the dashboard's school/class metric misleading |
| 2026-10-05 | Give existing teaching roles with both attendance-marking and result-entry permissions the separate `view_dashboard` permission | The local Teacher could perform teaching tasks but was denied `/dashboard`; this makes the new dashboard reachable without hardcoded role authorization |
| 2026-10-05 | Also grant `view_dashboard` to roles with `mark_attendance` alone | The local Teacher receives result and attendance permissions through separate roles, so requiring both on one role left the first grant ineffective |
| 2026-10-04 | Treat supplied HTML/screenshots as visual references, not executable product instructions; use the supplied JPEG as a single-school fallback behind an uploaded logo | Preserves live tenant data and profile-branding authority while matching the client-approved desktop/mobile look |
| — | Tenancy via `BelongsToTenant` trait + Global Scope + `TenantContext` singleton, not manual `where()` clauses | Prevents accidental cross-tenant data leaks from a forgotten manual scope; covers models even outside typical HTTP request flow when context is set explicitly |
| — | Table-per-role (`students`, `parents`, `staff`, `candidates`) instead of single `users` table with type column | Avoids a sprawling nullable-column `users` table; cleaner per-role querying |
| — | `students.candidate_id` nullable, intentionally | Supports both the entrance-exam admission pipeline and direct admin enrollment without an exam |
| — | Assignment Mode B (digital submission) deliberately excluded from this build | Keeps MVP scope boundary clean per `ROADMAP.md` |
| — | Build phases (Phase 0–5 in `PHASES.md`) kept distinct from MVP/Deferred scope split in `ROADMAP.md` | Avoids terminology collision between "what's in scope" and "what order we build it in" |
| — | Printable Result Card added to Phase 2 scope (`result_cards`, `term_default_comments` tables; single A4 page PDF with logo, school details, Principal's + Proprietor's comments) | Explicit client requirement; a result that can't be printed as a proper branded document isn't a finished Result Management module |
| — | Proprietor is School Admin wearing a second hat, not a distinct role — uses a separate `add_proprietor_comment` permission instead of a new `roles` row | Client confirmed; keeps comment-entry independently permissioned/audit-logged without adding an unneeded role |
| — | Principal's/Proprietor's result comments support both personalized and standard-default text, chosen per term/per student, no fixed system-wide mode | Client confirmed: schools may want either or a mix; `term_default_comments` + per-student override handles both without a mode switch |
| 2026-10-02 | Use Laravel 12 for the application scaffold | Supports the available PHP 8.2 runtime and broad cPanel compatibility without requiring PHP 8.3+ |
| 2026-10-03 | Use an SSH-driven, atomic-release cPanel deployment pipeline and verify it through Laravel's `/up` health endpoint | SSH permits one repeatable command to install production dependencies, migrate, cache, and switch releases without exposing a web deploy hook |
| 2026-10-03 | Change hosting proof to local-first; defer live-host proof until cPanel is purchased at project end | Avoids blocking Phase 0 and every later phase on a hosting account that does not yet exist |
| 2026-10-03 | Keep permissions global, scope user emails by school, and omit redundant `users.user_type` | Permission definitions are shared, while `user_roles` is the sole source of role assignment and different schools may use the same email |
| 2026-10-03 | Add `school_id` and same-school foreign keys to RBAC pivot tables | Prevents a role or user from being linked across school boundaries at the database layer |
| 2026-10-03 | Provision the first school administrator through an interactive CLI command | Allows initial login without exposing public registration or a tenant-management UI |
| 2026-10-03 | Use an isolated Alpine WSL host for the local deployment stand-in after Docker image extraction failed | It supplies PHP 8.2, SSH, Apache, cron, and Unix permissions without waiting for purchased cPanel access |
| 2026-10-03 | Allow reuse of an already verified asset build only for localhost deployment checks | Windows file locks made repeated `npm ci` unreliable; production deployment still requires the full preflight |
| 2026-10-03 | Give Academics child tables and class-subject assignments their own `school_id` and same-school foreign keys | Keeps all Phase 1 records tenant-scoped and prevents cross-school relationships at the database layer |
| 2026-10-03 | Use one `manage_academics` permission for the initial configuration screen | The first Phase 1 admin workflow is one coherent action; permissions remain data-driven and can be split later if needed |
| 2026-10-03 | Store school-year sessions in `academic_sessions`, not `sessions` | Laravel already uses `sessions` for authenticated HTTP sessions; separate tables prevent collision and preserve login data |
| 2026-10-03 | Keep school name in `schools` and public-facing content in `school_settings`; pass it to the Landing Page through a Core service | Preserves the Core/LandingPage module boundary and avoids hardcoded school identity in views |
| 2026-10-03 | Use a separate `manage_school_profile` permission and allow only same-site paths or HTTPS admissions links | Keeps public branding edits independent of Academics access and prevents unsafe link schemes |
| 2026-10-03 | Use staff-recorded payment confirmation and entrance outcome until Payments and CBT modules exist; use one `manage_admissions` permission for staff pipeline actions | Allows the Phase 1 admission journey without building Phase 2/4 integrations out of order; staff actions remain permission-gated and audited |
| 2026-10-03 | Create a shared `users` identity at candidate registration and select a class arm at conversion | Preserves table-per-role authentication and avoids assigning a student to an unconfirmed arm before admission |
| 2026-10-03 | Require at least one configured checklist item for ordinary conversion and a distinct `override_admission_checklist` permission for exceptions | Prevents an empty checklist from silently satisfying the gate and keeps override authority separate from routine admissions work |
| 2026-10-03 | Lapse expired unanswered offers in the existing cron-driven Laravel scheduler and permit an immediate staff lapse action | Enforces response deadlines without assuming a persistent worker on cPanel |
| 2026-10-03 | Treat Approved results as locked; store `locked_at`, percentage, and grade, and normalize component totals to 100 for grading | Matches the documented four-state workflow while making direct-request edit locks and scheme lookups explicit |
| 2026-10-03 | Use level-wide result templates with an optional class-specific override and an ordered component pivot. **Superseded by the 2026-10-26 Section-wide/Level/Class scope decision above.** | Allows a common grading configuration per level without duplicating it for every class, while supporting class exceptions |
| 2026-10-03 | Split result permissions into settings, score entry, and approval; use a module service for each Academic and Admissions lookup | Keeps approval authority independent of entry and preserves module boundaries while Staff HR/teacher assignments are not yet built |
| 2026-10-03 | Snapshot each result's class arm and template at first score entry | Later class moves or template changes must not silently alter historical term results or broadsheet grouping |
| 2026-10-03 | Assign score-entry users to a class/subject in Academics; Results settings managers may enter across assignments | Gives teachers server-enforced scope before Results entry is opened beyond school admins while retaining a practical admin correction path |
| 2026-10-03 | Use `barryvdh/laravel-dompdf` for report cards, without a browser or persistent render process | Compatible with the pinned PHP 8.2/Laravel 12 stack and cPanel shared-hosting constraints; A4 output still needs measured verification |
| 2026-10-03 | Keep personalized report-card comments nullable and resolve term defaults when compiling/rendering | Allows a later default edit to apply to non-personalized cards without overwriting a student's explicit remark |
| 2026-10-03 | **Superseded in part 2026-11-04:** CBT release remains an exam-level Immediate, Scheduled or Manual policy; the optional distinct staff-approval step was removed by the later no-approval decision. Internal exams still link to a Results component and entrance exams to Admissions. | Keeps release checks re-evaluable on every fetch while recording the later direct Head/Admin release rule. |
| 2026-10-03 | Rank report-card positions by mean percentage of approved subjects only, sharing positions on equal averages and showing the approved-subject count | Keeps broadsheets derived from locked scores while making incomplete approvals visible rather than silently treating them as complete |
| 2026-10-03 | Require PHP GD for deployment after adding PNG school logos to report-card PDFs | Dompdf needs GD to embed PNG files; preflight catches an unsupported host before switching releases |
| 2026-10-03 | Limit CBT to one attempt per participant/exam, allow 30 seconds of submit grace, and ignore answers received after the deadline | Prevents repeated attempts or extra-time answer changes without requiring a persistent timer process; late blank attempts currently score zero |
| 2026-10-03 | Allow a teacher assigned to any subject in the card's class to add its class-teacher comment until a form-teacher assignment model exists | Uses existing server-enforced class scope without inventing Phase 3 attendance/form-teacher structures early |
| 2026-10-03 | Interpret CBT date/time form entries as Africa/Lagos school time and store them in UTC | Avoids a one-hour scheduling/release error while keeping database timestamps consistent |
| 2026-10-04 | Store the current form teacher on each class arm and snapshot `marked_by` on attendance records | Gives Attendance an explicit server-enforced arm assignment without building Phase 4 Staff HR early |
| 2026-10-04 | Limit ordinary attendance edits to the same Africa/Lagos calendar day and reject explicitly locked records; generate incomplete-roster alerts at 17:00 WAT on weekdays | Meets the same-day trust rule and uses Phase 0 cron instead of a persistent worker; no holiday calendar exists yet |
| 2026-10-04 | Build only assignment-status pages for Student/Parent in Phase 3, backed by tenant-scoped parent-to-ward links; leave full portals to Phase 5 | Phase 3 requires immediate status visibility but Phase 5 owns broader portal/navigation work |
| 2026-10-04 | Store E-Note files privately behind an authorized download route and allow only HTTPS external links | Keeps classroom material within teacher/admin scope and avoids public storage exposure |
| 2026-10-04 | Replace the Phase 2 any-subject-teacher Class Teacher's Comment stopgap with the card arm's current form teacher | The Phase 3 form-teacher assignment now exists; deferring until then avoided inventing a second, conflicting teacher-to-arm model in Phase 2 |
| 2026-10-04 | Resolve displayed subject remarks from each approved result's template scheme, percentage, and grade; show the column if any result template enables it | Reads `grade_bands.remark` without new teacher input or relying on a possibly stale result remark snapshot; mixed templates retain per-subject toggle behavior |
| 2026-10-04 | Use integer NGN kobo amounts, separate pending gateway attempts from confirmed payments, and settle invoices once in Phase 4 | Avoids floating-point money errors and prevents a pending or retried callback from appearing as paid revenue |
| 2026-10-04 | Use Paystack for Phase 4 gateway integration; keep test/live secrets outside the repository and defer real sandbox proof until test keys exist | The client chose Paystack but has not yet obtained test keys; local signed-webhook tests cannot prove a real gateway transaction |
| 2026-10-04 | Require a settled Registration invoice for the Admissions Payment Confirmed transition; keep manual Bursar settlement separately permissioned | Removes the Phase 1 direct-status-change stub and makes all confirmation paths produce a payment/receipt record |
| 2026-10-04 | Store Staff HR salary history as NGN kobo with non-overlapping effective periods, and keep sensitive-view audits in a dedicated tenant table | Avoids floating-point salary drift, preserves progression, and makes each salary/disciplinary read independently traceable |
| 2026-10-04 | Use permission/profile-driven navigation and hide destinations without an implemented route; install current Livewire 4.4.7 for `wire:navigate`/`@persist` | Provides the documented navigation behavior without hardcoding role authorization or inventing Phase 5 screens |
| 2026-10-04 | Use Paystack's own Webhook Events API to redeliver the exact TEST `charge.success` event after temporary tunnel 502s | Proves the real signed provider event auto-settles through the webhook without fabricating a local callback or manually matching an invoice |

_(Add new rows here as real build decisions are made. Do not remove old
rows — this is a historical log.)_

## Blockers / Open Questions

These need a human (not an agent) decision — do not resolve these
silently:

1. How promotion works for schools with three Sections.
2. How to correct a pupil sent to **Awaiting admission** by mistake.
3. When a source Class has a Department but the next step has no Class with
   that Department, whether another destination should be suggested (5a
   currently requires an explicit choice).
4. Whether a deleted Class's name may be reused immediately.
5. Class-step combinations with a Class that has no Department while another
   Class on the same step has a Department.
6. Attendance: whether “not held” needs a reason note and how older unfixable
   alerts are handled. Marks made before not-held stay recorded but are not
   counted, and today's removable entry follows the existing alert generator;
   those parts are resolved.
7. Business: the original 12-week timeline and original price remain
    unconfirmed.

## Deferred Until Hosting Exists

- **Live host deployment proof:** when cPanel is purchased, run the SSH
  deployment on the real host, confirm the live domain's `/up` returns HTTP
  200, and check the plan's actual PHP version, GD extension, cron support, and file
  permissions. This is required for go-live, but is not a Phase 0 exit
  criterion. See `PHASES.md` → "Go-Live Checklist."

## Go-Live Checklist

- Select PHP 8.2 or higher in cPanel before deployment; Laravel 12 and the
  Composer platform requirement do not support PHP 7.x or 8.0/8.1.
- Back up before running the staged Result-configuration Section migration on
  a populated database: Migration A, dry run, audited real command, then
  Migration B.
- Set each Section's head title (Principal or Head Teacher) before printing
  report cards.
- Before launch, run a one-time migration making `levels.section_id` required
  once no Level without a Section exists (as was done for Subjects).
- Back up the production `APP_KEY` securely. Saved Paystack keys cannot be
  decrypted if that application key is lost or changed.
- Set the final Paystack keys through the Admin Payment gateway page after the
  production environment is live; do not place them in source or deployment
  scripts.
- On a populated database, back up first, run Migration A only, then
  `php artisan oakfield:migrate-subject-sections --dry-run`, review the counts,
  run the real command with `--actor=<admin-user-id>`, and only then run
  Migration B. Never run a plain all-migrations command for this change on a
  populated database.
- Deploy the twice-daily attendance change outside school hours. An arm that
  was already marked as a whole day cannot be split into sessions for that
  day.
- Configure a mailbox on the school's domain and set cPanel `MAIL_*` values in
  the private production environment before enabling self-service recovery.
  Run `php artisan oakfield:mail-test <external-address>` and confirm delivery;
  the command must report a real delivery mailer and must not print credentials.
  Verify the reset-link, password-changed and other-Admin messages with an
  external mailbox; never use the `log` mailer for reset links.
- Record the break-glass command in the operator runbook:
  `php artisan oakfield:reset-admin-password <email-or-username>`. Its temporary
  password is shown once and must not be copied into logs or tickets.
- Measure Student import seconds per row on the production host and set
  `STUDENT_IMPORT_SECONDS_PER_ROW`; confirm the displayed effective cap leaves
  half of the web `max_execution_time` available for the rest of the request.
- Configure cPanel cron to run
  `php /home/CPANEL_USER/oakfield-sms/current/artisan schedule:run` every
  minute. This also runs hourly cleanup of expired private Student-import CSVs
  and the five-minute bounded Paystack pending-attempt recheck.

## External Gateway Proof Record

- **Paystack sandbox transaction completed 2026-10-04:** test keys remain
  only in `.env`. The real TEST charge and provider webhook proof are
  recorded in the Phase 4 exit check and `docs/payments.md`. The temporary
  Cloudflare tunnel has been stopped; its test-mode webhook URL must be
  replaced or cleared before another test. This is not the separately
  deferred live cPanel deployment proof.

---

_Last updated: 2026-11-06 — Phase 4 is complete; Phase 5 is substantially
built but not formally complete. Batch 6C and Phase 6D-1/6D-2 are built; the
remaining owner-ordered work is listed under Next Up._
