# Spec: Not invoiced and single invoice — `screens/invoices-not-invoiced.html`

Batch 5 (2026-10-18). Money is stored as integer kobo and shown in naira only (₦45,000), never kobo. Permission names are proposals: Codex names them and logs them.

States: Normal · Invoice one student (sheet) · With an extra ticked · Creating · Invoice created · No fee schedule · Everyone invoiced · No permission.

## Who can open
The invoicing permission (Bursar and Admin by default). The Bursar's sidebar has a **Not invoiced** link.

## Design (D7, D8)
- A paged list of students with no invoice for the chosen term (name, class, registration number) and an **Invoice** button.
- **Invoice one student:** the sheet shows the required lines (fixed), the **extras** from the schedule as ticks with amounts, a live total and a due date. When an extra is ticked, a choice appears: put it **on the term invoice** (results wait for it) or on a **separate one-off invoice** that does not hold results back (D8). That choice is hidden until an extra is ticked.
- With no schedule for the student's scope, the list shows a warning and **Invoice is disabled** for those students.

## NEW data
None beyond `fee-schedule.md` and `invoices-generate.md`. **Verify first:** whether a separate one-off invoice already exists as a capability (D8 says "existing capability; verify") and how results gating reads it.

## Tests (trust-sensitive)
A one-off invoice never satisfies or blocks the result lock; the result lock reads only the term invoice; the invoice total equals the sum of its lines.

## Open questions (do not decide)
Whether extras can carry a custom amount for one student (not designed; the design uses the schedule amount).
