# Spec: Bursar dashboard — `screens/bursar-dashboard.html`

States: Normal (two-step approval) · One-step approval school · New term (nothing yet) · Error.

## Who can open
Proposal: `manage_payments`. The Bursar role does not exist in the seeded roles today (only `manage_payments` held by School Admin); Codex confirms how a Bursar account is created (see the staff accounts item in batch 6).

## Today
`payments.index` is one page (invoices, expenses, rule). There is no Bursar dashboard.

## Design
- Four metrics: **Outstanding (term invoices)**, **Collected this term**, **Awaiting approval**, **Not invoiced**. Amounts are in naira only (₦1,620,000), never kobo.
- **Outstanding** counts each term invoice's balance **once** (D10 trap 2); part-payment invoices are not added on top.
- **Do next:** Record a payment, Generate invoices, Not invoiced (links to batch 5 screens).
- **Recorded, waiting for Admin approval** list. A school that settles manual payments in **one step** (D9 setting) has no queue: the card and metric are hidden and an info note explains.
- Recent payments (with receipt numbers) and a short Not invoiced list.
- **No ticker:** D13 defines no events for the Bursar, and a ticker with nothing to show is hidden.

## Build note
"Awaiting approval", "Not invoiced" and the actions depend on **batch 5** data (manual approval, fee schedule, bulk invoicing). Build this dashboard after those exist, or build it **without** those cards. Do not invent substitutes.

## Dependent fields
Awaiting approval metric and list: only when the school uses two-step approval. Not invoiced: only when a fee schedule exists.

## Decided (owner, 2026-10-11)
The Bursar sees the **whole school, with no section switcher**. Heads and the Bursar are different cases: heads are section-scoped, the Bursar is not.
