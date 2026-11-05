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
               CASE WHEN operation='renew' THEN 'تمدید' ELSE 'خرید' END AS operation_label,
               COUNT(*) AS paid_orders,
               SUM(CASE WHEN order_status='active' THEN 1 ELSE 0 END) AS active_sales,
               SUM(CASE WHEN order_status='partial' THEN 1 ELSE 0 END) AS partial_sales,
               SUM(price_minor_snapshot) AS gross_amount,
               currency
        FROM orders
        WHERE payment_status='paid'
        GROUP BY product_name_snapshot, currency, operation
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
<title data-ui-title-key="title_admin_orders">گزارش خرید و فروش | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto}.top{display:flex;justify-content:space-between;gap:14px;align-items:center;padding:24px 0}.stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:20px 0}.stat,.report{padding:20px}.muted{color:var(--muted);line-height:1.8}.table-wrap{overflow:auto;max-width:100%;border:1px solid var(--border);border-radius:var(--ui-radius-md,12px);overscroll-behavior-inline:contain}table{width:100%;border-collapse:collapse}th,td{text-align:start;padding:13px;border-bottom:1px solid var(--border);vertical-align:top;white-space:normal;overflow-wrap:anywhere}th{background:var(--surface-2);color:var(--muted);font-size:.9rem;font-weight:650}tbody tr:last-child td{border-bottom:0}tbody tr:hover{background:color-mix(in srgb,var(--surface-2) 55%,transparent)}.notice{padding:12px 14px;border-radius:var(--ui-radius-md,12px);margin:14px 0}.ok{border:1px solid #44d6a044;background:#44d6a018}.err{border:1px solid #ff718044;background:#ff718018}textarea{display:block;width:100%;min-width:220px;min-height:70px;box-sizing:border-box;margin:6px 0;padding:10px 12px;border:1px solid var(--border);border-radius:var(--ui-radius-md,12px);background:var(--control-bg,var(--surface-2));color:var(--text);font:inherit;resize:vertical}textarea:focus-visible{outline:2px solid var(--accent);outline-offset:1px;box-shadow:0 0 0 3px var(--ui-focus-ring)}button{font:inherit;cursor:pointer}.review-action{min-width:250px;white-space:normal}.numeric-value{direction:ltr;unicode-bidi:plaintext;display:inline-block;text-align:left;font-variant-numeric:tabular-nums;max-width:100%;overflow-wrap:anywhere}.order-reference{direction:ltr;unicode-bidi:plaintext;display:inline-block;text-align:left;overflow-wrap:anywhere}.section-gap{margin-top:22px}@media(max-width:600px){.top{align-items:flex-start;flex-direction:column}.stats{grid-template-columns:1fr}}
</style>
</head>
<body><main class="admin-shell">
<?php require __DIR__ . '/_navigation.php'; ?>
<section class="panel report"><div class="eyebrow" data-ui-text="orders_reports_eyebrow">گزارشات</div><h1 data-ui-text="orders_report_title">گزارش خرید و فروش</h1><p class="muted" data-ui-text="orders_report_intro">فروش بر اساس سفارش‌هایی محاسبه می‌شود که وضعیت پرداخت آن‌ها «پرداخت‌شده» است. سفارش‌های در انتظار پرداخت در فروش لحاظ نمی‌شوند. مقادیر از snapshot سفارش خوانده می‌شوند تا تغییر نام یا قیمت محصول گزارش تاریخی را تغییر ندهد.</p>
<?php if ($message !== ''): ?><div class="notice ok" role="status" aria-live="polite"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err" role="alert" aria-live="assertive"><?= e($error) ?></div><?php endif; ?>
<div class="stats"><div class="panel stat"><span class="muted" data-ui-text="orders_total_paid">کل سفارش‌های پرداخت‌شده</span><strong><?= number_format($totalPaid) ?></strong></div><div class="panel stat"><span class="muted" data-ui-text="orders_total_pending">سفارش‌های در انتظار پرداخت</span><strong><?= number_format($totalPending) ?></strong></div></div>
<div class="table-wrap"><table><thead><tr><th data-ui-text="orders_service">سرویس</th><th>نوع عملیات</th><th data-ui-text="orders_paid_count">تعداد سفارش پرداخت‌شده</th><th data-ui-text="orders_activation_success">فعال‌سازی موفق</th><th data-ui-text="orders_partial_result">نتیجه جزئی</th><th data-ui-text="orders_gross_amount">مبلغ ناخالص</th><th data-ui-text="orders_unit">واحد</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="7" data-ui-text="orders_no_paid_sales">هنوز فروش پرداخت‌شده‌ای ثبت نشده است.</td></tr><?php endif; ?>
<?php foreach ($rows as $row): ?><tr><td><?= e((string)$row['service_name']) ?></td><td><?= e((string)$row['operation_label']) ?></td><td><?= number_format((int)$row['paid_orders']) ?></td><td><?= number_format((int)$row['active_sales']) ?></td><td><?= number_format((int)$row['partial_sales']) ?></td><td><?= number_format((int)$row['gross_amount']) ?></td><td><?= e((string)$row['currency']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>

<section class="section-gap" aria-labelledby="ambiguous-payments-title">
<h2 id="ambiguous-payments-title" data-ui-text="orders_ambiguous_title">بررسی موردی پرداخت‌های نامشخص</h2>
<p class="muted" data-ui-text="orders_ambiguous_intro">این بخش فقط در موارد نادر پرداخت‌هایی را نشان می‌دهد که شروع آن‌ها در درگاه نتیجهٔ قطعی نداشته است. پیش از آزاد کردن سفارش برای تلاش مجدد، در پنل درگاه بررسی کنید که فاکتور قابل پرداختی برای آن ایجاد نشده باشد. این اقدام درگاه را لغو نمی‌کند و صرفاً وضعیت داخلی سفارش را تغییر می‌دهد.</p>
<div class="table-wrap"><table><thead><tr><th data-ui-text="orders_transaction">تراکنش</th><th data-ui-text="orders_order">سفارش</th><th data-ui-text="orders_gateway">درگاه</th><th data-ui-text="orders_amount">مبلغ</th><th data-ui-text="orders_buyer">خریدار</th><th data-ui-text="orders_created_at">زمان ثبت</th><th data-ui-text="orders_action">اقدام</th></tr></thead><tbody>
<?php if (!$ambiguousRows): ?><tr><td colspan="7" data-ui-text="orders_no_ambiguous">پرداخت مبهمی برای بررسی وجود ندارد.</td></tr><?php endif; ?>
<?php foreach ($ambiguousRows as $row): ?><tr>
<td><span class="numeric-value">#<?= (int)$row['id'] ?></span></td><td><span class="order-reference"><?= e((string)$row['public_id']) ?></span></td><td><?= e((string)$row['provider']) ?></td>
<td><span class="numeric-value"><?= e(number_format((int)$row['amount_minor'])) ?> <?= e((string)$row['currency']) ?></span></td>
<td><?= e((string)$row['buyer_name']) ?><br><span class="numeric-value"><?= e((string)$row['buyer_phone']) ?></span></td><td><span class="numeric-value"><?= e((string)$row['created_at']) ?></span></td>
<td class="review-action"><form method="post" data-confirm-fa="آیا در پنل درگاه بررسی کردید که فاکتور قابل پرداختی برای این سفارش وجود ندارد؟" data-confirm-en="Did you check the gateway dashboard and confirm that no payable invoice exists for this order?">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="transaction_id" value="<?= (int)$row['id'] ?>">
<label for="note-<?= (int)$row['id'] ?>" data-ui-text="orders_review_note">یادداشت بررسی (الزامی)</label><textarea id="note-<?= (int)$row['id'] ?>" name="resolution_note" required maxlength="2000" placeholder="نتیجه بررسی درگاه را بنویسید" data-ui-placeholder="orders_review_placeholder"></textarea>
<button class="button" type="submit" data-ui-text="orders_mark_failed_retry">ثبت ناموفق و امکان تلاش مجدد</button></form></td>
</tr><?php endforeach; ?>
</tbody></table></div>
</section>
</section><?php require __DIR__ . '/_footer.php'; ?></main><script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</body></html>
