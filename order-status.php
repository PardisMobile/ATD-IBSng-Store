<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';

$config = app_config();
$orderPublicId = strtolower(trim((string)($_GET['order'] ?? '')));
$order = null;
$attempts = [];
$notFound = !preg_match('/^[a-f0-9]{32}$/D', $orderPublicId);

if (!$notFound) {
    try {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, public_id, product_name_snapshot, username, payment_status, order_status, created_at
             FROM orders WHERE public_id = ? LIMIT 1'
        );
        $stmt->execute([$orderPublicId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$order) {
            $notFound = true;
        } elseif ($order['payment_status'] === 'paid') {
            $attemptStmt = $pdo->prepare(
                'SELECT a.server_id, a.operation, a.status, s.name AS server_name
                 FROM provisioning_attempts a
                 JOIN ibsng_servers s ON s.id = a.server_id
                 WHERE a.order_id = ?
                 ORDER BY a.id ASC'
            );
            $attemptStmt->execute([(int)$order['id']]);
            $attempts = $attemptStmt->fetchAll(PDO::FETCH_ASSOC);
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
.status-shell{width:min(820px,calc(100% - 32px));margin:0 auto}.status-card{padding:clamp(20px,4vw,34px);margin:24px 0}.notice{padding:15px;border:1px solid var(--border);border-radius:14px;margin:16px 0;line-height:1.9}.muted{color:var(--muted);line-height:1.8}.target-list{display:grid;gap:12px;margin:18px 0}.target{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:14px;border:1px solid var(--border);border-radius:12px;background:var(--surface-2)}.target strong{display:block}.target small{display:block;color:var(--muted);margin-top:4px}.state{white-space:nowrap;font-weight:700}@media(max-width:520px){.target{align-items:flex-start;flex-direction:column}.state{white-space:normal}}
</style>
</head>
<body>
<main class="status-shell">
<header class="topbar"><a class="brand" href="<?= e(app_url()) ?>"><?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></a><a class="button" href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a></header>
<section class="status-card panel">
<div class="eyebrow">پیگیری سفارش</div>
<?php if ($notFound || !$order): ?>
<h1>سفارش پیدا نشد</h1>
<p class="muted">شناسه پیگیری معتبر نیست یا سفارش در دسترس نیست. لینک پیگیری را از صفحه نتیجه پرداخت باز کنید.</p>
<?php else: ?>
<h1><?= e((string)$order['product_name_snapshot']) ?></h1>
<div class="notice"><strong>شناسه سفارش:</strong> <code><?= e((string)$order['public_id']) ?></code><br><strong>نام کاربری:</strong> <?= e((string)$order['username']) ?><br><strong>وضعیت سفارش:</strong> <?= e($attemptLabel((string)$order['order_status'])) ?></div>
<?php if ($order['payment_status'] !== 'paid'): ?>
<p class="muted">پرداخت این سفارش هنوز تأیید نشده است؛ تا پیش از تأیید پرداخت، حسابی در IBSng ایجاد نمی‌شود.</p>
<?php elseif (!$attempts): ?>
<p class="muted">پرداخت ثبت شده است، اما هنوز نتیجه‌ای برای مقصدهای فعال‌سازی در دسترس نیست. لطفاً کمی بعد دوباره بررسی کنید.</p>
<?php else: ?>
<h2>نتیجه هر مقصد فعال‌سازی</h2>
<div class="target-list">
<?php foreach ($attempts as $attempt): ?>
<div class="target">
<div><strong><?= e((string)$attempt['server_name']) ?></strong><small><?= e((string)$attempt['operation']) ?></small></div>
<div class="state"><?= e($attemptLabel((string)$attempt['status'])) ?></div>
</div>
<?php endforeach; ?>
</div>
<p class="muted">اگر مقصدی نیازمند بررسی باشد، پشتیبانی نتیجه را از پنل IBSng تطبیق می‌دهد. وضعیت هر سرور مستقل ثبت می‌شود.</p>
<?php endif; ?>
<p class="muted">این لینک برای مشاهده وضعیت همین سفارش است؛ آن را در اختیار افراد دیگر قرار ندهید. اطلاعات رمز حساب در این صفحه نمایش داده نمی‌شود.</p>
<?php endif; ?>
</section>
</main>
</body>
</html>
