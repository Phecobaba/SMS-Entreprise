# AGENTS.md

Oakfield School Management System — Laravel (PHP) + Blade/Livewire/Alpine.js
+ MySQL, deployed to cPanel. Single-tenant deployment for Oakfield
International Schools, built SaaS-ready (not SaaS-active).

## Before Writing Any Code

Read, in order: `ARCHITECTURE.md` → `PHASES.md` → `docs/schema.md` →
`PROGRESS.md`. `PROGRESS.md` is the only source of truth for what's
actually built — don't assume state from memory of a prior session.

## Non-Negotiable Rules (won't be obvious from the code alone)

- Every tenant-owned model uses the `BelongsToTenant` trait — never query
  or create tenant data without it. See `ARCHITECTURE.md` §2.1.
- Never hardcode a role check (`if ($user->role === 'principal')`). Always
  check a permission (`can('approve_result')`). See `ARCHITECTURE.md` §4.
- Result-release rules, approval locks, and payment-gated visibility are
  enforced server-side, not just hidden in the UI. See `ARCHITECTURE.md` §6.
- Do not build anything under Platform Super Admin, billing, or tenant
  management — out of scope for this build. See `ARCHITECTURE.md` §2.3.
- Tests are required only for the trust-sensitive logic listed in
  `ARCHITECTURE.md` §8 (result locking, CBT scoring, payment gating,
  attendance locking, candidate conversion). Don't add tests elsewhere by
  default — this was an explicit client requirement to control token/time
  cost.

## How to Pick Up a Task / What Counts as Done

Full workflow in `CONTRIBUTING.md`. Short version: find the task in
`PROGRESS.md` → "Next Up" (ordered per the current phase in `PHASES.md`),
move it to "In Progress" before starting, update `docs/schema.md` if the
task changes the schema, and move it to "Completed" in `PROGRESS.md` only
once it meets the Definition of Done in `CONTRIBUTING.md`.

## Reference Docs (read only the ones relevant to your current task)

| File | Covers |
|------|--------|
| `ARCHITECTURE.md` | Stack, tenancy, module boundaries, RBAC, performance, testing rules |
| `PHASES.md` | Build order (Phase 0–5), entry/exit criteria per phase |
| `ROADMAP.md` | Product scope: what's in the MVP vs. deferred |
| `docs/schema.md` | Every table, per module |
| `docs/payments.md` | Paystack setup, verification flow, and completed sandbox proof |
| `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md` | Owner decisions D1-D15 for Admin foundations, sections, accounts, fees, payments, bulk operations, and activity/notification work |
| `docs/design-system.md` | Colors, typography, navigation pattern, component patterns — build every new screen against this, don't improvise styling |
| `docs/design/student/` | Approved Student component and screen references, amendments, implementation brief, and per-screen specs |
| `docs/dashboard-visual-rollout.md` | Client desktop/mobile dashboard references, implementation status, and continuation handoff |
| `CONTRIBUTING.md` | Task pick-up workflow, Definition of Done |
| `PROGRESS.md` | Current build state — always check this first |
