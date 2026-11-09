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

// The order snapshot is the fixed amount agreed with the customer at
// order creation. Product price changes must never alter historical sales.
$fromDate = trim((string)($_GET['from_date'] ?? ''));
$toDate = trim((string)($_GET['to_date'] ?? ''));
$datePattern = '/^\\d{4}-\\d{2}-\\d{2}$/';
if (($fromDate !== '' && (!preg_match($datePattern, $fromDate) || !checkdate((int)substr($fromDate, 5, 2), (int)substr($fromDate, 8, 2), (int)substr($fromDate, 0, 4))))
    || ($toDate !== '' && (!preg_match($datePattern, $toDate) || !checkdate((int)substr($toDate, 5, 2), (int)substr($toDate, 8, 2), (int)substr($toDate, 0, 4))))) {
    $fromDate = '';
    $toDate = '';
    $error = 'بازهٔ تاریخ واردشده معتبر نیست.';
}
if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
}
$dateWhere = '';
$dateParams = [];
if ($fromDate !== '') {
    $dateWhere .= ' AND o.created_at >= ?';
    $dateParams[] = $fromDate . ' 00:00:00';
}
if ($toDate !== '') {
    $dateWhere .= ' AND o.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $dateParams[] = $toDate . ' 00:00:00';
}

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
            SUM(o.price_minor_snapshot) AS paid_amount,
            o.currency
        FROM orders o
        WHERE o.payment_status = 'paid'
          AND EXISTS (SELECT 1 FROM payment_transactions t WHERE t.order_id=o.id AND t.status='verified')$dateWhere
        GROUP BY
            o.product_name_snapshot,
            o.currency,
            CASE
                WHEN COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(o.server_targets_snapshot, '$[0].operation')),
                    'create'
                ) = 'renew'
                THEN 'تمدید'
                ELSE 'خرید'
            END
        ORDER BY paid_orders DESC, service_name ASC";
$summaryStmt = $pdo->prepare($sql);
$summaryStmt->execute($dateParams);
$rows = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

$countSql = "SELECT COUNT(*) FROM orders o
             WHERE o.payment_status='paid'
               AND EXISTS (SELECT 1 FROM payment_transactions t WHERE t.order_id=o.id AND t.status='verified')$dateWhere";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($dateParams);
$totalPaid = (int)$countStmt->fetchColumn();

$pendingSql = "SELECT COUNT(*) FROM orders o WHERE o.payment_status='pending'$dateWhere";
$pendingStmt = $pdo->prepare($pendingSql);
$pendingStmt->execute($dateParams);
$totalPending = (int)$pendingStmt->fetchColumn();

$totalSql = "SELECT o.currency, SUM(o.price_minor_snapshot) AS total_amount
             FROM orders o
             WHERE o.payment_status='paid'
               AND EXISTS (SELECT 1 FROM payment_transactions t WHERE t.order_id=o.id AND t.status='verified')$dateWhere
             GROUP BY o.currency ORDER BY o.currency";
$totalStmt = $pdo->prepare($totalSql);
$totalStmt->execute($dateParams);
$totalSalesRows = $totalStmt->fetchAll(PDO::FETCH_ASSOC);

// Recent individual paid orders. A verified transaction is unique per order
// under PaymentTransactionService's locked paid-state transition.
$orderSql = "SELECT o.id, o.public_id, o.tracking_code, o.buyer_name, o.buyer_phone,
                    o.buyer_email, o.username, o.product_name_snapshot, o.price_minor_snapshot,
                    o.price_usdt_snapshot, o.currency AS order_currency, o.payment_status,
                    o.order_status, o.created_at, o.server_targets_snapshot,
                    CASE WHEN COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.server_targets_snapshot, '$[0].operation')), 'create') = 'renew'
                         THEN 'تمدید' ELSE 'خرید' END AS operation_label,
                    t.provider, t.provider_reference, t.verified_at, t.raw_result_json
             FROM orders o
             INNER JOIN payment_transactions t ON t.order_id=o.id AND t.status='verified'
             WHERE o.payment_status='paid'$dateWhere
             ORDER BY o.created_at DESC, o.id DESC LIMIT 100";
$orderStmt = $pdo->prepare($orderSql);
$orderStmt->execute($dateParams);
$orderRows = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
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
                        t.raw_result_json, o.public_id, o.tracking_code, o.buyer_name, o.buyer_phone
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
.admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto}.top{display:flex;justify-content:space-between;gap:14px;align-items:center;padding:24px 0}.report-filters{display:flex;flex-wrap:wrap;align-items:end;gap:12px;margin:18px 0;padding:14px;border:1px solid var(--border);border-radius:var(--ui-radius-md,12px)}.report-filters label{display:grid;gap:6px;color:var(--muted);font-size:.9rem}.report-filters input{min-height:42px;padding:8px 10px;border:1px solid var(--border);border-radius:var(--ui-radius-md,12px);background:var(--control-bg,var(--surface-2));color:var(--text);font:inherit}.filter-actions{display:flex;gap:8px;flex-wrap:wrap}.filter-actions a{display:inline-flex;align-items:center;text-decoration:none}.stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:20px 0}.stat,.report{padding:20px}.stat strong{display:block;margin-top:10px;font-size:1.65rem;overflow-wrap:anywhere}.sales-total-line{display:block;font-size:1.25rem;line-height:1.8}.sales-total-line small{font-size:.8rem;color:var(--muted);font-weight:600}.muted{color:var(--muted);line-height:1.8}.table-wrap{overflow:auto;max-width:100%;border:1px solid var(--border);border-radius:var(--ui-radius-md,12px);overscroll-behavior-inline:contain}table{width:100%;border-collapse:collapse}th,td{text-align:start;padding:13px;border-bottom:1px solid var(--border);vertical-align:top;white-space:normal;overflow-wrap:anywhere}th{background:var(--surface-2);color:var(--muted);font-size:.9rem;font-weight:650}tbody tr:last-child td{border-bottom:0}tbody tr:hover{background:color-mix(in srgb,var(--surface-2) 55%,transparent)}.notice{padding:12px 14px;border-radius:var(--ui-radius-md,12px);margin:14px 0}.ok{border:1px solid #44d6a044;background:#44d6a018}.err{border:1px solid #ff718044;background:#ff718018}textarea{display:block;width:100%;min-width:220px;min-height:70px;box-sizing:border-box;margin:6px 0;padding:10px 12px;border:1px solid var(--border);border-radius:var(--ui-radius-md,12px);background:var(--control-bg,var(--surface-2));color:var(--text);font:inherit;resize:vertical}textarea:focus-visible{outline:2px solid var(--accent);outline-offset:1px;box-shadow:0 0 0 3px var(--ui-focus-ring)}button{font:inherit;cursor:pointer}.review-action{min-width:250px;white-space:normal}.numeric-value{direction:ltr;unicode-bidi:plaintext;display:inline-block;text-align:left;font-variant-numeric:tabular-nums;max-width:100%;overflow-wrap:anywhere}.order-reference{direction:ltr;unicode-bidi:plaintext;display:inline-block;text-align:left;overflow-wrap:anywhere}.section-gap{margin-top:22px}.orders-detail-table{min-width:680px}.ambiguous-payments-table{width:100%;min-width:0;table-layout:fixed}.ambiguous-payments-table .col-transaction{width:6%}.ambiguous-payments-table .col-order{width:17%}.ambiguous-payments-table .col-gateway{width:10%}.ambiguous-payments-table .col-amount{width:11%}.ambiguous-payments-table .col-buyer{width:13%}.ambiguous-payments-table .col-created{width:17%}.ambiguous-payments-table .col-action{width:26%}.ambiguous-payments-table th{white-space:nowrap;overflow-wrap:normal;word-break:normal}.ambiguous-payments-table td{vertical-align:middle}.ambiguous-payments-table .order-reference{max-width:100%;overflow-wrap:anywhere;word-break:break-word}.ambiguous-payments-table .review-action{min-width:0;width:auto;max-width:100%;padding:8px}.ambiguous-payments-table .review-action form{display:block;width:100%;min-width:0;max-width:100%}.ambiguous-payments-table .review-action textarea{display:block;width:100%;min-width:0;max-width:100%;min-height:38px;height:38px;margin:4px 0;padding:5px 7px;box-sizing:border-box;font-size:.78rem}.payment-diagnostic-content pre{max-width:100%;max-height:55vh;overflow:auto;padding:12px;border:1px solid var(--border);border-radius:9px;background:var(--surface-2);white-space:pre-wrap;overflow-wrap:anywhere;font-size:.82rem;line-height:1.7;text-align:left}.payment-diagnostic-content .muted{font-size:.85rem;margin:8px 0}.transaction-reference-cell{min-width:0}.transaction-reference-toggle{display:block;margin-top:7px;padding:5px 7px;font-size:.7rem;white-space:normal;line-height:1.3}.ambiguous-payments-table .review-action .button{display:inline-flex;align-items:center;justify-content:center;width:auto;max-width:100%;min-width:0;padding:6px 8px;white-space:normal;overflow-wrap:anywhere;line-height:1.25;font-size:.72rem;box-sizing:border-box}.orders-detail-table .order-details-toggle{display:inline-flex;align-items:center;justify-content:center;white-space:nowrap;min-height:0;height:auto;padding:6px 10px;font-size:.82rem;line-height:1.25;border-radius:9px;max-width:100%;box-sizing:border-box}.order-details-toggle{font-weight:600}.order-details-dialog{width:min(680px,calc(100% - 32px));max-width:680px;max-height:min(82vh,760px);padding:0;border:1px solid var(--border);border-radius:16px;background:var(--surface,var(--surface-2));color:var(--text);box-shadow:0 24px 80px #0008}.order-details-dialog::backdrop{background:#050914a8;backdrop-filter:blur(4px)}.order-details-dialog-head{position:sticky;top:0;z-index:1;display:flex;align-items:center;justify-content:space-between;gap:14px;padding:16px 20px;background:var(--surface,var(--surface-2));border-bottom:1px solid var(--border)}.order-details-dialog-head h2{margin:0;font-size:1.1rem}.order-details-dialog-content{padding:8px 20px 18px;overflow:auto;max-height:calc(82vh - 72px)}.order-details-dialog .order-detail-panel{padding:0;background:transparent}.order-details-dialog .order-detail-grid{display:grid;grid-template-columns:1fr;gap:0}.order-details-dialog .order-detail-grid>div{padding:12px 4px;border-bottom:1px solid var(--border)}.order-details-dialog .order-attempts{margin-top:12px}.order-details{min-width:180px}.order-details-toggle{cursor:pointer}.order-details-template-row{display:none}.order-detail-panel{padding:14px 16px;background:color-mix(in srgb,var(--surface-2) 58%,transparent);border-radius:var(--ui-radius-md,12px)}.order-detail-grid{display:grid;grid-template-columns:1fr;gap:0;margin:0}.order-detail-grid>div{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;min-width:0;padding:12px 4px;border-bottom:1px solid var(--border)}.order-detail-grid>div>span{color:var(--muted);flex:0 0 auto}.order-detail-grid strong{overflow-wrap:anywhere;text-align:end}.order-attempts{border-top:1px solid var(--border);padding-top:12px;margin-top:12px}.attempt-line{display:grid;gap:4px;padding:10px 2px;border-bottom:1px solid var(--border)}.attempt-line:last-child{border-bottom:0}.jalali-picker{position:relative;display:inline-flex;align-items:center;gap:6px}.jalali-picker input[type=text]{min-width:150px;direction:ltr;text-align:center}.jalali-picker-button{min-height:42px;padding:8px 11px;border:1px solid var(--border);border-radius:var(--ui-radius-md,12px);background:var(--control-bg,var(--surface-2));color:var(--text);cursor:pointer}.jalali-calendar{position:absolute;z-index:30;top:calc(100% + 7px);inset-inline-start:0;width:280px;padding:12px;background:var(--surface,var(--surface-2));border:1px solid var(--border);border-radius:14px;box-shadow:0 12px 34px #0005}.jalali-calendar[hidden]{display:none}.jalali-calendar-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px}.jalali-calendar-head button,.jalali-calendar-day{border:0;background:transparent;color:var(--text);font:inherit;cursor:pointer;border-radius:8px}.jalali-calendar-head button{padding:6px 10px}.jalali-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:3px;text-align:center}.jalali-calendar-grid span{padding:6px 0;color:var(--muted);font-size:.78rem}.jalali-calendar-day{padding:7px 0}.jalali-calendar-day:hover,.jalali-calendar-day[aria-selected=true]{background:var(--accent);color:white}.jalali-calendar-day.outside{opacity:.35}@media(max-width:480px){.jalali-calendar{inset-inline-start:auto;inset-inline-end:0}}.attempt-line small{overflow-wrap:anywhere}@media(max-width:760px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:480px){.stats{grid-template-columns:1fr}}@media(max-width:600px){.top{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body><main class="admin-shell">
<?php require __DIR__ . '/_navigation.php'; ?>
<section class="panel report"><div class="eyebrow" data-ui-text="orders_reports_eyebrow">گزارش‌ها</div><h1 data-ui-text="orders_report_title">گزارش خرید و فروش</h1><p class="muted" data-ui-text="orders_report_intro">فروش سفارش‌های پرداخت‌شده بر اساس مبلغ ثابت‌شده هنگام ثبت سفارش محاسبه می‌شود؛ تغییر قیمت محصول روی سفارش‌های قبلی اثری ندارد.</p>
<form method="get" class="report-filters" aria-label="فیلتر تاریخ گزارش" data-ui-aria="orders_date_filter_aria">
<label><span data-ui-text="orders_from_date">از تاریخ</span> <input type="date" class="report-date-filter" name="from_date" value="<?= e($fromDate) ?>" autocomplete="off"></label>
<label><span data-ui-text="orders_to_date">تا تاریخ</span> <input type="date" class="report-date-filter" name="to_date" value="<?= e($toDate) ?>" autocomplete="off"></label>
<div class="filter-actions"><button class="button" type="submit" data-ui-text="orders_apply_range">اعمال بازه</button><a class="button button-secondary" href="<?= e('orders.php') ?>" data-ui-text="orders_clear_filter">حذف فیلتر</a></div>
</form>
<?php if ($message !== ''): ?><div class="notice ok" role="status" aria-live="polite"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err" role="alert" aria-live="assertive"><?= e($error) ?></div><?php endif; ?>
<div class="stats">
<div class="panel stat"><span class="muted" data-ui-text="orders_total_paid">کل سفارش‌های پرداخت‌شده</span><strong><?= number_format($totalPaid) ?></strong></div>
<div class="panel stat"><span class="muted" data-ui-text="orders_total_pending">سفارش‌های در انتظار پرداخت</span><strong><?= number_format($totalPending) ?></strong></div>
<div class="panel stat sales-total"><span class="muted" data-ui-text="orders_total_sales">کل مبلغ فروش</span><strong><?php if (!$totalSalesRows): ?>۰<?php else: ?><?php foreach ($totalSalesRows as $totalRow): ?><span class="sales-total-line"><?= number_format((int)$totalRow['total_amount']) ?> <small><?= e((string)$totalRow['currency']) ?></small></span><?php endforeach; ?><?php endif; ?></strong></div>
</div>
<div class="table-wrap"><table><thead><tr><th data-ui-text="orders_service">سرویس</th><th data-fa="نوع سفارش" data-en="Order type">نوع سفارش</th><th data-ui-text="orders_paid_count">تعداد سفارش پرداخت‌شده</th><th data-ui-text="orders_gross_amount">مبلغ فروش</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="4" data-ui-text="orders_no_paid_sales">هنوز فروش پرداخت‌شده‌ای ثبت نشده است.</td></tr><?php endif; ?>
<?php foreach ($rows as $row): ?><tr><td><?= e((string)$row['service_name']) ?></td><td><span data-fa="<?= e((string)$row['operation_label']) ?>" data-en="<?= $row['operation_label'] === 'تمدید' ? 'Renewal' : 'Purchase' ?>"><?= e((string)$row['operation_label']) ?></span></td><td><?= number_format((int)$row['paid_orders']) ?></td><td><strong><?= number_format((int)$row['paid_amount']) ?></strong> <?= e((string)$row['currency']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>

<section class="section-gap" aria-labelledby="individual-orders-title">
<h2 id="individual-orders-title" data-ui-text="orders_order_details_title">جزئیات سفارش‌های پرداخت‌شده</h2>
<p class="muted" data-ui-text="orders_order_details_intro">مبلغ پرداختی از تراکنش تأییدشده خوانده می‌شود. برای مشاهدهٔ مشخصات خریدار، اطلاعات پرداخت و نتیجهٔ هر سرور، جزئیات سفارش را باز کنید. حداکثر ۱۰۰ سفارش آخر نمایش داده می‌شود.</p>
<div class="table-wrap"><table class="orders-detail-table"><thead><tr><th data-ui-text="orders_username">نام کاربری</th><th data-ui-text="orders_order_type">نوع سفارش</th><th data-ui-text="orders_table_service">سرویس</th><th data-ui-text="orders_table_paid_amount">مبلغ پرداختی</th><th data-ui-text="orders_table_status">وضعیت</th><th data-fa="جزئیات" data-en="Details">جزئیات</th></tr></thead><tbody>
<?php if (!$orderRows): ?><tr><td colspan="5" data-ui-text="orders_empty_paid_orders">سفارش پرداخت‌شده‌ای برای نمایش وجود ندارد.</td></tr><?php endif; ?>
<?php foreach ($orderRows as $order): ?>
<?php $paymentMetadata = json_decode((string)($order['raw_result_json'] ?? ''), true); $isSandboxPayment = is_array($paymentMetadata) && (($paymentMetadata['gateway_environment']['sandbox'] ?? false) === true); $detailId = 'order-detail-' . (int)$order['id']; ?>
<tr>
<td><span class="numeric-value"><?= e((string)$order['username']) ?></span></td>
<td><span data-fa="<?= e((string)$order['operation_label']) ?>" data-en="<?= $order['operation_label'] === 'تمدید' ? 'Renewal' : 'Purchase' ?>"><?= e((string)$order['operation_label']) ?></span></td>
<td><?= e((string)$order['product_name_snapshot']) ?></td>
<td><strong><?= number_format((int)$order['price_minor_snapshot']) ?> <?= e((string)$order['order_currency']) ?></strong></td>
<td><span data-ui-status="<?= e((string)$order['order_status']) ?>"><?= e(match ((string)$order['order_status']) { 'active' => 'فعال', 'partial' => 'فعال‌سازی ناقص', 'failed' => 'ناموفق', 'provisioning' => 'در حال فعال‌سازی', 'paid' => 'پرداخت‌شده', default => (string)$order['order_status'] }) ?></span></td><td><button type="button" class="button order-details-toggle" aria-expanded="false" aria-controls="<?= e($detailId) ?>" data-fa="مشاهده جزئیات" data-en="View details">مشاهده جزئیات</button></td>
</tr>
<tr class="order-detail-template-row" hidden><td colspan="6"><template id="<?= e($detailId) ?>"><div class="order-detail-panel">
<div class="order-detail-grid">
<div><span data-ui-text="orders_order">سفارش</span><strong><span class="order-reference"><?= e((string)$order['public_id']) ?></span><br><small class="muted"><?= e((string)$order['tracking_code']) ?></small></strong></div>
<div><span data-ui-text="orders_username">نام کاربری</span><strong class="numeric-value"><?= e((string)$order['username']) ?></strong></div>
<div><span data-ui-text="orders_order_type">نوع سفارش</span><strong><span data-fa="<?= e((string)$order['operation_label']) ?>" data-en="<?= $order['operation_label'] === 'تمدید' ? 'Renewal' : 'Purchase' ?>"><?= e((string)$order['operation_label']) ?></span></strong></div>
<div><span data-ui-text="orders_order_created">زمان ثبت سفارش</span><strong class="report-date" data-date="<?= e((string)$order['created_at']) ?>"><?= e((string)$order['created_at']) ?></strong></div>
<div><span data-ui-text="orders_buyer_name">نام خریدار</span><strong><?= e((string)$order['buyer_name']) ?></strong></div>
<div><span data-ui-text="orders_phone">تلفن</span><?php if ((string)$order['buyer_phone'] !== ''): ?><strong class="numeric-value"><?= e((string)$order['buyer_phone']) ?></strong><?php else: ?><strong data-fa="بدون تلفن" data-en="No phone">بدون تلفن</strong><?php endif; ?></div>
<div><span data-ui-text="orders_email">ایمیل</span><?php if ((string)$order['buyer_email'] !== ''): ?><strong><?= e((string)$order['buyer_email']) ?></strong><?php else: ?><strong data-fa="بدون ایمیل" data-en="No email">بدون ایمیل</strong><?php endif; ?></div>
<div><span data-ui-text="orders_order_snapshot_amount">مبلغ زمان ثبت سفارش</span><strong><?= number_format((int)$order['price_minor_snapshot']) ?> <?= e((string)$order['order_currency']) ?></strong></div>
<div><span data-fa="درگاه پرداخت" data-en="Payment gateway">درگاه پرداخت</span><strong><?= e((string)$order['provider']) ?></strong></div>
<?php if ($isSandboxPayment): ?><div><span data-fa="محیط پرداخت" data-en="Payment environment">محیط پرداخت</span><strong><span data-fa="آزمایشی" data-en="Sandbox">آزمایشی</span></strong></div><?php endif; ?>
<div><span data-ui-text="orders_gateway_transaction_id">شناسه تراکنش درگاه</span><strong class="numeric-value"><?= e((string)($order['provider_reference'] ?: '—')) ?></strong></div>
<div><span data-ui-text="orders_payment_verified_at">زمان تأیید پرداخت</span><strong class="report-date" data-date="<?= e((string)($order['verified_at'] ?: '')) ?>"><?= e((string)($order['verified_at'] ?: '—')) ?></strong></div>
</div>
<?php if (!empty($attemptsByOrder[(int)$order['id']])): ?>
<div class="order-attempts"><strong data-ui-text="orders_ibsng_operation_result">نتیجه عملیات IBSng</strong>
<?php foreach ($attemptsByOrder[(int)$order['id']] as $attempt): ?>
<div class="attempt-line"><span><?php if ((string)$attempt['server_name'] !== ''): ?><?= e((string)$attempt['server_name']) ?><?php else: ?><span data-fa="سرور" data-en="Server">سرور</span><?php endif; ?> · <span data-fa="<?= $attempt['operation'] === 'renew' ? 'تمدید' : 'خرید' ?>" data-en="<?= $attempt['operation'] === 'renew' ? 'Renewal' : 'Purchase' ?>"><?= $attempt['operation'] === 'renew' ? 'تمدید' : 'خرید' ?></span></span><strong data-ui-status="<?= e((string)$attempt['status']) ?>"><?= e((string)$attempt['status']) ?></strong>
<?php if (!empty($attempt['remote_user_id'])): ?><small><span data-ui-text="orders_ibsng_user_id">شناسه کاربر IBSng:</span> <span class="numeric-value"><?= e((string)$attempt['remote_user_id']) ?></span></small><?php endif; ?>
<?php if (!empty($attempt['error_message'])): ?><small class="muted"><?= e((string)$attempt['error_message']) ?></small><?php endif; ?>
</div>
<?php endforeach; ?></div>
<?php else: ?><p class="muted" data-ui-text="orders_no_ibsng_details">هنوز جزئیات عملیات IBSng برای این سفارش ثبت نشده است.</p><?php endif; ?>
</div></template></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<dialog id="order-details-dialog" class="order-details-dialog" aria-labelledby="order-details-dialog-title"><div class="order-details-dialog-head"><h2 id="order-details-dialog-title" data-fa="جزئیات سفارش" data-en="Order details">جزئیات سفارش</h2><button type="button" class="button order-details-close" data-fa="بستن" data-en="Close">بستن</button></div><div class="order-details-dialog-content" id="order-details-dialog-content"></div></dialog>
</section>

<section class="section-gap" aria-labelledby="ambiguous-payments-title">
<h2 id="ambiguous-payments-title" data-ui-text="orders_ambiguous_section_title">بررسی موردی پرداخت‌های نامشخص</h2>
<p class="muted" data-ui-text="orders_ambiguous_intro">این بخش فقط در موارد نادر پرداخت‌هایی را نشان می‌دهد که شروع آن‌ها در درگاه نتیجهٔ قطعی نداشته است. پیش از آزاد کردن سفارش برای تلاش مجدد، در پنل درگاه بررسی کنید که فاکتور قابل پرداختی برای آن ایجاد نشده باشد. این اقدام درگاه را لغو نمی‌کند و صرفاً وضعیت داخلی سفارش را تغییر می‌دهد.</p>
<div class="table-wrap ambiguous-payments-wrap"><table class="ambiguous-payments-table"><colgroup><col class="col-transaction"><col class="col-order"><col class="col-gateway"><col class="col-amount"><col class="col-buyer"><col class="col-created"><col class="col-action"></colgroup><thead><tr><th data-ui-text="orders_transaction">تراکنش</th><th data-ui-text="orders_order">سفارش</th><th data-ui-text="orders_gateway">درگاه</th><th data-ui-text="orders_amount">مبلغ</th><th data-ui-text="orders_buyer">خریدار</th><th data-ui-text="orders_created_at">زمان ثبت</th><th data-ui-text="orders_action">اقدام</th></tr></thead><tbody>
<?php if (!$ambiguousRows): ?><tr><td colspan="7" data-ui-text="orders_no_ambiguous">پرداخت مبهمی برای بررسی وجود ندارد.</td></tr><?php endif; ?>
<?php foreach ($ambiguousRows as $row): ?><tr>
<td><span class="numeric-value">#<?= (int)$row['id'] ?></span></td><td class="transaction-reference-cell"><span class="order-reference transaction-reference-value"><?= e((string)($row['tracking_code'] ?? '—')) ?></span><button type="button" class="button button-secondary transaction-reference-toggle" data-order-id="<?= e((string)$row['public_id']) ?>" data-tracking-code="<?= e((string)($row['tracking_code'] ?? '—')) ?>" data-fa="نمایش شناسه پرداخت" data-en="Show payment ID">نمایش شناسه پرداخت</button></td><td><?= e((string)$row['provider']) ?></td>
<td><span class="numeric-value"><?= e(number_format((int)$row['amount_minor'])) ?> <?= e((string)$row['currency']) ?></span></td>
<td><?= e((string)$row['buyer_name']) ?><br><span class="numeric-value"><?= e((string)$row['buyer_phone']) ?></span></td><td><span class="numeric-value"><?= e((string)$row['created_at']) ?></span></td>
<td class="review-action">
<form method="post" data-confirm-fa="آیا در پنل درگاه بررسی کردید که فاکتور قابل پرداختی برای این سفارش وجود ندارد؟" data-confirm-en="Did you check the gateway dashboard and confirm that no payable invoice exists for this order?">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="transaction_id" value="<?= (int)$row['id'] ?>">
<label for="note-<?= (int)$row['id'] ?>" data-ui-text="orders_review_action_note">یادداشت بررسی (الزامی)</label><textarea id="note-<?= (int)$row['id'] ?>" name="resolution_note" required maxlength="2000" placeholder="نتیجه بررسی درگاه را بنویسید" data-ui-placeholder="orders_review_action_placeholder"></textarea>
<button class="button" type="submit" data-fa="ثبت ناموفق و آزادسازی" data-en="Mark failed & release">ثبت ناموفق و آزادسازی</button></form>
<?php
$diagnosticData = json_decode((string)($row['raw_result_json'] ?? ''), true);
$diagnosticText = is_array($diagnosticData)
    ? json_encode($diagnosticData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    : '';
$hasInitiationDiagnostic = is_array($diagnosticData['initiation_diagnostic'] ?? null);
?>
<button type="button" class="button button-secondary payment-diagnostic-open" data-dialog-target="payment-diagnostic-<?= (int)$row['id'] ?>" data-fa="مشاهده خطا و پاسخ درگاه" data-en="View error & gateway response">مشاهده خطا و پاسخ درگاه</button>
<template id="payment-diagnostic-<?= (int)$row['id'] ?>"><div class="payment-diagnostic-content">
<?php if (!$hasInitiationDiagnostic): ?><p class="muted" data-fa="برای این تلاش، متن خطا و پاسخ درگاه در نسخه فعلی ذخیره نشده است. اطلاعات موجود در زیر فقط فراداده تراکنش است." data-en="No exception or gateway response was stored for this attempt. The data below is transaction metadata only.">برای این تلاش، متن خطا و پاسخ درگاه در نسخه فعلی ذخیره نشده است. اطلاعات موجود در زیر فقط فراداده تراکنش است.</p><?php endif; ?>
<pre dir="ltr"><?= e((string)$diagnosticText) ?></pre></div></template>
</td>
</tr><?php endforeach; ?>
</tbody></table></div>
</section>
<dialog id="payment-diagnostic-dialog" class="order-details-dialog" aria-labelledby="payment-diagnostic-dialog-title"><div class="order-details-dialog-head"><h2 id="payment-diagnostic-dialog-title" data-fa="خطا و پاسخ درگاه" data-en="Gateway error & response">خطا و پاسخ درگاه</h2><button type="button" class="button payment-diagnostic-close" data-fa="بستن" data-en="Close">بستن</button></div><div class="order-details-dialog-content" id="payment-diagnostic-dialog-content"></div></dialog>
</section><?php require __DIR__ . '/_footer.php'; ?></main><script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
<script>
(() => {
  'use strict';
  const root = document.documentElement;
  const locale = 'fa-IR-u-ca-persian';
  const formatter = new Intl.DateTimeFormat(locale, {year:'numeric', month:'numeric', day:'numeric'});
  const latin = value => String(value).replace(/[۰-۹٠-٩]/g, ch => {
    const code = ch.charCodeAt(0);
    return String(code >= 0x06f0 && code <= 0x06f9 ? code - 0x06f0 : code - 0x0660);
  });
  const jalaliParts = date => {
    const values = {};
    formatter.formatToParts(date).forEach(part => { values[part.type] = latin(part.value); });
    return {year:Number(values.year), month:Number(values.month), day:Number(values.day)};
  };
  const utcDate = (year, month, day) => new Date(Date.UTC(year, month - 1, day, 12));
  const isoDate = date => date.getUTCFullYear() + '-' + String(date.getUTCMonth()+1).padStart(2,'0') + '-' + String(date.getUTCDate()).padStart(2,'0');
  const parseIso = value => {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return match ? utcDate(Number(match[1]), Number(match[2]), Number(match[3])) : null;
  };
  const jalaliMonthStart = (year, month) => {
    // Jalali months 1-10 begin in Gregorian March-December; months 11-12
    // begin in January-February of the following Gregorian year.
    const gregorianYear = year + 621 + (month >= 11 ? 1 : 0);
    const gregorianMonthIndex = month <= 10 ? month + 1 : month - 11;
    const guess = utcDate(gregorianYear, gregorianMonthIndex + 1, 15);
    for (let offset = -12; offset <= 12; offset++) {
      const candidate = new Date(guess.getTime() + offset * 86400000);
      const p = jalaliParts(candidate);
      if (p.year === year && p.month === month && p.day === 1) return candidate;
    }
    throw new Error('Unable to resolve Jalali month start');
  };
  const monthLength = (year, month) => {
    const first = jalaliMonthStart(year, month);
    const next = month === 12 ? jalaliMonthStart(year + 1, 1) : jalaliMonthStart(year, month + 1);
    return Math.round((next - first) / 86400000);
  };
  const monthNames = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
  const weekNames = ['ش','ی','د','س','چ','پ','ج'];
  const makeCalendar = input => {
    if (input.dataset.jalaliReady === '1') return;
    input.dataset.jalaliReady = '1';
    const fieldName = input.name;
    const initial = input.value;
    const wrap = document.createElement('span');
    wrap.className = 'jalali-picker';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    const hidden = document.createElement('input');
    hidden.type = 'hidden'; hidden.name = fieldName; hidden.value = initial; wrap.appendChild(hidden);
    input.removeAttribute('name');
    const trigger = document.createElement('button');
    trigger.type = 'button'; trigger.className = 'jalali-picker-button'; trigger.textContent = '▦';
    trigger.setAttribute('aria-label','باز کردن تقویم شمسی'); wrap.appendChild(trigger);
    const popup = document.createElement('div');
    popup.className = 'jalali-calendar'; popup.hidden = true; popup.setAttribute('role','dialog');
    popup.setAttribute('aria-label','تقویم شمسی'); wrap.appendChild(popup);
    let selected = parseIso(initial);
    let current = selected ? jalaliParts(selected) : jalaliParts(new Date());
    input.type = 'text'; input.readOnly = true; input.placeholder = 'سال/ماه/روز';
    input.value = selected ? formatter.format(selected) : '';
    const render = () => {
      popup.replaceChildren();
      const header = document.createElement('div'); header.className = 'jalali-calendar-head';
      const previous = document.createElement('button'); previous.type='button'; previous.textContent='‹'; previous.setAttribute('aria-label','ماه قبل');
      const heading = document.createElement('strong'); heading.textContent=monthNames[current.month-1]+' '+new Intl.NumberFormat('fa-IR').format(current.year);
      const next = document.createElement('button'); next.type='button'; next.textContent='›'; next.setAttribute('aria-label','ماه بعد');
      previous.addEventListener('click',()=>{current.month--;if(current.month<1){current.month=12;current.year--;}render();});
      next.addEventListener('click',()=>{current.month++;if(current.month>12){current.month=1;current.year++;}render();});
      header.append(previous,heading,next); popup.appendChild(header);
      const grid=document.createElement('div'); grid.className='jalali-calendar-grid';
      weekNames.forEach(name=>{const el=document.createElement('span');el.textContent=name;grid.appendChild(el);});
      const first=jalaliMonthStart(current.year,current.month);
      const offset=(first.getUTCDay()+1)%7;
      const days=monthLength(current.year,current.month);
      for(let i=0;i<42;i++){
        const day=i-offset+1; const cell=document.createElement('button');
        cell.type='button'; cell.className='jalali-calendar-day';
        if(day<1||day>days){cell.disabled=true;cell.classList.add('outside');cell.textContent=' ';}
        else{
          cell.textContent=new Intl.NumberFormat('fa-IR').format(day);
          const date=new Date(first.getTime()+(day-1)*86400000); const iso=isoDate(date);
          cell.setAttribute('aria-selected',String(hidden.value===iso));
          cell.addEventListener('click',()=>{hidden.value=iso;selected=date;input.value=formatter.format(date);popup.hidden=true;});
        }
        grid.appendChild(cell);
      }
      popup.appendChild(grid);
    };
    trigger.addEventListener('click',()=>{if(!popup.hidden){popup.hidden=true;return;}current=selected?jalaliParts(selected):jalaliParts(new Date());render();popup.hidden=false;});
    input.addEventListener('click',()=>trigger.click());
    document.addEventListener('pointerdown',event=>{if(!wrap.contains(event.target))popup.hidden=true;});
    input.form?.addEventListener('reset',()=>{hidden.value='';input.value='';selected=null;});
    input._atdJalali={wrap,hidden,fieldName};
  };
  const removeCalendar = input => {
    const state=input._atdJalali;if(!state)return;
    input.type='date';input.readOnly=false;input.name=state.fieldName;
    state.hidden.remove();state.wrap.querySelector('.jalali-picker-button')?.remove();
    state.wrap.querySelector('.jalali-calendar')?.remove();
    state.wrap.parentNode.insertBefore(input,state.wrap);state.wrap.remove();
    delete input._atdJalali;delete input.dataset.jalaliReady;
  };
  const applyCalendarLanguage = language => {
    document.querySelectorAll('.report-date-filter').forEach(input=>{
      if(language==='en')removeCalendar(input);else makeCalendar(input);
    });
  };
  const bindDetails = () => {
    const dialog=document.getElementById('order-details-dialog');
    const content=document.getElementById('order-details-dialog-content');
    if(!dialog||!content)return;
    document.querySelectorAll('.order-details-toggle').forEach(button=>{
      if(button.dataset.bound==='1')return;button.dataset.bound='1';
      button.addEventListener('click',()=>{
        const template=document.getElementById(button.getAttribute('aria-controls'));
        if(!template)return;
        content.replaceChildren(template.content.cloneNode(true));
        document.dispatchEvent(new CustomEvent('atd:language-change',{detail:{language:root.lang==='en'?'en':'fa',direction:root.dir}}));
        if(typeof dialog.showModal==='function')dialog.showModal();
        else dialog.setAttribute('open','');
        button.setAttribute('aria-expanded','true');
      });
    });
    dialog.querySelector('.order-details-close')?.addEventListener('click',()=>dialog.close());
    dialog.addEventListener('click',event=>{
      if(event.target===dialog)dialog.close();
    });
    dialog.addEventListener('close',()=>{
      content.replaceChildren();
      document.querySelectorAll('.order-details-toggle[aria-expanded="true"]').forEach(button=>button.setAttribute('aria-expanded','false'));
    });
  };
  const bindPaymentDiagnostics = () => {
    const dialog=document.getElementById('payment-diagnostic-dialog');
    const content=document.getElementById('payment-diagnostic-dialog-content');
    if(!dialog||!content)return;
    document.querySelectorAll('.payment-diagnostic-open').forEach(button=>{
      if(button.dataset.bound==='1')return;button.dataset.bound='1';
      button.addEventListener('click',()=>{
        const template=document.getElementById(button.dataset.dialogTarget||'');
        if(!template)return;
        content.replaceChildren(template.content.cloneNode(true));
        document.dispatchEvent(new CustomEvent('atd:language-change',{detail:{language:root.lang==='en'?'en':'fa',direction:root.dir}}));
        if(typeof dialog.showModal==='function')dialog.showModal();else dialog.setAttribute('open','');
      });
    });
    dialog.querySelector('.payment-diagnostic-close')?.addEventListener('click',()=>dialog.close());
    dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();});
    dialog.addEventListener('close',()=>content.replaceChildren());
    document.querySelectorAll('.transaction-reference-toggle').forEach(button=>{
      if(button.dataset.bound==='1')return;button.dataset.bound='1';
      button.addEventListener('click',()=>{
        const value=button.previousElementSibling;
        if(!value)return;
        const showingId=button.dataset.showingId==='1';
        value.textContent=showingId?button.dataset.trackingCode:button.dataset.orderId;
        button.dataset.showingId=showingId?'0':'1';
        const language=root.lang==='en'?'en':'fa';
        button.textContent=showingId
          ? (language==='en'?'Show payment ID':'نمایش شناسه پرداخت')
          : (language==='en'?'Show tracking code':'نمایش کد رهگیری');
      });
    });
  };
  const initialize=()=>{bindDetails();bindPaymentDiagnostics();applyCalendarLanguage(root.lang==='en'?'en':'fa');};
  document.addEventListener('DOMContentLoaded', initialize, {once:true});
  document.addEventListener('atd:language-change',event=>{
    const language=event.detail?.language==='en'?'en':'fa';applyCalendarLanguage(language);
  });
})();
</script>
<script>
(() => {
  const formatReportDates = () => {
    const language = document.documentElement.lang === 'en' ? 'en' : 'fa';
    document.querySelectorAll('.report-date[data-date]').forEach((node) => {
      const raw = node.dataset.date;
      if (!raw) return;
      const date = new Date(raw.replace(' ', 'T'));
      if (Number.isNaN(date.getTime())) return;
      const locale = language === 'en' ? 'en-GB' : 'fa-IR-u-ca-persian';
      node.textContent = new Intl.DateTimeFormat(locale, {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hour12: false
      }).format(date);
      node.title = language === 'en' ? 'Gregorian date' : 'تاریخ شمسی';
    });
  };
  document.addEventListener('DOMContentLoaded', formatReportDates, {once:true});
  document.addEventListener('atd:language-change', formatReportDates);
})();
</script>
</body></html>
