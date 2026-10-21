# Roadmap

> Phase boundaries as agreed in client discussions. This file exists so no
> agent builds a Phase 2 item early, or under-builds a Phase 1 "full" module.
> If scope changes, this file must be updated in the same session — do not
> let code and roadmap drift apart.

## MVP Scope (This Build)

> **Note on terminology:** this file defines *what* is in scope for the
> MVP vs. deferred — a business/product scope split. It does not define
> *build order* or engineering phases. For the week-by-week execution
> breakdown (Phase 0, Phase 1, Phase 2...), see `PHASES.md`.

### Full-suite modules (client-confirmed, no reduced version)
- **CBT** — all exam types (Internal, Mock, Entrance, Practice, Assessment,
  Promotional), full result-release configuration, auto-marking, score
  conversion logic.
- **Result Management** — assessment components vs. grading scale kept
  separate and configurable per academic level/class; full
  draft → submit → approve/reject → lock workflow with audit trail;
  auto-compiled report cards, broadsheets, positions.
- **Parent Portal** — multi-ward switching, payments, payment history,
  attendance summary, approved results, assignment status.
- **Payment Gateway Integration** — one gateway, automatic receipt
  generation, reconciliation with Bursar view.

### Supporting depth (not "full" by name, but must not be shallow —
they are load-bearing for the four modules above)
- **RBAC** — real permission-based access from day one (not deferred or
  simplified), since it underpins all four full modules.
- **Bursar Dashboard** — fees, invoicing, payment confirmation, debtor
  list, **plus expense/purchase log** (date, category, amount, vendor,
  receipt, approved-by) — explicitly not full Accountant-tier
  reconciliation/P&L.
- **Teacher / Admin / Principal Dashboards** — must fully support the
  Result Management approval workflow; cannot be a stripped-down version.

### Added to MVP Scope (client decision, scope increase acknowledged)
- **Attendance Management** — daily present/absent/late/excused marking
  by form teacher; same-day edit window then lock; Parent/Student summary
  view; **missing-attendance alerts for Admin/Principal** when a class's
  daily attendance goes unmarked.
- **Assignment Management — Mode A only (tick-based)** — teacher creates
  assignment, ticks Submitted/Not Submitted/Late per student; reflects on
  Student and Parent dashboards. (Mode B — digital upload/grading — is
  Phase 2, see below.)
- **E-Notes** — subject/class-scoped note uploads. Admin can upload/manage
  all; subject Teachers can upload for their own subjects only.
- **Staff History / HR Module** — employment info, salary history (stored
  as a history table to preserve progression, not a single overwritten
  field), disciplinary log (queries/warnings **and** commendations), exit
  records, document storage. Sensitive data split into distinct
  permissions (`view_staff_salary`, `view_staff_disciplinary`,
  `manage_staff_records`), every view audit-logged.
- **Tenant-aware Landing Page** — content driven from `school_settings`
  keyed by `school_id`, not hardcoded, even for this single-tenant
  deployment (see `ARCHITECTURE.md` §5).

### Admissions (full flow, client-driven fix)
- Candidate registration → payment → entrance exam → result release
- **Admission Offer step** inserted between "Passed" and "Converted to
  Student": Admin reviews an Admission Checklist (documents, interview if
  applicable, fee, class capacity), extends an offer with a response
  deadline. Candidate/parent Accepts (+ deposit if required) or
  Declines/lapses. Passing the exam alone never auto-creates a student
  record — this was an explicit client correction to the original flow.

## Deferred Scope (Not Built in This Cycle)

These are genuinely separable from the Phase 1 core and were deferred
specifically because they don't block or weaken any Phase 1 module:

- **Attendance history by term (all pupils and parents)** — the current
  portal view remains the last 30 recorded school days for now.
- **Forgot-password flow** — account passwords may be changed at first login
  or reset by an authorized Admin in this cycle, but self-service password
  recovery is deferred.
- **Assignment Mode B** — digital submission upload + in-system grading,
  and its eventual integration with the Result Management "Assignment"
  score component (explicitly left decoupled for now).
- **Deep analytics** — performance trend charts, pass-rate analysis beyond
  basic cards.
- **Additional notification channels** — SMS, WhatsApp Business
  integration (cost/template-approval implications noted, not built).
- **Additional payment gateways/channels** beyond the one integrated in
  Phase 1.
- **Granular/configurable permission editor** — Phase 1 ships with fixed,
  predefined roles and permissions; an admin-facing permission editor UI
  is deferred.
- **Full Accountant module** — payroll, income/expenditure reconciliation,
  financial statements, audit reports (distinct from the lightweight
  Bursar expense log shipped in Phase 1).
- **SaaS Platform layer** — subscription billing, tenant onboarding,
  platform admin dashboard. Not triggered by current scope; would only be
  revisited against real, confirmed multi-school demand (see
  `ARCHITECTURE.md` §2).

## Timeline Status — Open, Not Resolved

The original estimate was **12 weeks** against the original 8-module MVP
definition. Attendance, Assignments (Mode A), E-Notes, and Staff HR were
added to Phase 1 afterward. **Whether 12 weeks still holds has not been
confirmed with the client.** This file will be updated once a revised
timeline (or confirmation of 12 weeks) is agreed — no agent should assume
either answer and proceed silently.
