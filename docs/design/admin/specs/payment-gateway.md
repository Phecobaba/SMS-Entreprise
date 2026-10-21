# Spec: Payment gateway settings — `screens/payment-gateway.html`

Batch 5 (2026-10-18). Money is stored as integer kobo and shown in naira only (₦45,000), never kobo. Permission names are proposals: Codex names them and logs them.

States: Not set up · Using environment keys · Saved (Test) · Saved (Live) · Confirm password · Saving · Connection OK · Connection failed · Keys do not match · Bursar (no access).

## Who can open
New permission `manage_payment_gateway`, **Admin only by default** (D11). The Bursar does not hold it and sees the locked state.

## Today
Paystack keys are read only from `config/services.php` (`PAYSTACK_SECRET_KEY`), including the webhook HMAC check. **Verify first.**

## Design (D11)
- **Public key** is shown and editable. The **secret key** is write-only: saved masked ("sk_test_••••1234"), never shown again, never in a response, log, error message or audit entry.
- Changing keys needs the **Admin's password again**. The change is **audit-logged** (who and when, never values), shows **last changed by**, and **notifies the other Admins** in the app.
- **Prefix check:** a test public key with a live secret key (or the reverse) is refused with a clear message. A **Test / Live badge** shows the mode.
- **Test connection** makes a harmless read-only Paystack call and shows OK or failed. No money moves.
- **Webhook address** to copy, with short instructions. **Do not guess the Paystack dashboard menu path**; confirm the current location in Paystack's documentation before release. The URL in the design is illustrative: Codex shows the real route.
- With **no keys saved** (and none in the environment), student, parent and Bursar screens show **"Online payment isn't set up yet"** instead of Pay buttons; manual payments keep working.
- **Lookup order:** saved keys first, the environment variable as a fallback ("Using environment keys" state). The webhook signature must use the saved secret.
- Keys are **encrypted with the application key**. If the application key is lost or changed the keys cannot be decrypted and must be re-entered; this page says so.

## NEW data
Per-school encrypted gateway settings (public key, encrypted secret key, mode, changed by, changed at). Update `docs/payments.md` ("keys only in the environment").

## Tests (trust-sensitive)
The secret key never appears in any response, log or audit entry; a saved secret verifies a webhook signature and a wrong one does not; a test public key with a live secret key is refused; a change requires the password; with no keys, the Pay screens show the not-set-up state and manual payment still works.

## Open questions (do not decide)
The exact Paystack dashboard location for the webhook setting (confirm before release); the wording shown when decryption fails after an application-key change.
