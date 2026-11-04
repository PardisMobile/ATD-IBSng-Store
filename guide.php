<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/StorefrontView.php';
$config = app_config();
$siteTitle = (string)($config['site_title'] ?? 'ATD IBSng Store');
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title data-ui-title-key="title_guide">راهنمای استفاده | <?= e($siteTitle) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.guide{width:min(850px,calc(100% - 28px));margin:0 auto;padding:clamp(20px,4.5vw,38px);min-width:0;background:var(--surface);border:1px solid var(--border);border-radius:var(--ui-radius-lg,20px)}
.guide h1{margin:8px 0 14px;font-size:clamp(1.65rem,4vw,2.35rem);line-height:1.45;overflow-wrap:anywhere}
.guide>p{line-height:1.95}
.guide .muted{color:var(--muted)}
.guide section{position:relative;padding:clamp(16px,3vw,22px);margin:14px 0;border:1px solid var(--border);border-radius:var(--ui-radius-lg,18px);background:var(--surface-2);line-height:1.95;min-width:0;transition:border-color .18s ease,background-color .18s ease}
.guide section:hover{border-color:color-mix(in srgb,var(--accent,var(--blue)) 30%,var(--border))}
.guide section h2{font-size:clamp(1.05rem,2.5vw,1.25rem);line-height:1.6;margin:0 0 10px;overflow-wrap:anywhere}
.guide section p{margin:0;line-height:1.95}
.guide section ul{margin:8px 0 0;padding-inline-start:1.35rem}
.guide section li{padding:3px 0}
.guide a{color:var(--accent,var(--blue));text-decoration:underline;text-underline-offset:3px}
.guide section a{display:inline-flex;align-items:center;gap:8px;margin-top:4px;font-weight:700}
.guide section a:focus-visible{outline:2px solid var(--accent,var(--blue));outline-offset:4px;border-radius:4px}
@media(max-width:560px){.guide{width:calc(100% - 20px);border-radius:18px}.guide section{padding:15px}.guide section ul{padding-inline-start:1.1rem}}
@media(prefers-reduced-motion:reduce){.guide section{transition:none}}
</style>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</head>
<body>
<?php storefront_header($siteTitle, 'guide'); ?>
<main class="guide">
<h1 data-ui-text="guide_title">راهنمای استفاده از اکانت</h1>
<p class="muted" data-ui-text="guide_intro">اطلاعات ورود اکانت خریداری‌شده در ایمیل تحویل اکانت و صفحه پیگیری سفارش درج می‌شود. مراحل اتصال به نوع سرویس و برنامه‌ای که ارائه‌دهنده شما پشتیبانی می‌کند بستگی دارد.</p>
<section><h2 data-ui-text="guide_step_account_title">۱. اطلاعات اکانت را پیدا کنید</h2><p data-ui-text="guide_step_account_body">ایمیل تحویل اکانت را باز کنید یا از لینک پیگیری سفارش استفاده کنید. نام کاربری و رمز عبور را دقیقاً مطابق اطلاعات ارائه‌شده وارد کنید. به بزرگی و کوچکی حروف و فاصله‌های اضافی توجه داشته باشید.</p></section>
<section><h2 data-ui-text="guide_step_app_title">۲. برنامه یا روش اتصال مناسب را انتخاب کنید</h2><p data-ui-text="guide_step_app_body">برنامه، سیستم‌عامل و روش اتصال باید با مشخصات سرویس خریداری‌شده مطابقت داشته باشد. از تنظیمات یا راهنمای اختصاصی ارائه‌دهنده سرویس استفاده کنید؛ این صفحه اطلاعات سرور یا پروتکل را حدس نمی‌زند.</p></section>
<section><h2 data-ui-text="guide_step_troubleshoot_title">۳. اتصال و عیب‌یابی اولیه</h2><ul><li data-ui-text="guide_troubleshoot_credentials">نام کاربری و رمز عبور را دوباره بررسی کنید.</li><li data-ui-text="guide_troubleshoot_expiry">مطمئن شوید سرویس هنوز فعال و منقضی نشده است.</li><li data-ui-text="guide_troubleshoot_destination">اگر اطلاعات مقصد یا روش اتصال در ایمیل مشخص شده، همان مورد را انتخاب کنید.</li><li data-ui-text="guide_troubleshoot_support">در صورت باقی‌ماندن مشکل، شناسه سفارش را هنگام تماس با پشتیبانی ارائه دهید؛ رمز عبور را در پیام عمومی منتشر نکنید.</li></ul></section>
<section><h2 data-ui-text="guide_tracking_title">پیگیری سفارش</h2><p data-ui-text="guide_tracking_body">برای مشاهده وضعیت پرداخت، provisioning و جزئیات حساب، از صفحه پیگیری سفارش استفاده کنید.</p><p><a href="<?= e(app_url('order-status.php')) ?>" data-ui-text="guide_tracking_link">رفتن به پیگیری سفارش</a></p></section>
<?php storefront_footer($siteTitle); ?>
</main>
</body>
</html>
