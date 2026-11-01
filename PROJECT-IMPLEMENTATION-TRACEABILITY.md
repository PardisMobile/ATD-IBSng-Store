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

## IBSng server-admin error audit carried forward from main

### IBSNG-ERR-001 — Admin server last_error lifecycle

**Status: OPEN — incomplete audit; no completion claim.**

- **Observed Store behavior:** admin/ibsng-servers.php maps exceptions from add/update/test actions to a generic safe message. For the test action, it persists that generic message in ibsng_servers.last_error. A successful test clears last_error; add/update paths also clear it after success.
- **Known gap:** The same catch covers failures that may not be IBSng connectivity failures, including local database write failures after a successful remote connection test. The UI then describes the issue as an IBSng connection failure, which misclassifies the cause. Add/update failures are not consistently persisted to last_error, while test failures are. The current audit has not established the intended complete lifecycle for stale errors, validation failures, local DB errors, or success after prior failure.
- **Risk:** A generic label can mislead the administrator and a persisted last_error may not describe the latest state accurately.
- **Required next work:** Trace each action's order of operations and DB effects; define distinct internal categories (input, IBSng transport, IBSng authentication, unexpected IBSng response, local DB/persistence); map them to safe UI text; decide when last_error is set/cleared and whether it stores a safe display message or diagnostic code; add tests for success/failure and DB error paths.
- **Files in scope:** admin/ibsng-servers.php, app/IBSngClient.php, message catalog only if user-facing wording needs centralization, related tests and this trace file.
- **No unrelated edits** until this issue is resolved or deliberately paused.

### 2026-11-01 — Complete decision documentation reconciliation before main merge

- Incorporated the final main-branch decision for the IBSng server-admin error boundary into the Context document; feature-branch requirements and intentional removals remain preserved.
- Carried forward the main-branch `IBSNG-ERR-001` audit finding into this traceability record. This is an open audit item, not a claim that the implementation is complete or verified.
- This documentation reconciliation does not claim PHP lint, automated tests, or live IBSng/cPanel validation.


### IBSNG-ERR-003 — Preserve complete response on legacy IBSng timeout

**Status: FIXED (request capture/decision logic) / NOT VERIFIED (automated and live runtime).**

- Reference: RouteBox's IBSng request helper retains a complete HTTP response when legacy IBSng keeps the HTTP/1.0 connection open and cURL times out after the response has arrived.
- Store's prior `requestRaw()` used `curl_exec()` return data as a single string and threw whenever it returned `false`, discarding any response bytes received before the timeout.
- Change on `main`: `requestRaw()` now captures headers and body independently using cURL callbacks. A timeout is accepted only when the HTTP status is 2xx/3xx, an explicit `Content-Length` is present, and the captured body length exactly matches it. Connect/transport failures, incomplete bodies, missing length, and unsuccessful HTTP status remain failures.
- This does not retry a mutating request. It only preserves an already complete response, reducing the risk that the caller treats a successful remote mutation as failed and repeats it.
- Validation required: PHP lint, regression tests for full vs partial timeout response classification, existing lookup/router tests, and a live A1.24 test. Do not mark VERIFIED until evidence is recorded.
- Scope note: the independent `testConnection()` authentication probe remains unchanged; this fix addresses operation requests through `requestRaw()`.


### UI-I18N-IBSNG-001 — Admin IBSng user-management copy

**Status: FIXED (translation key alignment) / NOT VERIFIED (browser/runtime).**

- The Persian page copy accurately states that renewal is not available on this page while group changes are handled through the Edit User form.
- The English translation dictionary still contained an older sentence claiming both renewal and group changes were unavailable. Because the dictionary key did not match the current rendered copy, the language toggle could leave this sentence untranslated.
- Updated the exact translation pair to match the current UI behavior; no IBSng operation logic was changed.
- Validation required: inspect the page in both locales and run PHP lint/UI regression checks.


### IBSNG-RENEW-001 — Store renewal parity with RouteBox reference branch

**Status: IN PROGRESS — pure policy, renewal ledger schema, and read-only verified account-state parser implemented; remote attribute update, admin flow, and upgrade migration remain open.**

- **Exact behavioral reference:** `PardisMobile/routebox-telegram-bot`, branch `atd-panel-ibsng-renewal`. The reference is this branch, not the IBSng A1.24 source archive and not the RouteBox default branch.
- **Store baseline:** `admin/ibsng-users.php` currently supports lookup, credentials, group information, create, change-group, and delete. Its UI explicitly states that renewal is not available. Store schema currently has no dedicated IBSng renewal policy/ledger/settings tables.
- **Reference behavior found:** `IBSngService::renewByUsername()` reads verified account state from the Web Panel and delegates to `IBSngRenewal`. The renewal policy uses plan-specific IBSng validity days, first/repeat gift settings, and a durable per-user operation ledger/idempotency key. Expired accounts reset First Login and receive validity + gift from zero; active accounts retain remaining validity and add validity + gift. Absolute Expiration is intentionally untouched.
- **Store-specific adaptation required:** do not copy RouteBox tables/classes blindly. Store uses its own `IBSngClient`, PDO/MySQL schema, server/group mapping, and admin page. Reuse the behavior contract, adapt persistence and UI to Store conventions, and keep all edits on `main`.
- **Required sequence:** (1) add/test pure renewal policy; (2) trace and implement the required A1.24 client state-read and edit-user attribute submission contract against the RouteBox branch; (3) add a backward-compatible Store schema/migration path for validity, gift settings and renewal ledger; (4) wire admin username + mapped plan renewal with CSRF and authorization; (5) add bilingual messages and explicit unresolved-timeout handling; (6) add deterministic tests and run GitHub Actions PHP lint/test workflow.
- **Safety requirements:** do not retry an uncertain remote mutation automatically; never alter Absolute Expiration; ensure repeat/failed/pending operations cannot issue duplicate grants; do not report success until the Web Panel response is verified; preserve existing Store operations and avoid introducing Telegram/Worker behavior.
- **Initial code delivered on main:** `app/IBSngRenewalPolicy.php` implements the isolated duration policy; `tests/ibsng-renewal-policy-test.php` covers expired, active, zero-gift and invalid-value cases.
- **Validation evidence:** PHP syntax checks and the policy test script passed in the available PHP 8.4 CLI. This is limited to the new pure policy helper; it does not validate HTTP requests, database persistence, the admin workflow, GitHub Actions, or a live IBSng A1.24 server.

