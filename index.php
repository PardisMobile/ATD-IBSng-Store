<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Pricing.php';
require_once __DIR__ . '/app/Payments/PaymentManager.php';
require_once __DIR__ . '/app/StorefrontView.php';

$config = app_config();
$pageTitle = (string)($config['site_title'] ?? 'ATD IBSng Store');
$priceDisplayUnit = 'IRT';
$cryptoDisplayEnabled = false;
try {
    $pdoForSettings = Database::connection();
    $priceDisplayUnit = store_price_display_unit($pdoForSettings);
    $settingsQuery = $pdoForSettings->prepare(
        "SELECT setting_key, setting_value FROM app_settings
         WHERE setting_key IN ('crypto_gateway_enabled', 'payment_provider')"
    );
    $settingsQuery->execute();
    $storePaymentSettings = [];
    foreach ($settingsQuery->fetchAll(PDO::FETCH_ASSOC) as $settingRow) {
        $storePaymentSettings[(string)$settingRow['setting_key']] = (string)$settingRow['setting_value'];
    }
    $selectedProvider = strtolower(trim((string)($storePaymentSettings['payment_provider'] ?? '')));
    $showCryptoPrice = ($storePaymentSettings['crypto_gateway_enabled'] ?? '') === '1';
    $selectedTypeQuery = $pdoForSettings->prepare(
        'SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1'
    );
    if ($showCryptoPrice && $selectedProvider !== '') {
        $selectedTypeQuery->execute(['payment_' . $selectedProvider . '_type']);
        $selectedType = strtolower(trim((string)$selectedTypeQuery->fetchColumn()));
        if ($selectedType === 'crypto' && in_array($selectedProvider, PaymentManager::registeredCodes(), true)) {
            $cryptoDisplayEnabled = in_array('USDT', (new PaymentManager($selectedProvider))->supportedCurrencies(), true);
        }
    }
} catch (Throwable $e) {
    // Fail closed: do not advertise a crypto price unless the selected gateway
    // is configured for crypto and explicitly supports USDT.
}
$products = [];
$catalogError = false;
try {
    $pdo = Database::connection();
    $stmt = $pdo->query('SELECT id,name,description,duration_seconds,price_minor,price_usdt,currency,quota_bytes FROM products WHERE is_active=1 ORDER BY sort_order,id DESC');
    $products = $stmt->fetchAll();
} catch (Throwable $e) {
    $catalogError = true;
}

?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
  <style>
/* ATD Files Portal-inspired hero motion; storefront palette remains inherited. */
.storefront-hero{position:relative;isolation:isolate;overflow:hidden;min-height:300px;display:flex;align-items:center}
.storefront-hero-content{position:relative;z-index:2;max-width:min(66%,680px)}
.storefront-hero-art{position:absolute;z-index:1;inset-block:0;inset-inline-end:3%;width:min(38%,340px);min-width:230px;pointer-events:none}
.storefront-hero-glow{position:absolute;width:190px;height:190px;inset-inline-end:55px;top:48px;border-radius:50%;background:radial-gradient(circle,var(--accent-soft, color-mix(in srgb,var(--accent) 18%,transparent)),transparent 70%);filter:blur(6px);animation:storefrontHeroPulse 5s ease-in-out infinite}
.storefront-orbit{position:absolute;inset-inline-end:20px;top:76px;width:255px;height:128px;border:1px solid color-mix(in srgb,var(--accent) 34%,transparent);border-radius:50%;transform:rotate(-24deg)}
.storefront-orbit-one{animation:storefrontOrbitSpin 18s linear infinite}
.storefront-orbit-two{inset-inline-end:42px;top:52px;width:210px;height:168px;border-style:dashed;transform:rotate(48deg);opacity:.65;animation:storefrontOrbitSpin 28s linear infinite reverse}
.storefront-float-card{position:absolute;width:70px;height:80px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px;border:1px solid color-mix(in srgb,var(--accent) 30%,var(--border));border-radius:16px;background:color-mix(in srgb,var(--surface) 82%,transparent);box-shadow:0 14px 34px rgba(0,0,0,.14);backdrop-filter:blur(10px);color:var(--text);animation:storefrontFileFloat 5s ease-in-out infinite}
.storefront-float-card svg{width:27px;height:27px;stroke:currentColor}
.storefront-float-card span{font-size:9px;font-weight:800;letter-spacing:.08em}
.storefront-card-account{inset-inline-end:42px;top:38px;animation-delay:-1.2s}
.storefront-card-renew{inset-inline-end:158px;top:118px;color:var(--accent);animation-delay:-3s}
.storefront-card-payment{inset-inline-end:64px;top:178px;animation-delay:-2s}
@keyframes storefrontFileFloat{0%,100%{transform:translateY(0) rotate(-3deg)}50%{transform:translateY(-11px) rotate(3deg)}}
@keyframes storefrontOrbitSpin{from{rotate:0deg}to{rotate:360deg}}
@keyframes storefrontHeroPulse{0%,100%{opacity:.5;transform:scale(.92)}50%{opacity:1;transform:scale(1.08)}}
@media(max-width:760px){.storefront-hero{min-height:280px}.storefront-hero-content{max-width:100%;padding-inline-end:0}.storefront-hero-art{inset-inline-end:-75px;top:0;width:250px;height:210px;min-width:0;opacity:.24}.storefront-card-account{inset-inline-end:55px;top:15px}.storefront-card-renew{inset-inline-end:125px;top:95px}.storefront-card-payment{inset-inline-end:25px;top:125px}.storefront-hero-content{z-index:2}}
@media(prefers-reduced-motion:reduce){.storefront-hero-art,.storefront-hero-art *{animation:none!important}}
.catalog-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,270px),1fr));gap:clamp(14px,2.4vw,20px);align-items:stretch}
.plan-card{padding:clamp(18px,3vw,26px);display:flex;flex-direction:column;gap:13px;min-width:0;position:relative;overflow:hidden;transition:transform var(--ui-transition,180ms ease),border-color var(--ui-transition,180ms ease),box-shadow var(--ui-transition,180ms ease)}
.plan-card::before{content:"";position:absolute;inset-block-start:0;inset-inline:0;height:3px;background:var(--accent);opacity:.72}
.plan-card:hover{transform:translateY(-2px);border-color:var(--border-strong,var(--accent));box-shadow:var(--ui-shadow,0 18px 48px rgba(0,0,0,.18))}
.plan-card h3{margin:0;font-size:clamp(1.12rem,2vw,1.28rem);line-height:1.55;overflow-wrap:anywhere}
.plan-price{display:flex;align-items:baseline;flex-wrap:wrap;gap:7px;font-size:clamp(1.55rem,4vw,1.9rem);font-weight:800;line-height:1.4;color:var(--text);overflow-wrap:anywhere}
.plan-price small{font-size:.8rem;font-weight:600;color:var(--muted)}
.plan-meta{display:flex;justify-content:space-between;align-items:baseline;gap:12px;color:var(--muted);padding:10px 0;border-bottom:1px solid var(--border);min-width:0}
.plan-meta strong{text-align:end;color:var(--text);overflow-wrap:anywhere}
.plan-description{color:var(--muted);line-height:1.85;min-height:3.5em;margin:0;overflow-wrap:anywhere}
.plan-card>.button{width:100%;justify-content:center;margin-top:auto;white-space:normal}
.plan-card>.button:focus-visible{outline:2px solid var(--accent);outline-offset:3px;box-shadow:0 0 0 4px var(--ui-focus-ring)}
.plan-price,.plan-meta strong{font-variant-numeric:tabular-nums}
.topbar nav.hero-actions{display:flex;flex-wrap:wrap;align-items:center;gap:10px;min-width:0}
.topbar nav.hero-actions .button{white-space:normal;text-align:center}
@media(max-width:560px){.topbar nav.hero-actions{width:100%;justify-content:flex-start}.topbar nav.hero-actions .button{flex:1 1 auto}}
.catalog-notice{padding:clamp(15px,3vw,20px);border:1px solid var(--border);border-radius:var(--ui-radius-lg,16px);background:var(--surface-2);color:var(--text);line-height:1.85;overflow-wrap:anywhere}
.section{margin:clamp(26px,5vw,40px) 0}
.section-heading{display:flex;justify-content:space-between;align-items:flex-end;gap:14px;margin-bottom:18px}
.section-heading h2{margin:6px 0;line-height:1.45}
.admin-link{font-size:.9rem;color:var(--muted)}
.empty-state{min-width:0;padding:clamp(22px,5vw,36px);text-align:center}
.empty-state h3,.empty-state p{overflow-wrap:anywhere}
@media(max-width:560px){
 .catalog-grid{grid-template-columns:minmax(0,1fr)}
 .plan-card{padding:18px}
 .plan-description{min-height:0}
 .section-heading{align-items:flex-start;flex-direction:column}
 .plan-meta{gap:8px}
}
@media(prefers-reduced-motion:reduce){.plan-card{transition:none}.plan-card:hover{transform:none}}
</style>
</head>
<body>
  <?php storefront_header($pageTitle, 'home'); ?>
  <main class="store-shell">
    <section class="hero panel storefront-hero">
      <div class="storefront-hero-art" aria-hidden="true">
        <span class="storefront-orbit storefront-orbit-one"></span>
        <span class="storefront-orbit storefront-orbit-two"></span>
        <span class="storefront-hero-glow"></span>
        <div class="storefront-float-card storefront-card-account">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M18 4.5h2.5M19.25 3.25v2.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
          <span>ACCOUNT</span>
        </div>
        <div class="storefront-float-card storefront-card-renew">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M5.6 9a7 7 0 0 1 11.7-2L20 12M4 12l2.7 5a7 7 0 0 0 11.7-2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span>RENEW</span>
        </div>
        <div class="storefront-float-card storefront-card-payment">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3" stroke="currentColor" stroke-width="1.7"/><path d="M3 10h18M7 15h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
          <span>PAYMENT</span>
        </div>
      </div>
      <div class="storefront-hero-content">
        <div class="eyebrow">ATD • IBSng</div>
        <h1 data-ui-text="catalog_hero_title">سرویس موردنیازت رو ساده انتخاب کن</h1>
        <p data-ui-text="catalog_hero_intro">پلن‌های قابل ارائه را مشاهده کن؛ اطلاعات هر پلن بر اساس تنظیمات فروشگاه نمایش داده می‌شود.</p>
        <div class="hero-actions">
          <a class="button primary" href="#services" data-ui-text="catalog_view_services">مشاهده سرویس‌ها</a>
          <span class="muted" data-ui-text="catalog_no_registration">بدون نیاز به ثبت‌نام فروشگاهی</span>
        </div>
      </div>
    </section>
    <section id="services" class="section">
      <div class="section-heading">
        <div><div class="eyebrow" data-ui-text="catalog_section_eyebrow">خدمات</div><h2 data-ui-text="catalog_section_title">سرویس‌های فروشگاه</h2></div>
      </div>
      <?php if ($catalogError): ?>
        <div class="catalog-notice" role="alert" aria-live="polite" data-ui-text="catalog_load_error">فروشگاه در حال به‌روزرسانی است. لطفاً چند لحظه بعد دوباره تلاش کنید.</div>
      <?php elseif (!$products): ?>
        <div class="empty-state panel"><div class="empty-icon" aria-hidden="true"><svg class="ui-icon" viewBox="0 0 24 24"><path d="M12 3l1.9 5.8L20 11l-6.1 2.2L12 19l-1.9-5.8L4 11l6.1-2.2L12 3z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15z"/></svg></div><h3 data-ui-text="catalog_empty_title">هنوز پلنی برای فروش فعال نشده است</h3><p data-ui-text="catalog_empty_intro">مدیر فروشگاه می‌تواند از پنل مدیریت، محصولات را تعریف و برای نمایش فعال کند.</p></div>
      <?php else: ?>
        <div class="catalog-grid">
        <?php foreach ($products as $product): ?>
          <article class="plan-card panel">
            <div class="eyebrow" data-ui-text="catalog_plan_eyebrow">پلن سرویس</div>
            <h3><?= e((string)$product['name']) ?></h3>
            <p class="plan-description"><?= e((string)($product['description'] ?? '')) ?></p>
            <?php $displayPrice = store_format_price((int)$product['price_minor'], (string)$product['currency'], $priceDisplayUnit); ?>
            <div class="plan-price"><?= e($displayPrice['amount']) ?> <small><?= e($displayPrice['label']) ?></small></div>
            <?php if ($cryptoDisplayEnabled && $product['price_usdt'] !== null && (float)$product['price_usdt'] > 0): ?><div class="plan-meta"><span data-ui-text="catalog_crypto_price">قیمت کریپتو</span><strong><?= e(number_format((float)$product['price_usdt'], 8, '.', '')) ?> USDT</strong></div><?php endif; ?>
            <div class="plan-meta"><span data-ui-text="catalog_validity">مدت اعتبار</span><strong data-ui-duration="<?= (int)$product['duration_seconds'] ?>" data-ui-duration-fa="<?= e(storefront_duration_label((int)$product['duration_seconds'])) ?>"><?= e(storefront_duration_label((int)$product['duration_seconds'])) ?></strong></div>
            <?php $quotaLabel = storefront_bytes_label($product['quota_bytes'] === null ? null : (string)$product['quota_bytes']); ?>
            <div class="plan-meta"><span data-ui-text="catalog_quota">حجم</span><strong<?= $product['quota_bytes'] === null ? ' data-fa="نامحدود" data-en="Unlimited"' : '' ?>><?= e($quotaLabel) ?></strong></div>
            <a class="button primary" href="<?= e(app_url('checkout.php?product_id=' . (int)$product['id'])) ?>" data-ui-text="catalog_order">ثبت سفارش</a>
          </article>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <?php storefront_footer($pageTitle); ?>
  </main>
  <script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</body>
</html>
