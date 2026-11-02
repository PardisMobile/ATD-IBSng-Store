# Provisioning queue and payment-provider extensions

## Payment adapters

The payment core resolves adapters through `PaymentManager`. No provider is selected on a fresh install; an empty `payment_provider` setting means payment is disabled. Adapter configuration fields are supplied by each adapter's optional `configurationFields()` method and are rendered/validated by the admin settings page. Provider-specific values are stored as `payment_<provider-code>_<field-key>` and passed to the adapter generically.

Trusted adapter registrations belong in `app/Payments/providers.php`. Register an adapter class implementing `PaymentGateway` with `PaymentManager::registerGateway('<code>', AdapterClass::class)`. A registered class may be loaded from a file other than the conventional `<code>.php` because the registration hook can require it itself. The adapter code must match the registration key. Keep provider secrets server-side and never trust callback values as payment proof.

Zarinpal is currently one installed adapter, not a payment-core default. To add another gateway, implement the contract, describe its settings fields, register it in the trusted provider hook, and write adapter-level tests. Runtime credentials and live gateway sandbox checks are still deployment verification tasks.

## IBSng provisioning

Payment verification queues one `create` attempt per captured order destination. The admin dashboard links to `admin/provisioning.php`, which dispatches queued attempts individually; it is not a background worker.

The dispatcher locks and claims the queue item as `running` before remote activity. It first looks up the requested username. If it already exists, it reconciles only when both its group and retrievable credentials match the immutable order snapshot; any mismatch or unreadable credential result becomes `needs_review`. A new create is sent at most once by this dispatcher. If the remote response or subsequent confirmation is ambiguous, the attempt becomes `needs_review` and is not made available for direct retry. A stale `running` attempt is likewise not automatically retried, because the remote operation may have committed before the process stopped.

The queue's idempotency key is scoped to order/server/create. Multi-server results remain independent; successful destinations are not re-run when another destination is unresolved. Operator reconciliation must inspect the IBSng account and resolve the attempt explicitly before any repeat action. No automatic refund is performed.

The admin dispatcher and IBSng parsing should be verified against a non-production IBSng A1.24 instance before live sales. A PHP lint/CI pass does not establish remote IBSng correctness.
