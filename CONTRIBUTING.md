# Contributing / Agent Operating Guide

> This file answers two questions for whoever (human or AI agent) is about
> to work on this codebase: **"How do I pick up a task?"** and **"How do I
> know when it's actually done?"** `PHASES.md` tells you the big-picture
> order; this file tells you how to execute a single task within that
> order.

## Before You Touch Anything

Read, in this order:
1. `ARCHITECTURE.md` — the rules you must not break
2. `PHASES.md` — which phase are we in, what's its exit criteria
3. `docs/schema.md` — what tables/fields already exist or are planned
4. `PROGRESS.md` — what's actually been built so far (not what's planned —
   what's real)

Do not start writing code based on memory of a previous session or on
assumption. `PROGRESS.md` is the only source of truth for current state.

## How to Pick Up a Task

1. **Find it in `PROGRESS.md` → "Next Up"**, which is ordered by the
   current phase in `PHASES.md`. Don't jump ahead to a later phase's work
   unless the current phase's exit criteria are already met.
2. **Move it from "Next Up" to "In Progress"** in `PROGRESS.md` before
   starting — this is what protects against two sessions/agents
   duplicating work if there's a handoff mid-task.
3. **Check `docs/schema.md` first** for any table this task touches. If
   the task requires a schema change not already documented there, update
   `docs/schema.md` in the same session — schema and code must never drift
   apart.
4. **Build inside the module boundary** defined in `ARCHITECTURE.md` §3.
   If a task seems to require reaching into another module's tables
   directly, stop — that's a sign the task should go through a service
   class instead. Don't take the shortcut.
5. If you hit a decision that isn't already answered in `ARCHITECTURE.md`
   or `docs/schema.md` (e.g. a naming convention, a UI pattern, a validation
   rule), **make the smallest reasonable decision, document it in
   `PROGRESS.md` → "Decisions Log" with a one-line reason, and keep going.**
   Don't stop and wait unless it's a decision listed under "Blockers / Open
   Questions" (business/pricing/timeline calls) — those genuinely need a
   human.

## Definition of Done (Applies to Every Task)

A task is not done when the code runs once. It's done when **all** of the
following are true:

1. **It matches `ARCHITECTURE.md`.** Server-side enforcement rules (§6) are
   respected. Tenancy trait is applied if the task touches a tenant-owned
   table (§2.1). No spaghetti shortcuts across module boundaries (§3).
2. **It matches `docs/schema.md`**, or `docs/schema.md` was updated to
   match it. These two must never silently diverge.
3. **If it's trust-sensitive work** (anything in the list in
   `ARCHITECTURE.md` §8 — result locking, CBT scoring, payment
   confirmation, attendance locking, candidate conversion) — **a test
   exists and passes.** This is the one category where "it worked when I
   clicked through it" is not sufficient.
4. **If it's not trust-sensitive** (most CRUD screens, most Livewire
   components) — manual verification that it works is sufficient. Do not
   write exhaustive tests here; that burns time/tokens against a
   requirement that was explicitly to keep testing minimal-but-sufficient.
5. **Performance basics are respected** per `ARCHITECTURE.md` §7: no
   obvious N+1 query introduced, list views paginated, relevant foreign
   keys indexed.
6. **UX basics are respected**, specifically because the primary daily
   users are teachers and parents, not technical staff:
   - A UI task is not done until it matches its reference in `docs/design/`
     at desktop and phone widths; report deviations.
   - A common action (marking attendance for a class, ticking an
     assignment, checking a result) should be reachable in a small number
     of clicks from login — not buried in nested menus.
   - Error messages are in plain language ("This result has already been
     approved and can't be edited" — not a raw exception or stack trace).
   - Nothing trust-sensitive fails silently — if a save fails, the user
     sees that it failed.
7. **`PROGRESS.md` is updated**: the task is moved from "In Progress" to
   "Completed," with enough detail that the next person/agent understands
   what exists now without re-reading the code.

## If a Session Ends Abruptly

This is specifically why `PROGRESS.md` exists. Before stopping (if you have
any control over the stopping point):
- Leave the current task's real state in "In Progress," not "Completed,"
  if it isn't actually finished per the Definition of Done above.
- Note anything half-done or any known-broken state explicitly, so the
  next agent doesn't assume working code where there isn't any.
- If you made a judgment call mid-task, log it in the Decisions Log even
  if the task itself isn't finished — don't lose that reasoning.

## What NOT to Do

- Don't introduce a new package/library without checking it's justified —
  prefer what Laravel/Livewire already provide.
- Don't build anything listed as out-of-scope in `ARCHITECTURE.md` §2.3 or
  under "Deferred Scope" in `ROADMAP.md`, even if it seems like a small
  addition while you're already in that area of the code.
- Don't silently resolve an item listed under "Blockers / Open Questions"
  in `PROGRESS.md` — those are explicitly flagged as needing a human.
- Don't write a test suite for low-risk CRUD screens "to be safe" — this
  was an explicit client requirement to avoid excessive token/time cost on
  low-value testing.
