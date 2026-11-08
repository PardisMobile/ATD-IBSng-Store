<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Crypto.php';
require_once __DIR__ . '/app/IBSngClient.php';
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
$paidOrder = null;
$paidAccountPassword = null;
$paidAccountCards = [];

if ($success && $orderReference !== '') {
    try {
        $pdo = Database::connection();
        $orderStmt = $pdo->prepare('SELECT id,public_id,username,password_ciphertext FROM orders WHERE public_id=? LIMIT 1');
        $orderStmt->execute([$orderReference]);
        $paidOrder = $orderStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($paidOrder) {
            try {
                $paidAccountPassword = app_decrypt_secret((string)$paidOrder['password_ciphertext']);
            } catch (Throwable $decryptError) {
                error_log('Payment success credential decrypt failed: ' . $decryptError->getMessage());
            }
            $attemptStmt = $pdo->prepare(
                'SELECT a.server_id,a.status,s.host,s.port,s.username AS admin_username,s.password_ciphertext AS server_password
                 FROM provisioning_attempts a JOIN ibsng_servers s ON s.id=a.server_id
                 WHERE a.order_id=? AND a.status=\'succeeded\' ORDER BY a.id ASC'
            );
            $attemptStmt->execute([(int)$paidOrder['id']]);
            foreach ($attemptStmt->fetchAll(PDO::FETCH_ASSOC) as $attempt) {
                try {
                    $client = new IBSngClient(
                        (string)$attempt['host'],
                        (int)$attempt['port'],
                        (string)$attempt['admin_username'],
                        app_decrypt_secret((string)$attempt['server_password'])
                    );
                    $user = $client->lookupUser((string)$paidOrder['username']);
                    $groupName = trim((string)($user['group'] ?? ''));
                    $planName = '';
                    if ($groupName !== '') {
                        $planStmt = $pdo->prepare('SELECT plan_name FROM ibsng_groups WHERE server_id=? AND group_name=? AND is_active=1 LIMIT 1');
                        $planStmt->execute([(int)$attempt['server_id'], $groupName]);
                        $planName = (string)($planStmt->fetchColumn() ?: '');
                    }
                    $paidAccountCards[] = ['plan' => $planName];
                } catch (Throwable $detailError) {
                    error_log('Payment success account detail lookup failed for server ' . (int)$attempt['server_id'] . ': ' . $detailError->getMessage());
                    $paidAccountCards[] = ['plan' => ''];
                }
            }
        }
    } catch (Throwable $accountLoadError) {
        error_log('Payment success account load failed: ' . $accountLoadError->getMessage());
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="<?= e(app_asset_url('assets/icons/atd-app-icon.svg')) ?>"><link rel="apple-touch-icon" sizes="180x180" href="<?= e(app_asset_url('assets/icons/apple-touch-icon.png')) ?>"><link rel="manifest" href="<?= e(app_asset_url('site.webmanifest')) ?>">
<title data-ui-title-key="title_payment_result">نتیجه پرداخت | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.result-shell{width:min(820px,calc(100% - 32px));margin:0 auto}
.result-card{padding:clamp(20px,4vw,34px);margin:24px 0}.result-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}.result-actions .button{flex:1 1 180px;text-align:center}
.notice{padding:16px;border:1px solid var(--border);border-radius:14px;line-height:1.9}
.ok{border-color:#44d6a055;background:#44d6a012}.fail{border-color:#ff718055;background:#ff718012}
.account-details{margin:18px 0}
.account-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.account-item{padding:clamp(13px,2.5vw,17px);border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);background:var(--surface-2);min-width:0}
.account-item small{display:block;color:var(--muted);margin-bottom:8px;line-height:1.6}
.account-item strong{overflow-wrap:anywhere;line-height:1.7}
.account-item strong[dir="ltr"],.password-value[dir="ltr"]{direction:ltr;unicode-bidi:isolate;text-align:left;display:inline-block;max-width:100%}
.password-line{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.password-line button{font:inherit;cursor:pointer;min-height:40px}
.icon-button{width:42px;height:42px;padding:8px;flex:0 0 42px;display:inline-flex;align-items:center;justify-content:center}
.icon-button .ui-icon{width:21px;height:21px}
@media(max-width:560px){.account-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php storefront_header((string)($config['site_title'] ?? 'ATD IBSng Store'), 'home'); ?>
<main class="result-shell">
<section class="result-card panel">
<?php if ($success): ?>
<h1 data-ui-text="payment_success_title">پرداخت تأیید شد</h1>
<div class="notice ok"><span data-ui-text="payment_success_body">پرداخت سفارش شما توسط درگاه تأیید شد.</span><br><strong data-ui-text="payment_order_id">شناسه سفارش:</strong> <code><?= e($orderReference) ?></code></div>
<?php if ($paidOrder): ?>
<h2 data-ui-text="status_account_heading">مشخصات اکانت</h2>
<?php $cardsToShow = $paidAccountCards ?: [['plan' => '']]; foreach ($cardsToShow as $accountCard): ?>
<section class="account-details">
<div class="account-grid">
<div class="account-item"><small data-ui-text="status_username">نام کاربری</small><strong dir="ltr"><?= e((string)$paidOrder['username']) ?></strong></div>
<div class="account-item"><small data-ui-text="status_password">رمز عبور</small>
<?php if (is_string($paidAccountPassword)): ?>
<div class="password-line"><strong dir="ltr" class="password-value" data-password="<?= e($paidAccountPassword) ?>">••••••••</strong><button type="button" class="button primary icon-button" data-ui-password-toggle aria-label="نمایش رمز" aria-pressed="false"><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
<?php else: ?><strong data-ui-text="status_password_hidden">در حال حاضر قابل نمایش نیست</strong><?php endif; ?>
</div>
<div class="account-item"><small data-ui-text="status_plan">سرویس</small><strong><?= e(trim((string)($accountCard['plan'] ?? '')) !== '' ? (string)$accountCard['plan'] : 'در حال حاضر قابل دریافت نیست') ?></strong></div>
<div class="account-item"><small data-fa="راهنمای استفاده از اکانت" data-en="Account usage guide">راهنمای استفاده از اکانت</small><strong><a class="button primary" href="<?= e(app_url('userguide.php')) ?>" data-fa="راهنمای استفاده از اکانت" data-en="Account usage guide">راهنمای استفاده از اکانت</a></strong></div>
</div>
</section>
<?php endforeach; ?>
<?php endif; ?>
<p data-ui-text="payment_provisioning_note">نتیجه فعال‌سازی سرویس جداگانه ثبت و پیگیری می‌شود.</p>
<p><a class="button primary" href="<?= e(app_url('order-status.php?order=' . rawurlencode($orderReference))) ?>" data-ui-text="payment_track_activation">پیگیری وضعیت فعال‌سازی</a></p>
<?php else: ?>
<h1 data-ui-text="payment_result_title">نتیجه پرداخت</h1>
<div class="notice fail"><?= e($errorMessage) ?></div>
<?php endif; ?>
<div class="result-actions"><a class="button primary" href="<?= e(app_url('/')) ?>" data-ui-text="payment_back_store">بازگشت به فروشگاه</a><?php if ($success): ?><a class="button primary" href="<?= e(app_url('order-status.php?order=' . rawurlencode($orderReference))) ?>" data-ui-text="payment_track_activation">پیگیری وضعیت فعال‌سازی</a><?php endif; ?></div>
</section>
<?php storefront_footer((string)($config['site_title'] ?? 'ATD IBSng Store')); ?>
</main>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</body>
</html>
