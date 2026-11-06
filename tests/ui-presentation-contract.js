'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

const adminPages = [
  'index.php',
  'ibsng-servers.php',
  'ibsng-groups.php',
  'ibsng-users.php',
  'products.php',
  'provisioning.php',
  'orders.php',
  'email-settings.php',
  'email-templates.php',
  'settings.php',
  'upgrade.php',
];

for (const page of adminPages) {
  const source = read(path.join('admin', page));
  assert.match(source, /require __DIR__ \. '\/_navigation\.php'/u, `${page} must use the shared admin navigation`);
  assert.match(source, /require __DIR__ \. '\/_footer\.php'/u, `${page} must use the shared admin footer`);
  assert.doesNotMatch(source, /<footer>/iu, `${page} must not duplicate shell actions in a page footer`);
  assert.doesNotMatch(source, /langToggle|const pairs\s*=/u, `${page} must not keep a page-local language system`);
}

const publicPages = [
  'index.php',
  'checkout.php',
  'my-orders.php',
  'order-status.php',
  'customer-account.php',
  'guide.php',
  'payment-callback.php',
];

for (const page of publicPages) {
  const source = read(page);
  assert.match(source, /storefront_header\(/u, `${page} must use the shared storefront header`);
  assert.match(source, /storefront_footer\(/u, `${page} must use the shared storefront footer`);
  assert.match(source, /ui-preferences\.js/u, `${page} must load the shared appearance and language controls`);
  assert.match(source, /ui-components\.js/u, `${page} must load the shared component behavior`);
  assert.match(source, /assets\/css\/unified\.css/u, `${page} must load the shared component styles`);
  assert.doesNotMatch(source, /href="<\?= e\(app_url\(\)\) \?>"/u, `${page} must not emit an empty root link`);
  assert.ok(source.indexOf('storefront_header(') < source.indexOf('<main'), `${page} must render the shared storefront header above its narrower content shell`);
}

const navigation = read('admin/_navigation.php');
assert.match(navigation, /aria-current="page"/u, 'Admin navigation must expose the active page');
assert.match(navigation, /atd-admin-preferences-slot/u, 'Admin header must own the appearance controls');
assert.match(navigation, /class="ui-icon"/u, 'Admin navigation must use the shared SVG icon style');
assert.doesNotMatch(navigation, /langToggle/u, 'Admin header must not expose a second language control');

const css = read('assets/css/app.css') + read('assets/css/unified.css');
for (const selector of [
  '.store-header',
  '.atd-store-nav',
  '.atd-admin-nav',
  '.atd-preferences',
  '.atd-confirm-dialog',
  '.table-wrap',
  'input:-webkit-autofill',
  ':autofill',
  '@media(max-width:680px)',
  '@media(prefers-reduced-motion:reduce)',
]) {
  assert.ok(css.includes(selector), `Shared stylesheet is missing ${selector}`);
}

for (const page of [...publicPages, ...adminPages.map((file) => path.join('admin', file))]) {
  const source = read(page);
  if (!source.includes('assets/css/app.css')) continue;
  assert.match(source, /app_asset_url\('assets\/css\/app\.css'\)/u, `${page} must cache-bust application CSS`);
  assert.match(source, /app_asset_url\('assets\/css\/unified\.css'\)/u, `${page} must cache-bust component CSS`);
  assert.match(source, /app_asset_url\('assets\/js\/ui-preferences\.js'\)/u, `${page} must cache-bust preference JavaScript`);
  assert.match(source, /app_asset_url\('assets\/js\/ui-components\.js'\)/u, `${page} must cache-bust component JavaScript`);
}

for (const page of [...publicPages, ...adminPages.map((file) => path.join('admin', file))]) {
  const source = read(page);
  assert.doesNotMatch(source, /onsubmit="return confirm\(|onclick="[^"]*confirm\(/u, `${page} still uses a native confirmation dialog`);
}

const script = read('assets/js/ui-preferences.js');
assert.match(script, /data-fa/u, 'Shared JavaScript must apply bilingual labels');

const ibsngUsers = read(path.join('admin', 'ibsng-users.php'));
assert.match(ibsngUsers, /app_url\('admin\/upgrade\.php'\)/u, 'IBSng users must link to the current database upgrade page');
assert.doesNotMatch(ibsngUsers, /ibsng-renewal-migrate\.php/u, 'IBSng users must not link to the retired upgrade path');
for (const [source, translated] of [
  ['First Login', 'اولین ورود'],
  ['Last Login', 'آخرین ورود'],
  ['Relative Expiration', 'انقضای نسبی'],
  ['Creation Date', 'تاریخ ایجاد'],
]) {
  assert.ok(ibsngUsers.includes(source) && ibsngUsers.includes(translated), `IBSng account detail label is missing: ${source}`);
}
assert.match(ibsngUsers, /Translate field labels only\. IBSng values remain exactly as received\./u, 'IBSng values must remain raw while labels are translated');

const components = read('assets/js/ui-components.js');
assert.match(components, /atd-confirm-dialog/u, 'Shared JavaScript must provide the styled confirmation dialog');
assert.match(components, /data-ui-password-toggle/u, 'Password visibility must use the shared behavior');

for (const page of adminPages.map((file) => path.join('admin', file))) {
  const source = read(page);
  assert.match(source, /ui-components\.js/u, `${page} must load shared component behavior`);
  assert.match(source, /assets\/css\/unified\.css/u, `${page} must load shared component styles`);
}

console.log('UI presentation contract: PASS');
