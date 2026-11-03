<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Payments/PaymentFlow.php';

$provider = strtolower(trim((string)($_GET['provider'] ?? '')));
$success = false;
$orderReference = '';
$errorMessage = 'پرداخت توسط درگاه تأیید نشد. اگر مبلغ از حساب شما کسر شده، شناسه پیگیری را نگه دارید و با پشتیبانی تماس بگیرید.';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $provider !== '') {
    try {
        $result = PaymentFlow::handleCallback(Database::connection(), $provider, $_GET);
        $success = (bool)($result['paid'] ?? false);
        $orderReference = (string)$result['order_public_id'];
    } catch (Throwable $e) {
        // Provider/transport details are intentionally not exposed to customers.
        error_log('Payment callback processing failed: ' . $e->getMessage());
    }
}

$config = app_config();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>نتیجه پرداخت | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>.result-shell{width:min(700px,calc(100% - 32px));margin:0 auto}.result-card{padding:clamp(20px,4vw,34px);margin:24px 0}.notice{padding:16px;border:1px solid var(--border);border-radius:14px;line-height:1.9}.ok{border-color:#44d6a055;background:#44d6a012}.fail{border-color:#ff718055;background:#ff718012}</style>
</head>
<body>
<main class="result-shell">
<?php require __DIR__ . '/app/storefront-header.php'; ?>
<?php require __DIR__ . '/app/storefront-animated-header.php'; ?>
<section class="result-card panel">
<?php if ($success): ?>
<h1>پرداخت تأیید شد</h1>
<div class="notice ok">پرداخت سفارش شما توسط درگاه تأیید شد.<br><strong>شناسه سفارش:</strong> <code><?= e($orderReference) ?></code><p>نتیجه فعال‌سازی سرویس جداگانه ثبت و پیگیری می‌شود.</p><p><a class="button" href="<?= e(app_url('order-status.php?order=' . rawurlencode($orderReference))) ?>">پیگیری وضعیت فعال‌سازی</a></p></div>
<?php else: ?>
<h1>نتیجه پرداخت</h1>
<div class="notice fail"><?= e($errorMessage) ?></div>
<?php endif; ?>
<p><a class="button primary" href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a></p>
</section>
</main>
<script src="<?= e(app_url('assets/js/ui-preferences.js')) ?>" defer></script>
</body>
</html>
