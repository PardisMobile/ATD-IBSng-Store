# UI Enhancement Audit & Implementation Plan

> Project: ATD IBSng Store  
> Target branch: `main` only  
> Status: UI audit notes / implementation backlog  
> Scope: UI/UX only. This document records findings and expected design work; it does not authorize changes to business logic.

## 1. Guardrails

- Work only on `main`; do not create another branch.
- Do not alter core PHP behavior, IBSng integration, purchase/renewal flows, payment processing, callbacks, database schema/migrations, server-side validation, authentication/security, APIs, routes, or operational logic as part of this UI task.
- If a functional defect is discovered while improving the UI, document it separately for review; do not silently fix it under this UI scope.
- Keep the store focused on selling IBSng accounts: new account purchase and renewal of an existing account. No Telegram bot integration and no mandatory customer membership/login.
- Maintain Persian and English support, correct RTL/LTR behavior, dark appearance by default, and a coherent responsive experience.
- Keep the installer’s current decorative line/gradient background: it has been specifically approved as visually appealing. Redesign the installer’s layout and copy around that background.
- Use the RouteBox admin-card approach and ATD Files Portal palette/animation inspiration, while keeping the UI modular and consistent.
- Use Vazir/Vazirmatn for Persian typography. Avoid excessive motion and ensure animations do not interfere with usability.
- Display expiration dates in Gregorian and Persian calendars where applicable; show `Unlimited` for services with no volume cap.
- Do not claim visual/browser validation unless screenshots or browser rendering have actually been tested.

## 2. Overall UI system

### Findings
- Styling is split between the shared stylesheet and page-level inline CSS. This makes consistency and future refinement difficult.
- Repeated/redefined selectors and page-specific overrides can cause drift between otherwise similar controls.
- Several pages hard-code Persian language and RTL direction, while the installer supports locale and direction switching. This creates an inconsistent bilingual experience.
- The logo shine animation appears attached to the broad brand wrapper rather than only the logo mark, risking a rectangular shine artifact.
- Some confirmations and errors use native `alert()` / `confirm()` dialogs, which do not match the store’s visual language.

### Expected improvements
- Establish shared tokens and reusable styles for typography, surfaces, borders, spacing, buttons, form fields, cards, notices, status labels, tables, empty states, and dialogs.
- Reduce duplicated page CSS and standardize component behavior without changing the underlying submission or route logic.
- Ensure language selection updates page language and direction consistently; review every visible label, hint, status, validation message, and action for translation completeness.
- Keep visual effects subtle. Restrict the logo shine to the logo element, not the entire brand/container.
- Replace native dialogs with a styled confirmation/feedback component only where UI-only substitution is possible without changing the operation itself.
- Check keyboard focus, contrast, touch target sizes, and reduced-motion preferences.

## 3. Installer — `install/index.php`

### Findings
- The locale and RTL/LTR support are present; the issue is not a lack of bilingual capability.
- The form structure is basic and visually under-designed relative to the approved background.
- Site, database, and administrator inputs need stronger grouping and clearer hierarchy.
- The step indicator/progress decoration should not imply actual progress or completed stages if it is only decorative.
- Current helper text and labels need more polished, contextual wording.

### Design direction
- Preserve the existing lines/gradient background.
- Rebuild the foreground as a professional setup experience: clear title and introduction, visually legible step/context area, and a focused form surface.
- Organize fields into distinct groups: site identity, database connection, and administrator account.
- Make the database test and installation actions visually distinct, with clear primary/secondary hierarchy.
- Improve field hints, error/success presentation, spacing, and mobile stacking.
- Keep the current installer flow and POST actions intact; do not modify installation logic.

## 4. Public store / catalog — `index.php`

### Findings
- The document language and direction are hard-coded to Persian/RTL.
- Catalog and plan cards have local styling that should align with shared components.
- Empty-catalog presentation and hero actions need consistent hierarchy and responsive behavior.

### Expected improvements
- Support proper English/LTR and Persian/RTL rendering consistently.
- Refine hero copy, catalog section heading, plan cards, price/validity/volume information, and purchase CTA hierarchy.
- Make cards readable on narrow screens and long product names.
- Present no-volume products as `Unlimited` and avoid confusing blank/unknown quota labels.
- Keep customer actions clear: browse/buy a new IBSng account, view an account, and track/recover purchases.

## 5. Checkout / new purchase — `checkout.php`

### Findings
- Language and direction are hard-coded to Persian/RTL.
- Buyer details, credential selection, and order-related explanatory notices are presented as a long form.
- The optional email explanation and credential-method explanation need to be more clearly connected to their respective fields.
- Some field styling uses page-local fixed colors rather than shared design tokens.

### Expected improvements
- Structure checkout as clear sections: buyer/contact information, account credential choice, and order summary/payment context.
- Clarify the consequences of leaving email blank and the importance of retaining the tracking identifier.
- Visually distinguish user-chosen credentials from automatically generated credentials; show relevant help text in context.
- Standardize notices, form validation visuals, and action hierarchy.
- Ensure the layout remains comfortable on mobile and long product names/details do not overflow.
- Preserve all current purchase and payment behavior.

## 6. Unified order tracking and purchase recovery — `my-orders.php`, `order-status.php`

### Requirement
Order tracking and purchase recovery must live in one unified customer-facing area with two access methods:
1. Track an order using its order/tracking identifier.
2. Recover purchase history using the email address used at checkout and the existing verification flow.

### Findings
- The page already contains both paths, but they need a more intentional, unified layout and clear visual relationship.
- Email verification and order-history states need stronger hierarchy and consistent feedback.
- Order details and long identifiers should remain readable on mobile.

### Expected improvements
- Present both access methods as clearly labeled options within one unified section/page, not as disconnected experiences.
- Keep the order-ID lookup direct and easy to find.
- Make the email recovery process and verification-code state easy to understand, with clear status and next action.
- Refine order cards, order status, payment status, amounts, service details, and tracking identifiers.
- Preserve existing email verification and lookup logic.

## 7. Customer IBSng account view — `customer-account.php`

### Findings
- The page is hard-coded to Persian/RTL.
- Login/lookup inputs, account details, and password visibility controls need a more cohesive layout.
- Account information may become dense, especially on mobile.

### Expected improvements
- Create a clear distinction between the account lookup form and the returned account details.
- Improve field grouping, password show/hide affordance, loading/error/empty feedback, and copy/readability for account values.
- Ensure usernames, passwords, hostnames, and other LTR values render correctly inside RTL layouts.
- Make the account detail view responsive and keep sensitive values visually deliberate.
- Do not change account lookup/authentication behavior.

## 8. Admin dashboard and shared admin navigation

### Expected improvements
- Use a consistent admin shell, navigation, page header, breadcrumbs/back links, cards, actions, and content width.
- Make the dashboard cards and navigation consistent with the RouteBox-inspired visual approach.
- Separate page-level primary actions from secondary and destructive actions.
- Standardize status chips, notices, empty states, confirmation presentation, and responsive behavior.
- Ensure all admin pages have consistent language and direction behavior.

## 9. Products and plans — `admin/products.php`

### Findings
- The product editor is a long form with several distinct concepts: duration, IBSng validity, price, currency, USDT price, quota, ordering, and server/group mappings.
- Mapping controls and product fields need clearer grouping and responsive treatment.
- Existing labels include technical units and terminology that may be hard for store operators to scan.

### Expected improvements
- Group the editor into product basics, validity, pricing/currency, volume, IBSng server/group mappings, description, and publication state.
- Make units explicit and contextual; avoid ambiguous price units.
- Improve mapping rows and add/remove controls so the relationship between server and group is easy to understand.
- Keep the product list scannable with clear active/inactive state and separate edit/toggle/delete actions.
- Ensure `Unlimited` is used for products without a volume cap in customer-facing UI.
- Preserve product data and save logic.

## 10. IBSng servers — `admin/ibsng-servers.php`

### Findings
- The add-server form and server edit cards are dense.
- Each server card includes configuration and multiple actions: test, set default, activate/deactivate, and delete.
- Nested forms are present in the markup around server actions and should be reviewed as a markup/UI concern; do not alter the server operation semantics within this UI task.
- The server name (internal) and optional customer-facing service name need to be clearly differentiated.
- Bilingual text replacement is implemented client-side and needs a completeness/consistency review.

### Expected improvements
- Make server cards easier to scan: internal label, optional storefront display label, host/port, active/default states.
- Visually group connection settings and credentials, with a clear explanation for blank replacement credential fields where applicable.
- Separate routine actions from destructive actions; clarify the purpose of each button.
- Improve feedback for connection test and save outcomes.
- Keep optional customer-facing service title hidden from the storefront when unset; when set, display it as configured.
- Preserve connection and server-management logic.

## 11. IBSng plan/group mappings — `admin/ibsng-groups.php`

### Findings
- The mapping page mixes Persian and English labels and relies on client-side text replacement for language switching.
- The mapping concept (store plan name vs exact IBSng group name) is technical and needs clearer explanation.
- Existing mapping rows contain edit, activation, and delete actions.

### Expected improvements
- Explain the distinction between the store-facing plan label and the exact manually registered IBSng group name.
- Clarify server selection context and make mappings scannable.
- Standardize mapping cards, statuses, and action placement.
- Review bilingual translation completeness and correct RTL/LTR handling.
- Preserve the manual mapping approach; do not fetch or auto-create IBSng groups as part of UI work.

## 12. Admin products/orders reporting — `admin/orders.php`

### Findings
- Tables use nowrap cells and horizontal overflow, making narrow-screen review difficult.
- The sales-report explanation is long and visually dense.
- The ambiguous-payment review area requires a note and uses a native confirmation dialog.
- Stats, reports, transaction review, and order details need a clearer hierarchy.

### Expected improvements
- Improve report intro and separate summary metrics from detailed tables.
- On mobile, provide a deliberate responsive table strategy (e.g. readable row cards or carefully scoped horizontal scrolling) rather than forcing all columns into a single line.
- Make payment review actions, required notes, and the consequence of each action clear.
- Replace native confirmation UI with a styled confirmation where behavior can remain unchanged.
- Preserve transaction and order processing behavior.

## 13. Store settings and payment gateways — `admin/settings.php`

### Findings
- Support links, price display unit, active gateway, and gateway-specific settings are part of a broad settings form.
- Gateway-specific information needs to be more contextual and easier to scan.
- The IrCrypto webhook guidance is a known required UI/documentation item.

### Expected improvements
- Group settings by purpose: support contacts, currency/price display, payment gateway selection, and provider-specific configuration.
- Reveal or visually emphasize the relevant provider settings based on the selected gateway, while preserving current behavior.
- Add clear help text in the IrCrypto gateway settings for the webhook configuration (exact instructions/content to be confirmed against the gateway integration and existing implementation).
- Make sensitive credentials and configuration fields easy to identify and edit safely.
- Improve save feedback and field-level error visibility.
- Do not modify payment/callback/webhook logic in this UI task.

## 14. Admin login and other management pages

- Audit remaining admin screens (including login, gateway management, and any settings subpages) for the same shared shell, typography, form controls, language behavior, responsive layout, status feedback, and destructive-action treatment.
- Identify all pages with inline CSS/JS, fixed Persian direction, native alerts/confirms, inconsistent button classes, or unresponsive tables.
- Include any additional findings in this document before implementation, with page/component, severity, and UI-only remedy.
- Avoid changing authentication, permissions, or server-side behavior.

## 15. Visual QA checklist

After UI changes are authorized and implemented, verify at minimum:

- Desktop and narrow mobile widths; check for horizontal overflow and clipped controls.
- Persian RTL and English LTR on all audited pages.
- Long names, usernames, email addresses, order identifiers, hostnames, and error text.
- Empty, success, error, pending, inactive, and disabled states.
- Form focus and keyboard navigation, label association, contrast, and touch target size.
- Installer background retained and foreground redesign verified.
- Dates shown in both calendars where applicable; unlimited quota displayed as `Unlimited`.
- Order tracking by identifier and email recovery remain visibly unified.
- No changes to core functionality, API/routes, payment callbacks, IBSng processing, or database.
- Record any functionality defects separately instead of changing them under this UI-only scope.

## 16. Suggested implementation sequence

1. Establish shared UI tokens/components and language/direction conventions.
2. Redesign installer foreground while preserving its approved background.
3. Refine storefront catalog and checkout.
4. Unify order tracking and email recovery presentation.
5. Refine customer account lookup/details.
6. Standardize admin shell and dashboard.
7. Redesign products, IBSng servers, and group mappings.
8. Refine settings/payment gateways (including IrCrypto webhook guidance) and orders/reporting.
9. Audit remaining admin pages and perform responsive/bilingual visual QA.
10. Report any non-UI defects separately for explicit approval.

---

This file is a working UI backlog. It records the intended direction and discovered concerns so future work can proceed systematically without repeatedly re-auditing the same items or crossing into core functionality.


## 0. Main audit axes — required review before implementation

The following are the principal audit dimensions, not merely a list of cosmetic fixes. Review each against the actual page and user journey, then record the finding, severity, and UI-only remedy.

### Core customer journeys and page composition
- **Public store/catalog:** unified product card hierarchy for product name, price, validity, quota/specifications, and primary CTA; handle long names, unavailable/empty catalog, and responsive layouts.
- **Checkout:** organize the multi-part form with a persistent or clearly visible order summary; make required/optional fields, credential generation/selection, payment choice, notices, and next steps understandable.
- **Order tracking and purchase recovery:** one unified section with two explicit access methods—order/tracking ID and email-based verification/recovery—not separate disconnected experiences.
- **Order status:** visually distinguish order state, payment state, and provisioned service/account state so they are not conflated.
- **Customer account/details:** present account attributes in a clear information card, group related fields, handle LTR values in RTL pages, and make password visibility/copy affordances understandable.
- **Admin products:** use a sectioned editor and a scannable list; clarify duration, IBSng validity, currency/price, USDT price, quota, server/group mappings, publication state, and actions.
- **Order reporting/management:** separate summary metrics from details; ensure order and transaction details remain usable on mobile; clarify review/confirmation actions.
- **Settings:** group settings by purpose and gateway; provide contextual helper text and clear feedback.
- **IBSng servers and groups:** use consistent reusable UI patterns for cards, mapping rows, statuses, and actions; preserve the manually managed group mapping model.
- **Installer:** retain the approved decorative background while redesigning the foreground layout, step hierarchy, field groups, copy, and responsive behavior.

### Cross-cutting UI states and quality
- Audit success, error, warning, connection-test, empty/no-product, no-order, loading/processing, pending, inactive, and disabled states.
- Review product descriptions and primary/secondary CTA wording for clarity and consistency.
- Check Persian and English language coverage, RTL/LTR direction, and correct rendering of dates (Persian and Gregorian), currencies, USDT, and Unlimited quota label.
- Review complex forms for grouping, labels, helper text, field-level feedback, keyboard focus, and clear required/optional status.
- Use deliberate confirmation and feedback UI for delete, status changes, payment review/confirmation, and sensitive settings changes, without changing their underlying behavior.
- Validate mobile layouts and touch targets across public and admin pages; avoid clipped content and accidental horizontal overflow.
- Treat these as audit criteria and document functional defects separately; this UI backlog does not authorize core logic changes.


## 17. Detailed UI audit table transcribed from the supplied screenshots

This section preserves the screenshot-specific audit table as concrete, page-by-page items. These are separate from the broader audit axes above and must be checked individually during implementation.

| بخش | ایراد قابل مشاهده در سورس | اصلاح پیشنهادی |
|---|---|---|
| فروشگاه عمومی | استایل اختصاصی کارت پلن‌ها در خود صفحه | ساخت کامپوننت یکپارچه برای کارت محصول، قیمت، مشخصات و CTA |
| Checkout | استایل مستقل فیلدها و پیام‌ها؛ نمایش شرطی با inline style | فرم چندبخشی با خلاصه سفارش، فیلدهای منظم و وضعیت‌های مشخص |
| پیگیری سفارش | دو روش پیگیری در یک صفحه، اما بدون معماری بصری کاملاً روشن | یک بخش واحد با انتخاب روش پیگیری و اولویت‌بندی واضح |
| وضعیت سفارش | ترکیب وضعیت سفارش، تلاش پرداخت و اطلاعات اکانت | تفکیک مرحله‌ای سفارش، وضعیت پرداخت و اطلاعات سرویس |
| مشاهده حساب | مشخصات و رمز اکانت در یک شبکه ساده | طراحی کارت مشخصات با گروه‌بندی و کنترل‌های واضح |
| محصولات مدیریت | فرم طولانی با فیلدهای متعدد و نگاشت‌های سرور | فرم بخش‌بندی‌شده و قابل اسکن، همراه خلاصه نگاشت |
| گزارش سفارش‌ها | جدول با ستون‌های متعدد و اسکرول افقی در موبایل | نمایش اطلاعات مهم به صورت خلاصه و ارائه جزئیات در سطح مناسب |
| تنظیمات | تعداد زیاد تنظیمات در یک صفحه و کنترل‌های با ظاهر متفاوت | گروه‌بندی تنظیمات بر اساس موضوع، توضیح کوتاه و چیدمان ثابت |
| سرورها و گروه‌ها | فرم‌ها و کارت‌های مدیریتی با قواعد CSS مشابه اما تکرارشده | کامپوننت‌های مشترک برای سرور، گروه، وضعیت اتصال و عملیات |
| Installer | ساختار و چیدمان ابتدایی با وجود پس‌زمینه زیبا | حفظ پس‌زمینه، بازطراحی کامل ترکیب‌بندی و محتوای صفحه |

### مواردی که به‌طور خاص نباید از قلم بیفتند

- **پیام‌های سیستمی:** موفقیت، خطا، هشدار، خطای اتصال، نبود محصول، نبود سفارش و وضعیت‌های در حال پردازش.
- **متن‌های محصول:** عنوان، توضیحات، مدت اعتبار، حجم، قیمت، محدودیت‌ها و CTAها.
- **تاریخ‌ها و اعداد:** نمایش تاریخ شمسی و میلادی، ارز، قیمت USDT و مقدار نامحدود.
- **فرم‌های پیچیده:** نگاشت سرور/گروه، فیلدهای شرطی درگاه، انتخاب روش پرداخت و تنظیمات اعتبار.
- **تأیید عملیات:** حذف، تغییر وضعیت، بررسی پرداخت مهم و ذخیره تنظیمات.
- **موبایل:** ترتیب عناصر، عرض دکمه‌ها، جداول، کارت‌های چندستونه و ورودی‌هایی که متن طولانی دارند.
- **زبان:** متن‌های فارسی و انگلیسی، پیام‌های پویا و عناصر رابط؛ جهت RTL/LTR باید با زبان هماهنگ باشد.
- **انیمیشن:** به‌ویژه انیمیشن لوگو؛ محدوده اجرای آن باید خود لوگو باشد و روی wrapper یا سطح مستطیلی اطراف پخش نشود.

The table and checklist above are the specific screenshot-derived items requested by the project owner. Keep them intact as a traceable checklist; do not replace them with a high-level summary.


## 18. Theme modes and user-selectable color schemes — required

This requirement is explicit and applies to the public storefront and the administration UI. It is not satisfied by merely having a light and a dark theme or by changing a single accent-colored button.

### Theme mode palettes

Implement a complete, coherent palette for both modes using shared design tokens:

| Token / role | Light mode (Windows 11-inspired) | Dark mode (modern) |
|---|---|---|
| Page background | `#F3F6FC` | `#0B1020` |
| Main surface / cards | `#FFFFFF` | `#151D30` |
| Primary text | `#0F172A` | `#F1F5F9` |
| Secondary text | `#64748B` | `#A7B3C8` |
| Borders / separators | `#E2E8F0` | `#293449` |
| Default accent (initial scheme) | `#2563EB` | `#7AA7FF` |
| Complementary accent | `#0D9488` | `#42D6B0` |

These are initial design values; tune contrast and component states during implementation while retaining the intended Windows 11-inspired light and contemporary dark appearance.

### Accent/color-scheme selection

- Provide a clear user-facing color-scheme picker, not just a light/dark toggle.
- Offer multiple cohesive accent choices (for example: blue, violet, teal/green, rose, and amber) with visible color swatches and a selected state.
- Selecting a scheme must update the complete UI through CSS design tokens: primary/secondary buttons, links, active navigation and tabs, focus rings, selected controls, highlights, and other accent-dependent components across storefront and admin pages.
- Keep semantic colors for success, warning, error, and informational states distinct from the chosen accent so status meaning and accessibility remain clear.
- Verify accessible contrast for text, controls, hover/focus/disabled states in every supported mode and scheme.

### Persistence and global behavior

- Theme mode and accent scheme must persist across page navigation and subsequent visits; use the project’s appropriate preference mechanism without changing business logic or database schema.
- Apply the same design system consistently to public pages, checkout, order tracking/recovery, account details, admin pages, and installer UI where applicable.
- Avoid flashes of the wrong theme on initial page load where feasible.
- Respect reduced-motion preferences; color selection must not depend on animation.

### Theme and language controls

- Make theme mode and color scheme discoverable and easy to change through consistent controls.
- Keep language selection as a separate, explicit control: Persian must use RTL and English must use LTR, with the selected language consistently reflected in page language, direction, labels, and dynamic UI.
- Do not treat the language selector as a substitute for the color-scheme picker; both are required.

### Acceptance criteria

- [ ] Light and dark palettes are fully applied across the storefront and administration UI.
- [ ] User can switch among multiple accent/color schemes; the change is visibly applied throughout the interface.
- [ ] Theme mode and selected color scheme persist after navigation and reload.
- [ ] Language selection remains independent and correctly switches Persian/RTL and English/LTR.
- [ ] Semantic status colors remain meaningful and readable in all themes.
- [ ] Theme controls and all palette states are included in responsive and accessibility QA.


## 19. Unified SVG iconography, reusable commercial components, and responsive design

These are required design-system deliverables for the storefront and administration experience.

### SVG icon system

- Use a consistent family of clean, subtle SVG icons throughout the UI.
- Standardize icon dimensions, stroke width, line caps/joins, alignment, and default/active/disabled colors.
- Prefer reusable inline SVG components or a shared icon set; avoid a random mixture of emoji, unrelated icon fonts, and inconsistent icon libraries.
- Icons must support the active theme and accent scheme through shared color tokens, remain legible at common UI sizes, and include accessible labels when they convey meaning without adjacent text.
- Keep decorative icons hidden from assistive technology where appropriate; do not use iconography as the only indication of critical status or action.

### Commercial-grade reusable components

Establish a coherent shared visual language and reusable styling/patterns for at least:

- Product/plan cards, price display, validity and quota specifications, and purchase/renewal CTAs.
- Form fields, labels, helper text, validation feedback, select controls, and grouped/conditional fields.
- Primary, secondary, subtle, and destructive buttons, including hover, focus, loading, and disabled states.
- Order/payment/service status indicators and progress stages, with semantic color meaning.
- Success, error, warning, information, empty, and processing messages.
- Data tables, responsive order lists/cards, summary metrics, and pagination where present.
- Admin navigation/shell, page headers, section cards, mapping rows, and action groups.
- Confirmation and feedback patterns, without changing the underlying operation or business logic.

Shared components must look and behave consistently across public storefront, checkout, order tracking/recovery, customer account, and admin pages. Avoid duplicating page-specific styles where a shared component is appropriate.

### Responsive design — mobile, tablet, desktop

- Design and verify intentional layouts for mobile, tablet, and desktop breakpoints; do not merely shrink desktop columns and controls.
- Recompose page hierarchy and navigation for narrow screens; prioritize the information and actions most relevant to the current task.
- Product cards, checkout summaries, forms, order details, admin cards, and action groups must adapt their structure and spacing for each viewport.
- Tables must use a deliberate mobile presentation (such as prioritized row cards or controlled horizontal scrolling for genuinely tabular data), avoiding clipped content and accidental page-wide overflow.
- Ensure touch targets, control spacing, readable text, and keyboard/focus behavior are suitable across devices.
- Test long product names, identifiers, usernames, emails, hostnames, and translated text at all target widths.

### Acceptance criteria

- [ ] SVG iconography follows one consistent visual system and adapts to theme/color selection.
- [ ] Core commercial UI elements use shared, consistent component patterns.
- [ ] Mobile, tablet, and desktop layouts are deliberately composed and checked separately.
- [ ] No accidental horizontal page overflow, clipped primary actions, or unusable touch targets remain in tested views.
- [ ] UI-only work preserves all existing business logic and server-side behavior.


## 20. Implementation progress — shared UI layer (2026-11-03)

The following UI-only changes have been committed to `main`:

- Shared theme tokens and commercial surface styling were added to `assets/css/app.css`, including dark/light surfaces, reusable cards, controls, buttons, table states, focus rings, and accent palette variables.
- `assets/js/ui-preferences.js` applies theme, accent, language/direction attributes and mounts the preference toolbar. Language changes emit the `atd:language-change` event for page-level localization.
- The public catalog, customer account, order history/recovery, installer, and several admin screens now load the shared preference controls.
- Catalog card rhythm and narrow-screen composition were improved; customer-facing account wording avoids exposing the technical IBSng product name.
- Order list card surfaces and installer foreground styles now use theme variables rather than fixed dark colors.
- Admin login, dashboard, product editor, and orders pages load the shared UI preference controls; order table cells are allowed to wrap and align consistently.

### Remaining verification / implementation work

- The language preference mechanism and event are in place, but **page-wide Persian/English translation is not yet complete**. Each page needs a translation dictionary or localized server-rendered copy, including validation, status, and empty-state messages.
- Continue auditing remaining public/admin pages for shared preference script integration, hard-coded theme colors, inline component duplication, and responsive behavior.
- Run PHP syntax checks and browser-based visual/functional checks at mobile, tablet, and desktop widths. Do not treat code review alone as visual QA.
- Confirm the final icon system is consistent across all pages and that meaningful icons have accessible labels.
- Keep all remaining changes UI-only; do not alter payment, callback, IBSng, database, authentication, or business-operation behavior.


### Continued audit — preference control coverage (2026-11-03)

- Added the shared `assets/js/ui-preferences.js` include to the admin settings page and the public checkout, order-status, and guide pages. This closes the identified gaps in shared theme/accent/language preference control loading for these screens.
- The payment callback endpoint was intentionally not changed: it is a processing endpoint rather than a user-facing page, and the UI preference layer is not relevant to its server-side payment flow.
- This integration provides shared preference state and direction/theme application; it does **not** by itself complete full text translation. Existing IBSng management pages still have legacy page-level translation scripts and need a dedicated consolidation pass to avoid divergent language keys/behavior.
- Browser rendering and viewport-based QA remain outstanding; no visual test is claimed from source edits alone.


### Shared language preference compatibility (2026-11-03)

- The shared preference toolbar now reads the legacy `atd-ibsng-language` key when no shared language preference has been set, and writes both keys when the user switches language. This preserves prior user-selected language across IBSng admin screens while consolidating the preference source going forward.
- The toolbar emits the language-change event on both the root element and `document` to support existing page integrations listening at either level.
- This is a compatibility bridge, not a claim that legacy page translation dictionaries have been fully unified. Translation coverage and actual browser behavior still require follow-up verification.


### Shared language switch now updates legacy IBSng pages (2026-11-03)

- The IBSng server management, plan/group mapping, and IBSng account management pages now subscribe to the shared `atd:language-change` event and re-run their existing translation routines when the common toolbar changes language.
- This removes the previous split behavior where the shared control changed `lang`/`dir` but page copy could remain in its prior language until the page-specific toggle was used.
- Existing page-specific dictionaries and translation code remain in place for now. Full dictionary consolidation, coverage testing (including dynamically rendered feedback), and browser-based checks remain open.


### Shared preference script syntax and accessibility follow-up (2026-11-03)

- Corrected an escaped newline accidentally introduced between the shared root/document language-change dispatches in `assets/js/ui-preferences.js`; it is now a real line break so the script source remains valid JavaScript.
- The language control now initializes its accessible name according to the currently selected language, matching the name it receives after a language switch.
- This was a UI-layer correction only. It does not change store, payment, IBSng, or database behavior. Browser-based rendering and functional QA remain outstanding.


### Admin renewal-migration page preference coverage (2026-11-03)

- Added the shared `assets/js/ui-preferences.js` include to `admin/ibsng-renewal-migrate.php`, so the appearance, accent, and language controls are available on this admin utility page as well.
- Kept the migration form, CSRF handling, and database migration behavior unchanged; this is presentation-only integration.


### Preference palette accessibility follow-up (2026-11-03)

- The accent palette toggle now exposes `aria-controls` and synchronized `aria-expanded` state, so assistive technology can identify whether the color options are open.
- Outside-click and Escape dismissal now reset the expanded state as well as hiding the palette. This is a focused accessibility/UI change; preference persistence and application are unchanged.
- Browser and assistive-technology validation remains outstanding.


### Theme-aware admin form controls (2026-11-03)

- Removed fixed dark-only input/select/textarea surfaces from the shared admin form rule. Controls now use the common `--control-bg`, `--text`, `--border`, and accent focus tokens, allowing the selected light/dark theme and accent color to apply consistently.
- This changes presentation only; form fields, submitted values, validation, and server behavior are unchanged.
- Light-theme form rendering still requires browser-level verification across admin screens.

### Shared form state consistency follow-up (2026-11-03)

- Added common visual treatment for disabled form controls and disabled button-like elements, including a non-lifting hover state.
- Unified placeholder contrast and added token-based invalid-field styling for controls marked with `aria-invalid="true"` or the existing `.is-invalid` class.
- These styles only respond to existing UI state markers; they do not add validation, alter submission, or change server-side behavior.
- Browser/contrast verification is still required; this source change alone is not reported as visual QA.

### Preference toolbar accessible naming (2026-11-03)

- Improved accessible names and tooltips for the theme toggle, palette control, and accent swatches; color names are localized for Persian and English.
- The preference toolbar's accessible name and control labels update when the language preference changes, instead of retaining the initial-language labels.
- This is a JavaScript accessibility improvement only; theme, accent, and language persistence remain unchanged. Browser and screen-reader QA remains outstanding.

## 12. Shared appearance controls — initial accessibility state

- Initialize the theme control's accessible action label, pressed state, and tooltip from the active theme and language; avoid a generic “Toggle theme” label on first render.
- Initialize the palette and language controls with the active locale's accessible names and tooltips, and localize the palette group name before the user changes language.
- Keep these labels synchronized when the language changes. This is a presentation/accessibility refinement only; theme, accent, and locale persistence behavior is unchanged.
- Validation note: source-level inspection only; no browser or screen-reader test is claimed.
- Follow-up correction: the theme toggle now keeps its action label and tooltip localized after a mode change, rather than switching to English or mixing languages while Persian is selected.
- Touch-target refinement: appearance toolbar buttons now retain a 40px minimum target on mobile and desktop, with 34px color swatches; this increases usable hit area without changing control behavior.


### Brand wrapper animation correction (2026-11-03)

- Removed the shared shine pseudo-element from `.brand` wrappers. Current brand links render text rather than an isolated logo mark, so applying the shine to the wrapper produced an unintended rectangular highlight.
- The shine keyframe remains available for a future dedicated logo-mark element; no markup, navigation, or business behavior was changed.
- Source-level correction only. Browser visual confirmation remains outstanding.


### Checkout field theme-token alignment (2026-11-03)

- Replaced the checkout page's fixed dark input background with shared surface/control, border, radius, text, and accent-focus tokens. Checkout fields can now follow the selected light/dark theme instead of remaining dark-only.
- Added consistent focus-visible and placeholder styling to the existing checkout field rule.
- Only inline presentation CSS was changed. Checkout form markup, POST fields, validation, payment initiation, and order processing remain untouched.
- Source inspection only; light-theme browser rendering and responsive visual QA are still pending.


### Admin store settings form theme alignment (2026-11-03)

- Updated the support/payment/settings page's existing input and select presentation to use the shared theme, control-surface, border, radius, and focus tokens. This removes the remaining fixed dark backgrounds from the settings form and allows its controls to follow light/dark mode and the selected accent.
- Added consistent fieldset/legend spacing for gateway configuration groups. The existing IRCrypto Webhook help text remains in place and continues to show the installation-aware webhook URL.
- UI-only CSS/class adjustments: no settings keys, submitted names, gateway configuration, Webhook endpoint, validation, or save behavior were changed. Browser visual QA remains outstanding.


### Shared UI preferences JavaScript — localized theme tooltip synchronization (2026-11-03)

- Continued maintaining the shared `assets/js/ui-preferences.js` preference toolbar rather than introducing a second page-specific control implementation.
- Fixed a language-switch mismatch: the theme control's accessible action name, tooltip, and pressed state now update together in the newly selected language while reflecting the currently active light/dark mode.
- This is a client-side accessibility/localization fix only; persisted preference keys, theme application, language direction, and business workflows are unchanged. Browser and screen-reader validation remain outstanding.


### Shared preference integration coverage review (2026-11-03)

- Verified that the shared `assets/js/ui-preferences.js` and `assets/css/app.css` are included on the primary storefront, checkout, order tracking/recovery, customer account, guide, and sampled admin pages; no duplicate preference script was found in those page sources.
- Found an important language-scope limitation: the shared language switch updates the document `lang`/`dir` and the toolbar's own labels, but it does not translate page copy by itself. Only the IBSng server/group/user admin pages currently subscribe to the `atd:language-change` event in the sampled pages; other static pages do not.
- Logged this as an integration gap rather than making a broad, unsafe text rewrite. A complete bilingual pass needs page-specific translation keys and coverage checks, while preserving existing form names, validation, and payment/IBSng behavior.
- Review is source-based; browser-level RTL/LTR layout, keyboard, and screen-reader QA is still pending.


### Order tracking and purchase recovery visual alignment (2026-11-03)

- Refined `my-orders.php` recovery/list cards and form controls to use the shared radius, surface, accent-focus, and control-height tokens. Long product names and tracking values wrap safely; order actions stack to full width on narrow screens.
- Refined `order-status.php` purchase summary, per-server activation cards, account specification tiles, credential panel, and status chip to follow the shared theme tokens. Removed hard-coded dark gradient surfaces from notices/details so light mode is respected.
- Kept all PHP lookup/recovery logic, CSRF fields, POST action names, order identifiers, payment states, IBSng queries, and credential visibility rules unchanged. This is a source-level UI pass; responsive browser QA and RTL/LTR rendering verification are pending.


### Installer visual refinement (2026-11-03)

- Polished the foreground installer card, brand mark, locale switch, fieldsets, input focus states, feedback notices, and action hierarchy while retaining the approved decorative gradient/line background.
- Improved min-width handling and narrow-screen stacking for database/admin form fields and action buttons; reused shared radius, shadow, control-height, surface, and accent tokens where applicable.
- Installer locale selection, field names, CSRF, database connection test, install POST actions, validation, schema/config writing, and lock behavior were not modified. Visual verification is source-only; mobile/tablet/desktop browser QA remains pending.


### Admin product and IBSng server form consistency (2026-11-03)

- Standardized the product editor and IBSng server editor field sizing, theme-aware input/select backgrounds, focus rings, feedback notice colors, card padding, and responsive form/action layout using shared UI tokens.
- Server and product cards now handle long values more safely and stack form columns/actions on narrow screens.
- No product/server POST fields, mappings, IBSng connection/testing actions, CRUD logic, or database behavior were changed. Source review only; full viewport QA remains pending.


### Catalog quota label alignment (2026-11-03)

- Updated the public catalog's no-quota display to show the explicit `Unlimited` label when a product's quota is unset, matching the storefront requirement and avoiding the ambiguous “according to service settings” wording.
- Only the customer-facing label helper in `index.php` changed. Product quota data, pricing, checkout, and IBSng behavior remain untouched.
- Source-level verification only; browser layout and bilingual rendering checks remain pending.


### Order tracking identifier input direction (2026-11-03)

- Set the order/tracking identifier field in `my-orders.php` to LTR with left alignment and text-direction isolation, so Latin tracking IDs remain readable inside the Persian/RTL recovery screen. Added a text input hint without changing validation, query parameters, or lookup flow.
- No order tracking or recovery behavior changed. Source inspection only; device/browser verification remains pending.


### UI implementation note — email verification code field
- `my-orders.php`: render the six-digit email verification code as an explicitly LTR, centered numeric field with tabular digits and deliberate spacing, while retaining its existing one-time-code autocomplete, six-digit pattern, and form submission behavior. This is a presentation/accessibility refinement only; verification logic is unchanged.


### UI implementation note — customer account technical values
- `customer-account.php`: force usernames, passwords, and the four-digit anti-bot code into a predictable LTR presentation; center and space the code and keep account values readable in RTL cards. Lookup/authentication behavior and password visibility interaction remain unchanged.


### UI implementation note — admin payment report identifiers and values
- `admin/orders.php`: display transaction/order references, phone numbers, amount+currency, and timestamps with isolated LTR direction and tabular numerals to prevent bidi reordering in the RTL review table. Existing table data, review confirmation, POST fields, and transaction resolution behavior remain unchanged.


### UI implementation note — IBSng group mapping form theme and direction
- `admin/ibsng-groups.php`: replaced the hard-coded dark input/select surface with shared theme-aware control colors and a visible focus ring. Exact IBSng group-name fields now use LTR isolated text in create/edit forms, while plan names remain naturally RTL. Group mapping operations and submitted field names are unchanged.


### UI implementation note — provisioning queue table direction and mobile handling (2026-11-03)

- Added page-scoped presentation for the provisioning attempts and email-delivery tables. Order/attempt IDs, usernames, server labels, status codes, public order references, email addresses, attempt counts, and timestamps now use isolated LTR presentation and tabular numerals where applicable inside the RTL admin screen.
- Set the remote IBSng user-ID input to LTR, left-aligned text, and gave the tables a deliberate minimum width within their existing horizontal-scroll containers so columns remain legible instead of collapsing unpredictably on narrow screens.
- Kept error messages in their natural text direction and did not change table queries, action forms, CSRF, retry/resolve operations, or any provisioning/email-delivery behavior.
- Source-level change only; browser and viewport QA has not been performed.


### UI implementation note — IBSng user management theme and technical inputs (2026-11-03)

- Scoped the IBSng account-management page's form styles to its body and replaced the fixed dark input/select background with shared theme control tokens. Inputs now follow the selected theme and accent focus ring, with a visible keyboard focus treatment and consistent placeholder color.
- Username, password, remote-user-ID (where present), and credit inputs render LTR with isolated direction and tabular numerals to avoid bidirectional text confusion in the RTL admin workflow.
- Refined narrow-screen card padding while retaining the existing single-column form layout.
- Only markup class and inline presentation styles changed. IBSng calls, renewal flow, form names/values, validation, POST behavior, and confirmation prompts are unchanged. Browser and viewport QA remains pending.


### UI implementation note — IBSng account result values (2026-11-03)

- Isolated returned IBSng username, user ID, group name, and password in the admin result panel with explicit LTR direction, numeric alignment, and safe wrapping for long values.
- Password/code output can wrap within the panel instead of pushing the page width, while retaining the existing escaped output and visibility behavior.
- Only result presentation CSS/markup changed. No IBSng request, credential handling, result fields, or operation logic was modified. Browser viewport verification remains pending.


### UI implementation note — admin dashboard navigation layout (2026-11-03)

- Reorganized the crowded dashboard header into a compact brand/settings row and a distinct responsive navigation grid, giving management links consistent spacing and touch-friendly minimum heights.
- Kept every existing admin destination, logout POST endpoint, and CSRF token intact; this is a presentation/markup organization change only.
- Navigation collapses from four columns to two and then one on narrower screens. Browser/viewport QA remains pending.


### UI implementation note — email sender settings controls (2026-11-03)

- Replaced the sender/test email fields' fixed dark background with shared theme-aware control tokens, consistent control height/radius, and the shared visible keyboard-focus ring.
- Email addresses now render explicitly LTR and isolated within the RTL settings page, improving address readability and editing.
- The change is limited to presentation CSS; form names, validation, CSRF, save/test actions, and mail delivery behavior are unchanged. Browser/viewport QA remains pending.


### UI implementation note — email template editor and preview (2026-11-03)

- Updated subject, event selector, and template editor controls to use shared theme-aware control colors, consistent radius/height, and visible keyboard focus styling instead of fixed dark surfaces.
- Kept HTML/plain-text editors LTR and monospaced, enabled deliberate vertical resizing, and constrained editor sizing to prevent narrow-screen overflow.
- Made the preview frame width-safe with a viewport-aware height range. Template event selection, save/preview POST behavior, escaping, and email rendering remain unchanged. Browser/viewport QA remains pending.


### UI implementation note — admin sign-in form (2026-11-03)

- Made the sign-in card width-safe on narrow screens and aligned its spacing with the shared responsive sizing conventions.
- Username/password controls now use shared control height/radius, theme-aware backgrounds, and a visible keyboard-focus ring; inputs are border-box and cannot expand the card.
- Login form, autocomplete attributes, CSRF protection, credential validation, and password-reset link are unchanged. Browser/viewport QA remains pending.


### UI note — order list styling (2026-11-03)

- Added a consistent frame and rounded edges to the horizontally scrollable table, plus clearer column headers and subtle row hover feedback.
- Text areas now use theme-aware colors, shared corner sizing, vertical resizing, and visible keyboard focus.
- Presentation-only update; order processing and form actions remain as before. Visual browser QA is pending.


### UI note — IBSng server cards and action controls (2026-11-03)

- Server cards now have a defined theme-aware outline, consistent rounded corners, and restrained hover feedback.
- Card action buttons share a minimum touch height, padding, radius, and clear keyboard-focus treatment; action forms wrap more safely in narrow layouts.
- Server management actions, connection testing, default selection, activation, deletion, and credential handling were not changed. Visual browser QA is pending.


### UI note — provisioning queue action layout (2026-11-03)

- Made the provisioning attempts and email-delivery action column more usable by giving its inline forms a stable grid, consistent spacing, and full-width bounded controls. Action buttons retain a minimum touch height and wrap labels rather than forcing the table wider.
- Error/result text now has a readable wrapping width, and table headers/row hover use the shared theme surface tokens for clearer scanning in the admin view.
- No queries, POST fields, CSRF handling, retry/resolve behavior, or email/provisioning operations were changed. This is source-level UI work; browser and viewport QA remains pending.


### UI note — IBSng group mapping cards and controls (2026-11-03)

- Refined saved mapping cards with theme-aware borders, subtle accent hover, and a distinct inset treatment for the exact IBSng group name, improving the hierarchy between Store plan labels and the remote group identifier.
- Styled the existing edit disclosure as a consistent expandable section, including an open/closed indicator and keyboard focus treatment. Action forms/buttons now have consistent minimum height and padding, and stack cleanly on narrow screens.
- No mapping CRUD logic, form field names, CSRF, confirmation behavior, or database operations changed. Source-level only; browser/device QA remains outstanding.


### UI note — admin product cards and IBSng mapping editor (2026-11-03)

- Added theme-aware outlined surfaces and restrained hover feedback to saved product cards, with improved title/metadata wrapping and touch-friendly action controls.
- Grouped each product’s server/group mapping row in an inset surface; selects now wrap safely, and the editor grid becomes a single column on narrow screens. Product actions adapt to a compact responsive grid.
- This is a CSS-only presentation change. Product CRUD, mapping fields, duplicate-server checks, CSRF, confirmation prompts, validation, and database behavior remain unchanged. Source-level only; browser/viewport QA remains pending.


### UI note — customer order recovery cards and lookup actions (2026-11-03)

- Refined order-history cards with a clearer surface/hover treatment, improved wrapping and line-height for purchase facts, and more consistent touch-sized detail actions.
- Lookup inputs and action buttons now stay width-safe inside their card; small-screen spacing and reduced-motion behavior are accounted for.
- Presentation CSS only: email challenge, session verification, CSRF, order queries, tracking links, and logout behavior were not modified. Source-level review only; browser/device QA remains pending.


### UI note — checkout form and pricing presentation (2026-11-03)

- Aligned checkout fields, product price, notices, and primary action with the shared theme tokens, including responsive sizing, focus visibility, and safe text wrapping.
- Styled the existing credential-method radio choices as consistent selectable rows and improved small-screen card spacing and submit-button sizing.
- CSS-only presentation refinement. Checkout POST behavior, field names, CSRF/idempotency, credential generation, pricing, order persistence, and payment initiation were not changed. Source-level review only; browser/device QA remains pending.


### UI note — storefront catalog cards and responsive rhythm (2026-11-03)

- Refined product cards with theme-token accent edging, restrained hover elevation, clearer price hierarchy, and more consistent metadata alignment/wrapping.
- Tuned the catalog grid and card spacing for responsive widths, made the primary purchase action fill the card consistently, and improved empty/error catalog surfaces.
- CSS-only storefront presentation. Product queries, display-price calculations, crypto visibility conditions, product links, and checkout behavior remain unchanged. Source-level review only; browser/device QA remains pending.


### UI note — customer account lookup and result cards (2026-11-03)

- Refined the customer account lookup form with theme-aware control surfaces, consistent control height/radius, visible keyboard focus, and improved CAPTCHA sizing and alignment.
- Improved result-card spacing, responsive grid behavior, technical-value direction isolation, password reveal control wrapping, and error-message contrast using shared theme tokens.
- Presentation-only change: account lookup/authentication, CAPTCHA/session behavior, IBSng queries, field names, password reveal interaction, and returned data remain unchanged. Source-level review only; browser/device QA remains pending.


### UI note — account guide content cards (2026-11-03)

- Reworked the usage guide's plain section separators into consistent theme-aware cards with shared surface/border/radius tokens, stronger heading hierarchy, and improved text rhythm.
- Tuned the guide width, card padding, list spacing, and link focus treatment for mobile and keyboard use; added reduced-motion handling for the subtle card hover state.
- Presentation CSS only. Guide copy, navigation destinations, and account/order behavior remain unchanged. Source-level review only; browser/device visual QA remains pending.

 
### UI note — admin password recovery forms (2026-11-03)

- Unified the forgot-password and reset-password screens with theme-aware field surfaces, shared radius/focus tokens, responsive card sizing, and more readable feedback notices.
- Improved narrow-screen submit sizing, long error-message wrapping, heading scale, and keyboard-visible link focus.
- CSS only: password recovery tokens, CSRF fields, expiry/use rules, validation, email dispatch, and password update behavior remain unchanged. Source review only; browser/device visual QA remains pending.

 
### UI note — order status and account detail panels (2026-11-03)

- Refined order detail hierarchy and spacing, including subsection headings, status badges, account specification panels, and expandable password presentation.
- Added robust wrapping for long identifiers and technical values, keyboard-visible focus on password disclosure, and reduced-motion-aware card hover feedback.
- Styling only: order lookup, payment status, provisioning state, and credential visibility conditions were not changed. Source review only; browser/device visual QA remains pending.


### Order recovery form micro-consistency pass (2026-11-03)

- Fixed a literal escaped newline accidentally left between two page-scoped CSS rules in `my-orders.php`, restoring clean separation of the LTR field rule and six-digit code styling.
- Standardized the visible labels in the order recovery forms and isolated tracking references as LTR, wrapping-safe technical values with tabular numerals.
- Presentation-only: recovery request/verification, CSRF, query parameters, and order lookup logic were not changed. Source review only; browser/device visual QA remains pending.


### Installer technical-field direction alignment (2026-11-03)

- Set database host/name/user/password and administrator username/email/password fields to explicit LTR isolated presentation, with left alignment and tabular numerals for technical values. This prevents RTL locale direction from visually reordering hostnames, identifiers, email addresses, and credentials.
- Added a page-scoped CSS rule for those explicit LTR fields; ordinary site-title/display-name fields continue to follow the selected locale naturally.
- Installer field names, locale selection, CSRF, connection test/install actions, validation, and configuration/schema writing are unchanged. Source inspection only; browser RTL/LTR visual QA remains pending.


### Checkout technical input direction and keyboard focus pass (2026-11-03)

- Isolated email, phone, username, account-password, and displayed order tracking-code values in LTR direction so their technical strings remain readable inside the Persian checkout layout.
- Added a clear keyboard focus treatment for the credential-method radio controls and tightened their field spacing using existing theme tokens.
- CSS-only: form submission, credential policy, order creation, payment initiation, and validation remain unchanged. Source inspection only; visual browser/mobile QA remains pending.


### Store catalog responsive navigation and focus refinement (2026-11-03)

- Added a consistent visible keyboard-focus treatment to catalog purchase buttons, using existing accent/focus tokens.
- Applied tabular numeral styling to product prices and key plan values for steadier alignment; allowed the customer navigation links to wrap cleanly on narrow screens.
- Presentation-only changes in `index.php`; catalog queries, price eligibility, product values, and checkout destinations remain unchanged. Source review only; browser/device visual QA remains pending.


### Customer account responsive controls and focus pass (2026-11-03)

- Added consistent keyboard focus visibility to the account lookup submit, password reveal, and top navigation controls.
- Improved wrapping of customer navigation on narrow screens and kept password reveal controls usable when account identifiers are long.
- Applied tabular numerals to account detail values. CSS-only; CAPTCHA, IBSng account lookup, password matching, and displayed account data are unchanged. Source review only; browser/device visual QA remains pending.


### Admin login RTL form and feedback refinement (2026-11-03)

- Set administrator username/password fields to explicit LTR isolated rendering for predictable entry in the Persian interface.
- Harmonized login error notices with shared theme danger/surface tokens and improved wrapping, keyboard focus visibility, and small-screen card sizing.
- CSS-only: authentication, CSRF, login throttling, and password recovery routing are unchanged. Source review only; browser/device visual QA remains pending.


### Admin store settings — consolidated responsive and technical-field UI pass (2026-11-03)

- Completed one cohesive page-level styling pass for `admin/settings.php`: aligned section/card spacing, form labels and controls, focus states, responsive action layout, notice presentation, and theme-aware gateway fieldsets.
- Isolated URL/email/numeric and gateway configuration values as LTR technical strings; made the generated IRCrypto webhook endpoint readable and safely wrapping within RTL help text.
- Confirmed the page already contains the requested IRCrypto webhook guidance; retained its generated URL and the existing explanation of signature, status, amount, and currency validation rather than duplicating or changing payment logic.
- CSS-only presentation update. Settings persistence, provider/currency selection, validation, CSRF, webhook route and payment behavior were not changed. Source review only; browser/viewport QA remains pending.


### Shared accent-button contrast correction (2026-11-03)

- Added a theme-aware foreground token for primary actions: bright accent swatches in dark mode now use a dark foreground, while the deeper light-mode accent shades retain white text.
- Applied the token to shared primary button variants to prevent low-contrast text with violet, teal, rose, blue, and amber accents.
- Shared stylesheet-only change; no page markup, action handlers, payment behavior, or form logic changed. Source inspection only; contrast was reasoned from configured palette values, not browser-tested.


## 18. Email administration feedback accessibility (implemented)

- `admin/email-settings.php`: expose saved/test-send feedback as a polite status and validation/send failures as assertive alerts for assistive technology.
- `admin/email-templates.php`: use the same status/alert semantics for save and validation feedback.
- Scope is limited to feedback semantics; PHP processing, form submission, email delivery, and template behavior are unchanged.


## 19. Admin operation feedback accessibility consistency (implemented)

- Applied the same live-region semantics to success and error notices in `admin/orders.php`, `admin/provisioning.php`, and `admin/ibsng-groups.php`.
- Success messages are announced politely (`role=status`); errors are exposed as alerts (`role=alert`, assertive live region), matching the email administration pages.
- Only feedback markup changed; payment transaction resolution, provisioning execution/manual resolution, group mapping actions, validation, and CSRF handling are unchanged. Source review only; browser and assistive-technology QA remains pending.


## 20. IBSng and product admin feedback accessibility (implemented)

- Extended consistent status/alert semantics to dynamic success and error feedback in `admin/ibsng-servers.php`, `admin/products.php`, and `admin/ibsng-users.php`.
- Dynamic success feedback uses a polite status region; dynamic errors use an assertive alert. Static prerequisite/help notices remain unchanged.
- No server configuration, product, IBSng account/renewal, validation, or CSRF behavior changed. Source review only; browser and assistive-technology QA remains pending.


## 21. Customer checkout/account feedback accessibility (implemented)

- Exposed the dynamic account lookup error in `customer-account.php` as an assertive alert.
- Exposed checkout validation/errors in `checkout.php` as alerts and the successful order tracking confirmation as a polite status announcement.
- Static checkout guidance notices and order-recovery flow were left unchanged; no account lookup, order creation, credential, payment, or validation logic changed. Source review only; browser and assistive-technology QA remains pending.


## 22. Admin settings feedback accessibility (implemented)

- `admin/settings.php`: expose successful settings-save feedback through a polite status region and validation/save errors through an assertive alert.
- This completes the same dynamic feedback semantics used across the other reviewed admin pages; static explanatory notices remain unchanged.
- Markup-only update; payment configuration, settings persistence, validation, CSRF, and webhook behavior were not changed. Source review only; browser and assistive-technology QA remains pending.


## 23. Admin dashboard database-error announcement (implemented)

- The dashboard's conditional database-statistics failure notice now uses an assertive alert live region so the error is announced when present.
- The notice text, dashboard queries, statistics, and database handling remain unchanged. Source review only; browser and assistive-technology QA remains pending.


### UI implementation note — shared accent-scheme propagation (2026-11-03)

- Replaced the shared stylesheet's fixed blue/violet glow, hover-border, and decorative highlight colors with `color-mix(in srgb, var(--accent) …, transparent)`, so these surfaces follow the selected accent scheme rather than remaining blue when the user chooses violet, teal, rose, or amber.
- The update is limited to shared CSS presentation tokens/effects. Theme persistence, language preference, page markup, form handlers, and all server-side behavior remain unchanged.
- Source-level verification: reviewed the shared stylesheet for remaining fixed blue/violet highlight references; only the original base palette variable definitions remain. Browser-based contrast and viewport QA is still pending.


### UI implementation note — public catalog English copy (2026-11-03)

- Added explicit localization keys to the storefront's static navigation, hero, section headings, catalog error/empty states, plan metadata labels, and purchase CTA. The shared preference layer now applies the English copy when English is selected and restores the original Persian copy when switching back.
- Catalog validity durations are rendered in English units under the English locale while retaining their existing Persian rendering in Persian. Product names/descriptions and configured store title remain operator-provided content and are not machine-translated.
- Only customer-facing text presentation and locale-aware duration labels were changed; product queries, prices, crypto visibility, checkout URLs, and order creation behavior remain untouched.
- Source inspection completed. Browser RTL/LTR, reload persistence, and responsive visual QA remain pending.


### UI implementation note — persisted locale for server-rendered customer messages (2026-11-03)

- The shared preference script now mirrors the selected Persian/English locale into a one-year, same-site language cookie (with `Secure` on HTTPS), alongside the existing localStorage preference.
- `app/messages.php` now resolves the default display locale from that validated cookie, while callers that explicitly pass a locale keep their existing behavior. This lets existing server-rendered checkout validation/status messages use the selected language without changing their keys or business decisions.
- Checkout's static customer-facing copy now has localization keys for its heading, navigation, order confirmation, tracking action, buyer fields, credential method choices, notices, and submit action. The shared language preference applies English copy and restores Persian copy when switched back; configured product names and store title remain as entered.
- Source review confirmed this work is limited to presentation/localization. Browser interaction, real form-submit language persistence, and responsive visual QA have not yet been run.


### UI implementation note — order recovery and order-status English localization (2026-11-03)

- Added English presentation copy for the unified order lookup/email recovery page: both entry paths, verification step, order-history labels, tracking ID placeholder, and order actions.
- Added English copy for order status details, payment/order states, account attributes, quota units, password disclosure, and activation-status labels. Dynamic status values use explicit status keys rather than translating arbitrary user/server data.
- Hard-coded order-recovery feedback now resolves through the shared bilingual message catalog, which follows the persisted locale cookie. Order lookup queries, email verification, CSRF, session expiry, payment/provisioning state, and account data are unchanged.
- Source-level checks only. Browser-based English/RTL/LTR and responsive QA, including recovery-code submissions and real order states, remains outstanding.


### UI implementation note — customer account lookup/details English localization (2026-11-03)

- Added English labels and explanatory copy for the customer account lookup form, CAPTCHA input, returned account details, validity, expiration, remaining days, and password reveal control. The reveal button label now follows the selected locale even when the password is already shown.
- Customer account lookup's generic validation and unavailable/not-found feedback now uses the shared bilingual message catalog. Server-provided service and plan names, usernames, credentials, and expiration values remain data values rather than being translated.
- Account lookup, CAPTCHA/session behavior, IBSng requests, password matching, and returned account data are unchanged. Source review only; browser-based language switching and visual verification remain pending.


### UI implementation note — customer account English localization (2026-11-03)

- Added English copy for customer account navigation, lookup labels/instructions, CAPTCHA label/placeholder, account result headings, service/plan/validity fields, expiration, remaining-day units, and password reveal controls.
- The password reveal control now keeps its visible/hidden action label synchronized with the active locale. Generic lookup validation and unavailable/not-found messages use the bilingual message catalog.
- This is UI/localization work only. CAPTCHA, session, IBSng lookup, credential matching, and account result behavior are unchanged. Source inspection only; browser and device QA remains pending.


### UI implementation note — administration dashboard English localization (2026-11-03)

- Added English copy for the dashboard navigation, support link, dashboard welcome/introduction, database-statistics error, metric labels, catalog-management summary, and storefront return link.
- Dashboard navigation and statistics regions now expose localized accessible names as well as visible copy. Existing destinations, logout POST/CSRF form, database statistics, and administration operations remain unchanged.
- Source review only; browser and responsive visual QA remains pending.


### UI implementation note — product administration English localization (2026-11-03)

- Added English copy to the product editor's core fields, pricing/USDT guidance, IBSng server/group mapping controls, helper text, publication state, saved product cards, and their routine actions.
- Dynamic active/inactive state and product validity duration labels follow the selected language. The duplicate-server and delete confirmation prompts now use the active locale, while retaining their existing native dialog behavior and action flow.
- Product persistence, validation rules, server/group mapping constraints, CSRF, product queries, and store publication behavior were not changed. Validation/error copy is not yet fully translated; browser QA and operation-state verification remain pending.


### UI implementation note — store settings and IRCrypto Webhook English localization (2026-11-03)

- Added English copy for support-contact fields, price display units, gateway selection and currency mode, crypto-price visibility guidance, credential-generation policy, renewal gift settings, and save/store actions.
- The IRCrypto Webhook helper text is now localization-keyed without changing its generated endpoint or its description of signature, status, amount, and currency validation. Provider-defined gateway field labels remain sourced from their registered module metadata.
- Settings values, gateway/provider selection, CSRF, validation, webhook routing, payment behavior, and renewal configuration are unchanged. Source review only; browser and gateway-settings visual QA remain pending.


### UI implementation note — localized browser document titles and translation-key integrity (2026-11-03)

- Browser-tab titles for checkout, order history, order status, customer account, admin dashboard, product management, and support settings now switch to English while preserving the configured store name; Persian titles are restored when switching back.
- Source-level consistency check: reviewed the eight modified UI pages plus the shared preference script and confirmed every literal `data-ui-text`, `data-ui-aria`, `data-ui-placeholder`, and `data-ui-title-key` used by those pages has a corresponding shared dictionary key. This is a source-key check, not a browser test.
- GitHub combined status for the latest settings/title change returned no status entries; no automated test result is inferred from an empty status response.


### UI implementation note — unified language preference across legacy IBSng admin pages (2026-11-03)

- Fixed a real preference synchronization gap in the legacy IBSng server, group-mapping, and account-management translation controls: their initial locale now prioritizes the shared `atd-ui-language` preference, then falls back to the legacy key.
- Page-level language toggles now write both preference keys and emit the shared language-change event. The shared toolbar listens for that event and updates its own visible/accessible language state and the persisted locale cookie.
- This keeps the toolbar, legacy page copy, document language/direction, and server-rendered message locale aligned after either language control is used. Only UI preference synchronization changed; IBSng server/account/group operations are unchanged.
- Source review verified the shared preference read, dual-key write, and event wiring across all three pages. Browser interaction/reload testing remains pending.


### UI implementation note — sales reporting and ambiguous-payment review English localization (2026-11-03)

- Added English copy for the sales report explanation, paid/pending metrics, report columns, empty states, ambiguous-payment review guidance, transaction table, review-note field, and release-for-retry action.
- The gateway confirmation prompt and review-note placeholder now follow the selected locale. Existing transaction/order identifiers and customer data remain unchanged.
- Payment review decision logic, CSRF, required review note, transaction resolution, and order retry release behavior were not changed. Dynamic server feedback is not yet comprehensively localized; source review only, with browser/functional QA still pending.


### UI implementation note — IBSng server and group-mapping page translation coverage (2026-11-03)

- Added localization keys for remaining IBSng server-page headings, introduction, host/port/admin credential labels, save-and-test action, empty state, and dynamic last-test/default/error labels. Server deletion confirmation now follows the active language.
- Added the missing group-mapping page title and “Add mapping” heading translation. Existing legacy dictionaries continue to handle their previously mapped copy and validation messages.
- Shared translation-key source check passed for the newly added server/group keys. IBSng server creation/edit/test, manual group mapping, validation, default selection, deletion constraints, and data remain unchanged. Browser QA remains pending.


### UI implementation note — IBSng account management page translation gaps (2026-11-03)

- Added English labels for the remaining renewal-plan placeholder and day unit, plus the existing-user group-change section, its prerequisite/empty state, username and destination fields, and submit action.
- Added an English browser title for IBSng account management. Existing legacy translation dictionary continues to cover the page's other matched labels and feedback.
- No account creation, renewal, group-change, or deletion logic was changed. Source key review only; browser and operational QA remains pending.


### UI implementation note — installer locale and shared preference integration (2026-11-03)

- Installer locale resolution now accepts the shared language cookie after an explicit POST/`?lang` choice and before the session fallback. The installer writes its server-rendered locale into the shared and legacy localStorage keys plus the language cookie before the shared preference script initializes.
- Shared preference controls now mount in the installer's existing top header row rather than falling back to the end of the page.
- Installer validation, database connection testing, schema installation, admin creation, and configuration-file writing are unchanged. Source review only; installer/browser visual QA remains pending.


### UI implementation note — email template and delivery settings English localization (2026-11-03)

- Added English labels and guidance for email-template event selection, subject/HTML/plain-text fields, variable help, save/preview actions, and preview frame title. Email event names use explicit event keys for translation.
- Added English copy for sender address/name, mail() delivery explanation, test-email destination, save/test actions, and template-management navigation.
- Actual email template bodies, subjects, text versions, event definitions, sender settings, mail transport, and delivery behavior are unchanged. Source review only; browser preview and test-email flow have not been exercised.


### UI implementation note — admin email tools and sign-in localization (2026-11-03)

- Email-template editor, email delivery settings, and admin sign-in now expose English labels, help text, document titles, and actions through the shared locale. Email event names use explicit event identifiers.
- Editable email subject/HTML/plain-text content and provider/user-configured values are deliberately preserved as entered rather than translated.
- Email transport, template persistence, preview rendering, login authentication, CSRF, and password-recovery behavior are unchanged. Source review only; browser preview, test email, and sign-in flows have not been run.
