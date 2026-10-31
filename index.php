<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$config = app_config();
$pageTitle = (string)($config['site_title'] ?? 'ATD IBSng Store');
$products = [];
$catalogError = false;
try {
    $pdo = Database::connection();
    $stmt = $pdo->query('SELECT id,name,description,duration_seconds,price_minor,currency,quota_bytes FROM products WHERE is_active=1 ORDER BY sort_order,id DESC');
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
    if ($bytes === null || $bytes === '') return 'طبق تنظیمات سرویس';
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
  .catalog-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:18px}.plan-card{padding:24px;display:flex;flex-direction:column;gap:12px}.plan-card h3{margin:0;font-size:1.25rem}.plan-price{font-size:1.8rem;font-weight:800}.plan-meta{display:flex;justify-content:space-between;gap:10px;color:var(--muted);padding:10px 0;border-bottom:1px solid var(--border)}.plan-description{color:var(--muted);line-height:1.8;min-height:48px}.catalog-notice{padding:18px;border:1px solid var(--border);border-radius:16px;background:var(--surface-2);line-height:1.8}.section{margin:30px 0}.section-heading{margin-bottom:18px}.section-heading h2{margin:6px 0}.admin-link{font-size:.9rem;color:var(--muted)}@media(max-width:560px){.plan-card{padding:18px}}
  </style>
</head>
<body>
  <main class="store-shell">
    <header class="topbar">
      <a class="brand" href="<?= e(app_url()) ?>"><?= e($pageTitle) ?></a>
      <span class="status-chip">فروشگاه سرویس‌های IBSng</span>
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
            <div class="plan-price"><?= number_format((int)$product['price_minor']) ?> <small><?= e((string)$product['currency']) ?></small></div>
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
</body>
</html>
