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
  role); Principal's/Proprietor's comments support both personalized and
  standard-default text per term — see `ARCHITECTURE.md` §12.
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

Local SQLite has seven separate demo accounts (Admin, Teacher, Principal,
Student, Parent, Candidate, Bursar) for hands-on review. The first six were
created on 2026-10-04 and the Bursar was added on 2026-10-09.
The demo Teacher is assigned to the existing class arm and subject; the
demo Parent is linked to the demo Student. Existing accounts were not reset.
Passwords were delivered to the requester, not stored in this repository.

## Completed

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
  instead of silently reshaping data. The requested Levels/Subjects/Sessions
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
  attempts/answers, staff authoring and publish UI, candidate/student
  sitting UI with a server-enforced timer, automatic marking, 40/50→56/70
  Results component conversion, Immediate/Scheduled/Manual release policies,
  distinct release approval permission, server-side show-score/answer flags,
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

## In Progress

- Nothing currently in progress.

## Next Up (see `PHASES.md`)

- CBT bulk upload and guarded delete (remaining Admin foundations F).
- Accounts, students, bulk import and registration numbers.

## Deferred polish (attendance)

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

## Deferred polish (fees and invoicing)

- The Bursar **Awaiting approval** dashboard card belongs to the later manual
  payment approval work; no substitute count was invented in part 1.
- Custom per-student amounts for schedule extras remain undecided; part 1 uses
  the schedule amount exactly.
- Fee-item/schedule-line creation and per-student invoicing use responsive
  inline cards instead of the references' desktop sheets/mobile bottom sheets;
  the preview, validation and live-total behavior is present.

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

| Date | Decision | Reason | Source file |
|------|----------|--------|-------------|
| 2026-10-20 | Manual payments must settle the exact Invoice balance; partial manual payments are not allowed and must use the linked part-payment flow. Rejecting a manual payment releases its teller number for the Bursar to record again. | Keeps manual settlement deterministic while allowing rejected evidence to be corrected without permanently consuming the teller reference. | Owner decision for Batch 5 payment work |
| 2026-10-20 | Bursars may add Invoice adjustments only with a required reason; an adjustment may never reduce the Invoice total to zero or below the sum of settled and open part-payments. | Preserves an auditable reason and prevents an adjustment from invalidating money already settled or committed. | Owner decision for Batch 5 payment work |
| 2026-10-20 | A term Invoice with any settled part-payment cannot be voided, with no Admin override. | Settled financial history is immutable. | Owner decision for Batch 5 payment work |
| 2026-10-20 | Optional one-step manual-payment approval is Admin-only and disabled by default. | Keeps the normal maker/checker control while making the explicitly approved exception narrow and opt-in. | Owner decision for Batch 5 payment work |
| 2026-10-20 | The Parent “Fees due” status pill uses the same all-Term `termFeeOutstandingKobo()` balance as the portal: it appears only when term-fee arrears exceed zero and never exposes an amount; one-off Invoices do not trigger it. | Keeps the compact Parent status consistent with the detailed ledger without leaking amounts in the child switcher. | Owner decision for Batch 5 part 1, 7a gap closure |
| 2026-10-20 | Portal outstanding is the sum of the balance of every Student term-fee Invoice across all Terms, counting each Invoice once, subtracting all recorded Payments and treating paid Invoices as zero; one-off and other Invoice kinds remain excluded. Result locking remains isolated to the viewed Term's term-fee Invoice. | Shows real arrears without allowing an earlier debt to lock a later paid Term or unrelated charges to affect the result gate. | Owner decision for Batch 5 part 1, 7a remainder |
| 2026-10-20 | Resolve the current Term centrally as the date-containing Term with the latest start date in Africa/Lagos. Payment summary, automatic invoicing and Student portal context use that resolver; deletion deliberately continues protecting every date-containing Term. | Makes overlapping Term behaviour deterministic without weakening history protection. | Owner decision for Batch 5 part 1, 7a remainder |
| 2026-10-20 | Admin may edit/delete Levels and Class Arms in any Section; Heads use `manage_section_levels` and `manage_section_arms` only in assigned Sections. Sessions and Terms remain Admin-only; current date-containing Sessions/Terms cannot be deleted; date ranges may overlap but end must be after start; Levels have no independent order. Fee schedule copy preserves prices with no uplift. | Owner decisions for the academic setup small-items pass | `docs/design/admin/specs/academic-setup.md`; owner task dated 2026-10-18 |
| 2026-10-20 | Pause Subject ownership/edit/delete work until every existing school-wide Subject has an explicit Section assignment. The smallest safe change is a nullable `subjects.section_id` same-school foreign key, an explicit owner-approved assignment for every existing row, then a non-null constraint and Section-scoped uniqueness; do not infer ownership from current Class assignments because a Subject may currently be attached across Sections. | The current schema stores Subjects with only `school_id` and `name`, while the owner has now decided every Subject belongs to exactly one Section; silently assigning existing data would reshape live ownership. | `app/Modules/Academics/Database/Migrations/2026_10_03_000100_create_academics_tables.php`; owner task dated 2026-10-18 |
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
| 2026-10-17 | Keep a pupil's registration number by default; optionally assign a new Secondary number at acceptance; enforce school-wide uniqueness, preserve old numbers as searchable history, and log every change; afterward Admin or the head of the pupil's current Section may change it | Preserves identity history while supporting a Secondary numbering scheme under Section-scoped authority | `docs/design/admin/specs/incoming-admissions.md` |
| 2026-10-11 / 2026-10-17 | Use one school-wide Class sequence for promotion; default to the next Class by order in the same Department when possible and the same Arm name or first Arm; the last Class of the school moves Students to Graduated with history retained, no undo and a separate single-Student move; Heads promote only within their own Section and the server refuses out-of-scope moves; the last Class of a Section followed by another Section moves pupils immediately to Awaiting admission, removing them from that Section's lists, rosters, counts and automatic invoicing until the Secondary Head or Admin accepts them into an editable Class/Arm or marks them not continuing (Graduated); the flow is manual only with no scheduled lapse or Session/Term link | Consolidates the owner-approved promotion, cross-Section handoff, scope and defaults without inventing unresolved edge cases | `docs/design/admin/specs/promotion.md`; `docs/design/admin/specs/incoming-admissions.md` |
| 2026-10-19 | Graduated pupils, and parents viewing a Graduated pupil, retain read-only, payment-gated Attendance for the last 30 recorded school days alongside past report cards and paid receipts; Awaiting-admission pupils remain restricted to past report cards and receipts | Records the owner's explicit widening of the earlier Graduated portal-access decision while keeping Awaiting admission restricted |
| 2026-10-12 | Take Attendance in Morning and Afternoon sessions; null legacy rows count as both with no backfill; each Section's Head sets its alert times (Admin any Section, every change logged), defaulting to Morning 08:00 and Afternoon 14:00; the page defaults by Africa/Lagos noon but saves the chosen tab; Heads/Admin may mark a whole Section session not held for a date, suppressing its alert and excluding it from percentages; Excused is excluded, there is no whole-day shortcut or school calendar, and the alert job runs every five minutes | Confirms the complete twice-daily Attendance behavior and closes the Morning-time and Excused-percentage decisions | `docs/design/ATTENDANCE_TWICE_DAILY.md`; `docs/design/admin/specs/attendance-sessions.md` |
| 2026-10-17 | Store Section head assignments in tenant-scoped `section_user`, while showing one head per Section in the current UI | Owner-approved Admin Batch 4 design requires many-to-many assignments so a future vice principal does not require a schema change |
| 2026-10-11 | Portal report cards expose only the learner's own average and position, never the class broadsheet; locked HTML contains no scores, positions, subjects or percentages | The approved Student/Parent designs and privacy requirement supersede the old shared staff view |
| 2026-10-11 | Exclude Excused days from the Student/Parent attendance percentage denominator; Late still counts as present | Excused is not an absence and the old denominator incorrectly lowered attendance for an excusal |
| 2026-10-11 | **Superseded:** keep notifications without a `url` or type field and without deep links for this portal batch | Superseded by the 2026-10-17 decision to add a nullable same-site relative `url`; no notification type field was approved |
| 2026-10-11 | Omit optional Student/Parent self-service change-password card for this release; school-office reset remains the sign-in guidance | Both approved profile specs mark this card optional and the Admin brief defines authorized reset separately |
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
| 2026-10-10 | D6 — Admin creates Staff/Student identity and record together; school-unique nullable usernames support login; temporary passwords require first-login change; authorized Admin reset replaces deferred self-service recovery; role assignment is bounded | Owner decision; see `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` §D6 |
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
| 2026-10-03 | Use level-wide result templates with an optional class-specific override and an ordered component pivot | Allows a common grading configuration per level without duplicating it for every class, while supporting class exceptions |
| 2026-10-03 | Split result permissions into settings, score entry, and approval; use a module service for each Academic and Admissions lookup | Keeps approval authority independent of entry and preserves module boundaries while Staff HR/teacher assignments are not yet built |
| 2026-10-03 | Snapshot each result's class arm and template at first score entry | Later class moves or template changes must not silently alter historical term results or broadsheet grouping |
| 2026-10-03 | Assign score-entry users to a class/subject in Academics; Results settings managers may enter across assignments | Gives teachers server-enforced scope before Results entry is opened beyond school admins while retaining a practical admin correction path |
| 2026-10-03 | Use `barryvdh/laravel-dompdf` for report cards, without a browser or persistent render process | Compatible with the pinned PHP 8.2/Laravel 12 stack and cPanel shared-hosting constraints; A4 output still needs measured verification |
| 2026-10-03 | Keep personalized report-card comments nullable and resolve term defaults when compiling/rendering | Allows a later default edit to apply to non-personalized cards without overwriting a student's explicit remark |
| 2026-10-03 | Treat CBT release as an exam-level policy: Immediate, Scheduled, or Manual, optionally requiring a distinct staff approval; internal exams link to a Results component and entrance exams to Admissions | Makes release checks re-evaluable on every fetch and avoids exposing an unreleased candidate outcome |
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
3. Who may edit the registration number of a pupil still Awaiting admission
   (proposal: Secondary Head and Admin only).
4. When a source Class has a Department but the next step has no Class with
   that Department, whether another destination should be suggested (5a
   currently requires an explicit choice).
5. The Class deletion rule (proposal: block deletion while the Class or its
   Arms have Students, Results, Attendance or Invoices).
6. Whether a deleted Class's name may be reused immediately.
7. Class-step combinations with a Class that has no Department while another
   Class on the same step has a Department.
8. Attendance: whether “not held” needs a reason note; what happens to marks
   made before a session is declared not held; how older unfixable alerts are
   handled; and whether removing today's not-held entry re-raises an alert.
9. Teacher assignments: when another Teacher is chosen for a Class/Subject
    pair that already has one, should Save replace the current Teacher in one
    step or continue to require an explicit remove-and-save first?
10. Business: the original 12-week timeline and original price remain
    unconfirmed.
11. Academic setup: assign every existing school-wide Subject explicitly to
    one Section before adding a non-null `subjects.section_id` and enabling
    Section-scoped Subject create/edit/delete for Heads. Do not infer the
    mapping from present Class assignments because a Subject may span Sections.

## Deferred Until Hosting Exists

- **Live host deployment proof:** when cPanel is purchased, run the SSH
  deployment on the real host, confirm the live domain's `/up` returns HTTP
  200, and check the plan's actual PHP version, GD extension, cron support, and file
  permissions. This is required for go-live, but is not a Phase 0 exit
  criterion. See `PHASES.md` → "Go-Live Checklist."

## Go-Live Checklist

- Deploy the twice-daily attendance change outside school hours. An arm that
  was already marked as a whole day cannot be split into sessions for that
  day.

## External Gateway Proof Record

- **Paystack sandbox transaction completed 2026-10-04:** test keys remain
  only in `.env`. The real TEST charge and provider webhook proof are
  recorded in the Phase 4 exit check and `docs/payments.md`. The temporary
  Cloudflare tunnel has been stopped; its test-mode webhook URL must be
  replaced or cleared before another test. This is not the separately
  deferred live cPanel deployment proof.

---

_Last updated: 2026-10-20 — Phase 4 is complete; Phase 5 is substantially
built but not formally complete. Academic setup safeguards and fee-schedule
copy are complete except for the explicitly blocked Subject ownership mapping;
later Batch 5 payment work remains._
