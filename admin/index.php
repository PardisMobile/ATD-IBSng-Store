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
<title data-ui-title-key="title_admin_dashboard">داشبورد مدیریت | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto}.admin-top{display:grid;gap:16px;padding:24px 0}.admin-brand-row{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.admin-nav{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;padding:14px;border:1px solid var(--border);border-radius:var(--ui-radius-lg);background:var(--surface)}.admin-nav .button{display:flex;align-items:center;justify-content:center;min-height:44px;text-align:center;white-space:normal;line-height:1.5}.admin-nav form{margin:0;display:flex}.admin-nav form button{width:100%;border:1px solid var(--border);background:var(--surface-2);color:var(--text);font:inherit;cursor:pointer;border-radius:var(--ui-radius-md);min-height:44px}.admin-nav .button,.admin-nav form button{min-width:0;overflow-wrap:anywhere;transition:color .16s ease,background .16s ease,border-color .16s ease,box-shadow .16s ease,transform .16s ease}.admin-nav .button:hover:not(:disabled),.admin-nav form button:hover:not(:disabled){color:var(--text);border-color:color-mix(in srgb,var(--accent) 48%,var(--border));background:color-mix(in srgb,var(--surface-2) 76%,var(--accent) 24%);transform:translateY(-1px);box-shadow:0 5px 16px color-mix(in srgb,var(--accent) 10%,transparent)}.admin-nav .button:active:not(:disabled),.admin-nav form button:active:not(:disabled){background:color-mix(in srgb,var(--surface-2) 64%,var(--accent) 36%);transform:translateY(0) scale(.985);box-shadow:none}.admin-nav .button:focus-visible,.admin-nav form button:focus-visible{outline:2px solid var(--accent);outline-offset:3px;box-shadow:0 0 0 4px var(--ui-focus-ring)}.admin-nav .button[aria-current="page"]{color:var(--accent-foreground,#fff);background:var(--accent);border-color:transparent;box-shadow:0 7px 20px var(--ui-focus-ring)}.admin-nav form button:disabled{opacity:.52;cursor:not-allowed;transform:none;box-shadow:none}@media(prefers-reduced-motion:reduce){.admin-nav .button,.admin-nav form button{transition:none!important}.admin-nav .button:hover,.admin-nav .button:active,.admin-nav form button:hover,.admin-nav form button:active{transform:none!important}}.admin-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:28px 0}.stat{padding:22px}.stat span{color:var(--muted);display:block}.stat strong{font-size:2rem;display:block;margin-top:10px}.admin-welcome{padding:clamp(24px,5vw,42px)}.admin-welcome h1{font-size:2rem}.notice{padding:14px;border:1px solid #ffc56a44;background:#ffc56a12;border-radius:12px;margin:16px 0}@media(max-width:760px){.admin-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.admin-nav{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:460px){.admin-grid{grid-template-columns:1fr}.admin-nav{grid-template-columns:1fr}.admin-brand-row{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<main class="admin-shell">
<header class="admin-top">
<div class="admin-brand-row"><a class="brand" href="<?= e(app_url('admin/')) ?>"><?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?> · مدیریت</a><a class="button" href="<?= e(app_url('admin/settings.php')) ?>" data-ui-text="dashboard_support">راه‌های پشتیبانی</a></div>
<nav class="admin-nav" aria-label="بخش‌های مدیریت" data-ui-aria="dashboard_nav_aria">
<a class="button" href="<?= e(app_url('admin/email-templates.php')) ?>" data-ui-text="dashboard_email_templates">قالب‌های ایمیل</a>
<a class="button" href="<?= e(app_url('admin/email-settings.php')) ?>" data-ui-text="dashboard_email_settings">تنظیمات ارسال ایمیل</a>
<a class="button" href="<?= e(app_url('admin/ibsng-servers.php')) ?>" data-ui-text="dashboard_ibsng_servers">سرورهای IBSng</a>
<a class="button" href="<?= e(app_url('admin/ibsng-groups.php')) ?>" data-ui-text="dashboard_group_mapping">نگاشت پلن/گروه</a>
<a class="button" href="<?= e(app_url('admin/ibsng-users.php')) ?>" data-ui-text="dashboard_ibsng_users">مدیریت حساب‌های IBSng</a>
<a class="button" href="<?= e(app_url('admin/ibsng-renewal-migrate.php')) ?>" data-ui-text="dashboard_renewal_migration">ارتقای تمدید IBSng</a>
<a class="button" href="<?= e(app_url('admin/products.php')) ?>" data-ui-text="dashboard_products">مدیریت محصولات</a>
<a class="button" href="<?= e(app_url('admin/provisioning.php')) ?>" data-ui-text="dashboard_provisioning">صف Provisioning</a>
<a class="button" href="<?= e(app_url('admin/orders.php')) ?>" data-ui-text="dashboard_sales">گزارش فروش</a>
<form method="post" action="<?= e(app_url('admin/logout.php')) ?>"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><button class="button" type="submit" data-ui-text="dashboard_logout">خروج</button></form>
</nav>
</header>
<section class="admin-welcome panel">
<div class="eyebrow" data-ui-text="dashboard_eyebrow">داشبورد</div>
<h1><span data-ui-text="dashboard_greeting">سلام،</span> <?= e((string)($_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? 'مدیر')) ?></h1>
<p class="muted" data-ui-text="dashboard_intro">ورود مدیر و آمار پایه فعال‌اند. مدیریت سرورهای IBSng از بخش اختصاصی انجام می‌شود؛ قابلیت‌های بعدی پس از بررسی مرجع و آزمون اضافه خواهند شد.</p>
<?php if ($dbError): ?><div class="notice" role="alert" aria-live="assertive" data-ui-text="dashboard_database_error">خواندن آمار دیتابیس با خطا مواجه شد. اتصال و نصب schema را بررسی کنید.</div><?php endif; ?>
</section>
<section class="admin-grid" aria-label="آمار" data-ui-aria="dashboard_stats_aria">
<div class="stat panel"><span data-ui-text="dashboard_stat_servers">سرورهای IBSng</span><strong><?= $stats['servers'] ?></strong></div>
<div class="stat panel"><span data-ui-text="dashboard_stat_plans">پلن‌ها</span><strong><?= $stats['products'] ?></strong></div>
<div class="stat panel"><span data-ui-text="dashboard_stat_orders">سفارش‌ها</span><strong><?= $stats['orders'] ?></strong></div>
<div class="stat panel"><span data-ui-text="dashboard_stat_admins">مدیران</span><strong><?= $stats['admins'] ?></strong></div>
</section>
<section class="panel" style="padding:24px">
<h2 data-ui-text="dashboard_catalog_title">مدیریت کاتالوگ فروشگاه</h2>
<p class="muted" data-ui-text="dashboard_catalog_intro">از بخش محصولات، پلن‌های فروش را تعریف و به نگاشت فعال سرور/گروه IBSng وصل کنید. محصولات فعال در صفحه عمومی فروشگاه نمایش داده می‌شوند. ثبت سفارش، پرداخت و provisioning در حال تکمیل هستند.</p>
</section>
<footer><a href="<?= e(app_url()) ?>" data-ui-text="dashboard_back_store">بازگشت به فروشگاه</a></footer>
</main>
<script src="<?= e(app_url('assets/js/ui-preferences.js')) ?>" defer></script>
</body>
</html>
