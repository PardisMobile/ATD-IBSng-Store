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
- Checkout stores order email, buyer contact, chosen IBSng username, and encrypted account password; it snapshots product name, duration, price, and currency. The current checkout calls `PaymentFlow::initiate()` after creating the pending order. Verified callbacks are handled by `PaymentFlow::handleCallback()`, which confirms persisted transaction data before dispatching queued provisioning. Customer delivery is handled after provisioning reaches its terminal state. These source paths have contract coverage, but require database/provider/live IBSng end-to-end verification.
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

**Status: IMPLEMENTED IN SOURCE — payment initiation, persisted transaction verification, callback confirmation, and queued provisioning dispatch are wired. Contract/CI checks exist; live provider and database concurrency validation remain open.**

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
- `PaymentManager` uses the adapter registry and the admin settings expose provider selection/configuration. Checkout persists a transaction before provider initiation; callback handling resolves the provider reference and requires server-side verification before the paid transition and provisioning dispatch. Provider-specific live configuration, sandbox/live invoice lifecycle, and production concurrency/replay checks remain unverified. Adding another gateway still requires its adapter contract and registration/configuration support.
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


### 2026-11-02 — «سفارش‌های من»: پیگیری شناسه‌ای و بازیابی ایمیلی در یک بخش

**Status: PARTIALLY IMPLEMENTED IN SOURCE; EMAIL OWNERSHIP RECOVERY NOT IMPLEMENTED.**

- **محل واحد مشتری:** «سفارش‌های من». بازیابی ایمیلی روش دسترسی داخل همین بخش است؛ صفحه/منوی مستقل برای «بازیابی ایمیلی» نسازید. دو روش (شناسه سفارش و ایمیل) باید به نمایش جزئیات مشترک سفارش منتهی شوند.
- **شناسه سفارش:** lookup موجود بر پایه `orders.public_id` و صفحه جزئیات در `order-status.php` حاضر است. وضعیت provisioning هر مقصد مستقل نمایش داده می‌شود. `service_name` اختیاری تنها با مقدار غیرخالی نمایش داده می‌شود و اطلاعات اتصال داخلی سرور پنهان می‌ماند.
- **بازیابی با ایمیل:** هنوز پیاده‌سازی نشده. ایمیل تنها مجوز نیست؛ پیش از نمایش سفارش‌ها یا داده حساب باید مالکیت آن با کد/لینک یک‌بارمصرف تأیید شود. پاسخ اولیه نباید وجود سفارش را افشا کند. expiry، محدودیت تلاش، ارسال مجدد و rate limiting لازم‌اند.
- ایمیل checkout اختیاری باقی می‌ماند؛ خریدار بدون ایمیل همچنان از شناسه سفارش استفاده می‌کند. ثبت‌نام/ورود مشتری اضافه نشود.
- **تمدید سرویس** مسیر مستقلی دارد؛ **پنل کاربری** هم مستقل است و وضعیت سرویس قابلیتی درون پنل است، نه منوی جدا.
- شواهد فعلی: `tests/order-status-account-contract-test.php` شروط نمایش `service_name` و اطلاعات حساب مقصدها را پوشش می‌دهد؛ آزمون آن اثبات‌کننده بازیابی ایمیلی نیست.
- کارهای باز برای همین جریان واحد: رابط «سفارش‌های من» با هر دو روش، ارسال و تأیید توکن ایمیل، محدودیت/انقضا و پاسخ ضد-enumeration، اتصال به جزئیات مشترک، و آزمون‌های authorization، expiry، throttling و enumeration. هیچ‌یک از این موارد با این ثبت مستنداتی به‌عنوان پیاده‌سازی یا runtime-verified اعلام نمی‌شود.


### 2026-11-02 — IRCrypto settings page webhook guidance reminder

**Status: NOT IMPLEMENTED — REMINDER RECORDED.**

- Add instructional text to the IRCrypto gateway settings screen with the webhook callback URL, built from the configured site base URL plus `/webhook/ircrypto.php` (HTTPS for production).
- Clarify that the URL is configured in the IRCrypto provider dashboard, and that the Store endpoint performs signature validation and authoritative server-side invoice verification.
- Keep webhook endpoint placement outside `app/Payments/gateways/`, which is reserved for auto-discovered gateway adapters.
- This note is a follow-up requirement only; no settings UI/code change or live webhook test is claimed.


### 2026-11-02 — Hide empty customer service-name element

**Status: SOURCE AND CONTRACT TEST UPDATED ON `main`; execution/runtime validation pending.**

- Updated `order-status.php` to render the customer-facing `service_name` label only when the trimmed value is non-empty. Blank values now omit the entire `<strong>` element rather than emitting an empty label.
- Preserved the existing separation from internal server names, per-target provisioning statuses, and credential display conditions.
- Updated `tests/order-status-account-contract-test.php` to assert conditional rendering and reject the previous empty-string fallback pattern.
- This is source-level implementation only. The PHP contract test, PHP lint, browser rendering, and live deployment have not been run in this operation.


### 2026-11-02 — IRCrypto webhook guidance on gateway settings

**Status: SOURCE IMPLEMENTED ON `main`; PHP lint/UI/runtime validation pending.**

- Added an IRCrypto-only help block to the gateway configuration fieldset in `admin/settings.php`.
- The displayed complete callback address is generated through `app_url('webhook/ircrypto.php')`, honoring the Store's configured base URL and mount path rather than hardcoding a domain.
- The instructions say to register the URL in the IRCrypto dashboard and explain signature authentication plus server-side invoice/payment validation.
- Preserved the endpoint location outside `app/Payments/gateways/`, avoiding interference with adapter auto-discovery.
- No PHP lint, automated test, admin browser check, cPanel subdirectory test, or real webhook delivery was executed for this source change.

### 2026-11-03 — Editable email template editor slice

**Status: COMMITTED TO `main`; syntax/runtime and delivery wiring not yet verified.**

- `app/EmailTemplates.php`: template defaults and persisted template lookup, event catalog, and named-variable renderer.
- `admin/email-templates.php`: admin-editable subject, HTML body, plain-text alternative, and sandboxed sample preview for purchase success, renewal success, order recovery, email verification, and expiration reminder.
- `app/Router.php` and `admin/index.php`: explicit route registration and dashboard navigation.
- The template editor does not itself send email or activate expiration reminders. Separate transport configuration (host mail(), sender name/address, test-send), actual event integration, delivery status/retry, and secured customer email ownership recovery remain open.
- PHP lint could not be run in the available environment (the repository could not be cloned because GitHub DNS/network resolution was unavailable). No browser, mail transport, or live end-to-end verification is claimed.


### 2026-11-03 — Provisioning-bound account email delivery + usage guide

**Status: SOURCE COMMITTED ON `main`; not runtime-verified.**

- `app/CustomerAccountDelivery.php` coordinates the customer delivery email after provisioning reaches a terminal state. It includes the account credentials and only successful destination labels.
- `app/IBSngProvisioningDispatcher.php` invokes the delivery hook after the provisioning result is committed. A mail error is logged without changing the paid order or successful account state.
- Added unique durable delivery ledger `customer_email_deliveries` to `database/schema.sql` and migration `database/migrations/2026-11-03-customer-email-deliveries.sql`.
- Updated default `purchase_success` template with credentials, successful destination labels, guide link, and order tracking link. Added public `guide.php` and its explicit router entry.
- Customer-facing destination name only: uses configured `service_name`; no internal server name, address/port, or IBSng administrator credentials are included.
- Verification outstanding: PHP lint, schema/migration execution, mail transport test, and full payment→provisioning→email end-to-end test. Failed-send retry/recovery is not implemented yet; the delivery record is durable and the error is logged.


### 2026-11-03 — Failed delivery recovery and contract coverage

- Admin provisioning view now lists customer email delivery records and offers explicit retry only for `failed` records. Claim logic conditionally changes failed→sending; sent/sending rows remain blocked by the unique key and state predicate.
- Added `tests/customer-account-delivery-contract-test.php` for static behavior contracts.
- Not yet runtime tested (PHP CLI, database, mail server, end-to-end flow unavailable in this review).


### 2026-11-03 — Unified My Orders email recovery initial implementation

**Status: SOURCE COMMITTED ON `main`; deployment/runtime checks pending.**

- Added `/my-orders.php` to provide the unified customer order retrieval surface. Customers request a six-digit email code and, after verification, receive a list of orders for that email with links to the existing order status view.
- Uses the existing editable `order_recovery` email template and `EmailSender`; recovery challenges are stored as password hashes, expire after 10 minutes, have a five-attempt ceiling, and apply a combined per-email/IP request throttle.
- Uses a CSRF token and regenerates the session ID after successful verification. Unknown email addresses receive the same generic response.
- The route allowlist and router path contract test were already updated for this page. The page now exists on `main`.
- Remaining: verify the recovery migration is run by the supported installation/update flow; check whether email normalization aligns with historical order records; add focused tests for code expiry, attempts, enumeration, and rate limiting; PHP lint and runtime/mail/database/browser validation. No live delivery or production validation is claimed.


### 2026-11-03 — Recovery schema installation alignment
- Clarified deployment: fresh installations create `customer_order_recovery_codes` from `database/schema.sql`; this migration is for already-installed databases receiving the feature, not an extra step for a fresh install.
- Aligned the migration `code_hash` width with the fresh-install schema (`CHAR(255)`) so it can safely hold PHP password hashes.
- Migration application on an existing installation remains a manual deployment step unless/ until the project introduces a tracked migration runner.


## 2026-11-03 — Documentation-to-main audit checkpoint

**Audit scope:** Read the current root `PROJECT-CONTEXT.md`, `PROJECT-IMPLEMENTATION-TRACEABILITY.md`, `README.md`, `docs/PAYMENT-ARCHITECTURE.md`, and `docs/PAYMENTS-AND-PROVISIONING.md` on `main`; checked the current admin password recovery implementation files and the repository root/docs/database directory inventories. This is a source/documentation audit checkpoint, not a claim that every requirement in the 193 KB Context file has been exhaustively mapped to every PHP call site or runtime-tested.

### Confirmed documentation/code alignment

- Admin password recovery now has request and reset endpoints, login-page link, token table in fresh schema, and a standalone existing-install migration. The implementation uses hashed random tokens, 30-minute expiry, one-use consumption, and generic matching/non-matching account responses. The docs correctly mark PHP/runtime, migration, mail delivery, and browser flow as not verified.
- Payment docs describe the current provider adapter discovery path, order price/currency snapshots, callback/webhook separation, ambiguous payment reconciliation, and request-time (not worker) provisioning dispatch. The README also explicitly marks cPanel/IBSng end-to-end verification as outstanding.
- The customer-facing recovery / email-delivery feature records in Context distinguish product requirements from implemented slices and explicitly name unresolved retry/runtime checks; these should remain requirement/status entries, not be promoted to completed guarantees.

### Discrepancies and documentation debt identified

1. **Traceability file contains duplicated and temporally conflicting audit records.** In particular, `IBSNG-ERR-003` first appears as OPEN (request body handling uninspected) and later as FIXED at source level. Keep the later source state authoritative, but preserve validation as NOT VERIFIED; mark the earlier entry as superseded or consolidate it so readers do not mistake the old state for current status.
2. **The Context file is a requirements/history ledger, not a concise implementation status report.** It contains earlier dated “implementation underway” statements (e.g. credential creation) followed by later implementation slices. Readers must use the latest dated section and the traceability evidence; historical statements should be explicitly tagged as superseded when consolidated.
3. **README feature bullets are intentionally broad/aspirational.** Its status section qualifies that payment/provisioning, recovery, installer, and cPanel end-to-end readiness are not complete/verified. Do not treat the feature list alone as a completion claim.
4. **A full requirements-to-code matrix is still missing.** This pass did not establish line-by-line coverage for all Context requirements (especially store recovery by email, service renewal, all gateway lifecycle details, installer/update lifecycle, and multi-server/product policy edge cases). These remain open audit scope, not silently “matched.”

### Next reconciliation rules

- For each requirement, record one status only: `IMPLEMENTED — NOT VERIFIED`, `PARTIAL`, `OPEN`, or `VERIFIED` with named evidence. Do not use “done” for source-only changes.
- Resolve contradictory historical entries by preserving the date and adding `SUPERSEDED by <later section>`; do not erase the audit trail.
- Keep user-approved product decisions in `PROJECT-CONTEXT.md`; keep current implementation facts, exact source paths, tests, and verification gaps here.
- No PHP lint, automated suite, DB migration execution, live email, cPanel, gateway, or IBSng E2E run is claimed in this audit checkpoint.


## OpenVPN profile management (2026-11-11)

**Status: SOURCE IMPLEMENTED — NOT RUNTIME VERIFIED.**

- Added a dedicated OpenVPN management card to `admin/guide.php`, separate from the existing general customer guide editor and its save flow. Both management subsections are contained in the same card.
- Profile import accepts either a hosted HTTPS URL or an uploaded `.ovpn` file. URL imports are fetched once and stored as a snapshot; uploaded and fetched profile content is encrypted using the existing application encryption key. Basic profile structure, file size, URL scheme, DNS address scope, and risky executable/config directives are validated.
- Added public and personalized token links. Raw tokens are not stored; only SHA-256 token hashes are persisted. Personalized links require a paid order with at least one successful provisioning attempt and a valid admin-entered recipient email. The selected email is not written to `orders.buyer_email`.
- Public links are not tied to an order. Link records support disable/reactivate and download count/last-used tracking. Profile deletion removes associated links.
- Added the explicit `/openvpn-download.php` route and token-validated profile download endpoint. Profile data is decrypted only after token validation.
- Added fresh-install schema and idempotent schema synchronization for `openvpn_profiles` and `openvpn_links`.
- Added the bilingual `openvpn_profile` email event, using the existing EmailTemplates/EmailSender path and shared HTML shell. Email template editor preview includes the new variables. UI labels/placeholders and the event selector have Persian/English translations.
- **Personalized OpenVPN import flow (implementation status):** the admin-generated token opens `/openvpn-download.php?t=...`, a customer-facing landing page using the shared StorefrontView and the same `plan-card` visual language as the storefront. It presents two nested cards: a personalized import URL and a token-free public profile URL, each with its own copy action and bilingual UI text.
- The personalized URL resolves through `/openvpn-profile.php?t=...`. The endpoint retrieves the paid order's stored encrypted password, decrypts it server-side, and replaces the profile's standalone `auth-user-pass` directive with an inline `<auth-user-pass>` block containing that order's username/password. The public URL `/openvpn-profile.php?p=<profile_id>` returns the base profile without customer credentials or a token. Profile responses are no-store; secrets are never included in the landing-page HTML or query string.
- The admin still emails the token landing-page URL using the existing EmailTemplates/EmailSender flow. The admin's public-link action returns the token-free profile URL for placement in the pre-existing general guide text.
- OpenVPN 2.6 documentation describes inline `auth-user-pass` credentials, but compatibility with the user's exact OpenVPN Connect versions and the MikroTik server profile has not yet been runtime-tested. Do not claim end-to-end client verification until the customer tests Import from URL and connection on their devices.

- Outstanding verification: PHP lint, schema sync on an existing cPanel database, profile URL import against a real HTTPS host, upload, token expiry/revocation, order eligibility, bilingual email delivery, and OpenVPN client import/authentication. No runtime or live mail test is claimed.


## 2026-11-11 — OpenVPN personalized profile output correction

**Status: CODE AND CONTRACT TEST IMPLEMENTED — OPENVPN CONNECT RUNTIME TEST PENDING.**

- `app/OpenVpnManager.php`: profile validation now validates a normalized copy while returning the original bytes. Credential injection replaces exactly one standalone `auth-user-pass` directive and retains all bytes outside that directive, including line endings and embedded certificate/key blocks. Missing or duplicate standalone directives are rejected.
- `openvpn-profile.php`: response retains `application/x-openvpn-profile`, uses the configured profile name as the download filename metadata, and logs server-side exceptions without exposing secrets to the client.
- `tests/openvpn-personalized-profile-contract-test.php`: covers byte preservation, targeted credential insertion, unchanged remote/port/private-key content, duplicate directive rejection, and response metadata. Added to `.github/workflows/php-lint.yml`.
- GitHub Actions run `42941449923`: PHP lint and all workflow steps, including the new OpenVPN contract test, completed successfully. This is automated source-level validation only; it does not establish live HTTP endpoint or iOS OpenVPN Connect compatibility.
- Remaining user validation: deploy latest `main` to the hosting environment, generate/open the personalized import URL in OpenVPN Connect on iOS, confirm the configured profile name, and test authentication/connectivity. If import still returns “Incorrect response from server”, capture the corresponding server error-log entry and response status/headers before changing the profile format further.


## 2026-11-11 — OpenVPN profile endpoint error status

- `openvpn-profile.php` previously converted every caught profile-generation failure into HTTP 404, masking token, database, decryption, and profile-format errors as if the route did not exist. It now returns HTTP 500 for caught server-side generation failures, logs the actual exception server-side, and keeps a generic non-sensitive response body. The explicit router allowlist already includes `/openvpn-profile.php`; do not remove that route or the token validation.
- The personalized token remains necessary: the emailed URL is a bearer credential that grants access to the customer's generated profile containing their account credentials. Email-only distribution is not an access-control boundary.
- Contract test now asserts the endpoint's 500 failure behavior. This helps distinguish an application failure from a web-server/router 404; if the client still reports 404, the request is not reaching this PHP endpoint and hosting rewrite/routing must be inspected.


## 2026-11-11 — OpenVPN endpoint direct routing and inline response

**Status: SOURCE AND CONTRACT UPDATED ON `main`; LIVE HOST/CLIENT VALIDATION PENDING.**

- `openvpn-profile.php`: keeps `Content-Disposition: inline` and uses `Content-Type: application/x-openvpn-profile` for the direct profile endpoint, preserving the MIME contract required by OpenVPN URL import. Browser testing showed that this MIME may still trigger a download; a separate preview response is needed for readable in-browser display. The profile body and personalized credential injection are unchanged.
- `.htaccess`: added an explicit rewrite stop for `openvpn-profile.php` before the catch-all front-controller rule. This serves the existing PHP endpoint directly rather than relying on `app/Router.php` dispatch for the raw profile URL.
- `tests/openvpn-personalized-profile-contract-test.php`: now asserts inline disposition as well as the OpenVPN MIME type and existing error behavior.
- Token validation, expiration/revocation, paid-order checks, encrypted credential retrieval, and profile transformation are unchanged.
- The source change does not prove the hosting environment has deployed the new files. Verify that the exact personalized URL returns HTTP 200 and a raw `.ovpn` body in a browser, then import that same URL in OpenVPN Connect. If it still returns 404, inspect the active document-root `.htaccess`, PHP handler, and deployed file path; the issue is then outside this source-level endpoint change.


## 2026-11-11 — Pre-generated personalized OpenVPN files and Connect import action

- Personalized .ovpn content is materialized at admin link creation time in the single root ovpn/ directory; it is not generated on each customer download request.
- Filenames use a short profile-name slug plus a cryptographically random suffix. The filename is stored on the corresponding openvpn_links row. The file contains the same per-order inline username/password profile produced by the existing profileWithCredentials() routine; the uploaded base profile, remote/port, certificates, private key, and unrelated directives remain unchanged.
- The customer landing page keeps its existing email/token landing URL and displays the generated file URL as the personalized URL. It adds an Import to OpenVPN action using openvpn://import-profile/ followed by that HTTPS file URL. The copy-link fallback remains available.
- Generated files are served through a dedicated PHP endpoint after checking link activation, expiry, active profile, paid order, and filename association. The endpoint keeps the configured profile name in Content-Disposition so the imported display label is not replaced by the random storage suffix. The physical ovpn/ directory is not directly browsable or served as static content. Deleting a link/profile removes its generated file; disabling a link or profile blocks retrieval.
- Existing personalized links without a generated filename are backfilled when their download page is opened. Public profile URLs remain unchanged and contain no customer credentials.
- Fresh-install schema and idempotent DatabaseSchemaSync include openvpn_links.generated_filename; an additive SQL migration is included.
- Verification status: source implementation only. PHP lint, DatabaseSchemaSync on an existing cPanel database, generated-file permission checks, expiry/revocation, deep-link launch on iOS, and actual OpenVPN Connect import/authentication still require runtime testing after deployment. The custom openvpn://import-profile/ scheme must not be considered verified until tested with the user's OpenVPN Connect version.


### 2026-11-11 — Public OpenVPN physical profile and customer guide

**Status: FIXED (source) / NOT VERIFIED (PHP runtime, browser, and OpenVPN Connect import).**

- `OpenVpnManager::createLink()` now materializes a physical `.ovpn` file for both link types using the existing generated filename, directory, validation, exclusive file creation, permissions, and cleanup flow.
- Personalized content continues to pass through `profileWithCredentials()`; public content is written directly from the decrypted and validated base profile, without injecting customer credentials.
- `publicProfileFileUrl()` resolves the latest active, unexpired public link for the selected active profile and returns its generated physical file URL. The public customer guide uses this file URL for the `openvpn://import-profile/` action. The existing public copy URL remains the raw public profile endpoint.
- The public Copy and Import buttons now use the same `button primary` styling as the personalized actions.
- Customer-facing copy now says “account” rather than “IBSng” in the personalized-link description. The public-card sentence about placing the URL in the site's general guide was removed in both Persian and English (`data-fa` / `data-en`).
- `profileWithCredentials()` and `openvpn-profile.php` were not changed. No database schema or IBSng/payment behavior was changed.
- Validation gap: source changes were committed to `main`, but PHP lint, automated tests, browser rendering, and actual OpenVPN Connect import have not been run in this environment. Verify both public and personalized import on the target hosting before marking VERIFIED.


### 2026-11-11 — Fix public physical OpenVPN profile delivery

**Status: FIXED (source) / AUTOMATED CI AND HOST RUNTIME VALIDATION PENDING.**

- Root cause: `publicProfileFileUrl()` correctly materialized a credential-free public `.ovpn` file, but `resolveGeneratedFile()` rejected every generated file whose link type was not `personalized`. The public Import deep link therefore reached the file endpoint and was rejected before the file could be served.
- `app/OpenVpnManager.php::resolveGeneratedFile()` now accepts active, unexpired generated files associated with either `public` or `personalized` links and active profiles.
- Paid-order validation remains conditional and mandatory for `personalized` links only. Public links remain order-independent and serve the already-generated base profile without injecting credentials.
- File existence/readability validation, profile validation, download count/last-used tracking, link/profile activation and expiry checks remain in the common delivery path.
- `tests/openvpn-personalized-profile-contract-test.php` now asserts that both link types are accepted and that paid-order validation remains in the personalized-only branch.
- No change was made to `profileWithCredentials()`, `openvpn-profile.php`, profile construction, database schema, payment, or IBSng behavior.
- Validation gap: source changes are committed to `main`; CI result and actual public Import on the user's iPhone/hosting still need confirmation. Do not claim runtime verification until tested.


### 2026-11-11 — Fix public Import action on personalized OpenVPN landing page

**Status: FIXED (source) / CI AND HOST RUNTIME VALIDATION PENDING.**

- Root cause: the public profile card is also rendered on personalized landing pages, but `publicProfileFileUrl($profileId)` and `$publicImportUrl` were only initialized in the public-page (`?p=`) branch. On a token (`?t=`) page, the public Import anchor therefore had an empty target and tapping it did nothing.
- `openvpn-download.php`: public generated-file URL and its `openvpn://import-profile/` Deep Link are now resolved once after either route has established the active profile ID. This initializes the public Import action on both public and personalized landing pages.
- Personalized Import URL generation and its existing credentials/profile flow are unchanged. No change to `OpenVpnManager::profileWithCredentials()`, file delivery validation, database schema, payment, or IBSng behavior.
- `tests/openvpn-personalized-profile-contract-test.php`: added a source contract asserting the public file URL and Deep Link are initialized after the token/public route resolves the profile ID.
- Validation gap: source is committed to `main`; automated CI and actual tap testing on iPhone/macOS remain pending.


### 2026-11-11 — Unify personalized URL display with public URL style

**Status: IMPLEMENTED ON MAIN / HOST RUNTIME TEST PENDING.**

- The personalized card now displays and copies a clean endpoint-style URL: `openvpn-profile.php?t=TOKEN`, matching the public card's endpoint-style presentation.
- The generated physical `/ovpn/<filename>.ovpn` URL is held separately in `$personalFileUrl` and remains the target of the OpenVPN Connect Deep Link.
- The legacy `openvpn-profile.php?t=...` endpoint remains the fallback URL for manual Import from URL; its existing credential injection flow is unchanged.
- `profileWithCredentials()`, generated file contents, public card behavior, and all database/security rules are unchanged.
- Contract test added to assert the displayed/copy URL and Import URL remain separate.
- Host/iOS runtime test remains pending.


### 2026-11-11 — Customer-facing OpenVPN manual import guide wording

**Status: COPY AND CONTRACT TEST UPDATED ON `main` / PHP AND BROWSER RUNTIME VALIDATION PENDING.**

- `openvpn-download.php`: replaced the unsupported `Import from URL` fallback instructions with the user-tested manual flow: open the copied profile link in Safari/Chrome, open the downloaded file and choose Share, then select OpenVPN Connect.
- The manual-import section is shown for both public and personalized profile landing pages; the existing Import deep links, displayed/copy URLs, profile generation, and credential handling are unchanged.
- Updated the personalized-card privacy sentence to the user-approved Persian wording and its English translation.
- Updated `tests/openvpn-personalized-profile-contract-test.php` to assert the three manual-import steps, absence of the old Import from URL instructions, and the approved personalized-link warning.
- No changes to `OpenVpnManager`, profile bytes, database schema, payment, or IBSng behavior.
- Validation remains source-level only until PHP lint/contract test and browser download/share/import are exercised on the target host and devices.


### 2026-11-11 — Restore approved public OpenVPN link explanation

**Status: COPY AND CONTRACT ASSERTION UPDATED ON `main` / HOST RUNTIME VALIDATION PENDING.**

- `openvpn-download.php`: restored the user-approved public-card explanation: «این لینک فقط شامل تنظیمات اتصال به سرور است و اطلاعات ورود هیچ مشتری‌ای را در خود ندارد. برای استفاده از حساب خود باید اطلاعات ورود معتبر را وارد کنید.» Added the corresponding English translation.
- `tests/openvpn-personalized-profile-contract-test.php`: added source assertions for the Persian and English public-card wording.
- This is a customer-facing copy-only change. Public/personalized URL generation, profile contents, credentials, import actions, database, payment, and IBSng behavior are unchanged.
- PHP lint, automated test execution, and browser verification on the hosting environment remain pending; this commit must not be described as runtime-verified.


### 2026-11-11 — Fix OpenVPN contract-test interpolation failure

**Status: TEST SOURCE FIXED ON `main` / CI RE-RUN PENDING.**

- The PHP syntax and router tests passed in workflow run 42967356387, but `tests/openvpn-personalized-profile-contract-test.php` failed because double-quoted expected-source strings interpolated the test process's undefined `$token` variable.
- Rewrote the three expected source fragments as single-quoted PHP strings with escaped embedded quotes, so the test compares literal PHP source instead of interpolating variables.
- No application code, profile generation, credentials, URL behavior, database, payment, or IBSng logic was changed in this fix.
- Awaiting the new GitHub Actions run triggered by this test-only commit; do not mark CI green until that run completes successfully.


### 2026-11-11 — Align OpenVPN card descriptions across locales

**Status: FIXED ON `main` / CI AND BROWSER VALIDATION PENDING.**

- Increased the desktop description area minimum height in `openvpn-download.php` from 5.1em to 7.4em so the longer English copy does not push the URL field and actions out of alignment with the neighboring card.
- Retained the mobile override that lets descriptions use natural height when cards stack vertically.
- Copy, URLs, import actions, generated profile content, credentials, and backend behavior are unchanged.
- Validate both Persian and English layouts at desktop and mobile widths; adjust only if actual browser rendering shows clipping or excessive whitespace.


### 2026-11-11 — Clarify OpenVPN profile management heading

**Status: COPY UPDATED ON `main` / CI AND BROWSER VALIDATION PENDING.**

- Updated the OpenVPN management disclosure title in `admin/guide.php` to Persian «مدیریت پروفایل‌های OpenVPN و ساخت لینک اتصال» and English “Manage OpenVPN Profiles and Create Connection Links”.
- This is a bilingual admin-label-only change. OpenVPN profile, link creation, import, credential, and delivery behavior are unchanged.


### 2026-11-11 — Add the approved bilingual OpenVPN management description in the correct location

**Status: UPDATED ON `main` / CI AND ADMIN-BROWSER VALIDATION PENDING.**

- In `admin/guide.php`, moved the explanatory paragraph into the OpenVPN profile-management disclosure, immediately below its summary heading. Previously, a different generic note appeared above the management grid, so the approved description was not displayed under the heading as requested.
- Persian: «در این بخش می‌توانید پروفایل‌های OpenVPN را مدیریت کنید، لینک عمومی یا اختصاصی بسازید و فایل‌های پروفایل را برای اتصال کاربران آماده کنید.»
- English: “In this section, you can manage OpenVPN profiles, create public or personalized links, and prepare profile files for users to connect.”
- Both translations use the existing `data-fa` / `data-en` language-switching architecture. No new translation mechanism or application behavior was introduced.


### 2026-11-11 — Restore OpenVPN description above management cards

**Status: POSITION CORRECTED ON `main` / CI AND ADMIN-BROWSER VALIDATION PENDING.**

- Moved the approved bilingual OpenVPN management description out of the collapsible profile-management card and placed it above the two management cards, within the OpenVPN management section.
- Kept the management card title and its expand/collapse behavior unchanged.
- No OpenVPN profile/link behavior or other application logic changed.


### 2026-11-11 — Rename the profile-management card only

**Status: UPDATED ON `main` / CI AND BROWSER VALIDATION PENDING.**

- Changed the profile form card title in `admin/guide.php` to Persian «مدیریت پروفایل‌های OpenVPN» and English “Manage OpenVPN Profiles”.
- The separate links card remains titled «مدیریت لینک‌های OpenVPN» / “Manage OpenVPN links”.
- The section description remains above both cards. No behavior changed.


### 2026-11-11 — Normalize OpenVPN link action button dimensions

**Status: CSS FIX COMMITTED ON `main` / CI AND BROWSER VALIDATION PENDING.**

- In `admin/guide.php`, applied the same fixed 34px height/min-height, padding, font sizing, and icon sizing to all buttons inside `.ovpn-link-actions`, including Copy, Enable/Disable, and Delete.
- Previously, the sizing selector excluded buttons with `data-copy-value`, leaving Copy at the global button dimensions while the other actions had compact dimensions.
- No action handlers, permissions, or link behavior changed.


### 2026-11-11 — Align guide method icon choices with supported protocols

**Status: IMPLEMENTED ON `main` / CI AND VISUAL VALIDATION PENDING.**

- Replaced the guide icon choices V2Ray and WireGuard with Cisco and L2TP / PPTP; the four selectable choices are now OpenVPN, Cisco, L2TP / PPTP, and Generic.
- Added Cisco and shared L2TP/PPTP SVG path definitions to the existing `app/GuideIcons.php` icon library. The SVGs use the same 24×24 viewBox, no-fill outline style, currentColor stroke, 1.8 stroke width, and rounded caps/joins as the existing guide icons; no external icon package was added.
- Updated admin selector options, dynamic icon preview mapping, validation allowlist, and name-based icon inference.
- Legacy stored V2Ray/WireGuard icon keys are no longer selectable/accepted; when rendered they safely fall back to Generic unless the method name identifies another supported icon. Existing method records and their text are preserved.
- No connection method execution, customer guide content, payment, account, or IBSng behavior changed.

### 2026-11-12 — Storefront currency label follows selected language

**Status: SOURCE FIX COMMITTED ON `main`; BROWSER/RUNTIME VALIDATION PENDING.**

- Updated the storefront product-card currency label in `index.php` to use the existing `data-fa` / `data-en` language-switching contract: `تومان` / `Toman` for IRT and `ریال` / `Rial` for IRR.
- Confirmed `assets/js/ui-preferences.js` already applies these attributes when the customer changes the storefront language; no new translation mechanism or JavaScript behavior was added.
- Kept `app/Pricing.php` amount conversion and formatting unchanged. The stored price, selected display unit, numeric amount, USDT price, checkout, payment, and historical order snapshots are untouched.
- Existing `tests/pricing-display-test.php` continues to cover the numeric conversion and Persian labels returned by the domain formatter; browser verification of the English label is still required.

### 2026-11-12 — Remove visible literal newline markers from storefront head

**Status: SOURCE FIX COMMITTED ON `main`; BROWSER VALIDATION PENDING.**

- Removed the two unintended literal `\\n` text sequences in `index.php` between the charset, SEO description, and SEO keywords markup. They were rendered as visible text above the storefront header.
- Replaced only those two sequences with actual source line breaks; no metadata values, page content, styling, or storefront behavior changed.
- Commit: `a16ce243f345b9ed305e50f6e8dc0d04e86a65e6`.
- Browser verification on Safari/iOS and PHP lint remain pending.


### 2026-11-12 — Fix mobile overflow for ambiguous payment review table

**STATUS: CSS SOURCE FIX COMMITTED ON `main`; MOBILE BROWSER VALIDATION PENDING.**

- `admin/orders.php`: at viewport widths up to 760px, the ambiguous-payment table retains a 1040px layout and scrolls horizontally within its own wrapper. This prevents seven columns, long timestamps, gateway names, and review buttons from being crushed into narrow vertical text.
- The responsive rule is scoped only to `.ambiguous-payments-wrap` and `.ambiguous-payments-table`; other report tables, desktop layout, payment diagnostics, form submission, and order resolution behavior are unchanged.
- Commit: `d6520b5e57a837dcc69f331063ca308c752f7901`.
- No runtime/browser validation or PHP lint is claimed yet.


### 2026-11-12 — IBSng session reuse and payment initiation recovery

**Status: IMPLEMENTED ON MAIN / AWAITING HOST RUNTIME TEST.**

- The supplied Python HTTP integration reference and the Store target the same IBSng installation/model; the Python sample was tested against the same server used by the Store. No separate IBSng version is assumed.
- `IBSngClient` retains a permission-restricted cookie jar in the system temporary directory, isolated by server host/port, admin username, and a one-way credential fingerprint. The jar expires after 15 minutes and concurrent access is serialized with a lock file.
- A read-only request that receives the actual IBSng login form invalidates the cached jar, performs one fresh login, and retries that GET once. Mutating POST requests are never automatically replayed.
- Customer account lookup isolates per-server failures and continues checking remaining configured IBSng servers; if no candidate matches and at least one server failed, it reports lookup failure rather than falsely reporting that the account does not exist.
- Zarinpal cURL transport errors preserve the cURL error number. DNS resolution, TCP connect, proxy DNS, and TLS handshake failures are treated as pre-request failures. DNS-resolution timeouts are also safely failed when cURL timing confirms it never reached the HTTP transfer phase; timeouts after that boundary remain unresolved and are not blindly retried.
- Provisioning and renewal retry policy was not changed.
- Automated and live validation still required: PHP lint, existing test suite, IBSng same-server session reuse/expiry test, multi-server account lookup, and Zarinpal DNS/connect failure plus successful retry on the same order.


### 2026-11-12 — Customer retry after proven pre-transfer payment gateway failure

**Status: SOURCE FIX COMMITTED ON `main` / HOST RUNTIME VALIDATION PENDING.**

- The recurring first-checkout failure was leaving a payment transaction in `initiated` when cURL reported a DNS-resolution timeout. The existing classifier handled selected DNS/connect/TLS cURL errors but not `CURLE_OPERATION_TIMEDOUT`, which is also the code used for a DNS-resolution timeout.
- `ZarinpalGateway::postJson()` now captures cURL transfer-phase diagnostics (DNS lookup, TCP connect, TLS/app-connect, pre-transfer, first-byte timing, primary IP) and records whether cURL may have reached the HTTP request-transfer phase.
- A typed `ZarinpalTransportException` exposes that distinction to `PaymentFlow`. If the failure is proven to have happened before the HTTP request was sent, including DNS-resolution timeout, the attempt is marked `failed`. The existing order-status page then offers the customer a new payment attempt on the same pending order; no administrator release is needed.
- If cURL had entered the HTTP transfer phase, the attempt remains ambiguous. This deliberately avoids assuming that a request with a lost response was rejected by the gateway.
- Checkout now tells the customer to retry from the order-tracking page instead of asking them to contact support for this recoverable start-payment failure.
- No payment verification, callback, amount/currency, provisioning, renewal, or IBSng behavior was changed.
- Validation gap: PHP lint, payment contract tests, and live cPanel/Zarinpal testing are still required. Confirm that a DNS-resolution timeout becomes `failed`, exposes the retry button, and that a normal successful first request still redirects to Zarinpal.


## PAY-ARCH-002 — Provider-neutral payment initiation failures

**Status: FIXED (source) / NOT VERIFIED (PHP lint, automated tests, cPanel runtime).**

- Removed the Zarinpal-specific exception type and provider-name checks from the central `PaymentFlow::initiate()` failure classifier.
- Added the shared `PaymentGatewayInitiationException` contract. An adapter may mark an initiation failure safe to retry only when it can establish that no payable provider session was created; ambiguous outcomes remain unresolved.
- Moved provider-specific configuration completeness and transaction metadata generation behind `PaymentGateway` and `PaymentManager`. Zarinpal now validates its active Merchant ID and snapshots its Sandbox/Live environment in its own adapter; the central application flow no longer contains Zarinpal-specific configuration checks.
- Zarinpal transport diagnostics (HTTP status, cURL error, timing, destination IP and response excerpt) are attached to the generic exception and persisted with the initiation diagnostic. Credentials and merchant secrets are not included.
- A definitive provider API rejection without an Authority is classified by the Zarinpal adapter as safe to retry. Transport failures are safe only when cURL timing proves the HTTP transfer phase was not reached. Post-transfer/ambiguous failures remain `initiated`.
- Generic input-validation failures are marked failed because validation occurs before the provider request. Unknown adapter exceptions remain ambiguous by default.
- Payment transaction persistence, callback verification, provider retry strategy, provisioning and renewal policies were not redesigned.
- Both active adapters, Zarinpal and IRCrypto, implement the expanded `PaymentGateway` contract. Zarinpal owns its Sandbox/Live snapshot and conditional Merchant ID validation; IRCrypto returns no extra transaction metadata and validates its API key/webhook secret inside its adapter.
- Required validation before production: PHP lint for all touched PHP files, the existing test suite, adapter contract tests, safe-vs-ambiguous failure tests, and first-attempt/retry runtime comparison on cPanel. No live payment verification is claimed.


### 2026-11-12 — Remove internal platform name from pending-order customer notice

**STATUS: BILINGUAL COPY AND CONTRACT ASSERTIONS UPDATED ON `main`; CI AND STOREFRONT RUNTIME VALIDATION PENDING.**

- `app/messages.php`: Persian pending-order copy now says «این سفارش هنوز پرداخت نشده و هیچ اکانتی ایجاد نشده است. پرداخت و فعال‌سازی پس از برقراری ارتباط با درگاه معتبر تکمیل می‌شود.» English: “This order has not been paid yet, and no account has been created. Payment and activation will be completed once a valid payment gateway is available.”
- The customer-facing server-mapping error no longer includes the internal platform name. Related submitted/pending notices consistently use «اکانت» / “account”.
- Extended `tests/order-status-account-contract-test.php` to assert the exact Persian and English copy and ensure the pending notice does not expose the internal platform name.
- No payment, order-state, account provisioning, server integration, or database behavior changed. PHP lint, automated CI, and browser verification remain pending.


### 2026-11-12 — Private IBSng diagnostic log and stale-session observability

**STATUS: SOURCE IMPLEMENTED / HOST RUNTIME TEST PENDING.**

- Added app/DiagnosticLogger.php; the first relevant diagnostic creates storage/logs/ibsng-debug.txt on the host. No admin-panel UI or customer-accessible viewer was added.
- Added storage/.htaccess denial and a directory index guard. The intended viewing method is cPanel File Manager. Writes use append + exclusive lock, restrictive file permissions, and a 1 MiB single-generation rotation.
- The logger filters sensitive context keys and does not record IBSng credentials, cookies, customer usernames, passwords, email addresses, phone numbers, or raw request bodies. Logging is best-effort and cannot fail the purchase flow.
- IBSngClient recognizes login-form input attributes with either quote style and records cookie reuse, transport failures (HTTP status/cURL errno/error), stale-session detection, fresh-login attempt, and recovery outcome. It retries only the read-only request once; mutating requests are not replayed.
- checkout.php records the automatic-username-generation exception and order-creation exception internally while preserving safe customer messages.
- Added tests/ibsng-session-diagnostics-contract-test.php. Validation required: PHP lint, all existing contract tests, CI, deploy to cPanel, reproduce first-attempt failure, and inspect the text log. Host runtime is not yet verified.


### Log location and operation labels (2026-11-12)

- Canonical log path is now `logs/ibsng-debug.txt` at the project root. The earlier `storage/logs` path is superseded.
- Every entry is timestamped using the hosting server's local timezone (including UTC offset) and identifies the event and operation. Checkout username availability, order creation, IBSng session reuse/validation/re-login/recovery, and HTTP transport requests have explicit operation labels.
- Direct web access to `logs/` is denied; inspection is via cPanel File Manager. No admin-panel UI is introduced.


### 2026-11-12 — Unified diagnostic policy and hourly IBSng session reuse

**STATUS: SOURCE UPDATED / AUTOMATED CI AND CPANEL RUNTIME VALIDATION PENDING.**

- Restored `ibsng_session_cookie_reused` as a useful diagnostic event, rate-limited to at most once per hour per IBSng server (host/port key). The throttle state stores only a SHA-256 key and timestamp, not credentials or cookies.
- Both IBSng and gateway diagnostics share the same lifecycle: rotate at 1 MiB, retain the active file plus `.1` and `.2`, then clear all three generations after seven days on the next diagnostic write.
- Weekly cleanup also clears the session-reuse throttle state. Logging and throttling remain best-effort and do not block checkout if file operations fail.
- This policy is the default for future integration debug logs. No admin UI or public log viewer is introduced.
- Validation gap: PHP lint, contract tests, CI, and cPanel runtime behavior remain to be verified.
