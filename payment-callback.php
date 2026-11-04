<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Payments/PaymentFlow.php';
require_once __DIR__ . '/app/StorefrontView.php';

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
<title data-ui-title-key="title_payment_result">نتیجه پرداخت | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>.result-shell{width:min(700px,calc(100% - 32px));margin:0 auto}.result-card{padding:clamp(20px,4vw,34px);margin:24px 0}.notice{padding:16px;border:1px solid var(--border);border-radius:14px;line-height:1.9}.ok{border-color:#44d6a055;background:#44d6a012}.fail{border-color:#ff718055;background:#ff718012}</style>
</head>
<body>
<main class="result-shell">
<?php storefront_header((string)($config['site_title'] ?? 'ATD IBSng Store'), 'home'); ?>
<section class="result-card panel">
<?php if ($success): ?>
<h1 data-ui-text="payment_success_title">پرداخت تأیید شد</h1>
<div class="notice ok"><span data-ui-text="payment_success_body">پرداخت سفارش شما توسط درگاه تأیید شد.</span><br><strong data-ui-text="payment_order_id">شناسه سفارش:</strong> <code><?= e($orderReference) ?></code><p data-ui-text="payment_provisioning_note">نتیجه فعال‌سازی سرویس جداگانه ثبت و پیگیری می‌شود.</p><p><a class="button" href="<?= e(app_url('order-status.php?order=' . rawurlencode($orderReference))) ?>" data-ui-text="payment_track_activation">پیگیری وضعیت فعال‌سازی</a></p></div>
<?php else: ?>
<h1 data-ui-text="payment_result_title">نتیجه پرداخت</h1>
<div class="notice fail"><?= e($errorMessage) ?></div>
<?php endif; ?>
<p><a class="button primary" href="<?= e(app_url('/')) ?>" data-ui-text="payment_back_store">بازگشت به فروشگاه</a></p>
</section>
<?php storefront_footer((string)($config['site_title'] ?? 'ATD IBSng Store')); ?>
</main>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</body>
</html>
