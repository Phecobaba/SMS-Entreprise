# Payments handoff

The authenticated Student or linked-Parent checkout return is `/my-fees`
for the invoice's student (with `?student={id}` for a Parent). The return
handler calls Paystack's server-side verify endpoint and settles only after
the reference, currency, amount, and provider success match the pending
attempt; the browser redirect alone never marks an invoice Paid. Guests
return to the public homepage. Linked Parents may initialize payments only
for their own wards through `BillingRecipients::owns()`; `payments.invoice_id`
remains unique, so future D10 part-payments use linked invoices.

Phase 4 uses Paystack for NGN online payments. Keep `PAYSTACK_SECRET_KEY` in
the deployment environment (test key first, live key only at go-live); never
commit it or put it in a browser response. The Bursar issues a Registration,
Tuition, or Offer Deposit invoice. A candidate can initiate Paystack payment
from My Admission; Bursar can initiate it from Payments. Manual settlement
requires `manage_payments` and creates the same invoice/receipt record.

Configure the Paystack webhook URL as
`https://<live-domain>/payments/paystack/webhook` when a reachable host and
keys exist. The endpoint checks the raw-body `x-paystack-signature` HMAC
before calling Paystack's transaction-verify API. It only settles an invoice
when the verified reference, NGN currency, and kobo amount match its stored
attempt. Replays cannot produce a second payment. The Paystack callback
also uses `/payments/paystack/verify?reference=...`, but webhook
reconciliation is the automatic path.

The local tests fake Paystack's API and sign webhook payloads with a fake
test key. They verify code behavior, not a real Paystack transaction.

**Real TEST transaction proof (2026-10-04):** Paystack initialized and
successfully charged a dedicated ₦50 sandbox Offer Deposit invoice. The
first webhook deliveries returned 502 while temporary tunnels and the
local PHP server were being corrected. Paystack's Webhook Events API
accepted a resend of that exact `charge.success` event to the corrected
temporary HTTPS tunnel. Paystack then marked the event **Delivered / HTTP
200**. The local webhook log recorded `newly_settled: true`; invoice #2
became Paid by an Online payment and receipt `OAK-1-00000002` rendered on
the Bursar screen without manual matching. The focused reconciliation test
and full test suite still pass. The earlier failed, unpaid test invoice and
its pending attempts were removed; the successful invoice and receipt remain.

This proves the application reconciles a real Paystack webhook, including a
provider resend after a transport failure. It does **not** prove cPanel
deployment or uninterrupted first-attempt webhook delivery on the eventual
live host. The temporary tunnel has been stopped; replace or clear its
Paystack **test-mode** webhook URL before future tests. At go-live, use the
real host URL and repeat the live-host checks in `PHASES.md`.
