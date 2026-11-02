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

**Status: IN PROGRESS — pure policy, ledger schema + upgrade SQL, verified read-only account-state parser, and low-level renewal attribute submission implemented; durable orchestration and admin workflow remain open.**

- **Exact behavioral reference:** `PardisMobile/routebox-telegram-bot`, branch `atd-panel-ibsng-renewal`. The reference is this branch, not the IBSng A1.24 source archive and not the RouteBox default branch.
- **Store baseline:** `admin/ibsng-users.php` currently supports lookup, credentials, group information, create, change-group, and delete. Its UI explicitly states that renewal is not available. Store schema currently has no dedicated IBSng renewal policy/ledger/settings tables.
- **Reference behavior found:** `IBSngService::renewByUsername()` reads verified account state from the Web Panel and delegates to `IBSngRenewal`. The renewal policy uses plan-specific IBSng validity days, first/repeat gift settings, and a durable per-user operation ledger/idempotency key. Expired accounts reset First Login and receive validity + gift from zero; active accounts retain remaining validity and add validity + gift. Absolute Expiration is intentionally untouched.
- **Store-specific adaptation required:** do not copy RouteBox tables/classes blindly. Store uses its own `IBSngClient`, PDO/MySQL schema, server/group mapping, and admin page. Reuse the behavior contract, adapt persistence and UI to Store conventions, and keep all edits on `main`.
- **Required sequence:** (1) add/test pure renewal policy; (2) trace and implement the required A1.24 client state-read and edit-user attribute submission contract against the RouteBox branch; (3) add a backward-compatible Store schema/migration path for validity, gift settings and renewal ledger; (4) wire admin username + mapped plan renewal with CSRF and authorization; (5) add bilingual messages and explicit unresolved-timeout handling; (6) add deterministic tests and run GitHub Actions PHP lint/test workflow.
- **Safety requirements:** do not retry an uncertain remote mutation automatically; never alter Absolute Expiration; ensure repeat/failed/pending operations cannot issue duplicate grants; do not report success until the Web Panel response is verified; preserve existing Store operations and avoid introducing Telegram/Worker behavior.
- **Initial code delivered on main:** `app/IBSngRenewalPolicy.php` implements the isolated duration policy; `tests/ibsng-renewal-policy-test.php` covers expired, active, zero-gift and invalid-value cases.

- **New groundwork on main:** `app/IBSngRenewalState.php` extracts `first_login`, `rel_exp`, and the displayed `Nearest Expiration Date` using the selected RouteBox A1.24 parsing approach. `IBSngClient::lookupUser()` now additionally returns the already-read HTML for that parser; the public `getRenewalState()` delegates to it. Deterministic normalization tests are in `tests/ibsng-renewal-state-test.php`.
- **Low-level update added:** `IBSngClient::submitRenewalAttributes()` sends only `rel_exp` and optionally `first_login` through the native Edit User form, validates the group ID, and requires the expected user-info redirect before it returns success. It does not touch Absolute Expiration and is not yet exposed to the admin page.
- **Schema upgrade artifact:** `database/migrations/001_ibsng_renewal_ledger.sql` adds the renewal ledger for existing installations. This migration is not yet wired into an automatic installer/migration runner.
- **Validation status:** source changes have been committed, but the new client/state parser has not yet been PHP-linted or run against an actual IBSng A1.24 Web Panel. Do not enable renewals until the orchestration, CSRF-protected admin flow, and runtime validation are complete.

- **Validation evidence:** PHP syntax checks and the policy test script passed in the available PHP 8.4 CLI. This is limited to the new pure policy helper; it does not validate HTTP requests, database persistence, the admin workflow, GitHub Actions, or a live IBSng A1.24 server.



### 2026-11-02 — Renewal orchestration groundwork and CI audit

- Added `app/IBSngRenewalService.php` as the central single-attempt coordinator: serialize renewal attempts per server/account with a MySQL advisory lock; read verified account state; calculate policy; reserve the durable ledger before remote mutation; and never automatically replay a mutation whose outcome is uncertain.
- Added an account-level unresolved-ledger guard. A different idempotency key cannot initiate another grant while an earlier operation is pending, failed, or marked `needs_review`.
- Corrected a PHP syntax error in the native group-ID parser in `app/IBSngClient.php`, which had prevented CI from reaching tests.
- Investigated the next CI failure: the router base-path test exposed a root `SCRIPT_NAME=/router.php` case that incorrectly treated a subdirectory deployment as root. Updated `store_detect_base_url()` to fall back to route-suffix inference in that case.
- Expanded the PHP workflow to execute the existing IBSng renewal policy, state parser, and ledger contract tests.
- Validation update: GitHub Actions run 149 passed PHP lint plus router, IBSng lookup, renewal policy, renewal state, and renewal ledger contract tests on commit `1bc80ca`. This is automated source-level validation only. No live IBSng A1.24 request or end-to-end admin renewal flow has been run. The admin UI remains intentionally disabled until Store-specific validity/gift configuration is added and connected.


### 2026-11-02 — Store renewal policy configuration and admin wiring

- Added explicit nullable `products.ibsng_validity_days`; the value is separate from generic `duration_seconds` and must be configured by the administrator. Fresh schema and upgrade migration `002_ibsng_renewal_policy.sql` now include it.
- Added configurable first-renewal and repeat-renewal gift settings in `app_settings`, with reference defaults of 1 and 5 days. The product/admin settings UI exposes these values and validates non-negative integer inputs.
- Updated product management to require and persist an explicit IBSng renewal duration; existing generic durations are not copied or guessed.
- Connected the admin IBSng user page's renewal form to the central service. It only lists active mapped products with explicit IBSng validity; it passes the exact mapped group and request idempotency key. Existing CSRF/admin authorization remains in force.
- The service chooses the gift based on completed ledger entries while holding the per-account advisory lock. An unresolved prior ledger operation blocks another renewal even with a different key.
- Added English translations for the renewal controls and confirmation copy.
- Validation still required after this UI/configuration slice: latest GitHub Actions run; a live A1.24 test; migration execution on an existing database; and browser-level verification. This work does not claim a successful real renewal.


### Validation update — admin renewal slice

- GitHub Actions run 168 on commit `cf35e4f` passed PHP lint and all configured router, IBSng lookup, renewal policy, renewal state, and renewal ledger contract tests.
- The admin renewal form now verifies the selected plan maps to the same server and requires the account's parsed IBSng group to exactly match the chosen renewal group. A mismatch stops before any remote mutation.
- The renewal result is confirmed by the native Web Panel redirect check in `IBSngClient::submitRenewalAttributes()`; the code does not claim a live A1.24 test. Manual database migration and live-instance/browser validation remain outstanding.


### Upgrade path for existing Store installations

- Added authenticated admin route `admin/ibsng-renewal-migrate.php`, linked from the admin dashboard. It checks for the renewal validity column, applies the missing column only when absent, and inserts gift defaults without overwriting administrator settings.
- Existing installations are routed to this upgrade page before product administration can run renewal-specific SQL. The IBSng user page keeps its existing lookup/create/group/delete actions available but does not expose renewal until the schema is ready.
- GitHub Actions run 173 on commit `29fefd6` passed PHP lint and all configured automated tests after the migration route and legacy-schema handling were added.
- The one-click upgrade path has not yet been exercised against a real cPanel/MySQL database; verify it on a backed-up staging database before production. Live IBSng A1.24 renewal remains unverified.


### Final CI update for current renewal implementation

- GitHub Actions run 177 on commit `29aad36` passed PHP lint and all configured automated checks.
- Idempotent completed-ledger replay is now bound to the same server, username, and selected product; malformed/missing historical result JSON is handled safely.
- Ledger error-message truncation now has a fallback when PHP mbstring is unavailable.
- The authenticated one-click schema-upgrade page is allowlisted and reachable from the admin dashboard. Existing product administration is routed through it if the new column is absent.
- Still not verified: applying the schema change on a real database, browser behavior, and remote mutation against a live IBSng A1.24 Web Panel. The current CI is not evidence of a live renewal.


### Follow-up audit — upgrade completeness

- Corrected a gap found during continued audit: the earlier one-click upgrade verified only the product validity column and gift defaults, but did not guarantee the durable `ibsng_renewals` table existed on legacy databases.
- The upgrade page now creates the ledger table idempotently with the same columns/statuses/indexes as migration 001, and readiness now requires both the product validity column and the ledger table.
- The IBSng renewal admin page checks both schema components before enabling the renewal action. A missing ledger no longer appears as a ready renewal configuration.
- Added a static contract assertion for this upgrade/readiness behavior. CI for these latest commits is pending; no real database or live IBSng test has been performed.


### 2026-11-02 — Customer account access and recovery requirements audit

**Status: REQUIREMENTS CLARIFIED / IMPLEMENTATION NOT YET VERIFIED**

The following requirements were clarified during the current review and must be treated as scope, not as completed features:

- Customer access is a limited self-service area, not an administrative panel or broad membership system.
- A customer must be able to view only their own IBSng account information, including username, plan/group display, expiry date, and account status/details available from the supported IBSng Web Panel.
- The customer may change the password of that same IBSng account and renew that same account, subject to the Store's mapped plan and renewal policy. No account enumeration or access to another customer's account is permitted.
- Customer access uses the IBSng username and password plus a generated four-digit verification/security code. The code is an additional verification factor; it does not replace the IBSng credential check.
- Purchase/order recovery is performed through the email address attached to the purchase. A verification code must be sent to that address and ownership verified before revealing or granting access to the associated purchase/account information.
- New purchase checkout remains available without requiring customer registration/login.
- Verification codes require expiration, attempt limits, resend throttling, and non-disclosing responses to avoid account/order enumeration. Recovery must not expose stored IBSng passwords in plaintext or bypass IBSng authentication.

Audit observations:
- The current order schema stores buyer email and an encrypted IBSng password, but that alone does not establish a safe customer portal or email recovery flow.
- The current admin settings page is presently focused on support contact fields and renewal-gift days; it does not by itself demonstrate a complete general site-settings interface.
- The installer administrator-password minimum was corrected from 12 to 8 characters in commit d25e34f on main.

Implementation sequence:
1. Trace order/subscription ownership and the IBSng lookup/change-password/renew methods, then document the exact authorization boundary.
2. Design the four-digit verification and email-recovery persistence, expiry, attempt/rate limits, and email delivery against existing app conventions.
3. Implement the limited customer account view and owner-only password-change/renewal actions, with CSRF and server-side ownership checks.
4. Add email recovery without revealing whether an email has an order; require successful verification before displaying linked purchases.
5. Add bilingual customer-facing messages and deterministic tests for authorization, expiry, invalid/used codes, throttling, and recovery enumeration resistance.
6. Expand admin site settings only against the previously recorded product requirements; avoid duplicating existing server, gateway, product, or renewal configuration screens.

No customer portal, four-digit code, email recovery, or broad site-settings completion is claimed by this audit entry. PHP lint, automated tests, live IBSng A1.24 checks, and cPanel mail-delivery validation remain required before marking these requirements verified.


## 2026-11-02 — Admin capability audit against project requirements

This is a source-level inventory of the current `main` branch, not a claim of runtime verification. “Present” means an implementation is visible in the inspected source; it does not imply cPanel/browser/IBSng production validation.

### Present in source (runtime validation still separate)

- Admin authentication/login/logout and CSRF-protected state-changing forms.
- Dashboard with actual database summary figures and navigation to the current admin areas.
- IBSng server management: add (connection test before saving), edit credentials/settings, retest, activate/deactivate, choose default server, and guarded deletion where known dependent records exist.
- Manual IBSng group/plan mapping per server: add/edit/activate/deactivate/delete, with product mapping dependency checks. Automatic group-list fetching is intentionally not a requirement.
- IBSng user management actions visible in the current implementation: lookup, create, credentials, group information, change group, delete, and renewal flow groundwork/configuration. Renewal is backed by the policy/service/ledger path and CI records, but live A1.24 validation and operational staging verification remain necessary.
- Product management: add/edit, activate/deactivate, guarded delete, price/currency, duration, quota, sort order, and server/group mapping; IBSng validity days are explicitly configured rather than inferred.
- Orders reporting: paid/pending counts and aggregate sales by product snapshot, including active/partial counts.
- Support contact settings: optional Telegram contact, WhatsApp target, and custom HTTPS link.
- Renewal gift settings: first/repeat renewal day gifts.

### Requirements not evidenced as complete / gaps to track

- General site settings: editable site title and broader storefront settings are not provided by the inspected `admin/settings.php`; it currently handles support contacts and renewal gifts.
- Payment management: modular Iranian payment gateway configuration, credentials, enable/disable, sandbox/test mode, callback verification, and operational payment reconciliation are not established as complete by the inspected admin pages.
- Orders as an operational console: current `admin/orders.php` is an aggregate report, not a full order detail/management screen with payment/provisioning state, retry controls, refund status, manual refund record, and per-target outcomes.
- Multi-server provisioning policy: ability to select one vs both servers for create/renew, per-destination result and safe retry, and clear partial-completion handling require an end-to-end audit beyond the currently inspected pages.
- Discount/coupon administration and discount calculation/usage limits are not implemented/evidenced as complete.
- Product Gift Days / Relative Days fields and their snapshot + provisioning semantics are not evidenced as complete; do not conflate with the separate global renewal gift-day settings.
- Customer email recovery and self-service portal controls are not complete; they remain a distinct customer-facing scope, not an admin panel feature.
- Editable UI text/message editor, comprehensive mail provider/templates, guide/instructions editor with live preview, and a complete admin activity/audit trail are requirements or ideas recorded in context, but not verified as implemented.
- Site-wide settings for independently enabling/requiring email and phone fields at checkout are not evidenced in the current admin settings page.
- Admin password reset via email and invalidation of prior sessions are not verified complete.
- Test Mode must remain explicitly payment simulation only and keep test orders separated from real sales; current admin source review did not establish this end-to-end behavior.

### Audit guardrails

- Preserve the existing admin navigation/sidebar structure unless the user explicitly requests a change.
- Keep RouteBox as the behavioral reference for IBSng admin operations; keep Telegram bot/Worker and unrelated bot/payment features out of the IBSng scope.
- For each item, report source implementation, automated test evidence, and live runtime evidence separately.


## 2026-11-02 — Customer portal and checkout source trace

Targeted source review of `checkout.php`, `database/schema.sql`, `app/messages.php`, and GitHub code search on `main`:

- Checkout currently accepts an optional email and warns that email-based recovery is unavailable if no email is entered. The user requirement calls for email ownership verification to recover orders; this is not yet implemented.
- Checkout stores order email, buyer contact, chosen IBSng username, and an encrypted account password; it snapshots product name, duration, price, and currency. It currently creates a pending-payment order only. It does not initiate a gateway, verify a callback, provision the account, or send the promised purchase email.
- Schema includes orders, payment transaction, subscriptions, provisioning attempts, and renewal ledger tables. Having these tables does not itself establish completed customer account authorization, email verification, actual gateway integration, or live IBSng operations.
- Message catalog has recovery-related copy keys, but repository code search did not find a customer login using IBSng username/password plus a generated four-digit code, a customer password-change portal, an email OTP recovery flow, or a customer order-history interface.
- Recovery must verify email ownership before returning *all* relevant orders and account details, not use a public order ID as authorization. OTP/token expiry, throttling, attempt limits, generic responses, session/CSRF protections, and owner-bound authorization are required.
- The independent admin settings for whether email/phone are enabled or required at checkout are not present in the currently inspected support-settings page. Keep these distinct from the current optional checkout email behavior.

Status: source-audited; these customer-facing workflows remain pending implementation and testing. No runtime behavior is claimed.


## 2026-11-02 — Customer feature implementation dependency audit

A follow-up inspection of the current schema and source was performed before beginning customer-facing implementation:

- Existing schema provides order ownership-contact fields (buyer_email, buyer_phone), order state/payment state, payment transaction records, subscriptions, and provisioning-attempt records. These structures are useful groundwork but do not currently provide a verified customer identity or email-OTP challenge record.
- Current checkout still treats phone as required and email as optional in its own validation; no independent persisted checkout visibility/required settings were found in the inspected route.
- Repository code search did not find an email delivery abstraction/configuration or SMTP send implementation, nor customer OTP login/recovery routes. Therefore a customer email-recovery feature cannot safely be completed by only adding a page; it needs a shared mail service/configuration, challenge persistence and expiry/attempt controls, plus ownership-scoped order retrieval.
- The existing IBSng client is an admin-panel HTTP/HTML client. Customer panel operations must be designed separately from admin authentication and must not reuse administrator credentials/session.
- Existing schema and current order report are not evidence of a complete customer purchase history: admin/orders.php is a summary report grouped by product/currency, not customer-facing order detail.
- Implementation sequencing recommendation: (1) define and migrate the customer access/recovery persistence model and shared mail contract, (2) build and test email ownership verification + order recovery, (3) build the owner-bound order history/details UI, (4) connect account panel actions only after confirming IBSng user lookup, credential handling, and renewal contracts. Preserve the no-registration checkout and avoid claiming live email/IBSng success without runtime validation.

Status: dependency audit only. No customer workflow or mail delivery was implemented by this audit; no PHP lint/runtime execution was available in this environment.


## 2026-11-02 — Customer store renewal order-flow audit

**Status: SOURCE TRACE COMPLETE FOR THE CURRENT ENTRY POINTS / CUSTOMER RENEWAL FLOW NOT IMPLEMENTED.**

Reviewed the current `main` source in `checkout.php`, `admin/ibsng-users.php`, `app/IBSngRenewalService.php`, `app/IBSngRenewalCoordinator.php`, and `database/schema.sql`.

- `checkout.php` is currently the new-account checkout only. It validates a customer-selected username, checks availability against the mapped IBSng server, and creates an order with `payment_status='pending'` and `order_status='pending_payment'`. The order stores product name/duration/price/currency snapshots and an encrypted password. It does not currently create a renewal order or initiate a payment gateway.
- `database/schema.sql` already defines `payment_transactions`, including provider/reference, status, callback ID, and verification timestamp. These tables are persistence groundwork only; their existence does not mean a gateway integration or verified callback exists.
- The inspected repository code search did not identify an implemented customer payment initiation/callback handler that verifies the gateway and idempotently transitions an order to paid. Do not mark an order paid based only on a browser return or unverified callback parameters.
- `admin/ibsng-users.php` has a separate admin-initiated renewal action. It chooses a mapped active product for the selected server and calls the central `IBSngRenewalService`; this is not a customer renewal order and must not be treated as such.
- The central service already serializes per-account operations, uses a durable `ibsng_renewals` ledger, checks for unresolved attempts, and marks uncertain remote outcomes `needs_review`. Its idempotency key currently identifies the renewal-service attempt; it is not yet wired to a paid customer renewal order.
- No customer order-to-renewal provisioning/callback path was established by this source review. Consequently, the intended sequence (renewal order with immutable plan snapshot + idempotency key → pending payment → verified idempotent callback → exactly-once service dispatch → needs-review on uncertain IBSng response) remains an implementation task.

**Required implementation boundary:** introduce the customer renewal order path and payment-provider contract using the existing Store order/payment/provisioning architecture. The payment callback must verify amount, currency, order identity, provider reference and provider result; persist the transaction and paid transition atomically/idempotently; and dispatch renewal through the existing central service using an order-bound idempotency key. Repeated callbacks must return the prior result without another IBSng mutation. If the remote mutation result is uncertain, persist a review-required state and prohibit automatic replay.

**Not changed in this audit:** no UI/sidebar changes, no direct IBSng user-management scope, no customer payment status changes, and no claim of live gateway or IBSng verification.


### Detailed renewal-service contract audit

- `IBSngRenewalService::renew()` accepts a caller-supplied idempotency key, serializes concurrent attempts with a MySQL advisory lock scoped to server + normalized username, resolves a completed prior ledger entry, and blocks when an unresolved ledger entry exists.
- Before the IBSng mutation it reads remote account state and reserves the `ibsng_renewals` row. A remote exception after reservation is handled as uncertain and attempts to mark that row `needs_review`; the service has no automatic remote retry.
- `IBSngRenewalCoordinator::resolveExisting()` reuses only completed results and verifies the key's server, username, and product binding. A pending/failed/needs_review record is a stop condition.
- This is suitable groundwork for customer order dispatch, but it does **not** implement payment callback idempotency or order-level claiming. Those must be added separately: lock/claim the paid renewal order and transaction, then call the service once with a deterministic key derived from the order's immutable identity.
- Recovery nuance: payment callback replay should acknowledge the already-verified transaction and never start a second renewal dispatch. A separate reconciler/operator action must inspect the renewal ledger/IBSng state before resolving a `needs_review` operation; simply resetting it to pending or creating a fresh key is unsafe.
- Existing `admin/orders.php` is only a paid sales aggregate report. It is not an order processing console and currently has no renewal-order handling.

**Provider dependency:** repository source currently exposes no concrete gateway adapter/configuration or callback endpoint. A real callback cannot be safely implemented without the selected provider's documented initiation and server-side verification contract. Keep provider logic behind an adapter rather than fabricating a generic “verified” response or accepting browser-return parameters as proof of payment.


### PAY-CHAIN-001 — Payment amount/currency contract and callback orchestration

**Status: OPEN — adapter exists; central payment initiation, callback persistence, and provisioning idempotency are not yet implemented.**

- The order schema stores the immutable price as `orders.price_minor_snapshot` with `orders.currency`; the payment ledger stores `payment_transactions.amount_minor` and `payment_transactions.currency`. The intended invariant is exact equality between those snapshots at transaction creation and callback validation. No implicit IRR/IRT/USDT conversion is permitted in the central flow.
- `app/Payments/PaymentGateway.php` defines the adapter boundary. The Zarinpal adapter is now located at `app/Payments/zarinpal.php`; the implementation class remains `ZarinpalGateway`.
- Checkout currently creates a pending order only. It does not persist a payment transaction, invoke the adapter, or redirect to the gateway. The explicit route map has no payment callback route.
- Required implementation sequence: persist a transaction before redirect; bind its provider reference/Authority to the transaction; callback loads transaction/order from storage; validate stored state, provider, authority, amount, currency, and gateway-side verification; commit payment exactly once; hand off provisioning through a durable idempotency ledger and never repeat an IBSng mutation merely because a callback is replayed.
- Payment success and IBSng provisioning outcome must remain separate states. If remote IBSng mutation has an ambiguous outcome, do not blindly replay it; mark for reconciliation/review.
- Zarinpal API currency unit/amount interpretation and code-101 verification semantics require current authoritative documentation review and sandbox/real-provider testing before enabling live payment. No live payment validation is claimed.
- Validation still required: transaction/order consistency tests; forged, altered, mismatched, and repeated callbacks; callback concurrency; provider timeout and duplicate callback cases; provisioning exactly-once/reconciliation behavior; PHP lint; gateway sandbox test; live IBSng integration tests.


### 2026-11-02 — Provider-independent payment resolution groundwork

**Status: IMPLEMENTED IN SOURCE / END-TO-END PAYMENT FLOW STILL OPEN.**

- Added `app/Payments/PaymentManager.php` on `main`. It resolves a gateway from a server-side allowlist and delegates initiation/verification through the existing `PaymentGateway` contract.
- Checkout initiation resolves the configured selected provider. Callback verification resolves the provider persisted on the payment transaction, not the current global selection. This is intentional: changing the selected gateway must not invalidate or redirect an already-started transaction to another provider.
- Provider settings are passed as trusted, provider-keyed server-side settings. The manager does not accept credentials from browser input.
- The manager currently allows only the existing Zarinpal adapter. This does **not** make Zarinpal mandatory: a future provider can be added as a separate adapter implementing `PaymentGateway` plus one entry in the manager's explicit allowlist. Admin selection/settings persistence, transaction orchestration, callback route, atomic paid transition, provisioning dispatch, tests, and live gateway verification remain incomplete.
- No PHP runtime/lint or end-to-end payment test was executed in this environment. Do not describe payment as enabled or production-ready based on this resolver alone.


### 2026-11-02 — Simplify gateway inventory

**Status: SIMPLIFIED IN SOURCE / NOT RUNTIME-VERIFIED.**

- The separate `PaymentGatewayRegistry.php` was removed as unnecessary indirection for the current gateway inventory.
- `PaymentManager.php` contains the explicit allowlist and dispatches to adapter files; `PaymentGateway.php` remains the shared interface and each provider remains an independent adapter.
- Adding a provider requires its adapter file and one allowlist entry in `PaymentManager.php`. No separate registry file or filesystem-wide auto-discovery is used.
- This keeps the adapter modules independent without multiplying manager/registry files. Payment settings UI, checkout initiation, callback route, transaction persistence, idempotent paid transition, provisioning integration, automated tests, PHP lint, and live/sandbox validation remain open.
- Simplification commits on `main`: manager `c999ea62ec4077025c4ece90f5c4ebefd4ce8ed3`; registry removal `9d917837f98bcc6d19af92f7827b7f451677c392`. Earlier registry commits remain in history but their implementation was superseded.


### 2026-11-02 — Pricing policy foundation (display unit + independent USDT catalog price)

**Status: SOURCE CHANGES ON `main`; NOT YET RUNTIME-VERIFIED; PAYMENT FLOW REMAINS OPEN.**

- Added `app/Pricing.php` as the shared formatter for storefront catalog, checkout summary, and admin product list.
- Store setting `price_display_unit` accepts only `IRT` (Toman) or `IRR` (Rial). Missing setting defaults to Toman, including existing installations until the setting is explicitly changed.
- Display conversion is presentation-only: IRR↔IRT uses the fixed 10:1 relation. Product base values and already-created order price/currency snapshots are not rewritten. Checkout continues to snapshot the stored base amount/currency.
- Added nullable `products.price_usdt DECIMAL(18,8)` as a separately maintained per-product crypto price; it is not calculated from an FX rate. Product admin validates positive decimal input up to eight decimal places. The public catalog does not expose this value before a crypto gateway is enabled.
- Fresh installs receive the default through `database/schema.sql`. Existing installations must back up their database and run `database/migrations/2026-11-02-pricing-policy.sql` once; this is a manual schema upgrade and is not silently run by the site.
- Still open before gateway completion: crypto gateway enablement/configuration, deciding provider-specific transaction amount/currency from the selected payment method, transaction persistence, callback verification/idempotency, order payment status transitions, and tests. Do not treat the current price UI/storage changes as payment implementation.
- No PHP lint, automated test suite, database migration, or runtime checkout test has been executed as part of this source update.


### 2026-11-02 — Conditional USDT catalog display setting

**Status: SOURCE IMPLEMENTED ON `main`; NOT RUNTIME-VERIFIED.**

- Added the explicit `crypto_gateway_enabled` store setting, defaulting to `0` on fresh installs and in the existing-install pricing migration.
- Admin settings can toggle whether the storefront displays each product's separately maintained `price_usdt`. The public catalog renders USDT only when this setting is enabled and the product has a positive USDT price; otherwise it remains hidden.
- This flag currently controls display only. It is not proof that a crypto provider is integrated, configured, or able to accept payments. Keep it disabled until a real crypto gateway adapter and its server-side verification path are implemented and tested.
- No payment amount selection, transaction initiation, callback verification, paid-state transition, or duplicate-order protection was added by this display-only change. Those remain open items under PAY-CHAIN-001.
- No PHP lint, automated test, or live checkout verification was performed.


### PAY-CHAIN-002 — Central immutable amount resolution groundwork

**Status: SOURCE IMPLEMENTED ON `main` / NOT INTEGRATED OR RUNTIME-VERIFIED.**

- Added `app/Payments/PaymentAmount.php` as a provider-independent amount resolver.
- Fiat resolution uses only the persisted order price/currency snapshot and accepts IRR/IRT; it does not apply the storefront display-unit conversion.
- Crypto resolution uses a separately persisted product `price_usdt`, parses the decimal string without floating-point arithmetic, and scales to eight decimal places for integer storage with currency `USDT`. It does not calculate USDT from an exchange rate.
- Invalid/missing amounts, unsupported currencies/methods, and out-of-range USDT values fail closed.
- This is a pure amount-selection helper only. It is not yet called by checkout, does not create a transaction, and does not enable a crypto provider. Payment initiation/callback state machine and idempotent payment processing remain open.
- No PHP lint, automated tests, gateway sandbox, or live payment verification was performed.


### 2026-11-02 — Clarify crypto display setting semantics

**Status: UI LABEL CORRECTED ON `main` / NOT RUNTIME-VERIFIED.**

- Updated the label in `admin/settings.php` so the USDT display toggle explicitly says it is a display option and does not enable crypto payment.
- This aligns the admin control with the current implementation: the setting only gates storefront price visibility; no crypto payment adapter or verified crypto callback exists.
- Commit: `ff8755c0e1b649ce12c8751f8a8404014e359ca3`.
- No runtime/browser validation was performed.


### 2026-11-02 — Zarinpal request currency is explicit

**Status: SOURCE FIX COMMITTED ON `main`; provider/runtime verification remains pending.**

- Updated `app/Payments/zarinpal.php` to send the persisted order currency (`IRR` or `IRT`) as the explicit Zarinpal request `currency` field alongside the persisted `amount_minor`.
- This removes reliance on an implicit provider currency mode at payment-request time. The adapter still does not convert Rial/Toman; the amount and currency must originate from the same immutable order/payment transaction snapshot.
- The verification request continues to use the transaction's stored amount and the matched Authority. The central callback route, transaction creation, atomic payment state transition, and provisioning dispatch are still not connected.
- Commit: `a62cb1f6ca89f2a99a5730f3210825f6ffa81d24`.
- No sandbox/live provider call or runtime test was performed by this source edit.


### 2026-11-02 — Payment transaction persistence/state-transition service

**Status: SOURCE IMPLEMENTED ON `main`; NOT YET CONNECTED TO CHECKOUT/CALLBACK OR RUNTIME-VERIFIED.**

- Added `app/Payments/PaymentTransactionService.php` to persist a payment transaction before the provider redirect, then attach the provider reference after initiation.
- Creation locks and checks the order is still awaiting payment, validates fiat amount/currency against the immutable order snapshot, and validates USDT against the separately stored product price using `PaymentAmount`.
- Creation rejects another active transaction for the same order. Provider and amount/currency are persisted before redirect; this service does not accept client-supplied provider settings.
- `confirmVerified()` requires an affirmative adapter verification result and exact match of the stored provider reference, amount, and currency. It updates the transaction and order paid states in one database transaction.
- Repeated confirmation of the same already-verified payment returns without another state transition; a conflicting replay is rejected.
- Provisioning is intentionally not dispatched here yet. Payment callback route, gateway configuration, checkout wiring, durable provisioning handoff, automated DB-backed concurrency tests, PHP lint, and sandbox/live verification remain open.
- Commits on `main`: `4e5925151e9c0723dd8a0fb23428909c3c424dd8`, `bf8ae1fcf942058dc639b9957e9f40cc89e03b93`.

### 2026-11-02 — First checkout-to-provider callback connection

**Status: SOURCE FLOW CONNECTED FOR CONFIGURED ZARINPAL; NOT YET RUNTIME OR PROVIDER-VERIFIED.**

- Added `app/Payments/PaymentFlow.php` to coordinate checkout initiation and provider callback verification using the existing manager, amount resolver, and transaction state service.
- Checkout now creates the provider transaction from the persisted order snapshot and redirects to the provider only after the transaction reference is persisted. Provider initiation errors are logged server-side and do not expose raw diagnostics to the buyer.
- Added `payment-callback.php` and its explicit router allowlist entry. Callback lookup binds provider and Authority to the persisted transaction; only the adapter's server-side verification result reaches atomic confirmation.
- Admin settings now allow selecting the configured Zarinpal gateway and storing its merchant ID. Selecting no provider leaves payment initiation disabled. The independent USDT price-display setting does not activate crypto payment.
- The callback page reports payment confirmation separately from IBSng provisioning. Provisioning dispatch, order management/reconciliation, payment retry recovery, automated DB-backed tests, PHP lint completion, and Zarinpal sandbox/live verification remain outstanding. Do not enable live checkout until CI passes and provider amount/currency semantics are confirmed against current documentation and controlled tests.

### 2026-11-02 — Payment contract tests and cancellation recovery

- Added `PaymentManagerTest.php` and wired it plus the existing `PaymentAmountTest.php` into the PHP workflow. The provider allowlist and exact fiat/USDT integer amount contract now run in CI.
- Added a guarded failed-payment transition for provider-declared cancellation so a canceled attempt can release the order's active-payment slot. The callback UI distinguishes a verified payment from a canceled/failed return; an unverified return is never marked paid.
- GitHub Actions run `41434145003` passed PHP syntax lint and the router, payment amount, payment manager, IBSng lookup, renewal policy/state, and renewal ledger contract tests.
- This is source/CI verification only: no live database checkout, Zarinpal sandbox request, or IBSng provisioning was executed. The paid-to-provisioning handoff remains the next implementation boundary.


## Payment-to-provisioning handoff (2026-11-02)

**Status: destination snapshot and durable queue code added; remote provisioning execution NOT implemented or runtime-verified.**

- Checkout persists every active server/group mapping configured for that product, including server ID, IBSng group ID, and exact group name, into `orders.server_targets_snapshot`. One destination uses `selected`; multiple product destinations use `configured_set`. Username availability is checked against each captured active destination before accepting the order. Later product/server-group mapping changes do not silently redirect the order. This source-flow change is in commit `dc381ded`.
- Fresh-install schema includes the JSON snapshot column. Existing installations require the one-time migration `database/migrations/2026-11-02-order-target-snapshot.sql`.
- After verified payment, `PaymentTransactionService::confirmVerified()` inserts idempotent `create` provisioning attempts using only captured order targets, in the same database transaction. It changes order status to `provisioning` when a queue row is inserted.
- This stage only creates the durable queue. It does not call IBSng `createUser()`, implement an admin retry/reconciliation screen, or resolve uncertain remote outcomes. Account activation is not complete until those pieces are implemented.
- Older orders whose target snapshot is NULL can still be marked paid, but this handoff cannot infer a correct destination for them; they require explicit reconciliation.
- Validation not yet performed: PHP lint, migration execution, MySQL integration test, or live IBSng mutation.


### 2026-11-02 — Per-gateway currency type setting

**Status: SOURCE CHANGE COMMITTED ON `main`; RUNTIME/PAYMENT-PROVIDER TESTING NOT YET RUN.**

- Each configured gateway now has one admin setting: `fiat` (IRR/IRT, regular product price) or `crypto` (USDT, independent `products.price_usdt`).
- `PaymentFlow::initiate()` reads that setting and resolves the amount/currency accordingly. The resulting amount and currency are persisted in the payment transaction before the provider request; the existing transaction service validates them against the corresponding persisted product/order price.
- This setting only chooses what amount/currency is sent to that gateway. It does not add or activate a gateway. A gateway configured for a currency it does not support is expected to reject the request; e.g. Zarinpal should remain `fiat`.
- The separate `crypto_gateway_enabled` flag remains storefront USDT-price display only and is not consulted for payment type selection.
- No new payment registry or additional payment module was introduced. Existing `PaymentManager`, `PaymentAmount`, `PaymentTransactionService`, and settings page were reused.
- PHP lint and end-to-end/sandbox tests have not been run for this change.
- Commits on `main`: `4025293751d2cb0e87877d623200c1bdb3497090`, `1821f88d7b0d75e984b0b559ef5f71667b632a2e`, `4d6d281be7c4fb8c38c94edd446c14d254440d06`.


### 2026-11-02 — Adapter-driven currency choices in admin

**Status: SOURCE CHANGE COMMITTED ON `main`; CI pending at time of entry.**

- The admin currency selector now reads each adapter's declared `supportedCurrencies()` values and only renders compatible choices: fiat when IRR/IRT is supported, and USDT when USDT is supported.
- The administrator still selects the configured payment mode for that gateway; the selection is not customer-selectable or auto-switched per transaction.
- Server-side compatibility validation remains in place when saving settings and initiating payment, so the UI is not treated as the security boundary.
- Documentation updated in `docs/PAYMENTS-AND-PROVISIONING.md`.
- Commit: `20af275933748c8c033a675459eb4447bf24f8bc` (admin UI), `5ce687fa4ad93739b232c934503266f7205115a7` (documentation).
- Browser/runtime validation and CI completion are pending; no provider sandbox or live payment was run.


### 2026-11-02 — Immediate provisioning dispatch after verified payment

**Status: SOURCE CHANGE COMMITTED ON `main`; CI pending at time of entry.**

- After `PaymentTransactionService::confirmVerified()` commits the verified payment and durable provisioning attempts, `PaymentFlow::handleCallback()` now makes a best-effort synchronous pass over queued create attempts for that order.
- The dispatcher still claims each attempt in the database before remote IBSng activity. Concurrent/repeated callbacks cannot claim the same attempt twice; the dispatcher converts ambiguous remote outcomes to `needs_review` and does not automatically replay them.
- A provisioning error is logged and does not reverse or misreport an already verified payment. Remaining queued/review cases stay visible in the admin provisioning page for recovery.
- This is request-time dispatch, not a cron/worker. No automatic retry or refund is introduced. The admin page copy and payment/provisioning documentation were updated to reflect its recovery role.
- Commits: `5d6bcfe87d8fdee1584310b7ac21a90b44665b03` (dispatch), `3434243315a8970f9e3d0e77cf9b0ef3ea6d0d95` (docs), `a291774cec90e8a0554a1b8c33d41bafc7785e2d` (admin copy).
- CI, browser-level behavior, database concurrency, and live IBSng execution remain to be verified. No live provisioning was run.


### 2026-11-02 — Payment architecture documentation reconciliation

**Status: DOCUMENTATION CHANGES COMMITTED ON `main`; PHP lint CI pending for final doc commits.**

- Added `docs/PAYMENT-ARCHITECTURE.md` as the current source of truth: checkout snapshots, fiat/USDT amount resolution, admin provider/mode settings, adapter contract/discovery, transaction confirmation, callback/webhook trust boundaries, provisioning handoff, idempotency, and deployment verification boundaries.
- Reconciled `docs/PAYMENTS-AND-PROVISIONING.md` to link the architecture source and focus on adapter extension/provisioning lifecycle.
- Rewrote `docs/PAYMENT-IRCrypto-Integration.md` to remove stale module paths and claims that provisioning was not implemented; it now records current adapter-specific behavior and still-unverified live deployment checks.
- Corrected the outdated feature-branch instruction in `docs/ADMIN-ORDER-MANAGEMENT.md` to match the project rule that implementation remains on `main`.
- Commits: `f3f962d811d7e4f76457d83cbd7f1d5087d77df2` (canonical architecture), `528fe14e8e520f1d01684abbcf027d08e05480e2` (cross-reference), `86c64fe93ed14eed0b3d9ceb0ab353b14724460d` (IRCrypto notes), `eabc7f936914fb4198d93d5fb65d255c075af5e0` (branch-rule correction).
- Live gateway/provider callbacks, sandbox transaction, MySQL concurrency, and live IBSng actions remain deployment-level verification, not claimed as completed.


### 2026-11-02 — IRCrypto webhook endpoint placement

**Status: IMPLEMENTED ON `main`; runtime validation pending.**

- Endpoint moved to `webhook/ircrypto.php`; the explicit router allowlist now maps `/webhook/ircrypto.php` to it.
- The endpoint is outside `app/Payments/gateways/`, whose direct PHP files are auto-discovered as gateway adapters. No gateway discovery behavior was changed.
- Updated `PROJECT-CONTEXT.md`, `docs/PAYMENT-ARCHITECTURE.md`, and `docs/PAYMENT-IRCrypto-Integration.md` to document this separation.
- The handler's signature validation and PaymentFlow-based authoritative invoice verification were preserved. PHP lint, subdirectory/cPanel routing, and real provider webhook delivery remain unverified.


### 2026-11-02 — Unified order tracking and email purchase recovery requirement

**Status: PARTIALLY IMPLEMENTED IN SOURCE; EMAIL RECOVERY NOT IMPLEMENTED.**

- Product decision recorded: one customer-facing area, “Order tracking and purchase recovery,” supports both the existing public order ID and email lookup. Both modes must resolve to the same order-detail view; do not create a separate email-recovery page.
- The existing order-ID lookup and shared detail view are present in `order-status.php`. Per-IBSng-target provisioning status is rendered independently. The optional `service_name` is displayed as “Service name” only when non-empty; internal server connection details remain private.
- Email lookup is not present. Email alone must not authorize access: require ownership verification by a one-time code/link sent to the submitted address before revealing associated orders/account data. Use generic initial responses to prevent order/email enumeration, plus expiry, attempt limits, and resend/rate limiting.
- Email remains optional at checkout and the store must not add customer registration/login. Customers who omit email retain order-ID tracking.
- Current implementation/test evidence: `tests/order-status-account-contract-test.php` covers optional service-name rendering and per-target credential visibility; it does not establish an email lookup or verification flow.
- Follow-up implementation must use the existing shared order-detail presentation, avoid a duplicate recovery screen, and add tests for verification, authorization, expiry, throttling, and enumeration resistance. This entry records the agreed scope only; no code behavior or runtime test is claimed by this documentation update.
