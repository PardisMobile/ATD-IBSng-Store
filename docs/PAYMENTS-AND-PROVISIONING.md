# Provisioning queue and payment-provider extensions

## Payment adapters

PaymentManager is the single registry and dispatcher for payment modules. No provider is selected on a fresh install; an empty payment_provider setting means payment is disabled. Each adapter implements PaymentGateway; its optional configurationFields() metadata drives the admin settings UI and validation. Provider-specific values are stored as payment_<provider-code>_<field-key> and passed generically to the adapter.

Register additional adapters with PaymentManager::registerGateway('<code>', AdapterClass::class) during trusted application bootstrap. There is no second provider registry file. The adapter code must match the registration key. Keep provider secrets server-side and never trust callback values as payment proof.

Zarinpal is currently a built-in adapter, not a payment-core default. Adding a gateway requires its adapter, trusted registration, and adapter-level tests. Runtime credentials and live gateway sandbox checks are deployment verification tasks.

## IBSng provisioning

Payment verification queues one create attempt per captured order destination. The admin dashboard links to admin/provisioning.php, which dispatches queued attempts individually; it is not a background worker.

The dispatcher locks and claims the queue item as running before remote activity. It first looks up the requested username. If it already exists, it reconciles only when both its group and retrievable credentials match the immutable order snapshot; any mismatch or unreadable credential result becomes needs_review. A new create is sent at most once by this dispatcher. If the remote response or subsequent confirmation is ambiguous, the attempt becomes needs_review and is not made available for direct retry. A stale running attempt is likewise not automatically retried, because the remote operation may have committed before the process stopped.

The queue's idempotency key is scoped to order/server/create. Multi-server results remain independent; successful destinations are not re-run when another destination is unresolved. The admin page permits an operator to record a manual succeeded/failed outcome for a needs_review or stale running attempt only after inspecting IBSng; this action does not send another remote request and requires an audit note. There is no automatic retry or refund.

The IBSng behavior is based on the tested reference repository; the Store's integration must preserve that behavior. CI syntax/contract checks do not replace the reference implementation's established test coverage.
