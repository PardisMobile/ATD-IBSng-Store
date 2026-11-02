# Payment Architecture (Current Source of Truth)

> This document describes the payment implementation currently on `main`. Read this before changing checkout, a gateway adapter, callbacks, or payment state. The companion `PAYMENTS-AND-PROVISIONING.md` covers adapter extension details and provisioning recovery.

## At a glance

```text
Product catalog
  ├─ price_minor + currency (IRR or IRT)
  └─ price_usdt (independent USDT price)
          |
          v
Checkout creates an order snapshot
  ├─ price_minor_snapshot + currency
  ├─ price_usdt_snapshot (nullable for legacy orders)
  ├─ product/name/duration snapshot
  └─ server_targets_snapshot (captured IBSng destinations)
          |
          v
Admin-selected provider + per-provider payment type
  ├─ fiat   -> order's fiat snapshot
  └─ crypto -> order's USDT snapshot
          |
          v
PaymentFlow::initiate()
  ├─ validates adapter-supported currency
  ├─ PaymentTransactionService::create()
  │    └─ stores provider, exact amount, currency, durable state
  └─ adapter initiates hosted/provider payment
          |
          v
Provider return / signed webhook
          |
          v
PaymentFlow::handleCallback()
  ├─ resolve transaction by provider + provider reference
  ├─ adapter performs authoritative provider-side verification
  └─ PaymentTransactionService::confirmVerified()
       ├─ transaction -> verified
       ├─ order payment -> paid
       └─ provisioning attempts inserted in same DB transaction
          |
          v
Best-effort immediate queue dispatch (request-time; no worker)
  ├─ success -> provisioning/order state updated
  └─ ambiguous remote result -> needs_review, no blind retry
```

## Main components and responsibilities

| Component | Responsibility |
|---|---|
| `app/Payments/PaymentGateway.php` | Provider adapter contract: code, supported currencies, callback reference, initiate, verify. |
| `app/Payments/PaymentManager.php` | Discovers trusted adapter files under `app/Payments/gateways/*.php`, resolves provider and delegates calls. |
| `app/Payments/PaymentAmount.php` | Resolves amount/currency only from immutable order snapshots. Fiat uses integer minor units; USDT uses 8-decimal scaling. |
| `app/Payments/PaymentFlow.php` | Checkout initiation and callback orchestration; does not itself trust callback payload as proof. |
| `app/Payments/PaymentTransactionService.php` | Durable transaction/order state transitions, amount consistency checks, and atomic queue creation after verified payment. |
| `payment-callback.php` | Customer-facing provider return endpoint. |
| `webhook/ircrypto.php` | IRCrypto signed POST delivery validation; then calls PaymentFlow for server-side invoice verification. Kept outside `app/Payments/gateways/` so automatic adapter discovery cannot load it as a gateway module. |
| `admin/settings.php` | Provider selection, module-provided configuration fields, and supported currency-mode selection. |
| `admin/provisioning.php` | Recovery/reconciliation for provisioning states; does not undo verified payment. |
| `admin/payment-reconciliation.php` | Manual review of gateway-initiation transactions with no persisted provider reference; records a required operator note before releasing the order for another attempt. |

## Order price and currency rules

- Product edits affect new orders only. Checkout captures price values into the order. An existing order/payment transaction is not repriced when an admin edits a product.
- Fiat payment uses `orders.price_minor_snapshot` and the order's `currency` (`IRR` or `IRT`).
- Crypto payment uses the independent `orders.price_usdt_snapshot`; it is not an FX conversion from the fiat price.
- New installs include `price_usdt_snapshot` in `database/schema.sql`. Existing installs must apply `database/migrations/2026-11-02-order-usdt-price-snapshot.sql`. Legacy orders without a snapshot remain NULL; crypto payment initiation fails closed for them.
- The `crypto_gateway_enabled` setting controls storefront visibility of USDT prices. It is not a payment-provider selector and does not activate a gateway.

## Admin configuration model

There are three distinct layers:

1. `payment_provider`: which discovered adapter is currently selected.
2. `payment_<provider>_<field>`: adapter-defined credentials/configuration fields. The adapter's `configurationFields()` metadata creates the admin form; the admin supplies values, not field definitions.
3. `payment_<provider>_type`: admin-selected `fiat` or `crypto` mode for that provider.

The adapter's `supportedCurrencies()` declares capabilities. Admin only renders fiat when `IRR` or `IRT` is supported and USDT when `USDT` is supported. Saving settings and starting a payment both validate compatibility. The mode is a single admin configuration per provider; customer-side selection between multiple currencies on one provider is not implemented.

Current adapters:
- `zarinpal`: `IRR`, `IRT`; fiat only.
- `ircrypto`: `IRR`, `IRT`, `USDT`; admin may configure one mode at a time.

## Ambiguous gateway initiation

- After the outbound gateway initiation call begins, an exception is not assumed to mean that no invoice was created. The internal transaction remains `initiated` with no provider reference, and the active-transaction guard prevents a new attempt for the same order.
- An administrator can review these records from the dashboard's **بررسی پرداخت‌های مبهم** page. The page does not call the gateway and must only be used after checking the provider portal for an outstanding invoice.
- Releasing an ambiguous transaction requires a non-empty reconciliation note. The service transitions only a matching `initiated` row with a NULL provider reference to `failed`; the note and resolution timestamp are retained in `raw_result_json`. This is an operator assertion, not a provider-side cancellation.
- Do not mark a transaction failed if a payable invoice may still exist. Reconcile with the gateway first; otherwise a customer could pay twice.

## Verification and idempotency guarantees

- Provider callbacks are untrusted input. The adapter verifies the persisted provider reference and amount/currency using provider-side verification. IRCrypto webhook HMAC authenticates the webhook delivery but does not replace invoice lookup.
- The transaction stores provider, amount, currency, provider reference, status, callback ID, verification timestamp, and provider result data.
- Confirmation is transactional and guarded by persisted state. Repeated callbacks cannot pay the order a second time or recreate already-keyed provisioning attempts.
- Provisioning attempts use an order/server/operation idempotency key. Remote IBSng create outcomes that cannot be confirmed are marked `needs_review`; they are not blindly replayed because IBSng may have committed despite a lost response.
- Provisioning dispatch currently runs best-effort synchronously after payment confirmation. This is not a background worker or scheduled retry mechanism. A verified payment remains verified if provisioning needs review.

## Extension path: add a payment provider

1. Add one trusted adapter file under `app/Payments/gateways/`.
2. Implement `PaymentGateway`: stable `code()`, `supportedCurrencies()`, callback reference extraction, initiate, and authoritative verify.
3. Expose adapter-specific admin fields through `configurationFields()` metadata.
4. Ensure adapter initiation and verification agree exactly with the stored transaction amount/currency and provider reference.
5. Add adapter contract/unit tests and update the provider list above.
6. Do not add a parallel provider registry or edit generic checkout/callback orchestration for provider-specific behavior.

## Deployment validation still required

Source/CI checks do not prove live merchant configuration. Before enabling live payment, verify credentials, HTTPS callback/webhook URLs, actual provider payloads, provider sandbox or low-value payment, callback replay/concurrency behavior, and end-to-end IBSng provisioning/reconciliation in the target environment.