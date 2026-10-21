# Spec: Invoice detail, part-payments, adjustments, void — `screens/invoice-detail.html`

Batch 5 (2026-10-18). Money is stored as integer kobo and shown in naira only (₦45,000), never kobo. Permission names are proposals: Codex names them and logs them.

States: Unpaid · Part-paid · Whole balance allocated · Paid · Awaiting approval · Voided · Online payment not set up · A part-payment invoice · Create a part-payment · Part-payment too large · Void a part-payment · Add an adjustment line · Void the invoice · Void blocked · No permission.

## Who can open
Bursar and Admin (invoicing permission). Creating and voiding part-payments, adjustments and voids use the same permission (Codex may split them and logs it).

## Design (D8, D10)
- **Status** with a progress line: "₦20,000 of ₦45,000 paid · balance ₦25,000", lines, part-payments, receipts and a **History** list (who, when, why).
- **Create a part-payment:** the sheet shows the **balance still allocatable** (total minus settled minus open), amount, due date and note. The server refuses an amount that would push settled plus open above the term total. Payers never type an amount; each part is its own invoice with its own receipt.
- **Void a part-payment:** only while unpaid; logged with actor and note; the amount becomes allocatable again.
- **Adjustment line:** a named line with a signed amount and a **required reason**; logged. Original lines never change.
- **Void invoice:** only if nothing is paid. With a settled part-payment the **void is blocked** and the sheet explains the alternatives.
- **Online payment not set up:** an info note; manual payment stays available.
- A **part-payment invoice** shows its parent term invoice and only its own actions.

## NEW data
A parent link on invoices (a part-payment invoice points to its term invoice); an adjustment line type and reason; a void reason; the history source. Codex confirms and logs names.

## Rules (the two traps in D10)
1. The **result lock reads only the term invoice**. A paid part-payment never satisfies it.
2. **Outstanding is the term invoice's balance**, never the sum of unpaid invoices (no double counting). `PaymentLedger::portalSummary` must change.
Allowed only on term fee invoices, not registration or offer-deposit invoices.

## Tests (trust-sensitive)
A settled part-payment does not unlock results and the final one does; outstanding is counted once; settled plus open part-payments can never exceed the term total; a term invoice with a settled part-payment cannot be voided; a part-payment can be voided only while unpaid; parts cannot be created on registration or offer-deposit invoices; each part-payment has its own receipt; creation and voids are logged.

## Open questions (do not decide)
Partial manual payments against a part-payment invoice (see `payment-record.md`); whether an Admin may override the void block.
