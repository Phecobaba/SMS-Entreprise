# Spec: Generate invoices — `screens/invoices-generate.html`

Batch 5 (2026-10-18). Money is stored as integer kobo and shown in naira only (₦45,000), never kobo. Permission names are proposals: Codex names them and logs them.

States: Choose · Review · Generating · Review (no schedule for some) · Everyone already invoiced · No fee schedule · Result report · Error · No permission.

## Who can open
`manage_fee_schedule` or the invoicing permission Codex chooses (log it). Bursar and Admin by default.

## Today
Invoices are created one at a time. There is no bulk generation.

## Design (D8)
- Choose **term**, **due date** and **who** (whole school, one section, one level). **Preview** first: "Will be invoiced 389 · Already have one 23 · Students in scope 412", then each scope's students and invoice total.
- **Idempotent:** a student who already has the term invoice is skipped, so running it twice never duplicates. The report says how many were created and skipped.
- Each invoice **copies its lines** from the schedule at generation. Extras are not added in bulk.
- If a scope has no schedule, the preview warns and skips those students; with no schedule at all, the screen sends the user to the fee schedule.
- A student enrolled later is **invoiced automatically** at enrolment when a schedule exists (D7).

## NEW data
Invoice lines (copied), a term on the invoice, a due date, and a creation source (bulk or single). Codex confirms what the invoice table needs. A bulk run is logged (who, when, term, scope, created, skipped).

## Tests (trust-sensitive)
Running the same generation twice creates no duplicate; the preview counts equal the created and skipped counts; an invoice's total equals the sum of its lines; a later schedule edit does not change an issued invoice; students with no matching schedule are skipped, not invoiced at zero.

## Open questions (do not decide)
Behaviour when a student matches more than one scope (see `fee-schedule.md`); whether students with no class are listed or only skipped.
