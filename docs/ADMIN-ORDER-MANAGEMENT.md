# Admin Order Management and Feature Boundaries

> **Correction:** This document previously mixed unrelated store capabilities and described an unverified admin provisioning workflow. That description was misleading and is withdrawn. Do not use the former workflow/checklist as requirements or implementation guidance.

## Three distinct features

The project has three separate concepts. They must not be merged in documentation, navigation, or implementation:

1. **Store Test** — a store-level test capability. Its exact scope and expected behavior must be taken from the original project requirements; it is not the IBSng test tools and is not the sales report. This document does not define or invent its workflow.
2. **IBSng Test** — tools that test the IBSng integration itself (for example, connectivity or IBSng-side operations). These belong to the IBSng integration/admin tools, not the store test feature.
3. **Sales Report** — reporting on sales/orders. It is a reporting feature and does not define, replace, or own either test capability.

## Order/reporting boundary

`admin/orders.php` is identified as the sales report. Do not treat it as the Store Test feature or as an order-provisioning workflow based solely on its name or current presence.

Any future order-management or manual provisioning workflow requires its own explicit requirement and traceability; it must not be inferred from the sales report or from either test feature.

## Existing project constraints

- Reuse the established IBSng HTTP/HTML integration; do not introduce an IBSng API client or rewrite validated IBSng behavior.
- Keep Store Test, IBSng Test, and Sales Report separate.
- Payment gateways are modular and are a separate concern from the test feature definitions.
- No Telegram bot logic in the Store.
- Work on `feat/router-safe-dispatch`; do not modify `main`.

## Documentation status

The exact Store Test behavior is intentionally left unspecified here until it is verified against the original requirement/source discussion. Do not implement assumptions such as creating real IBSng users, simulating checkout, changing payment state, or provisioning orders under the label “Store Test” without that verification.
