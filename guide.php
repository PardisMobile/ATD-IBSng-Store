<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
$config = app_config();
$siteTitle = (string)($config['site_title'] ?? 'ATD IBSng Store');
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>راهنمای استفاده | <?= e($siteTitle) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.guide{max-width:850px;margin:32px auto;padding:clamp(20px,4vw,36px)}.guide h1{margin-top:0}.guide section{padding:18px 0;border-bottom:1px solid var(--border);line-height:1.9}.guide .muted{color:var(--muted)}.guide a{color:var(--accent,#9baaff)}
</style>
</head>
<body>
<main class="guide panel">
<p class="muted"><?= e($siteTitle) ?></p>
<h1>راهنمای استفاده از اکانت</h1>
<p class="muted">اطلاعات ورود اکانت خریداری‌شده در ایمیل تحویل اکانت و صفحه پیگیری سفارش درج می‌شود. مراحل اتصال به نوع سرویس و برنامه‌ای که ارائه‌دهنده شما پشتیبانی می‌کند بستگی دارد.</p>
<section><h2>۱. اطلاعات اکانت را پیدا کنید</h2><p>ایمیل تحویل اکانت را باز کنید یا از لینک پیگیری سفارش استفاده کنید. نام کاربری و رمز عبور را دقیقاً مطابق اطلاعات ارائه‌شده وارد کنید. به بزرگی و کوچکی حروف و فاصله‌های اضافی توجه داشته باشید.</p></section>
<section><h2>۲. برنامه یا روش اتصال مناسب را انتخاب کنید</h2><p>برنامه، سیستم‌عامل و روش اتصال باید با مشخصات سرویس خریداری‌شده مطابقت داشته باشد. از تنظیمات یا راهنمای اختصاصی ارائه‌دهنده سرویس استفاده کنید؛ این صفحه اطلاعات سرور یا پروتکل را حدس نمی‌زند.</p></section>
<section><h2>۳. اتصال و عیب‌یابی اولیه</h2><ul><li>نام کاربری و رمز عبور را دوباره بررسی کنید.</li><li>مطمئن شوید سرویس هنوز فعال و منقضی نشده است.</li><li>اگر اطلاعات مقصد یا روش اتصال در ایمیل مشخص شده، همان مورد را انتخاب کنید.</li><li>در صورت باقی‌ماندن مشکل، شناسه سفارش را هنگام تماس با پشتیبانی ارائه دهید؛ رمز عبور را در پیام عمومی منتشر نکنید.</li></ul></section>
<section><h2>پیگیری سفارش</h2><p>برای مشاهده وضعیت پرداخت، provisioning و جزئیات حساب، از صفحه پیگیری سفارش استفاده کنید.</p><p><a href="<?= e(app_url('order-status.php')) ?>">رفتن به پیگیری سفارش</a></p></section>
</main>
</body>
</html>
