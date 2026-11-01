# Project Context Implementation Traceability

> This file tracks implementation and validation evidence against `PROJECT-CONTEXT.md`. Requirements and architecture decisions remain in the Context file; do not append progress notes there.

## Working rules

- Use IBSng A1.24 and the designated RouteBox source as behavioral references; do not invent semantics.
- Trace each change through the reference, Store code/call sites, persistence effects, runtime behavior, tests, and user-facing feedback.
- Keep changes narrow. Do not refactor adjacent features without need.
- `FIXED` means code changed; `VERIFIED` requires relevant automated or runtime validation. State validation gaps explicitly.

## Checkout and message catalog

### UI-MSG-001 — Centralized bilingual checkout messages

**Status: FIXED (code) / NOT VERIFIED (runtime and automated tests).**

- `app/messages.php` is the stable Persian/English customer-facing message catalog.
- Checkout validation and feedback now resolve through catalog keys instead of scattering literal messages through `checkout.php`.
- Catalog includes validation, unavailable product/server, pending-payment/order feedback, username conflict, and safe lookup-failure messages.
- Raw exception text and remote IBSng response content must not be shown directly to customers.
- Relevant call sites: `checkout.php`; catalog resolver: `app_message()`.
- Validation still required: PHP lint, catalog key coverage, checkout regression tests, and tests distinguishing a confirmed username conflict from transport/authentication/unexpected-response failures.

### UI-MSG-002 — Centralized admin login feedback

**Status: FIXED (code) / NOT VERIFIED (runtime and automated tests).**

- `admin/login.php` uses the shared catalog for CSRF, throttling, invalid credentials, and unavailable-login feedback.
- The Persian and English message keys are maintained in `app/messages.php`.
- This is a presentation/message consistency change; it does not alter the authentication policy or throttle rules.
- Validation still required: PHP lint and login flow tests for each message path.

### IBSNG-CONN-001 — RouteBox-aligned login test classification

**Status: FIXED (code) / NOT VERIFIED (runtime).**

- Reference: RouteBox IBSng client login behavior; for IBSng A1.24, the no-follow redirect after POST to `/admin/` is used as the successful login signal.
- In `app/IBSngClient.php::testConnection()`, an arbitrary HTTP 200 is no longer classified as invalid admin credentials. A detected login form is the authentication-failure signal; an unexpected response is reported separately.
- Scope is limited to classifying the connection-test response. No IBSng API, new endpoint, or new operation flow is introduced.
- Validation gap: PHP lint and a live connection test against the supported IBSng reference environment have not been run here. Do not label this VERIFIED without evidence.

## Change log

### 2026-11-01 — Transfer selected main-branch message and connection-test changes

- Transferred centralized checkout messages and typed username-conflict mapping to `feat/router-safe-dispatch`.
- Transferred centralized admin-login feedback.
- Transferred the bounded RouteBox-aligned IBSng login-test classification change.
- The separate admin IBSng server error lifecycle/display change was intentionally excluded from this transfer, as requested.
- No live IBSng or cPanel runtime validation is claimed.

## Additional open audit items carried forward from main (selected scope)

### IBSNG-ERR-002 — Checkout username lookup error classification

**Status: OPEN — prior narrow change exists; end-to-end contract still unverified.**

- **Current behavior:** checkout.php treats UsernameAlreadyExistsException as username_taken; other exceptions become username_available_check_failed.
- **Prior change:** Commit 9928c82da604f1618785cc1d5b58acb69bfc2d1e introduced the typed conflict mapping.
- **Remaining trace:** Confirm that the IBSng client raises the typed exception only for a confirmed existing username, and that transport/authentication/unexpected HTML failures never become an “already exists” result. Trace idempotency and order creation sequencing. Add meaningful tests before marking verified.

### IBSNG-ERR-003 — Preserve response body on legacy IBSng timeout

**Status: OPEN — reference behavior identified; Store behavior needs implementation-level comparison and tests.**

- **Reference behavior:** RouteBox's IBSng request helper preserves a complete 2xx/3xx response body if libcurl reports CURLE_OPERATION_TIMEDOUT after receiving the response, due to the legacy IBSng Apache connection remaining open.
- **Risk to check:** Store's requestRaw() may discard a response on cURL failure. For mutating actions, losing a success response can make a retry repeat an operation (e.g., duplicate account creation).
- **Required next work:** Inspect the full Store requestRaw() implementation and operation callers. Establish what response bytes/status/headers are retained on timeout, distinguish connect timeout from post-response timeout, and ensure retry policy does not blindly replay a mutating request. Implement only after contract and tests are clear.

### DOC-TRACE-001 — Keep requirement context separate from implementation trace

**Status: APPLIED (documentation structure) / NOT VERIFIED (full documentation audit).**

- `PROJECT-CONTEXT.md` remains the source for product requirements, decisions, architecture, and reference rules.
- `PROJECT-IMPLEMENTATION-TRACEABILITY.md` records implementation state, audits, deviations, and validation evidence.
- Feature-specific router/subfolder and installer-recovery requirements remain preserved in the Context document. The selected message-catalog, typed username-conflict, login-classification, and exact RouteBox reference records were integrated without importing option D's IBSng server-admin error lifecycle.

## Change log

### 2026-11-01 — Reconcile selected main documentation into feature branch

- Synced the exact IBSng reference branch and selected message catalog, admin login, typed checkout conflict, and RouteBox login classification documentation from main.
- Clarified Test Mode as payment Sandbox only while retaining feature-branch safeguards around real IBSng operations and safe test execution.
- Preserved feature-specific router-safe dispatch and installer recovery records.
- Explicitly deferred the IBSng server-admin error lifecycle (option D) for separate review against the user's documented rules.
- No runtime, PHP lint, or CI validation is claimed by this documentation merge.
