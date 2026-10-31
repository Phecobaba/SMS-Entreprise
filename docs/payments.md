# Payments handoff

The authenticated Student or linked-Parent checkout return is `/my-fees`
for the invoice's student (with `?student={id}` for a Parent). The return
handler calls Paystack's server-side verify endpoint and settles only after
the reference, currency, amount, and provider success match the pending
attempt; the browser redirect alone never marks an invoice Paid. Guests
return to the public homepage. Linked Parents may initialize payments only
for their own wards through `BillingRecipients::owns()`; `payments.invoice_id`
remains unique, so future D10 part-payments use linked invoices.

Phase 4 uses Paystack for NGN online payments. An Admin with
`manage_payment_gateway` manages a school's public and write-only secret key
on **Payment gateway**. The secret is encrypted with `APP_KEY`; only its last
four characters are stored separately for the mask. Saved keys take priority
over `PAYSTACK_PUBLIC_KEY` / `PAYSTACK_SECRET_KEY` environment fallbacks. If
`APP_KEY` is lost or changed, the saved secret cannot be read and both keys
must be entered again. Never commit keys or put a secret in a browser response,
log, exception, old input or audit entry.

The Bursar sees the gateway's locked state and cannot read or change keys.
Changing keys requires the Admin's current password, is rate-limited, records
only who and when in `audit_logs`, and notifies the other Admins. Test and Live
prefixes must match. The connection check makes the read-only Paystack request
`GET /transaction?perPage=1&page=1` with a timeout; it never moves money.

When neither a valid saved pair nor an environment pair exists, online payment
is refused server-side and Student, Parent and Bursar payment screens show
"Online payment isn't set up yet" instead of Pay controls. This applies to
term-fee, linked part-payment and one-off invoices. Manual bank-teller payments
continue to work.

Configure the Paystack webhook URL as
`https://<live-domain>/payments/paystack/webhook` when a reachable host and
keys exist. Add it as the webhook URL in the Paystack dashboard's **API Keys &
Webhooks** settings for the matching Test or Live mode. The endpoint resolves
the same saved-first secret as initialization and verification, then checks the
raw-body `x-paystack-signature` SHA-512 HMAC with `hash_equals`
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
