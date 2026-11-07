<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_admin();

$config = app_config();
$stats = [
    'servers' => 0,
    'products' => 0,
    'orders' => 0,
    'admins' => 0,
];
$dbError = false;
try {
    $pdo = Database::connection();
    foreach (['servers' => 'ibsng_servers', 'products' => 'products', 'orders' => 'orders', 'admins' => 'admin_users'] as $key => $table) {
        $stats[$key] = (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }
} catch (Throwable $e) {
    $dbError = true;
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="<?= e(app_asset_url('assets/icons/atd-app-icon.svg')) ?>"><link rel="apple-touch-icon" href="<?= e(app_asset_url('assets/icons/atd-app-icon.svg')) ?>"><link rel="manifest" href="<?= e(app_asset_url('site.webmanifest')) ?>">
<title data-ui-title-key="title_admin_dashboard">داشبورد مدیریت | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto}
.admin-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:28px 0}
.stat{padding:22px}
.stat span{color:var(--muted);display:block}
.stat strong{font-size:2rem;display:block;margin-top:10px}
.admin-welcome{padding:clamp(24px,5vw,42px)}
.admin-welcome h1{font-size:2rem}
.notice{padding:14px;border:1px solid #ffc56a44;background:#ffc56a12;border-radius:12px;margin:16px 0}
@media(max-width:760px){.admin-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:460px){.admin-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<main class="admin-shell">
<?php require __DIR__ . '/_navigation.php'; ?>
<section class="admin-welcome panel">
<div class="eyebrow" data-ui-text="dashboard_eyebrow">داشبورد</div>
<h1><span data-ui-text="dashboard_greeting">سلام،</span> <?= e((string)($_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? 'مدیر')) ?></h1>
<p class="muted" data-ui-text="dashboard_intro">مدیریت سرورها، محصولات، سفارش‌ها و تنظیمات فروشگاه از این پنل در دسترس است.</p>
<?php if ($dbError): ?><div class="notice" role="alert" aria-live="assertive" data-ui-text="dashboard_database_error">خواندن آمار دیتابیس با خطا مواجه شد. اتصال و نصب schema را بررسی کنید.</div><?php endif; ?>
</section>
<section class="admin-grid" aria-label="آمار" data-ui-aria="dashboard_stats_aria">
<div class="stat panel"><span data-ui-text="dashboard_stat_servers">سرورهای IBSng</span><strong><?= $stats['servers'] ?></strong></div>
<div class="stat panel"><span data-ui-text="dashboard_stat_plans">پلن‌ها</span><strong><?= $stats['products'] ?></strong></div>
<div class="stat panel"><span data-ui-text="dashboard_stat_orders">سفارش‌ها</span><strong><?= $stats['orders'] ?></strong></div>
<div class="stat panel"><span data-ui-text="dashboard_stat_admins">مدیران</span><strong><?= $stats['admins'] ?></strong></div>
</section>
<section class="panel panel-card">
<h2 data-ui-text="dashboard_catalog_title">مدیریت کاتالوگ فروشگاه</h2>
<p class="muted" data-ui-text="dashboard_catalog_intro">از بخش محصولات، پلن‌های فروش را تعریف و به نگاشت فعال سرور/گروه IBSng وصل کنید. محصولات فعال در صفحه عمومی فروشگاه نمایش داده می‌شوند.</p>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
</main>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</body>
</html>
