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

// Sales are counted only when the order is paid AND its gateway transaction
// is verified. The verified transaction is the immutable source of actual paid
// amount; order price snapshots are shown separately as historical list prices.
$sql = "SELECT
            o.product_name_snapshot AS service_name,
            CASE
                WHEN COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(o.server_targets_snapshot, '$[0].operation')),
                    'create'
                ) = 'renew'
                THEN 'تمدید'
                ELSE 'خرید'
            END AS operation_label,
            COUNT(DISTINCT o.id) AS paid_orders,
            COUNT(DISTINCT CASE WHEN o.order_status = 'active' THEN o.id END) AS active_sales,
            COUNT(DISTINCT CASE WHEN o.order_status = 'partial' THEN o.id END) AS partial_sales,
            SUM(t.amount_minor) AS paid_amount,
            t.currency
        FROM orders o
        INNER JOIN payment_transactions t ON t.order_id = o.id AND t.status = 'verified'
        WHERE o.payment_status = 'paid'
        GROUP BY
            o.product_name_snapshot,
            t.currency,
            CASE
                WHEN COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(o.server_targets_snapshot, '$[0].operation')),
                    'create'
                ) = 'renew'
                THEN 'تمدید'
                ELSE 'خرید'
            END
        ORDER BY paid_orders DESC, service_name ASC";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$totalPaid = (int)$pdo->query("SELECT COUNT(*) FROM orders o WHERE o.payment_status='paid' AND EXISTS (SELECT 1 FROM payment_transactions t WHERE t.order_id=o.id AND t.status='verified')")->fetchColumn();
$totalPending = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status='pending'")->fetchColumn();
$totalSalesRows = $pdo->query("SELECT t.currency, SUM(t.amount_minor) AS total_amount
    FROM payment_transactions t
    INNER JOIN orders o ON o.id=t.order_id
    WHERE t.status='verified' AND o.payment_status='paid'
    GROUP BY t.currency ORDER BY t.currency")->fetchAll(PDO::FETCH_ASSOC);

// Recent individual paid orders. A verified transaction is unique per order
// under PaymentTransactionService's locked paid-state transition.
$orderSql = "SELECT o.id, o.public_id, o.tracking_code, o.buyer_name, o.buyer_phone,
                    o.buyer_email, o.username, o.product_name_snapshot, o.price_minor_snapshot,
                    o.price_usdt_snapshot, o.currency AS order_currency, o.payment_status,
                    o.order_status, o.created_at, o.server_targets_snapshot,
                    t.provider, t.provider_reference, t.amount_minor AS paid_amount,
                    t.currency AS paid_currency, t.verified_at
             FROM orders o
             INNER JOIN payment_transactions t ON t.order_id=o.id AND t.status='verified'
             WHERE o.payment_status='paid'
             ORDER BY o.created_at DESC, o.id DESC LIMIT 100";
$orderRows = $pdo->query($orderSql)->fetchAll(PDO::FETCH_ASSOC);
$orderIds = array_map(static fn(array $r): int => (int)$r['id'], $orderRows);
$attemptsByOrder = [];
if ($orderIds !== []) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $attemptSql = "SELECT a.order_id, a.operation, a.status, a.remote_user_id, a.error_message,
                          a.created_at, s.name AS server_name
                   FROM provisioning_attempts a
                   LEFT JOIN ibsng_servers s ON s.id=a.server_id
                   WHERE a.order_id IN ($placeholders)
                   ORDER BY a.order_id, a.id";
    $attemptStmt = $pdo->prepare($attemptSql);
    $attemptStmt->execute($orderIds);
    foreach ($attemptStmt->fetchAll(PDO::FETCH_ASSOC) as $attempt) {
        $attemptsByOrder[(int)$attempt['order_id']][] = $attempt;
    }
}

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
<link rel="icon" type="image/svg+xml" href="<?= e(app_asset_url('assets/icons/atd-app-icon.svg')) ?>"><link rel="apple-touch-icon" sizes="180x180" href="<?= e(app_asset_url('assets/icons/apple-touch-icon.png')) ?>"><link rel="manifest" href="<?= e(app_asset_url('site.webmanifest')) ?>">
<title data-ui-title-key="title_admin_orders">گزارش خرید و فروش | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto}.top{display:flex;justify-content:space-between;gap:14px;align-items:center;padding:24px 0}.stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:20px 0}.stat,.report{padding:20px}.stat strong{display:block;margin-top:10px;font-size:1.65rem;overflow-wrap:anywhere}.sales-total-line{display:block;font-size:1.25rem;line-height:1.8}.sales-total-line small{font-size:.8rem;color:var(--muted);font-weight:600}.muted{color:var(--muted);line-height:1.8}.table-wrap{overflow:auto;max-width:100%;border:1px solid var(--border);border-radius:var(--ui-radius-md,12px);overscroll-behavior-inline:contain}table{width:100%;border-collapse:collapse}th,td{text-align:start;padding:13px;border-bottom:1px solid var(--border);vertical-align:top;white-space:normal;overflow-wrap:anywhere}th{background:var(--surface-2);color:var(--muted);font-size:.9rem;font-weight:650}tbody tr:last-child td{border-bottom:0}tbody tr:hover{background:color-mix(in srgb,var(--surface-2) 55%,transparent)}.notice{padding:12px 14px;border-radius:var(--ui-radius-md,12px);margin:14px 0}.ok{border:1px solid #44d6a044;background:#44d6a018}.err{border:1px solid #ff718044;background:#ff718018}textarea{display:block;width:100%;min-width:220px;min-height:70px;box-sizing:border-box;margin:6px 0;padding:10px 12px;border:1px solid var(--border);border-radius:var(--ui-radius-md,12px);background:var(--control-bg,var(--surface-2));color:var(--text);font:inherit;resize:vertical}textarea:focus-visible{outline:2px solid var(--accent);outline-offset:1px;box-shadow:0 0 0 3px var(--ui-focus-ring)}button{font:inherit;cursor:pointer}.review-action{min-width:250px;white-space:normal}.numeric-value{direction:ltr;unicode-bidi:plaintext;display:inline-block;text-align:left;font-variant-numeric:tabular-nums;max-width:100%;overflow-wrap:anywhere}.order-reference{direction:ltr;unicode-bidi:plaintext;display:inline-block;text-align:left;overflow-wrap:anywhere}.section-gap{margin-top:22px}.orders-detail-table{min-width:980px}.order-details{min-width:180px}.order-details summary{cursor:pointer;color:var(--accent);font-weight:650}.order-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:12px 0}.order-detail-grid>div{display:grid;gap:3px;min-width:0}.order-detail-grid strong{overflow-wrap:anywhere}.order-attempts{border-top:1px solid var(--border);padding-top:10px;margin-top:10px}.attempt-line{display:grid;gap:4px;padding:9px 0;border-bottom:1px solid var(--border)}.attempt-line:last-child{border-bottom:0}.attempt-line small{overflow-wrap:anywhere}@media(max-width:480px){.order-detail-grid{grid-template-columns:1fr}}@media(max-width:760px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:480px){.stats{grid-template-columns:1fr}}@media(max-width:600px){.top{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body><main class="admin-shell">
<?php require __DIR__ . '/_navigation.php'; ?>
<section class="panel report"><div class="eyebrow" data-ui-text="orders_reports_eyebrow">گزارش‌ها</div><h1 data-ui-text="orders_report_title">گزارش خرید و فروش</h1><p class="muted" data-ui-text="orders_report_intro">فروش فقط از تراکنش‌های تأییدشدهٔ درگاه محاسبه می‌شود. مبلغ پرداختی هر سفارش ثابت می‌ماند و تغییر قیمت محصولات، گزارش پرداخت‌های قبلی را تغییر نمی‌دهد.</p>
<?php if ($message !== ''): ?><div class="notice ok" role="status" aria-live="polite"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err" role="alert" aria-live="assertive"><?= e($error) ?></div><?php endif; ?>
<div class="stats">
<div class="panel stat"><span class="muted" data-ui-text="orders_total_paid">کل سفارش‌های پرداخت‌شده</span><strong><?= number_format($totalPaid) ?></strong></div>
<div class="panel stat"><span class="muted" data-ui-text="orders_total_pending">سفارش‌های در انتظار پرداخت</span><strong><?= number_format($totalPending) ?></strong></div>
<div class="panel stat sales-total"><span class="muted">کل مبلغ فروش</span><strong><?php if (!$totalSalesRows): ?>۰<?php else: ?><?php foreach ($totalSalesRows as $totalRow): ?><span class="sales-total-line"><?= number_format((int)$totalRow['total_amount']) ?> <small><?= e((string)$totalRow['currency']) ?></small></span><?php endforeach; ?><?php endif; ?></strong></div>
</div>
<div class="table-wrap"><table><thead><tr><th data-ui-text="orders_service">سرویس</th><th data-fa="نوع سفارش" data-en="Order type">نوع سفارش</th><th data-ui-text="orders_paid_count">تعداد سفارش پرداخت‌شده</th><th data-ui-text="orders_activation_success">فعال‌سازی موفق</th><th data-ui-text="orders_partial_result">نتیجه جزئی</th><th data-ui-text="orders_gross_amount">مبلغ پرداختی</th><th data-ui-text="orders_unit">واحد</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="7" data-ui-text="orders_no_paid_sales">هنوز فروش پرداخت‌شده‌ای ثبت نشده است.</td></tr><?php endif; ?>
<?php foreach ($rows as $row): ?><tr><td><?= e((string)$row['service_name']) ?></td><td><?= e((string)$row['operation_label']) ?></td><td><?= number_format((int)$row['paid_orders']) ?></td><td><?= number_format((int)$row['active_sales']) ?></td><td><?= number_format((int)$row['partial_sales']) ?></td><td><?= number_format((int)$row['paid_amount']) ?></td><td><?= e((string)$row['currency']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>

<section class="section-gap" aria-labelledby="individual-orders-title">
<h2 id="individual-orders-title">جزئیات سفارش‌های پرداخت‌شده</h2>
<p class="muted">مبلغ پرداختی از تراکنش تأییدشده خوانده می‌شود. برای مشاهدهٔ مشخصات خریدار، اطلاعات پرداخت و نتیجهٔ هر سرور، جزئیات سفارش را باز کنید. حداکثر ۱۰۰ سفارش آخر نمایش داده می‌شود.</p>
<div class="table-wrap"><table class="orders-detail-table"><thead><tr><th>شناسه سفارش</th><th>سرویس</th><th>نام کاربری</th><th>نوع</th><th>مبلغ پرداختی</th><th>وضعیت سفارش</th><th>زمان ثبت</th><th>جزئیات</th></tr></thead><tbody>
<?php if (!$orderRows): ?><tr><td colspan="8">سفارش پرداخت‌شده‌ای برای نمایش وجود ندارد.</td></tr><?php endif; ?>
<?php foreach ($orderRows as $order): ?>
<tr>
<td><span class="order-reference"><?= e((string)$order['public_id']) ?></span><br><small class="muted"><?= e((string)$order['tracking_code']) ?></small></td>
<td><?= e((string)$order['product_name_snapshot']) ?></td>
<td><span class="numeric-value"><?= e((string)$order['username']) ?></span></td>
<td><?= (isset(json_decode((string)$order['server_targets_snapshot'], true)[0]['operation']) && json_decode((string)$order['server_targets_snapshot'], true)[0]['operation'] === 'renew') ? 'تمدید' : 'خرید' ?></td>
<td><strong><?= number_format((int)$order['paid_amount']) ?> <?= e((string)$order['paid_currency']) ?></strong></td>
<td><?= e((string)$order['order_status']) ?></td>
<td><span class="numeric-value"><?= e((string)$order['created_at']) ?></span></td>
<td><details class="order-details"><summary>مشاهده جزئیات</summary>
<div class="order-detail-grid">
<div><span class="muted">نام خریدار</span><strong><?= e((string)$order['buyer_name']) ?></strong></div>
<div><span class="muted">تلفن</span><strong class="numeric-value"><?= e((string)$order['buyer_phone'] ?: 'بدون تلفن') ?></strong></div>
<div><span class="muted">ایمیل</span><strong><?= e((string)$order['buyer_email'] ?: 'بدون ایمیل') ?></strong></div>
<div><span class="muted">مبلغ زمان ثبت سفارش</span><strong><?= number_format((int)$order['price_minor_snapshot']) ?> <?= e((string)$order['order_currency']) ?></strong></div>
<div><span class="muted">درگاه پرداخت</span><strong><?= e((string)$order['provider']) ?></strong></div>
<div><span class="muted">شناسه تراکنش درگاه</span><strong class="numeric-value"><?= e((string)($order['provider_reference'] ?: '—')) ?></strong></div>
<div><span class="muted">زمان تأیید پرداخت</span><strong class="numeric-value"><?= e((string)($order['verified_at'] ?: '—')) ?></strong></div>
</div>
<?php if (!empty($attemptsByOrder[(int)$order['id']])): ?>
<div class="order-attempts"><strong>نتیجه عملیات IBSng</strong>
<?php foreach ($attemptsByOrder[(int)$order['id']] as $attempt): ?>
<div class="attempt-line"><span><?= e((string)($attempt['server_name'] ?: 'سرور')) ?> · <?= $attempt['operation'] === 'renew' ? 'تمدید' : 'خرید' ?></span><strong><?= e((string)$attempt['status']) ?></strong>
<?php if (!empty($attempt['remote_user_id'])): ?><small>شناسه کاربر IBSng: <span class="numeric-value"><?= e((string)$attempt['remote_user_id']) ?></span></small><?php endif; ?>
<?php if (!empty($attempt['error_message'])): ?><small class="muted"><?= e((string)$attempt['error_message']) ?></small><?php endif; ?>
</div>
<?php endforeach; ?></div>
<?php else: ?><p class="muted">هنوز جزئیات عملیات IBSng برای این سفارش ثبت نشده است.</p><?php endif; ?>
</details></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</section>

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
