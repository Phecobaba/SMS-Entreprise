# Build Phases

> This file defines **engineering execution order** — the sequence a
> developer or AI agent actually builds in. It is different from
> `ROADMAP.md`, which defines **product scope** (what's in the MVP vs.
> deferred to later). Everything in every phase below is part of the MVP
> Scope defined in `ROADMAP.md` — these phases just break that scope into
> buildable, verifiable chunks with a clear order and a clear finish line
> for each.
>
> Each phase has an **Entry Criteria** (what must be true to start it) and
> an **Exit Criteria** (what must be true to call it done). A phase is not
> "done" because time was spent on it — it's done when its exit criteria
> are met. See `CONTRIBUTING.md` for the task-level (not phase-level)
> pick-up/done workflow.

---

## Phase 0 — Foundation

**Goal:** prove the environment works end-to-end before building any real
feature, and stand up the tenancy/RBAC skeleton everything else depends on.

**Entry criteria:** Laravel stack confirmed (done — see `ARCHITECTURE.md`
§1). Real cPanel hosting access is not required to begin this phase.

**Work:**
- Laravel project scaffold
- cPanel-compatible deployment pipeline, proven against a local environment
  configured to match the real shared-hosting constraints as closely as
  possible
- `schools`, `school_settings`, `users`, `roles`, `permissions`,
  `role_permissions`, `user_roles` tables + migrations
- `TenantContext` singleton, `ResolveTenant` middleware, `BelongsToTenant`
  trait implemented and applied to at least one real model, per
  `ARCHITECTURE.md` §2.1
- Login/auth scaffolding (no role-specific dashboards yet, just auth
  working)

**Exit criteria (all must be true):**
- **Local deployment proof:** the SSH atomic-release deployment process and
  rollback run successfully against a local stand-in, using pinned PHP
  8.2.x, standard shared-hosting file permissions, and cron-driven
  scheduling. The app must not rely on persistent Node or PHP processes;
  continuous queue workers are not assumed unless the purchased cPanel plan
  is confirmed to support them. This local proof is a stand-in for the real
  host, not a replacement for proving the live deployment later.
- A logged-in user's queries are automatically scoped to their
  `school_id` — verified by a test that two schools' data cannot leak into
  each other (even though only one school exists today, this proves the
  mechanism).
- At least one permission check (`can('...')`) gates at least one real
  route, proving the RBAC pattern works, not just exists in a migration.

---

## Go-Live Checklist — After cPanel Is Purchased

**Live host deployment proof (deferred; not a Phase 0 blocker):** once real
cPanel credentials and the live domain exist, run the SSH deployment against
the purchased host, confirm `/up` returns HTTP 200 on the live domain, and
  check the plan's actual PHP version, GD extension (for report-card logos),
  cron support, and file permissions for quirks the local stand-in could not
  verify. Track the outcome in
`PROGRESS.md` before calling the system ready to go live.

---

## Phase 1 — Academic Core & Admissions

**Entry criteria:** Phase 0 exit criteria met.

**Work:**
- Academics module: `academic_levels`, `academic_sessions`, `terms`, `classes`,
  `class_arms`, `subjects`, `class_subjects`
- Tenant-aware Landing Page (early and low-risk — validates the
  `school_settings`-driven rendering pattern used elsewhere later)
- Admissions/Candidates module, including the full pipeline through the
  **Admission Offer** step and Candidate→Student conversion (with
  checklist enforcement per `ARCHITECTURE.md` §8)

**Exit criteria:**
- A class/arm/subject structure can be fully configured through the UI,
  not just via seeders.
- A candidate can go through the entire pipeline — register, pay, sit
  entrance exam (can be stubbed if CBT isn't built yet — see note below),
  receive an offer, accept it, and be converted to a student record — with
  every status transition logged.
- Landing page renders entirely from `school_settings` data; no hardcoded
  school name/branding anywhere in the view layer.

> Note: if CBT (Phase 2) isn't built yet when this phase starts, the
> entrance-exam step in Admissions can be stubbed with a manually-set
> "Passed" status for now, so the Offer/Conversion logic can still be
> built and tested independently. Wire it to real CBT once Phase 2 lands.

---

## Phase 2 — Results & CBT (Trust-Sensitive Core)

**Entry criteria:** Phase 1 exit criteria met (Academics structure must
exist before results/exams can be attached to classes/subjects).

**Work:**
- Result Management: `assessment_components`, `grading_schemes`,
  `grade_bands`, `result_templates`, `results`, `result_items`,
  `result_audit_log`, full draft→submit→approve/reject→lock workflow
- CBT: `exam_types`, `exams`, `questions`, `question_options`,
  `exam_submissions`, `exam_answers`, auto-marking, result-release
  configuration, score conversion logic, wired into Results via the
  `ResultService` pattern (`ARCHITECTURE.md` §3)
- **Printable Result Card**: `result_cards` + `term_default_comments`
  tables, PDF rendering of the compiled report card (logo, school
  details, subject table, class teacher/principal/proprietor comments),
  constrained to a single A4 page — see `ARCHITECTURE.md` §10 for the
  full requirement and layout constraints

**Exit criteria:**
- Required tests from `ARCHITECTURE.md` §8 pass: result locking after
  approval, CBT score conversion math, candidate conversion checklist
  enforcement.
- A full result cycle works end-to-end through the UI: teacher
  enters/uploads scores → submits → principal approves → report
  card/broadsheet auto-compiles correctly.
- A full CBT exam cycle works end-to-end: exam created → student/candidate
  sits it → auto-marked → result released per configured rule → feeds into
  Results where applicable.
- Server-side enforcement verified: attempting to bypass result-release
  rules or edit an approved result via a direct request (not just the UI)
  fails.
- **The printable result card renders correctly as a single A4 page PDF**,
  with logo, school details, and both Principal's and Proprietor's
  comments visible — tested against the Secondary template specifically
  (highest subject count / most content), not just a lighter template, per
  `ARCHITECTURE.md` §10.

---

## Phase 3 — Daily Operations (Attendance, Assignments, E-Notes)

**Entry criteria:** Phase 1 exit criteria met (depends on Academics
structure; does not strictly require Phase 2, so this can run in parallel
with Phase 2 if more than one developer/agent is working).

**Work:**
- Attendance: daily marking, same-day edit window + lock, missing-
  attendance alerts
- Assignments (Mode A only): creation, per-student tick-marking
- E-Notes: upload (Admin + subject-scoped Teacher), class/subject
  filtering

**Exit criteria:**
- A form teacher can mark a full class's attendance in under the UX
  target (see `CONTRIBUTING.md` "Definition of Done" for the UX bar).
- A missed day of attendance generates a visible alert for Admin/Principal
  without manual checking.
- An assignment's tick-status reflects on Student and Parent dashboards
  immediately (no stale cache/delay).
- A teacher can upload a note scoped to their own subject only; cannot
  upload to a subject they don't teach.

---

## Phase 4 — Finance & Staff HR

**Entry criteria:** Phase 0 exit criteria met (these two modules don't
depend on Academics/Results/CBT, so this phase can also run in parallel
with Phases 2–3 if resourcing allows).

**Work:**
- Bursar/Payments: `invoices`, `payments`, payment gateway integration,
  `expenses`
- Staff HR: `staff`, `staff_salary_history`, `staff_disciplinary_log`,
  `staff_documents`, `staff_exit_records`, the sensitive-data permission
  split, and view-audit logging

**Exit criteria:**
- Required test from `ARCHITECTURE.md` §8 passes: payment confirmation
  correctly drives result-access gating.
- A payment made through the gateway reconciles automatically and is
  visible to Bursar without manual matching.
- Salary history is queryable as a progression (not just a current value),
  verified with at least one staff record that has more than one salary
  entry.
- Viewing a staff member's salary or disciplinary log writes an audit
  entry — verified, not assumed.

---

## Phase 5 — Portals, Notifications, Hardening

**Entry criteria:** Phases 1–4 exit criteria all met (this phase ties
everything together, so it genuinely needs the rest done first).

**Work:**
- Parent Portal: multi-ward switching, results, payments, attendance,
  assignments, all in one coherent dashboard
- Student Portal: same data, scoped to self
- In-app Notifications
- Performance pass: N+1 query check, index verification, pagination
  confirmed on every list view per `ARCHITECTURE.md` §7
- Full pass through the trust-sensitive test list in `ARCHITECTURE.md` §8

**Exit criteria:**
- A parent can log in and, within the UX target, find their child's
  latest result, attendance, and payment status without hunting through
  menus.
- Every list that grows with school activity is paginated. Small structural
  lists bounded by how the school is organised may load whole within a
  documented cap and must be reviewed if that cap is approached (see
  `ARCHITECTURE.md` §7).
- No N+1 query pattern remains on any of the core dashboards (verified by
  query-count inspection, e.g. Laravel Debugbar/Telescope during manual
  review — not necessarily an automated test).
- All trust-sensitive tests from `ARCHITECTURE.md` §8 are green.

---

## Timeline Status — Still Open

These phases are **not yet mapped to calendar weeks.** The original
estimate was 12 weeks against a smaller module set; scope has since grown
(Attendance, Assignments, E-Notes, Staff HR added). Phases 3 and 4 running
in parallel with Phase 2 (if more than one person/agent is available)
would help recover some of that gap, but **this file does not assume a
resolved timeline** — that is a business decision between WOKES and
Oakfield, not something to be silently decided by an agent picking up this
project. Update this section once a real timeline is confirmed.
