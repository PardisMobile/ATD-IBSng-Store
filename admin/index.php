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
<a class="brand" href="<?= e(app_url('admin/')) ?>"><?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?> · مدیریت</a><a class="button" href="<?= e(app_url('admin/settings.php')) ?>"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z"/><path d="M19 12a7 7 0 0 0-.1-1.2l1.2-.9-1.5-2.6-1.4.5a7.8 7.8 0 0 0-1.5-.9l-.2-1.5h-3l-.2 1.5a7.8 7.8 0 0 0-1.5.9l-1.4-.5-1.5 2.6 1.1.9a6.8 6.8 0 0 0 0 1.8l-1.1.9 1.5 2.6 1.4-.5a7.8 7.8 0 0 0 1.5.9l.2 1.5h3l.2-1.5a7.8 7.8 0 0 0 1.5-.9l1.4.5 1.5-2.6-1.1-.9A7 7 0 0 0 19 12Z"/></svg> راه‌های پشتیبانی</a><a class="button" href="<?= e(app_url('admin/ibsng-servers.php')) ?>"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="3" width="16" height="7" rx="2"/><rect x="4" y="14" width="16" height="7" rx="2"/><path d="M8 6.5h.01M8 17.5h.01M12 6.5h4M12 17.5h4"/></svg> سرورهای IBSng</a><a class="button" href="<?= e(app_url('admin/ibsng-groups.php')) ?>"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h6l2 2h8v10H4z"/><path d="M4 7V5h6l2 2M9 13h6M12 10v6"/></svg> نگاشت پلن/گروه</a> <a class="button" href="<?= e(app_url('admin/ibsng-users.php')) ?>"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1M16 5a3 3 0 0 1 0 6M18 14a4 4 0 0 1 3 4v2"/></svg> مدیریت حساب‌های IBSng</a> <a class="button" href="<?= e(app_url('admin/products.php')) ?>"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m4 7 8-4 8 4-8 4zM4 7v10l8 4 8-4V7M12 11v10"/></svg> مدیریت محصولات</a> <a class="button" href="<?= e(app_url('admin/orders.php')) ?>"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5M4 19h17M8 16v-5M13 16V8M18 16V3"/></svg> گزارش فروش</a>
<form method="post" action="<?= e(app_url('admin/logout.php')) ?>"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><button class="button" type="submit"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/></svg> خروج</button></form>
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
