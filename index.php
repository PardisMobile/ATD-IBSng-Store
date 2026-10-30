<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$config = app_config();
$pageTitle = (string)($config['site_title'] ?? 'ATD IBSng Store');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
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
      <p>خرید و تمدید سرویس‌های اینترنتی در یک تجربه ساده و امن.</p>
      <div class="hero-actions">
        <a class="button primary" href="#services">مشاهده سرویس‌ها</a>
        <span class="muted">بدون نیاز به ثبت‌نام فروشگاهی</span>
      </div>
    </section>
    <section id="services" class="section">
      <div class="section-heading">
        <div><div class="eyebrow">خدمات</div><h2>سرویس‌های فروشگاه</h2></div>
      </div>
      <div class="empty-state panel">
        <div class="empty-icon">✦</div>
        <h3>سرویس‌ها به‌زودی نمایش داده می‌شوند</h3>
        <p>پس از اتصال و پیکربندی سرورهای IBSng و تعریف پلن‌ها، سرویس‌های قابل خرید در این قسمت قرار می‌گیرند.</p>
      </div>
    </section>
    <footer>© <?= date('Y') ?> <?= e($pageTitle) ?></footer>
  </main>
</body>
</html>
