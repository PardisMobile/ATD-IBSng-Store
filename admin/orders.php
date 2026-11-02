<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_once dirname(__DIR__) . '/app/Payments/PaymentTransactionService.php';
require_admin();

$config = app_config();
$pdo = Database::connection();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        $error = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } else {
        try {
            $transactionId = filter_var($_POST['transaction_id'] ?? null, FILTER_VALIDATE_INT);
            $note = trim((string)($_POST['resolution_note'] ?? ''));
            if ($transactionId === false || $transactionId === null || $transactionId < 1) {
                throw new InvalidArgumentException('شناسه تراکنش معتبر نیست.');
            }
            if ($note === '') {
                throw new InvalidArgumentException('یادداشت بررسی درگاه الزامی است.');
            }
            $service = new PaymentTransactionService($pdo);
            if (!$service->resolveAmbiguousInitiationAsFailed((int)$transactionId, $note)) {
                throw new RuntimeException('تراکنش تغییر کرده یا دیگر در وضعیت مبهم قابل تعیین تکلیف نیست.');
            }
            $message = 'تراکنش پس از ثبت یادداشت بررسی، ناموفق تعیین شد. امکان تلاش مجدد برای سفارش آزاد شد.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

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

$ambiguousSql = "SELECT t.id, t.order_id, t.provider, t.amount_minor, t.currency, t.created_at,
                        o.public_id, o.buyer_name, o.buyer_phone
                 FROM payment_transactions t
                 JOIN orders o ON o.id = t.order_id
                 WHERE t.status = 'initiated' AND t.provider_reference IS NULL
                 ORDER BY t.created_at ASC, t.id ASC";
$ambiguousRows = $pdo->query($ambiguousSql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>گزارش خرید و فروش | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto}.top{display:flex;justify-content:space-between;gap:14px;align-items:center;padding:24px 0}.stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:20px 0}.stat,.report{padding:20px}.muted{color:var(--muted);line-height:1.8}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{text-align:right;padding:13px;border-bottom:1px solid var(--border);vertical-align:top;white-space:nowrap}.notice{padding:12px 14px;border-radius:12px;margin:14px 0}.ok{border:1px solid #44d6a044;background:#44d6a018}.err{border:1px solid #ff718044;background:#ff718018}textarea{width:100%;min-width:220px;min-height:70px;box-sizing:border-box;margin:6px 0;padding:10px;border:1px solid var(--border);border-radius:9px;background:var(--surface-2);color:var(--text);font:inherit}button{font:inherit;cursor:pointer}.review-action{min-width:250px;white-space:normal}.section-gap{margin-top:22px}@media(max-width:600px){.top{align-items:flex-start;flex-direction:column}.stats{grid-template-columns:1fr}}
</style>
</head>
<body><main class="admin-shell">
<header class="top"><a class="brand" href="<?= e(app_url('admin/')) ?>">ATD IBSng Store · مدیریت</a><a class="button" href="<?= e(app_url('admin/')) ?>">داشبورد</a></header>
<section class="panel report"><div class="eyebrow">گزارشات</div><h1>گزارش خرید و فروش</h1><p class="muted">فروش بر اساس سفارش‌هایی محاسبه می‌شود که وضعیت پرداخت آن‌ها «پرداخت‌شده» است. سفارش‌های در انتظار پرداخت در فروش لحاظ نمی‌شوند. مقادیر از snapshot سفارش خوانده می‌شوند تا تغییر نام یا قیمت محصول گزارش تاریخی را تغییر ندهد.</p>
<?php if ($message !== ''): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
<div class="stats"><div class="panel stat"><span class="muted">کل سفارش‌های پرداخت‌شده</span><strong><?= number_format($totalPaid) ?></strong></div><div class="panel stat"><span class="muted">سفارش‌های در انتظار پرداخت</span><strong><?= number_format($totalPending) ?></strong></div></div>
<div class="table-wrap"><table><thead><tr><th>سرویس</th><th>تعداد سفارش پرداخت‌شده</th><th>فعال‌سازی موفق</th><th>نتیجه جزئی</th><th>مبلغ ناخالص</th><th>واحد</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="6">هنوز فروش پرداخت‌شده‌ای ثبت نشده است.</td></tr><?php endif; ?>
<?php foreach ($rows as $row): ?><tr><td><?= e((string)$row['service_name']) ?></td><td><?= number_format((int)$row['paid_orders']) ?></td><td><?= number_format((int)$row['active_sales']) ?></td><td><?= number_format((int)$row['partial_sales']) ?></td><td><?= number_format((int)$row['gross_amount']) ?></td><td><?= e((string)$row['currency']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>

<section class="section-gap" aria-labelledby="ambiguous-payments-title">
<h2 id="ambiguous-payments-title">بررسی موردی پرداخت‌های نامشخص</h2>
<p class="muted">این بخش فقط در موارد نادر پرداخت‌هایی را نشان می‌دهد که شروع آن‌ها در درگاه نتیجهٔ قطعی نداشته است. پیش از آزاد کردن سفارش برای تلاش مجدد، در پنل درگاه بررسی کنید که فاکتور قابل پرداختی برای آن ایجاد نشده باشد. این اقدام درگاه را لغو نمی‌کند و صرفاً وضعیت داخلی سفارش را تغییر می‌دهد.</p>
<div class="table-wrap"><table><thead><tr><th>تراکنش</th><th>سفارش</th><th>درگاه</th><th>مبلغ</th><th>خریدار</th><th>زمان ثبت</th><th>اقدام</th></tr></thead><tbody>
<?php if (!$ambiguousRows): ?><tr><td colspan="7">پرداخت مبهمی برای بررسی وجود ندارد.</td></tr><?php endif; ?>
<?php foreach ($ambiguousRows as $row): ?><tr>
<td>#<?= (int)$row['id'] ?></td><td><?= e((string)$row['public_id']) ?></td><td><?= e((string)$row['provider']) ?></td>
<td><?= e(number_format((int)$row['amount_minor'])) ?> <?= e((string)$row['currency']) ?></td>
<td><?= e((string)$row['buyer_name']) ?><br><?= e((string)$row['buyer_phone']) ?></td><td><?= e((string)$row['created_at']) ?></td>
<td class="review-action"><form method="post" onsubmit="return confirm('آیا در پنل درگاه بررسی کردید که فاکتور قابل پرداختی برای این سفارش وجود ندارد؟');">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="transaction_id" value="<?= (int)$row['id'] ?>">
<label for="note-<?= (int)$row['id'] ?>">یادداشت بررسی (الزامی)</label><textarea id="note-<?= (int)$row['id'] ?>" name="resolution_note" required maxlength="2000" placeholder="نتیجه بررسی درگاه را بنویسید"></textarea>
<button class="button" type="submit">ثبت ناموفق و امکان تلاش مجدد</button></form></td>
</tr><?php endforeach; ?>
</tbody></table></div>
</section>
</section><footer><a href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a></footer></main></body></html>
