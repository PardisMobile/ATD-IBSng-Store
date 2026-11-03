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
