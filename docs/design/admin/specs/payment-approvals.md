# Spec: Awaiting approval (approve or reject) — `screens/payment-approvals.html`

Batch 5 (2026-10-18). Money is stored as integer kobo and shown in naira only (₦45,000), never kobo. Permission names are proposals: Codex names them and logs them.

States: Normal (Admin) · Bursar view (read-only) · Approve · Reject (reason) · View the slip · Approved · Rejected · Already handled · Nothing waiting · One-step school · No permission.

## Who can open
Admin approves and rejects. The Bursar sees the same list **read-only** (no buttons; "Admin approves"). Codex names the approval permission and logs it.

## Design (D9)
- A list of recorded payments: student and class, bank and teller number, date paid, amount, who recorded it, **View slip**, **Approve**, **Reject**.
- **Approve** states the effect: the term invoice is marked Paid, a receipt is issued, and results and attendance open **only if** this settles the whole term invoice. It cannot be undone.
- **Reject** needs a reason; the invoice stays unpaid; the Bursar sees the reason.
- **Already handled:** if another Admin acted first, nothing is changed and the list refreshes.
- A **one-step school** has no queue; the page says so.

## NEW data
See `payment-record.md`. Also an approval log (who, when, decision, reason).

## Tests (trust-sensitive)
Only an Admin can approve or reject (server-side); approving twice issues one receipt; approving a part-payment invoice does not unlock results until the term invoice is fully paid; a rejected payment does not settle anything.

## Open questions (do not decide)
Rejection wording and what happens to the teller number after a rejection; whether the Bursar is notified in the app.


## Added 2026-10-18: One-step approval toggle
An **Admin-only** switch card at the top of this page: **One-step approval**, **Off by default**. Off: the Bursar records a payment and an Admin approves it before the invoice shows Paid. On: a payment the Bursar records is approved at once (the invoice shows Paid and a receipt is issued), with every payment kept in the history; payments already waiting stay in the queue. Turning it **on** asks for confirmation (`confirm-on`, with the effect and "you can turn it off again"); turning it off needs none. Each change is logged (who, when). The Bursar sees a read-only line, "Approval: two steps", and no switch. States added: Turn on one-step approval, One-step approval is on.
- **NEW:** one school setting (name it, default off); it is the setting `payment-record.md` calls the one-step setting.
- **Verify first:** where school settings are stored and whether an Admin-only permission already guards settings.
- **Tests:** only an Admin can change it (server-side); the default is off; with it on, a recorded payment is approved at once with exactly one receipt and an automatic approval entry; turning it on or off does not change payments already waiting; the Bursar cannot change it.
- **Open:** whether the other Admins are told when it changes (design: logged only).
