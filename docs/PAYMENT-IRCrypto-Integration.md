# IRCrypto Payment Adapter — Implementation Notes

## Scope

The IRCrypto adapter is registered in the Store's explicit `PaymentManager` allowlist and implements the existing `PaymentGateway` contract. It is an independent provider adapter; the order and transaction state services remain provider-neutral.

## Implemented on main

- `app/Payments/ircrypto.php`: provider settings metadata, fixed invoice creation, provider invoice reference, hosted payment redirect, and server-side invoice lookup during verification.
- `app/Payments/PaymentManager.php`: explicit IRCrypto registration.
- `ircrypto-webhook.php`: POST-only webhook endpoint that checks the `ir-signature` timestamp and HMAC-SHA256 against the exact raw body, applies a 300-second timestamp tolerance, and then routes invoice verification through `PaymentFlow`.
- `app/Router.php`: explicit route allowlist entry for the webhook.
- `tests/PaymentManagerTest.php`: registration, invoice-reference, and secret-setting metadata checks.

## Amount behavior

- IRR invoices use the transaction's integer amount directly.
- IRT amounts are converted to IRR using the fixed 10:1 unit relation.
- USDT catalog price is mapped to a nominal USD invoice amount at 1:1. IRCrypto's hosted page chooses the actual enabled crypto asset/network. The adapter rejects USDT values with nonzero 7th/8th decimal places because IRCrypto documents a maximum of six decimal places for invoice amounts; it does not silently round.
- The adapter verifies the remote invoice is `paid`, and compares invoice `external_ref`, amount, and currency with the stored transaction/order.

## Operational requirements / not yet verified

- Configure both `payment_ircrypto_api_key` and `payment_ircrypto_webhook_secret` in Admin > Store settings > Payment gateway. The webhook endpoint is `/ircrypto-webhook.php` relative to the Store installation base path; configure that exact HTTPS URL in the IRCrypto dashboard.
- A signed webhook is only the delivery authenticity check. PaymentFlow still calls IRCrypto's invoice-read endpoint to establish provider-side paid status and compare the persisted transaction details.
- Provider sandbox/live payment, callback redirect parameter behavior, actual webhook payload shape under the merchant account, MySQL callback replay/concurrency, and provisioning execution have not been verified in this environment.
- Do not enable live payment until these checks and CI are confirmed. The existing queue after payment is durable but remote IBSng provisioning is not yet implemented.
