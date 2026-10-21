# Spec: Fee schedule — `screens/fee-schedule.html`

Batch 5 (2026-10-18). Money is stored as integer kobo and shown in naira only (₦45,000), never kobo. Permission names are proposals: Codex names them and logs them.

States: Normal · Add a fee item to a scope · Error (amount) · Saved · Fee items · Add a fee item (name) · No schedule yet · No permission.

## Who can open
`manage_fee_schedule` (D8: Bursar and Admin by default). Whole school; no section switcher (the Bursar sees the whole school).

## Today
No fee catalog, no schedule and no invoice lines exist. Invoices have a purpose and a single amount; there is no per-term scope.

## Design (D8)
- **Fee items** are a catalog of names (Tuition, Development levy, Textbooks...). A price lives on the schedule, not on the item.
- The **schedule** attaches items and amounts to a **term** and a **scope** (level, class or department). Each line has a **Required** tick: ticked items go on every invoice automatically; unticked items are **extras** the Bursar adds to one student.
- Amounts are typed in naira; the screen never shows kobo. A summary table lists the scopes that have a schedule this term and their required totals.
- A notice states that **changing a price never changes invoices already issued** (invoices copy their lines when issued).

## NEW data
Fee items (name, school); schedule lines (term, scope type and id, item, amount in kobo, required flag); permission `manage_fee_schedule`. Codex names tables and columns and logs them.

## Dependent fields
The scope picker follows the scope type (Level, Class, Department). The Department scope is offered only when departments exist. The Add-line sheet lists catalog items not already on this scope and term.

## Tests (trust-sensitive)
Editing a schedule price does not change any invoice already issued; amounts are stored as integer kobo and rendered as naira; a user without `manage_fee_schedule` cannot read or write the schedule (server-side).

## Decided (owner)
Catalog + schedule + Required tick (D8).

## Open questions (do not decide)
- **When more than one scope matches a student** (a level, a class and a department), whether their items add up or one scope wins. The design shows one scope at a time and never combines them.
- Whether a fee item that is on a schedule or an issued invoice may be removed (the design disables removal and says why: a proposal).
- Whether a schedule can be copied from the previous term (not designed).


## Added 2026-10-18: Copy from previous term
**Copy from previous term** (button above the schedule, `copy-sheet`): choose the earlier term and **every scope** or **only the scope on screen**; the sheet shows **what will be copied** (lines per scope) and **what already exists** in the chosen term, then **Copy N lines**. Amounts are copied as they were. Lines that already exist in the target term are **skipped, never overwritten**; **invoices already issued are never touched**. States: Copy, Copy one scope, Nothing new to copy, Previous term empty (button disabled), Copying, Copied. Running it twice copies nothing the second time.
- **NEW:** nothing; it writes ordinary schedule lines. Log who copied from which term to which, and how many lines.
- **Verify first:** the schedule's key (term, scope, item) so "already exists" can be decided; what happens to a source scope that no longer exists (design: skip it and say so).
- **Tests:** copying changes no issued invoice; existing target lines are not overwritten; copying twice is a no-op; the preview counts equal the applied counts; only a user with `manage_fee_schedule` can copy.
- **Open:** a price uplift when copying (not designed).
