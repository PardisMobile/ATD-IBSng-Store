# Admin Order Management

## Scope
This document defines the admin-side order workflow for ATD IBSng Store. It is intentionally separate from customer checkout and payment gateway implementation.

## Existing constraints
- IBSng integration must reuse the already validated HTTP/HTML implementation in the project/reference code. Do not introduce an IBSng API client or rewrite that behavior.
- The store supports a manager-operated test/manual path that can exercise account provisioning without collecting payment.
- The test/manual path must not mark an order as paid or mutate payment state.
- Payment gateways remain modular and can be integrated later.
- Work is performed on `feat/router-safe-dispatch`; do not modify `main`.

## Intended admin workflow
1. Admin opens an order from the order-management view.
2. The view shows customer/order snapshot, product and server policy, payment state, provisioning history, and any existing subscription.
3. Admin explicitly starts the appropriate provisioning action.
4. The application validates the order/product/server/group mapping and records a provisioning attempt before making the remote call.
5. The existing IBSng client/service is invoked for the operation; its established request/parsing behavior is retained.
6. The result is recorded against the attempt and subscription/order provisioning status. Payment status remains unchanged.
7. Duplicate submissions must be guarded using a stable idempotency key and database uniqueness/locking, so a retry does not create a second IBSng account.
8. Failures are surfaced through a user-friendly message and a traceable attempt record; raw credentials and sensitive remote response content must not be exposed.

## Implementation notes
- Keep this capability in admin-facing modules and reuse the existing admin authentication and CSRF protections.
- Never treat a successful provisioning result as proof of payment.
- Never let a customer-facing checkout request trigger this manual test path.
- Keep the IBSng group name exact as manually configured in the store.
- The existing `admin/orders.php` sales report is not itself the order-management workflow; the management UI should be a separate module or an explicitly linked detail action.

## Verification checklist
- Admin authorization and CSRF validation.
- Valid order and product mapping required.
- Duplicate click/retry is safe.
- Provisioning success creates/updates subscription and attempt records.
- Provisioning failure remains visible and retryable without falsely marking payment paid.
- No Telegram bot logic.
- No changes to `main`.
