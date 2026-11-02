<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Crypto.php';
require_once __DIR__ . '/app/OrderTrackingCode.php';

$config = app_config();
$orderLookupValue = trim((string)($_GET['order'] ?? ''));
$isTrackingCode = (bool)preg_match('/^ATD-?[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{7}$/iD', $orderLookupValue);
$orderPublicId = strtolower($orderLookupValue);
$trackingCode = OrderTrackingCode::normalize($orderLookupValue);
$order = null;
$attempts = [];
$accountPassword = null;
$notFound = !$isTrackingCode && !preg_match('/^[a-f0-9]{32}$/D', $orderPublicId);

if (!$notFound) {
    try {
        $pdo = Database::connection();
        $lookupColumn = $isTrackingCode ? 'tracking_code' : 'public_id';
        $lookupValue = $isTrackingCode ? ('ATD-' . substr($trackingCode, 3)) : $orderPublicId;
        $stmt = $pdo->prepare(
            'SELECT id, public_id, tracking_code, product_name_snapshot, duration_seconds_snapshot, price_minor_snapshot, price_usdt_snapshot, currency, username, password_ciphertext, payment_status, order_status, created_at
             FROM orders WHERE ' . $lookupColumn . ' = ? LIMIT 1'
        );
        $stmt->execute([$lookupValue]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$order) {
            $notFound = true;
        } elseif ($order['payment_status'] === 'paid') {
            $attemptStmt = $pdo->prepare(
                'SELECT a.server_id, a.operation, a.status, s.name AS server_name, s.service_name
                 FROM provisioning_attempts a
                 JOIN ibsng_servers s ON s.id = a.server_id
                 WHERE a.order_id = ?
                 ORDER BY a.id ASC'
            );
            $attemptStmt->execute([(int)$order['id']]);
            $attempts = $attemptStmt->fetchAll(PDO::FETCH_ASSOC);
            if (array_filter($attempts, static fn(array $attempt): bool => $attempt['status'] === 'succeeded')) {
                try {
                    $accountPassword = app_decrypt_secret((string)$order['password_ciphertext']);
                } catch (Throwable $decryptError) {
                    error_log('Customer order credential decrypt failed: ' . $decryptError->getMessage());
                    $accountPassword = null;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('Customer order status lookup failed: ' . $e->getMessage());
        http_response_code(503);
    }
}

$statusLabels = [
    'pending_payment' => 'در انتظار پرداخت',
    'paid' => 'پرداخت تأیید شد',
    'provisioning' => 'در حال فعال‌سازی',
    'active' => 'فعال',
    'partial' => 'فعال‌سازی ناقص',
    'failed' => 'فعال‌سازی ناموفق',
    'queued' => 'در صف اجرا',
    'running' => 'در حال اجرا',
    'succeeded' => 'موفق',
    'needs_review' => 'نیازمند بررسی پشتیبانی',
    'skipped' => 'انجام نشده',
];
$attemptLabel = static fn(string $status): string => $statusLabels[$status] ?? 'در حال بررسی';
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>وضعیت سفارش | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.status-shell{width:min(820px,calc(100% - 28px));margin:0 auto}.status-card{padding:clamp(18px,4vw,30px);margin:22px 0 36px}.status-card h1{font-size:clamp(1.45rem,4vw,2rem);line-height:1.5;margin:8px 0 14px}.notice{padding:18px;border:1px solid var(--border);border-radius:18px;margin:14px 0;line-height:1.95;background:linear-gradient(145deg,rgba(28,39,61,.78),rgba(15,22,38,.86))}.muted{color:var(--muted);line-height:1.8}.target-list{display:grid;gap:12px;margin:18px 0}.target{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:18px;border:1px solid var(--border);border-radius:18px;background:linear-gradient(145deg,rgba(28,39,61,.84),rgba(15,22,38,.9))}.target strong{display:block}.target small{display:block;color:var(--muted);margin-top:4px}.account-details{display:grid;gap:8px;margin-top:10px}.account-details code{overflow-wrap:anywhere}.account-details details{padding:8px 10px;border:1px solid var(--border);border-radius:8px}.state{white-space:nowrap;font-weight:700;padding:6px 12px;border-radius:999px;background:rgba(68,214,160,.1);color:var(--green)}@media(max-width:520px){.target{align-items:flex-start;flex-direction:column}.state{white-space:normal}.status-shell{width:calc(100% - 24px)}}
.purchase-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:18px 0}.purchase-fact{padding:15px 16px;border:1px solid var(--border);border-radius:16px;background:var(--surface-2);min-width:0}.purchase-fact span{display:block;color:var(--muted);font-size:.85rem}.purchase-fact strong{display:block;margin-top:7px;overflow-wrap:anywhere;line-height:1.7}.purchase-fact .paid-amount{color:var(--green)}.credential-panel{padding:14px;border:1px solid var(--border);border-radius:16px;background:rgba(24,36,58,.7)}.credential-row{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:8px 0}.credential-row+ .credential-row{border-top:1px solid var(--border)}.credential-row>span{color:var(--muted)}.credential-row code{overflow-wrap:anywhere;text-align:left}.credential-row details{max-width:100%}.credential-row summary{cursor:pointer}.target{min-width:0}.target>div:first-child{min-width:0;overflow-wrap:anywhere}@media(max-width:520px){.purchase-summary{grid-template-columns:1fr}.credential-row{align-items:flex-start;flex-direction:column}}</style>
</head>
<body>
<main class="status-shell">
<header class="topbar"><a class="brand" href="<?= e(app_url()) ?>"><?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></a><nav class="hero-actions" aria-label="ناوبری سفارش"><a class="button" href="<?= e(app_url('my-orders.php')) ?>">پیگیری و بازیابی خریدها</a><a class="button" href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a></nav></header>
<section class="status-card panel">
<div class="eyebrow">پیگیری سفارش</div>
<?php if ($notFound || !$order): ?>
<h1>سفارش پیدا نشد</h1>
<p class="muted">شناسه پیگیری معتبر نیست یا سفارش در دسترس نیست. لینک پیگیری را از صفحه نتیجه پرداخت باز کنید.</p>
<?php else: ?>
<h1><?= e((string)$order['product_name_snapshot']) ?></h1>
<div class="purchase-summary">
<div class="purchase-fact"><span>شناسه پیگیری</span><strong><code><?= e((string)$order['tracking_code']) ?></code></strong></div>
<div class="purchase-fact"><span>وضعیت سفارش</span><strong><?= e($attemptLabel((string)$order['order_status'])) ?></strong></div>
<div class="purchase-fact"><span>مبلغ پرداخت‌شده</span><strong class="paid-amount"><?php if (strtoupper((string)$order['currency']) === 'USDT' && $order['price_usdt_snapshot'] !== null): ?><?= e(number_format((float)$order['price_usdt_snapshot'], 2)) ?> USDT<?php else: ?><?= e(number_format((int)$order['price_minor_snapshot'])) ?> <?= e((string)$order['currency']) ?><?php endif; ?></strong></div>
<div class="purchase-fact"><span>تاریخ خرید</span><strong><?= e((string)$order['created_at']) ?></strong></div>
<div class="purchase-fact"><span>وضعیت پرداخت</span><strong><?= e($order['payment_status'] === 'paid' ? 'پرداخت تأیید شد' : ($order['payment_status'] === 'pending' ? 'در انتظار پرداخت' : (string)$order['payment_status'])) ?></strong></div>
<div class="purchase-fact"><span>محصول</span><strong><?= e((string)$order['product_name_snapshot']) ?></strong></div>
</div>
<?php if ($order['payment_status'] !== 'paid'): ?>
<p class="muted">پرداخت این سفارش هنوز تأیید نشده است؛ تا پیش از تأیید پرداخت، حسابی در IBSng ایجاد نمی‌شود.</p>
<?php elseif (!$attempts): ?>
<p class="muted">پرداخت ثبت شده است، اما هنوز نتیجه‌ای برای مقصدهای فعال‌سازی در دسترس نیست. لطفاً کمی بعد دوباره بررسی کنید.</p>
<?php else: ?>
<h2>نتیجه هر مقصد فعال‌سازی</h2>
<div class="target-list">
<?php foreach ($attempts as $attempt): ?>
<div class="target">
<div><?php if (trim((string)($attempt['service_name'] ?? '')) !== ''): ?><strong><?= e((string)$attempt['service_name']) ?></strong><?php endif; ?><small><?= e((string)$attempt['operation']) ?></small>
<?php if ($attempt['status'] === 'succeeded' && is_string($accountPassword)): ?>
<div class="account-details credential-panel">
<div class="credential-row"><span>نام کاربری</span><code><?= e((string)$order['username']) ?></code></div>
<div class="credential-row"><span>رمز عبور</span><details><summary>نمایش رمز عبور</summary><code><?= e($accountPassword) ?></code></details></div>
</div>
<?php elseif ($attempt['status'] === 'succeeded'): ?>
<small>حساب ایجاد شده است؛ نمایش رمز در حال حاضر در دسترس نیست.</small>
<?php endif; ?>
</div>
<div class="state"><?= e($attemptLabel((string)$attempt['status'])) ?></div>
</div>
<?php endforeach; ?>
</div>
<p class="muted">اگر مقصدی نیازمند بررسی باشد، پشتیبانی نتیجه را از پنل IBSng تطبیق می‌دهد. وضعیت هر سرور مستقل ثبت می‌شود.</p>
<?php endif; ?>
<p class="muted">رمز عبور فقط برای مقصدهایی نمایش داده می‌شود که فعال‌سازی آن‌ها با موفقیت تأیید شده است. کد پیگیری را محرمانه نگه دارید.</p>
<?php endif; ?>
</section>
</main>
</body>
</html>
