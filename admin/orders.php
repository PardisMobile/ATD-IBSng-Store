<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_admin();

$config = app_config();
$pdo = Database::connection();

// Paid-order sales report by immutable product name snapshot.
// Count paid orders whose provisioning has reached a non-pending terminal/result state.
$sql = "SELECT product_name_snapshot AS service_name,
               COUNT(*) AS paid_orders,
               SUM(CASE WHEN order_status='active' THEN 1 ELSE 0 END) AS active_sales,
               SUM(CASE WHEN order_status='partial' THEN 1 ELSE 0 END) AS partial_sales,
               SUM(price_minor_snapshot) AS gross_amount,
               currency
        FROM orders
        WHERE payment_status='paid'
        GROUP BY product_name_snapshot, currency
        ORDER BY paid_orders DESC, service_name ASC";
$rows = $pdo->query($sql)->fetchAll();
$totalPaid = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status='paid'")->fetchColumn();
$totalPending = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status='pending'")->fetchColumn();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>گزارش فروش | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto}.top{display:flex;justify-content:space-between;gap:14px;align-items:center;padding:24px 0}.stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:20px 0}.stat,.report{padding:20px}.muted{color:var(--muted);line-height:1.8}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{text-align:right;padding:13px;border-bottom:1px solid var(--border);white-space:nowrap}@media(max-width:600px){.top{align-items:flex-start;flex-direction:column}.stats{grid-template-columns:1fr}}
</style>
</head>
<body><main class="admin-shell">
<header class="top"><a class="brand" href="<?= e(app_url('admin/')) ?>">ATD IBSng Store · مدیریت</a><a class="button" href="<?= e(app_url('admin/')) ?>">داشبورد</a></header>
<section class="panel report"><div class="eyebrow">گزارشات</div><h1>گزارش فروش به تفکیک سرویس</h1><p class="muted">فروش بر اساس سفارش‌هایی محاسبه می‌شود که وضعیت پرداخت آن‌ها در دیتابیس «paid» است. سفارش‌های pending در فروش لحاظ نمی‌شوند. مقادیر از snapshot سفارش خوانده می‌شوند تا تغییر نام/قیمت محصول گزارش تاریخی را تغییر ندهد.</p>
<div class="stats"><div class="panel stat"><span class="muted">کل سفارش‌های پرداخت‌شده</span><strong><?= number_format($totalPaid) ?></strong></div><div class="panel stat"><span class="muted">سفارش‌های در انتظار پرداخت</span><strong><?= number_format($totalPending) ?></strong></div></div>
<div class="table-wrap"><table><thead><tr><th>سرویس</th><th>تعداد سفارش پرداخت‌شده</th><th>فعال‌سازی موفق</th><th>نتیجه جزئی</th><th>مبلغ ناخالص</th><th>واحد</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="6">هنوز فروش پرداخت‌شده‌ای ثبت نشده است.</td></tr><?php endif; ?>
<?php foreach ($rows as $row): ?><tr><td><?= e((string)$row['service_name']) ?></td><td><?= number_format((int)$row['paid_orders']) ?></td><td><?= number_format((int)$row['active_sales']) ?></td><td><?= number_format((int)$row['partial_sales']) ?></td><td><?= number_format((int)$row['gross_amount']) ?></td><td><?= e((string)$row['currency']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></section><footer><a href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a></footer></main></body></html>
