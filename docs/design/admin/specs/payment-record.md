# Spec: Record a manual (bank teller) payment — `screens/payment-record.html`

Batch 5 (2026-10-18). Money is stored as integer kobo and shown in naira only (₦45,000), never kobo. Permission names are proposals: Codex names them and logs them.

States: Empty form · Filled in · Error (teller number already used) · Error (amount) · Recording · Recorded: waiting for approval · Recorded: one-step school · No permission.

## Who can open
The Bursar and the Admin (`manage_payments` today; Codex confirms how a Bursar account is created, see batch 6).

## Today
`PaymentController::manual` settles an invoice **immediately**, captures no bank, teller number, date, amount or photo, and any `manage_payments` user can do it. **Verify first.**

## Design (D9)
- Fields: **invoice** (search by student name or registration number), **bank**, **teller number**, **date paid**, **amount (₦)**, optional **teller photo**.
- A **teller number is accepted only once, school-wide**: a second use shows an error naming the earlier use (date and student). The same slip can never settle two invoices.
- Two steps by default: the Bursar records it and it is **Awaiting approval**; an Admin approves. A **school setting** (the Admin-only **One-step approval** toggle on the Awaiting approval page, off by default) allows single-step; the screen then says "Recorded and approved".
- The amount must match the invoice balance (design assumption; see open questions).

## NEW data
On a manual payment: bank, teller number (unique per school), paid date, amount in kobo, optional photo reference, recorded by, status (Awaiting approval, Approved, Rejected), approved or rejected by and when, and a reason on rejection. A school setting for one-step approval. Codex names them and logs them.

## Photo upload (proposal, not decided)
Stored on a private disk, never public; JPG, PNG or PDF; a size limit Codex chooses and logs.

## Tests (trust-sensitive)
A teller number cannot be recorded twice (a unique constraint, race-safe); an unapproved payment does not settle the invoice or open results; approval settles the invoice and issues exactly one receipt; the amount recorded and the amount settled match.

## Open questions (do not decide)
- **Partial manual payments:** may a manual payment be less than the invoice balance? The design requires an exact match and points to part-payments.
- **Manual-payment rejection wording** and what the Bursar can do after a rejection (record again with a new teller number, or reuse the same one?). The design returns the payment to the Bursar with the Admin's reason.
