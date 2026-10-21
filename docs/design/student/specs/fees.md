# Spec: Fees — `screens/fees.html`

States: Unpaid · Confirming payment · Payment failed · Paid up · No invoices.

## Today
`PaymentLedger::portalSummary` returns the latest 5 invoices and `outstanding_kobo`; the dashboard shows "Pay now" per invoice (`payments.mine.begin`, POST → Paystack redirect). `payments.verify` redirects to **`route('home')`**, the public homepage, with a flash.

## Changes
- New route `GET /my-fees`: all invoices (paginated), each with purpose, term, due date, amount, status; paid ones show `paid_at` and `receipt_number`. There is no receipt PDF: the number is shown as text only (do not invent a download).
- "Overdue" is derived (`due_date` < today and Unpaid); there is no stored overdue status.
- **Pay sheet** before leaving for Paystack (what happens, that they return here).
- **Fix the return trip:** `payments.verify` should redirect to `/my-fees` for students and parents (keep `home` only for guests).
- **Confirming** state when the latest `payment_attempt` is Pending; **Failed** when the latest is Failed. Settlement still only comes from the verified webhook/verify, never from the redirect.
- Payers: the code lets the student or a linked parent pay (`BillingRecipients::owns`). The copy says so ("Ask a parent or guardian to pay, or pay it yourself"). Decide this knowingly.


## Update (batch 3): fees v2 for students (see `specs/parent-fees.md`)
Invoices become expandable rows with **line items and total**; part-paid invoices show progress, part-payments (only the open one has Pay) and receipts; unpaid invoices with no part-payments show "To pay in parts, please visit the bursar." Pay sheet names the amount being paid. Line items and part-payments are **NEW** (names from `ADMIN_FOUNDATIONS_BRIEF.md`); until they exist, ship the current invoice list with the summary card and the Pay sheet.
Locked states stay neutral. Nothing about results is shown behind a locked card.
**Awaiting approval (decided, owner 2026-10-10):** when staff have recorded a manual bank payment, the invoice shows the dashed "Awaiting approval" pill, the line "The school has recorded your bank payment and is confirming it", and **Pay is hidden** on that invoice (proposal). The design file has an "Awaiting approval" state. Same rules and NEW data as `specs/parent-fees.md`.


## Added 2026-10-18 (batch 5): "Online payment isn't set up yet"
New state **Online payment not set up**: a notice replaces the Pay buttons ("You can't pay online right now. To pay, please visit the bursar with your bank teller...") on every invoice, in the summary and inside each invoice row; line items, receipts and status stay. It shows whenever the school has no payment gateway keys (saved or in the environment) per `admin/specs/payment-gateway.md`. Manual payments still work and **Awaiting approval** still shows after the school records one. Test: with no keys, no Pay control is rendered for a student.
