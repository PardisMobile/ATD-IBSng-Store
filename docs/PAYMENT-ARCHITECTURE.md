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
Customer-selected enabled provider + per-provider payment type
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
| `admin/settings.php` | Consistent settings cards, multi-provider activation, module-provided credentials, and per-provider currency-mode selection. |
| `admin/provisioning.php` | Recovery/reconciliation for provisioning states; does not undo verified payment. |
| `admin/payment-reconciliation.php` | Manual review of gateway-initiation transactions with no persisted provider reference; records a required operator note before releasing the order for another attempt. |

## Order price and currency rules

- Product edits affect new orders only. Checkout captures price values into the order. An existing order/payment transaction is not repriced when an admin edits a product.
- Fiat payment uses `orders.price_minor_snapshot` and the order's `currency` (`IRR` or `IRT`).
- Crypto payment uses the independent `orders.price_usdt_snapshot`; it is not an FX conversion from the fiat price.
- New installs include `price_usdt_snapshot` in `database/schema.sql`. Existing installs must apply `database/migrations/2026-11-02-order-usdt-price-snapshot.sql`. Legacy orders without a snapshot remain NULL; crypto payment initiation fails closed for them.
- The `crypto_gateway_enabled` setting controls storefront visibility of USDT prices. It is not a payment-provider selector and does not activate a gateway. USDT checkout choices additionally require an enabled provider configured for crypto mode and a valid USDT order snapshot.

## Admin configuration model

There are three distinct layers:

1. `payment_enabled_providers`: JSON list of enabled, discovered adapter codes. When this key is absent, the legacy `payment_provider` value is treated as the sole enabled provider so existing installations keep working.
2. `payment_provider`: retained as a legacy compatibility value and updated to the first enabled provider; it is no longer the multi-gateway selector.
3. `payment_<provider>_<field>`: adapter-defined credentials/configuration fields. The adapter's `configurationFields()` metadata creates each provider card; the admin supplies values, not field definitions.
4. `payment_<provider>_type`: admin-selected `fiat` or `crypto` mode for that provider.

The adapter's `supportedCurrencies()` declares capabilities. Each enabled provider is checked independently for supported currency and required credentials. Checkout derives the choices from enabled, configured adapters and the immutable order price snapshot; the customer selects one provider. The chosen provider, amount, and currency are persisted in the payment transaction before the provider request. Callback verification continues to resolve the adapter from that persisted transaction, so changing enabled providers cannot affect existing payments.

Current adapters:
- `zarinpal`: `IRR`, `IRT`; fiat only. Live and Sandbox are selected independently in the adapter configuration. Sandbox mode uses the Zarinpal v4 sandbox request/verify endpoints and sandbox StartPay host. The selected environment is snapshotted in `payment_transactions.raw_result_json` at transaction creation, so a later admin toggle cannot send an existing callback to the wrong environment. Sandbox transactions use the separate Sandbox Merchant ID setting.
- `ircrypto`: `IRR`, `IRT`, `USDT`; admin may configure one mode at a time.

## Ambiguous gateway initiation

- After the outbound gateway initiation call begins, an exception is not assumed to mean that no invoice was created. The internal transaction remains `initiated` with no provider reference, and the active-transaction guard prevents a new attempt for the same order.
- An administrator can review these records from the dashboard's **بررسی پرداخت‌های مبهم** page. The page does not call the gateway and must only be used after checking the provider portal for an outstanding invoice.
- Releasing an ambiguous transaction requires a non-empty reconciliation note. The service transitions only a matching `initiated` row with a NULL provider reference to `failed`; the note and resolution timestamp are retained in `raw_result_json`. This is an operator assertion, not a provider-side cancellation.
- Do not mark a transaction failed if a payable invoice may still exist. Reconcile with the gateway first; otherwise a customer could pay twice.

## Customer payment retry and transaction history

Payment retry is intentionally provider-aware. The generic customer page and `PaymentFlow` coordinate the operation, while each gateway adapter declares how an existing payment session is retried through the `PaymentGateway` contract:

- `retryStrategy(): string` must return either `new` or `resume`.
- `resume(array $transaction, array $settings): string` reopens the already-issued provider session and must not create a replacement invoice/session.
- The order-status page uses the latest transaction row (`ORDER BY id DESC`) to decide which retry action is available. It does not rewrite or delete earlier transaction rows; each new attempt is a separate durable `payment_transactions` record.

Current behavior:

| Situation | Retry behavior |
|---|---|
| No transaction exists yet | A retry is offered only when exactly one currently available provider can be selected unambiguously; it starts a new transaction. |
| Latest transaction is definitively `failed` | A new transaction/provider session may be created, provided that the same provider is still enabled, configured, and compatible with the order. |
| Latest transaction is `pending` and has a provider reference; adapter strategy is `new` | Start a new transaction and obtain a fresh provider session. The prior transaction and its reference remain in history. |
| Latest transaction is `pending` and has a provider reference; adapter strategy is `resume` | Reopen the existing provider session through the adapter. Do not create a new transaction or invoice. |
| Latest transaction is `initiated` without a provider reference | No customer retry is offered. This is an ambiguous initiation and requires provider-side reconciliation before it can be released. |
| Latest transaction is `pending` without a provider reference, or the provider is no longer available | No retry is offered because there is no safe resumable session or compatible provider choice. |

Adapter-specific rules:

- **Zarinpal — `new`:** every new attempt requests a fresh Authority. A pending transaction with an existing Authority can therefore be followed by a new transaction/Authority while the order remains unpaid; `order-status.php` passes `allowConcurrentPending=true` for this adapter strategy so the old pending Authority remains recorded rather than overwritten. The new transaction is the latest attempt shown to the customer, but callbacks for older attempts still resolve against their own persisted provider reference and transaction.
- **IRCrypto — `resume`:** retry re-fetches the existing invoice by its persisted invoice ID and reopens its hosted `payment_url`. It never creates a replacement invoice in the resume path. If the latest transaction is definitively failed, the generic failed-attempt rule permits a fresh transaction; the adapter's `resume` strategy applies to a still-pending invoice with a stored reference.

A resume operation is restricted to a transaction whose status is `pending`, whose provider reference is present, and whose order is still `pending` / `pending_payment`. The adapter is selected from the provider stored on that transaction, not from a newly selected global provider. A successful resume returns a validated HTTPS redirect URL; the existing transaction row and provider reference remain unchanged.

### Initiation failure classification and manual reconciliation

A payment transaction is persisted as `initiated` before the outbound gateway request. If initiation throws before a provider reference is attached, `PaymentFlow` stores a diagnostic in `payment_transactions.raw_result_json` under `initiation_diagnostic` (exception class, bounded message, and timestamp), preserving existing metadata such as Zarinpal's environment snapshot.

- **Definitive Zarinpal rejection:** when the Zarinpal adapter throws an `InvalidArgumentException` for invalid/incomplete request parameters, or returns a structured API rejection with no Authority (`Zarinpal did not issue a payment authority. Response: ...`), `PaymentFlow` marks that initiation transaction `failed`. No provider Authority was issued, so a new attempt can safely be started for the same unpaid order.
- **Unknown/ambiguous outcome:** network errors, timeouts, and other unclassified exceptions remain `initiated` with no provider reference. The system does not assume that the provider failed to create a payable session. An administrator must inspect the provider portal and use the ambiguous-payment reconciliation flow before the transaction can be released.
- This definitive-rejection classification is currently explicit Zarinpal handling in `PaymentFlow`; do not generalize it to another provider without a provider-specific, evidence-backed rule. Other adapters' exceptions remain ambiguous unless their own safe classification is deliberately implemented and tested.
- Manual release is limited to the matching `initiated` row with NULL provider reference. The required operator note and resolution timestamp are retained with the initiation diagnostic. It changes the Store's internal transaction state only; it does not cancel an invoice at the gateway.

The distinction is deliberate: a provider-declared/known failure can allow a new attempt, whereas an uncertain request must not be replayed blindly. Keeping every attempt as its own row preserves Authority/invoice history and allows callbacks to be matched to the exact transaction that created them.

## Verification and idempotency guarantees

- Provider callbacks are untrusted input. The adapter verifies the persisted provider reference and amount/currency using provider-side verification. IRCrypto webhook HMAC authenticates the webhook delivery but does not replace invoice lookup.
- The transaction stores provider, amount, currency, provider reference, status, callback ID, verification timestamp, and provider result data. Zarinpal's environment selection is persisted with the transaction before the outbound request and remains authoritative for its callback.
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

Source/CI checks do not prove live merchant configuration. Before enabling live payment, verify live credentials, HTTPS callback/webhook URLs, actual provider payloads, sandbox request/return/server-side verification, callback replay/concurrency behavior, and end-to-end IBSng provisioning/reconciliation in the target environment. Sandbox payment avoids a real charge; it does not sandbox the configured IBSng servers, so a verified sandbox payment will still execute provisioning against those servers.