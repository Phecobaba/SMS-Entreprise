# Architecture

> This document is the source of truth for how the Oakfield School Management
> System is structured. Any AI agent or developer picking up this project
> should read this file before writing code. If something here conflicts
> with a request, this file wins unless a human explicitly updates it.

## 1. Stack (confirmed)

| Layer       | Choice                                   | Why |
|-------------|-------------------------------------------|-----|
| Backend     | Laravel (PHP)                             | cPanel-compatible, mature ecosystem |
| Frontend    | Blade + Livewire + Alpine.js              | No separate SPA build pipeline; one deploy, one language; still gets reactive UX (attendance ticking, assignment status, etc.) without full page reloads |
| Database    | MySQL                                     | Universal cPanel support; Laravel-native |
| Hosting     | cPanel (shared-hosting compatible)        | Client requirement |

No other languages, frameworks, or build tools should be introduced without
this file being updated first. This is a deliberate constraint to avoid
architectural drift ("no spaghetti code").

## 1.1 Visual Design System

All colors, typography, navigation patterns, and component patterns are
defined in `docs/design-system.md` — that file is the source of truth,
not this one. Every new screen built from Phase 4 onward should be built
against it directly, not styled ad hoc and reconciled later.

**Navigation**: implemented via Livewire's `wire:navigate` for SPA-like
page transitions without a full reload. Persistent layout chrome
(sidebar, header, mobile bottom tab bar) is wrapped in `@persist` so it
does not re-render between navigations — only the content area swaps.
This was chosen deliberately over adopting Inertia.js, which would
require replacing Blade with a Vue/React view layer entirely and
rebuilding every already-completed phase from scratch — `wire:navigate`
gets the same instant-feeling navigation within the existing Blade/
Livewire/Alpine stack from §1.

## 2. Deployment Model: Single-Tenant, SaaS-Ready

**Current reality:** this is a private, single-school deployment for Oakfield
International Schools. There is no second school, no subscription billing,
and no platform admin UI in this build. A previously discussed second-school
SaaS deal did not go through — this is not being built right now.

**What "SaaS-ready" means in practice here:** the application is built so
that adding a second school later is a data change, not a rewrite. It is
**not** being built as a multi-tenant SaaS platform today.

### 2.1 Tenancy Mechanism — RESOLVED

**Decision:** tenancy is enforced via a `BelongsToTenant` trait + Eloquent
Global Scope, backed by a `TenantContext` singleton resolved once per
request by middleware. This was chosen over scattering `->where('school_id',
...)` across every query (too easy to forget one, which is exactly the kind
of mistake that leaks data across tenants) and over a raw middleware-only
approach (doesn't protect queries made outside a controller, e.g. in queued
jobs or console commands).

**How it works:**

1. `TenantContext` is a singleton bound in the service container. It holds
   the current `school_id` for the request/process.
2. A middleware (`ResolveTenant`), run early in the HTTP pipeline, sets
   `TenantContext` based on `APP_MODE`:
   - `single_school` mode: always sets it to `DEFAULT_SCHOOL_ID` from config.
   - `saas` mode (reserved, not active): would resolve it from the request's
     subdomain/domain. Not implemented in this build — see §2.2.
3. Every tenant-owned Eloquent model uses the `BelongsToTenant` trait. The
   trait:
   - Registers a Global Scope that automatically applies
     `where('school_id', TenantContext::id())` to every query against that
     model.
   - Automatically sets `school_id` on create, via a `creating` model event,
     so it never has to be passed manually and can't be forgotten.
4. Console commands and queued jobs that run outside an HTTP request must
   explicitly set `TenantContext` before touching tenant data (e.g. a
   scheduled job iterates schools and sets context per iteration). This is
   flagged here specifically so it isn't missed later, since it's the one
   place the HTTP middleware doesn't cover.

This is the pattern for **every** module. No model listed in
`docs/schema.md` as tenant-owned should query the database without this
trait applied.

### 2.2 App Mode

```
APP_MODE=single_school
DEFAULT_SCHOOL_ID=1
```

In `single_school` mode (the only mode active in this build):
- `TenantContext` is always set to `DEFAULT_SCHOOL_ID` (§2.1).
- No "create another school" UI exists.
- No school-selection step at login.
- Platform-level navigation/menus are hidden entirely, not just disabled.

A `saas` mode is **reserved** for a possible future phase. Its resolution
logic (subdomain lookup) is described above only so the chosen tenancy
pattern doesn't have to be redesigned later — no `saas`-mode code should be
written speculatively in this build.

### 2.3 Module Boundary — What Gets Built vs. What Stays Out

Because this is school-edition only, the following modules are explicitly
**out of scope for this build** and must not be created, even as stubs:

- Platform Super Admin dashboard
- Subscription / billing engine
- Plan/tier management
- Tenant onboarding workflow
- Platform revenue analytics

If any future work resumes on SaaS, it happens as a clearly separate,
later initiative — not folded into this codebase casually.

## 3. Module Structure

Each domain is a bounded module. Modules do not reach into each other's
internals directly — they communicate through well-defined service classes
or events, not by querying another module's tables ad hoc from unrelated
controllers. This is the core "no spaghetti code" rule for this project.

```
app/
  Modules/
    Core/              (schools, school_settings, users, roles, permissions)
    Academics/         (academic_levels, classes, class_arms, subjects, sessions, terms)
    Admissions/         (candidates, admission_checklist, admission_offers)
    CBT/                (exams, exam_types, questions, exam_submissions)
    Results/            (assessment_components, grading_schemes, results, result_audit_log)
    Attendance/          (attendance_records, attendance_alerts)
    Assignments/         (assignments, assignment_submissions)
    ENotes/              (enotes)
    Payments/            (invoices, payments, expenses)
    StaffHR/             (staff, staff_salary_history, staff_disciplinary_log, staff_documents)
    Notifications/        (notifications, notification_logs)
    LandingPage/          (school_settings-driven public site — see §5)
```

Each module owns its own migrations, models, controllers, Livewire
components, and policies. Cross-module interaction (e.g. CBT writing a
score into Results) goes through a defined service method
(`ResultService::recordComponentScore(...)`), never a raw cross-module
Eloquent query.

## 4. RBAC Design

- Roles do not hardcode permissions in code. Roles are rows; permissions are
  rows; the mapping is a pivot table (`role_permissions`). This allows
  permission changes without a deploy.
- Every protected action checks a permission, not a role name, e.g.
  `can('approve_result')`, not `if ($user->role === 'principal')`.
- Sensitive data gets its own permission, separate from general module
  access. Confirmed examples:
  - `view_staff_salary`
  - `view_staff_disciplinary`
  - `manage_staff_records`
  - `add_principal_comment`
  - `add_proprietor_comment`
  (the latter two are distinct permissions even though, today, one
  School Admin user is likely to hold both — see §12)
- **Every view (not just edit) of salary or disciplinary data is written to
  an audit log.** This is a hard requirement, not optional, per explicit
  client instruction.

## 5. Tenant-Aware Landing Page

Even though there is one school today, the landing page is **not**
hardcoded. Branding, about text, contact info, and admissions form target
are stored in a `school_settings` table keyed by `school_id`, and the
public site renders from that data. This costs little extra effort now and
avoids a rebuild if a second school is ever onboarded.

## 6. Server-Side Enforcement (Non-Negotiable)

The following rules must be enforced in the backend, never only hidden in
the frontend UI:

- CBT result-release rules (`result_release_type`, `show_score`,
  `show_correct_answers`, `requires_approval`) are checked server-side on
  every result-fetch request.
- A result cannot be edited by a teacher after admin/principal approval —
  this is enforced at the controller/service layer via a status check, not
  just a disabled button in the UI.
- Result and attendance visibility to Parents/Students is gated by the
  configured payment rule, checked server-side on every request, not cached
  client-side.

## 7. Performance Requirements

- No N+1 queries: eager-load relationships on any list view (student
  rosters, result sheets, staff lists, attendance grids).
- Every list that grows with school activity (students, invoices, results,
  attendance, notifications, etc.) must be paginated. Small structural lists
  bounded by how the school is organised (sections, levels, exam types) may
  load whole, provided they are capped (e.g. 100 rows) and reviewed if the
  cap is ever approached.
- Index every `school_id` column and every foreign key used in a `WHERE`
  or `JOIN`.
- Livewire components should scope their queries tightly (e.g. a teacher's
  attendance component only ever queries their assigned class, not the
  whole school).
- Avoid heavy client-side JS; keep pages light for parents/teachers on
  inconsistent mobile connections.

## 8. Testing Philosophy

Tests are intentionally **not exhaustive everywhere** — this is a deliberate
choice to avoid excessive token/time cost on low-risk code. Required test
coverage is limited to trust-sensitive logic only:

- Result approval/locking (a teacher cannot edit an approved result)
- CBT scoring and score conversion (e.g. 40/50 → 56/70 conversion math)
- Payment confirmation and result-access gating tied to payment status
- Attendance session uniqueness, posted-session marking, per-session locking,
  legacy whole-day counting, percentage exclusions, Section scope/auditing,
  alert suppression/resolution, and payment-gated visibility
- Candidate → Student conversion (cannot happen without checklist complete
  or explicit, logged admin override)

Everything else (CRUD screens, basic Livewire components, UI-only
behavior) does not require dedicated automated tests at this stage unless
a bug is found there — then a regression test is added for that specific
case.

## 9. Data Model Decisions — RESOLVED

These were previously open questions. They are now final for this build;
revisiting them requires a human decision and an update to this file plus
`docs/schema.md`.

- **Table-per-role (final):** `students`, `parents`, `staff`, and
  `candidates` each get their own table, each linked to a shared `users`
  table via `user_id` for authentication. Rejected alternative: a single
  `users` table with a `type` discriminator column — rejected because it
  would accumulate a large number of nullable, role-specific columns on one
  table (salary history doesn't apply to a parent, ward-switching doesn't
  apply to a teacher), which is harder to reason about and query cleanly
  than separate tables with a shared auth identity.
- **`students.candidate_id` is nullable, intentionally:** a student record
  can be created two ways — (a) converted from a `candidate` after an
  accepted admission offer (`candidate_id` set), or (b) enrolled directly
  by an Admin without going through the entrance-exam pipeline
  (`candidate_id` null). Both paths are valid; the system does not force
  every student to have sat an entrance exam.

## 10. Report Card Rendering (Added After Initial Draft)

Printable result cards are a real part of the Result Management module,
not a cosmetic afterthought — a result that can't be printed as a proper
branded document isn't a finished feature. Requirements, as confirmed by
the client:

- Must include the school logo and standard school details (name,
  address, motto — sourced from `school_settings`, not duplicated).
- Must include **Principal's comment** and **Proprietor's comment** (see
  §10 for the Proprietor role gap this surfaced).
- **Must fit a single A4 page.** This is a hard layout constraint, not a
  soft preference — the template must be designed around it from the
  start, not fitted to it after the fact. Practical implications:
  - The result template (assessment components × subjects for a given
    level) determines how many rows the subject table has — a Secondary
    template with many subjects and a full CA1/CA2/Assignment/Exam
    breakdown has much less vertical room than a Nursery qualitative
    template, and both still need to fit one page.
  - Comment fields (class teacher, principal, proprietor) need a sensible
    character limit enforced at input time, not just visually truncated
    at render time — truncating a comment silently on the printed page is
    worse than preventing an overlong comment from being saved.
  - A grading-key legend, attendance summary, and position, if all shown
    on the card, must be accounted for in the layout budget alongside the
    subject table — this needs to be test-printed against the
    highest-subject-count template (Secondary) before being considered
    done, not just checked against a light template.
- **Rendering approach**: a PDF generation library (e.g. `barryvdh/laravel-dompdf`
  or a wkhtmltopdf-based package such as `barryvdh/laravel-snappy`) renders
  a dedicated Blade view into a single-page A4 PDF. The exact library choice
  is a build-time decision, not prescribed further here — but whichever is
  chosen, the one-page constraint must be verified against the
  highest-content template as part of that task's Definition of Done
  (`CONTRIBUTING.md`), not assumed to work from a quick visual check.

## 11. Open Decisions (Business, Not Engineering — Still Unresolved)

- Whether the 12-week timeline holds given the expanded MVP scope
  (Attendance, Assignments, E-Notes, Staff HR, and Report Card Rendering
  added after the original estimate) — see `PHASES.md` for the tentative
  phase breakdown and its own caveat on this.
- Whether the original ₦2.3M MVP price still holds against the expanded
  scope — business decision, not an engineering one, not addressed by this
  document.

## 12. Proprietor & Result Comment Behavior — RESOLVED

**Proprietor role:** confirmed as **not a distinct login/role**. The
Proprietor is the School Admin wearing a second hat — one user account,
one `School Admin` role. No separate `Proprietor` role is added to the
`roles` table.

What this means in practice: rather than a role check, the system uses two
**distinct permissions** — `add_principal_comment` and
`add_proprietor_comment` — both grantable to the `School Admin` role (and
`add_principal_comment` also grantable to a `Principal` role if the school
later wants an actual Principal user entering their own comment
separately). This keeps the two comment fields independently
permissioned and independently audit-trailed, even though today one person
(School Admin) is likely to hold both permissions. If the school later
splits "Principal" into a separate real login, no schema change is
needed — just a different permission assignment.

**Comment default behavior:** confirmed as **supporting both**,
school's choice per term, not a fixed default baked into the system.
Mechanism (already reflected in `docs/schema.md`):

1. `term_default_comments` holds an optional standard remark per term, per
   comment type (Principal, Proprietor).
2. At result-card compile time, if no personalized comment has been
   entered for a student, the term default is used.
3. Admin can override any individual student's comment at any time,
   personalized text takes precedence over the default for that student.
4. This means a school can run entirely generic ("Keep up the good work
   this term.") for every student, entirely personalized, or a mix —
   without needing a system-level setting to pick one mode in advance.
