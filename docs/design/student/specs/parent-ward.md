# Spec: Ward bar and switcher (all parent pages) — `screens/parent-dashboard.html`, `components.html`

## Who can open
Proposal: any signed-in user with at least one linked child (`ParentAccountDirectory::wardIds($user) !== []`). Every ward id from the request is checked server-side (`isWard`), never trusted. Codex: map to your RBAC names; no new permission is required by the design.

## Today
Ward choice exists only on the dashboard: `GET /dashboard?student={id}` through a `<select onchange="this.form.submit()">` (auto-submit, no label button). No other page knows the selection, and `assignments.parent` lists every child's rows mixed together.

## Design
- A **ward bar** at the top of every parent page: avatar initial, "Viewing", child's name, class and **section** (Primary School / Secondary School), and a **Switch child** button (44px).
- **Switch child** opens a dialog (centred on desktop, bottom sheet on phones): radio list of children, each with class, section and a **Fees due** pill when that child has an unpaid invoice. Buttons: Cancel, Show this child. No auto-submit.
- One child: same bar, **no switch button, no sheet**.
- Locked or unpaid state is always the **selected child's**, never the parent's.

## Dependent fields
Switch button: only when the parent has more than one child. "Fees due" pill: only for a child with at least one unpaid invoice.

## NEW (decision for Codex, log it)
The selection must follow the parent to every page. Proposal: carry `?student={id}` on every parent link and fall back to the first child; optionally remember the last choice in the session. Do not rely on the dashboard form alone.

## States
Two children · One child · No child linked (see `specs/parent-dashboard.md`).

## Decided (owner, 2026-10-10)
- The **"Fees due" pill stays**. It shows status only (never amounts) and only for the parent's **own** linked children. Fees remain a single-child view.
- The pill is **state-driven**, not permanent: it shows for a child who has an unpaid invoice that still offers Pay (Unpaid, Part paid, Payment failed). It is hidden when everything is paid, while a Paystack payment is being confirmed, and while a recorded bank payment is awaiting approval (nothing left for the parent to do). *Proposal for the last two; confirm.*
- In the design the sample data is kept consistent: Jane's pill appears only in the "Fees unpaid" states, never beside "Fees outstanding ₦0".
