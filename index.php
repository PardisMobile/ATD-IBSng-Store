<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Pricing.php';
require_once __DIR__ . '/app/Payments/PaymentManager.php';

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

function store_duration_label(int $seconds): string
{
    if ($seconds % 86400 === 0) return (int)($seconds / 86400) . ' روز';
    if ($seconds % 3600 === 0) return (int)($seconds / 3600) . ' ساعت';
    if ($seconds % 60 === 0) return (int)($seconds / 60) . ' دقیقه';
    return number_format($seconds) . ' ثانیه';
}

function store_bytes_label(?string $bytes): string
{
    if ($bytes === null || $bytes === '') return 'Unlimited';
    $value = (float)$bytes;
    if ($value >= 1099511627776) return number_format($value / 1099511627776, 2) . ' TiB';
    if ($value >= 1073741824) return number_format($value / 1073741824, 2) . ' GiB';
    if ($value >= 1048576) return number_format($value / 1048576, 2) . ' MiB';
    if ($value >= 1024) return number_format($value / 1024, 2) . ' KiB';
    return number_format($value) . ' B';
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
  <style>
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
  <main class="store-shell">
    <header class="topbar">
      <a class="brand" href="<?= e(app_url()) ?>"><?= e($pageTitle) ?></a>
      <nav class="hero-actions" aria-label="دسترسی مشتری"><a class="button" href="<?= e(app_url('customer-account.php')) ?>">حساب کاربری</a><a class="button" href="<?= e(app_url('my-orders.php')) ?>">پیگیری و بازیابی خریدها</a><span class="status-chip">فروشگاه سرویس‌ها</span></nav>
    </header>
    <section class="hero panel">
      <div class="eyebrow">ATD • IBSng</div>
      <h1>سرویس موردنیازت رو ساده انتخاب کن</h1>
      <p>پلن‌های قابل ارائه را مشاهده کن؛ اطلاعات هر پلن بر اساس تنظیمات فروشگاه نمایش داده می‌شود.</p>
      <div class="hero-actions">
        <a class="button primary" href="#services">مشاهده سرویس‌ها</a>
        <span class="muted">بدون نیاز به ثبت‌نام فروشگاهی</span>
      </div>
    </section>
    <section id="services" class="section">
      <div class="section-heading">
        <div><div class="eyebrow">خدمات</div><h2>سرویس‌های فروشگاه</h2></div>
      </div>
      <?php if ($catalogError): ?>
        <div class="catalog-notice">نمایش محصولات فعلاً ممکن نیست. اتصال دیتابیس و نصب schema را بررسی کنید.</div>
      <?php elseif (!$products): ?>
        <div class="empty-state panel"><div class="empty-icon" aria-hidden="true"><svg class="ui-icon" viewBox="0 0 24 24"><path d="M12 3l1.9 5.8L20 11l-6.1 2.2L12 19l-1.9-5.8L4 11l6.1-2.2L12 3z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15z"/></svg></div><h3>هنوز پلنی برای فروش فعال نشده است</h3><p>مدیر فروشگاه می‌تواند از پنل مدیریت، محصولات را تعریف و برای نمایش فعال کند.</p></div>
      <?php else: ?>
        <div class="catalog-grid">
        <?php foreach ($products as $product): ?>
          <article class="plan-card panel">
            <div class="eyebrow">پلن سرویس</div>
            <h3><?= e((string)$product['name']) ?></h3>
            <p class="plan-description"><?= e((string)($product['description'] ?? '')) ?></p>
            <?php $displayPrice = store_format_price((int)$product['price_minor'], (string)$product['currency'], $priceDisplayUnit); ?>
            <div class="plan-price"><?= e($displayPrice['amount']) ?> <small><?= e($displayPrice['label']) ?></small></div>
            <?php if ($cryptoDisplayEnabled && $product['price_usdt'] !== null && (float)$product['price_usdt'] > 0): ?><div class="plan-meta"><span>قیمت کریپتو</span><strong><?= e(number_format((float)$product['price_usdt'], 8, '.', '')) ?> USDT</strong></div><?php endif; ?>
            <div class="plan-meta"><span>مدت اعتبار</span><strong><?= e(store_duration_label((int)$product['duration_seconds'])) ?></strong></div>
            <div class="plan-meta"><span>حجم</span><strong><?= e(store_bytes_label($product['quota_bytes'] === null ? null : (string)$product['quota_bytes'])) ?></strong></div>
            <a class="button primary" href="<?= e(app_url('checkout.php?product_id=' . (int)$product['id'])) ?>">ثبت سفارش</a>
          </article>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <footer>© <?= date('Y') ?> <?= e($pageTitle) ?> · <a class="admin-link" href="<?= e(app_url('admin/login.php')) ?>">ورود مدیریت</a></footer>
  </main>
  <script src="<?= e(app_url('assets/js/ui-preferences.js')) ?>" defer></script>
</body>
</html>
