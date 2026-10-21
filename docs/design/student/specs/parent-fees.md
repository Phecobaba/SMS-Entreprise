# Spec: Parent fees — `screens/parent-fees.html` (also updates `screens/fees.html` for students)

States: Unpaid · Part-paid · Confirming payment · **Awaiting approval** · Payment failed · Paid up · No invoices · One child.

## Who can open
Proposal: linked parent, selected child only. A parent can pay any linked child's open invoice (`BillingRecipients::owns` must include linked parents: verify). Staff use `payments.index`.

## Today
`PaymentLedger::portalSummary`: last 5 invoices and `outstanding_kobo`. `payments.mine.begin` (POST) starts Paystack. `payments.verify` redirects to `route('home')`.

## Design
- Summary card: **Outstanding** (naira), one primary **Pay** button. **No "Pay all"** (not specified).
- Each invoice is an expandable row: title, due date, amount, status pill (Unpaid, Part paid, Confirming, Paid). Expanded: **line items and total**; receipts.
- **Part-paid invoice:** "₦20,000 of ₦45,000 paid · balance ₦25,000", a progress bar, a part-payment list (paid ones show receipt; **only the open part-payment has a Pay button**), and a receipts list.
- **"To pay in parts, please visit the bursar."** appears on an unpaid invoice with no part-payments. There is no request form.
- **Pay sheet** before leaving for Paystack: names the amount, the child and what happens next. The sheet title changes with what is being paid (full invoice or a part).
- **Return trip:** after Paystack the parent lands on **this page** (not the homepage) in Confirming, Paid or Failed state. Settlement still only comes from verified webhook/verify.
- **Awaiting approval (decided):** once staff have **recorded a manual (bank) payment**, the parent sees a dashed pill with a clock icon, "Awaiting approval", and the text "The school has recorded your bank payment and is confirming it. You don't need to pay again." The expanded invoice shows a "Recorded payment" line (method, date recorded, amount) and "You'll get a notification when the school approves it." Results and attendance stay locked until approval.
  **Proposal (confirm): the Pay button is hidden on that invoice** (and the summary card's Pay button) while the payment is awaiting approval. No teller number, bank name or photo is shown to the parent.
- Money is shown in naira, whole amounts, never kobo.

## NEW (names to be matched to `ADMIN_FOUNDATIONS_BRIEF.md`)
- Invoice **line items** (description, amount, total must equal the invoice amount).
- **Part-payments** on an invoice (amount, due date, status, receipt).
- Constraint to resolve: `payments.invoice_id` is **unique** today (one settlement per invoice), so several receipts on one invoice needs part-payments as their own settleable rows or a relaxed rule. Decide in the Admin brief, not here.
- `payments.verify` redirect fix (students and parents to their fees page).

## Dependent fields
Part-payment block and progress only when the invoice has part-payments. "Visit the bursar" line only when unpaid with none. Pay button only on open invoices or the open part-payment. Confirming/Failed notices come from the latest `payment_attempt` (Pending/Failed).

## Decided (owner, 2026-10-10)
- Fees remain a **single-child view** (no all-children summary).
- Parents see "Awaiting approval" for a recorded manual payment (above). The Pay-hidden rule is a **proposal**.
- The switcher's "Fees due" pill stays; it does not show for Confirming or Awaiting approval (proposal; see `specs/parent-ward.md`).

## Open questions
- What the parent sees if staff **reject** the recorded payment (the design assumes the invoice returns to Unpaid with Pay back; the wording of any notice is not designed).
- Whether a recorded manual payment that covers only part of an invoice also hides Pay for the remaining open items (not designed).

## NEW data for Awaiting approval
A manual payment record with a status (awaiting approval / approved / rejected), as the Admin foundations brief describes for the Bursar-and-Admin queue. Names to be matched there; do not invent them.


## Added 2026-10-18 (batch 5): "Online payment isn't set up yet"
New state **Online payment not set up**: a notice replaces the Pay buttons ("You can't pay online right now. To pay, please visit the bursar with your bank teller...") on every invoice, in the summary and inside each invoice row; line items, receipts and status stay. It shows whenever the school has no payment gateway keys (saved or in the environment) per `admin/specs/payment-gateway.md`. Manual payments still work and **Awaiting approval** still shows after the school records one. Test: with no keys, no Pay control is rendered for a parent.
