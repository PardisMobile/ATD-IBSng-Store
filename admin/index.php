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
<title>داشبورد مدیریت | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto}.admin-top{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:24px 0}.admin-top form{margin:0}.admin-top button{border:1px solid var(--border);background:var(--surface-2);color:var(--text);font:inherit;cursor:pointer}.admin-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:28px 0}.stat{padding:22px}.stat span{color:var(--muted);display:block}.stat strong{font-size:2rem;display:block;margin-top:10px}.admin-welcome{padding:clamp(24px,5vw,42px)}.admin-welcome h1{font-size:2rem}.notice{padding:14px;border:1px solid #ffc56a44;background:#ffc56a12;border-radius:12px;margin:16px 0}@media(max-width:760px){.admin-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:460px){.admin-grid{grid-template-columns:1fr}.admin-top{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<main class="admin-shell">
<header class="admin-top">
<a class="brand" href="<?= e(app_url('admin/')) ?>"><?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?> · مدیریت</a><a class="button" href="<?= e(app_url('admin/ibsng-servers.php')) ?>">سرورهای IBSng</a><a class="button" href="<?= e(app_url('admin/ibsng-groups.php')) ?>">نگاشت پلن/گروه</a> <a class="button" href="<?= e(app_url('admin/ibsng-users.php')) ?>">مدیریت حساب‌های IBSng</a> <a class="button" href="<?= e(app_url('admin/products.php')) ?>">مدیریت محصولات</a>
<form method="post" action="<?= e(app_url('admin/logout.php')) ?>"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><button class="button" type="submit">خروج</button></form>
</header>
<section class="admin-welcome panel">
<div class="eyebrow">داشبورد</div>
<h1>سلام، <?= e((string)($_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? 'مدیر')) ?></h1>
<p class="muted">ورود مدیر و آمار پایه فعال‌اند. مدیریت سرورهای IBSng از بخش اختصاصی انجام می‌شود؛ قابلیت‌های بعدی پس از بررسی مرجع و آزمون اضافه خواهند شد.</p>
<?php if ($dbError): ?><div class="notice">خواندن آمار دیتابیس با خطا مواجه شد. اتصال و نصب schema را بررسی کنید.</div><?php endif; ?>
</section>
<section class="admin-grid" aria-label="آمار">
<div class="stat panel"><span>سرورهای IBSng</span><strong><?= $stats['servers'] ?></strong></div>
<div class="stat panel"><span>پلن‌ها</span><strong><?= $stats['products'] ?></strong></div>
<div class="stat panel"><span>سفارش‌ها</span><strong><?= $stats['orders'] ?></strong></div>
<div class="stat panel"><span>مدیران</span><strong><?= $stats['admins'] ?></strong></div>
</section>
<section class="panel" style="padding:24px">
<h2>مدیریت کاتالوگ فروشگاه</h2>
<p class="muted">از بخش محصولات، پلن‌های فروش را تعریف و به نگاشت فعال سرور/گروه IBSng وصل کنید. محصولات فعال در صفحه عمومی فروشگاه نمایش داده می‌شوند. ثبت سفارش، پرداخت و provisioning در حال تکمیل هستند.</p>
</section>
<footer><a href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a></footer>
</main>
</body>
</html>
