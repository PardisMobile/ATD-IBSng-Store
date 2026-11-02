# IRCrypto Payment Adapter — Current Integration Notes

> The canonical flow, state model, and currency configuration are documented in [PAYMENT-ARCHITECTURE.md](PAYMENT-ARCHITECTURE.md). This document records IRCrypto-specific behavior only.

## Adapter location and registration

- Adapter: `app/Payments/gateways/ircrypto.php`
- Contract: `PaymentGateway`
- Discovery: `PaymentManager` scans trusted adapter files in `app/Payments/gateways/*.php`; there is no separate hard-coded provider registration requirement for the normal extension path.
- Declared currencies: `IRR`, `IRT`, `USDT`.
- Admin fields are exposed through `configurationFields()`: API key and webhook secret. The admin enters the values; the module defines the fields.
- Admin selects one configured mode (`fiat` or `crypto`) per provider. This does not offer simultaneous customer-side currency choice.

## Invoice denomination

- IRR order amounts are sent as IRR.
- IRT order amounts are converted to IRR using the exact 10:1 relation.
- USDT snapshot amount is mapped nominally 1:1 to USD invoice amount. The payer selects the settlement asset/network on IRCrypto's hosted invoice page.
- IRCrypto invoice amount precision is limited to six decimal places for the USDT-to-USD mapping. The adapter rejects values with unsupported precision instead of rounding.
- Verification retrieves the invoice server-side and checks paid status, external order reference, amount, and provider invoice currency against the persisted payment transaction.

## Webhook flow

- Endpoint: `webhook/ircrypto.php` (relative to the Store installation base path; dispatched by the explicit router allowlist).
- Accepts POST only.
- Validates the `ir-signature` timestamp and HMAC-SHA256 against the exact raw request body, with a 300-second timestamp tolerance.
- A valid signature authenticates webhook delivery only. `PaymentFlow::handleCallback()` still performs provider-side invoice lookup and compares persisted transaction details before marking the order paid.
- Non-paid or unrelated events do not confirm a payment.

## Setup and operational checks

Configure `payment_ircrypto_api_key` and `payment_ircrypto_webhook_secret` in Admin → Store settings → Payment gateway, then set the matching HTTPS webhook URL in IRCrypto.

Live provider account configuration, actual webhook payload shape, sandbox/live invoice lifecycle, replay/concurrency, and live provisioning are deployment checks. Do not claim these are verified from syntax/CI alone.