# Local Paystack testing

Paystack cannot call a local-only address. Use a temporary public HTTPS tunnel,
set the application URL to that tunnel, and test with Paystack Test keys. Never
put a secret key in this document, source control, screenshots, logs or tickets.

## 1. Start the app and a tunnel

Run the Laravel app locally, normally at `http://127.0.0.1:8000`. In another
terminal, start one tunnel:

```text
ngrok http 8000
```

or:

```text
cloudflared tunnel --url http://127.0.0.1:8000
```

Copy the tunnel's `https://...` address into the local `.env` as `APP_URL`, then
run `php artisan config:clear`. Keep both the Laravel server and tunnel running.
Do not use the tunnel's HTTP address.

## 2. Copy the URLs into Paystack Test mode

Sign in as a School Admin and open **Payment gateway**. Confirm that its
Callback URL and Webhook URL use the tunnel's HTTPS host and that the local-host
warning is absent.

In the Paystack dashboard, select **Test mode**, then open **Settings → API Keys
& Webhooks**. Under **API Configuration - Test Mode**:

1. paste the app's **Callback URL** into the Test Callback URL field;
2. paste the app's **Webhook URL** into the Test Webhook URL field; and
3. save the Test configuration.

Test and Live settings are separate. Do not paste a temporary tunnel URL into a
Live field. Paystack documents these fields in its
[API keys and webhooks guide](https://support.paystack.com/en/articles/2123458).

## 3. Enter Test keys in Oakfield

Copy the Test public and Test secret keys from Paystack's Test configuration.
On Oakfield's **Payment gateway** page, enter them in **Public key** and
**Secret key**, confirm with **Your current password**, and choose **Save
keys**. The secret is write-only and will not be displayed again. Run **Test
connection**; it performs a read-only request and moves no money.

## 4. Use Paystack's documented Test cards

Paystack's current [Test Payments documentation](https://paystack.com/docs/payments/test-payments/)
lists these examples:

| Scenario | Card number | Expiry | CVV | PIN / OTP |
|---|---|---|---|---|
| Successful, no validation | `4084 0840 8408 4081` | Any future date | `408` | None |
| Successful, PIN and OTP | `5060 6666 6666 6666 666` | Any future date | `123` | PIN `1234`; OTP `123456` |
| Declined | `4084 0800 0000 5408` | Any future date | `001` | None |

Use only Paystack's published Test details while the app is configured with
`pk_test_...` and `sk_test_...` keys.

## 5. Manual checklist

- Payment gateway shows the tunnel HTTPS Callback and Webhook URLs.
- Both URLs are saved in Paystack's Test fields.
- Oakfield shows **Test mode** and **Connection OK**.
- A Student or Parent can open an unpaid invoice and continue to Paystack.
- A documented Test card completes checkout and returns through the Callback
  URL.
- The invoice becomes Paid and its receipt appears without manual matching.
- Paystack's Test webhook history shows `charge.success` delivered with HTTP
  200 to the displayed Webhook URL.
- Retrying that webhook does not create a second payment or receipt.
- After testing, stop the tunnel and replace or clear its temporary URLs in
  Paystack Test mode before using a different tunnel.
