<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Crypto.php';
require_once __DIR__ . '/app/IBSngClient.php';
require_once __DIR__ . '/app/Payments/PaymentFlow.php';
require_once __DIR__ . '/app/StorefrontView.php';
require_once __DIR__ . '/app/StorefrontInfoIcons.php';

$provider = strtolower(trim((string)($_GET['provider'] ?? '')));
$success = false;
$orderReference = '';
$orderTrackingCode = '';
$paymentReference = '';
$errorMessage = 'پرداخت توسط درگاه تأیید نشد. اگر مبلغ از حساب شما کسر شده، شناسه پیگیری را نگه دارید و با پشتیبانی تماس بگیرید.';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $provider !== '') {
    try {
        $result = PaymentFlow::handleCallback(Database::connection(), $provider, $_GET);
        $success = (bool)($result['paid'] ?? false);
        $orderReference = (string)$result['order_public_id'];
        $paymentReference = trim((string)($result['provider_reference'] ?? ''));
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
        $orderStmt = $pdo->prepare('SELECT o.id,o.public_id,o.tracking_code,o.product_id,o.username,o.password_ciphertext,o.validity_days_snapshot,o.server_targets_snapshot,p.quota_bytes FROM orders o LEFT JOIN products p ON p.id=o.product_id WHERE o.public_id=? LIMIT 1');
        $orderStmt->execute([$orderReference]);
        $paidOrder = $orderStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($paidOrder) {
            $orderTrackingCode = (string)($paidOrder['tracking_code'] ?? '');
        }
        if ($paidOrder) {
            try {
                $paidAccountPassword = app_decrypt_secret((string)$paidOrder['password_ciphertext']);
            } catch (Throwable $decryptError) {
                error_log('Payment success credential decrypt failed: ' . $decryptError->getMessage());
            }
            $attemptStmt = $pdo->prepare(
                'SELECT a.server_id,a.status,s.name AS server_name,s.service_name,s.host,s.port,s.username AS admin_username,s.password_ciphertext AS server_password
                 FROM provisioning_attempts a JOIN ibsng_servers s ON s.id=a.server_id
                 WHERE a.order_id=? AND a.status=\'succeeded\' ORDER BY a.id ASC'
            );
            $attemptStmt->execute([(int)$paidOrder['id']]);
            foreach ($attemptStmt->fetchAll(PDO::FETCH_ASSOC) as $attempt) {
                $serverId = (int)$attempt['server_id'];
                $accountCard = [
                    'server' => trim((string)($attempt['service_name'] ?? '')) ?: (string)($attempt['server_name'] ?? ''),
                    'plan' => '',
                    'validity' => max(0, (int)$paidOrder['validity_days_snapshot']),
                    'quota_bytes' => $paidOrder['quota_bytes'] === null ? null : (int)$paidOrder['quota_bytes'],
                    'expiry' => '',
                    'remaining' => null,
                    'expired' => null,
                ];
                try {
                    $client = new IBSngClient(
                        (string)$attempt['host'],
                        (int)$attempt['port'],
                        (string)$attempt['admin_username'],
                        app_decrypt_secret((string)$attempt['server_password'])
                    );
                    $user = $client->lookupUser((string)$paidOrder['username']);
                    $groupName = trim((string)($user['group'] ?? ''));
                    if ($groupName !== '') {
                        try {
                            $planStmt = $pdo->prepare('SELECT plan_name FROM ibsng_groups WHERE server_id=? AND group_name=? AND is_active=1 LIMIT 1');
                            $planStmt->execute([$serverId, $groupName]);
                            $accountCard['plan'] = trim((string)($planStmt->fetchColumn() ?: ''));
                        } catch (Throwable $planError) {
                            error_log('Payment success plan lookup failed for server ' . $serverId . ': ' . $planError->getMessage());
                        }
                    }
                    try {
                        $state = IBSngRenewalState::fromClient($client, (string)$paidOrder['username']);
                        $accountCard['expiry'] = trim((string)($state['nearest_exp_date'] ?? ''));
                        $accountCard['remaining'] = isset($state['remaining_days']) ? (int)$state['remaining_days'] : null;
                        $accountCard['expired'] = isset($state['expired']) ? (bool)$state['expired'] : null;
                    } catch (Throwable $stateError) {
                        error_log('Payment success optional expiry lookup failed for server ' . $serverId . ': ' . $stateError->getMessage());
                    }
                } catch (Throwable $detailError) {
                    error_log('Payment success IBSng detail lookup failed for server ' . $serverId . ': ' . $detailError->getMessage());
                }
                $paidAccountCards[] = $accountCard;
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
.result-card{padding:clamp(20px,4vw,34px);margin:24px 0;position:relative;overflow:hidden}.result-card::before{content:"";position:absolute;inset:0 0 auto;height:4px;background:linear-gradient(90deg,var(--blue),var(--purple),var(--accent));pointer-events:none}.result-card>*{position:relative;z-index:1}.result-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}.result-actions .button{flex:1 1 180px;text-align:center}
.notice{padding:16px;border:1px solid var(--border);border-radius:14px;line-height:1.9}
.ok{border-color:#44d6a055;background:#44d6a012}.fail{border-color:#ff718055;background:#ff718012}
.account-details{margin:18px 0}.payment-details{margin:20px 0}.payment-details h2{margin-bottom:0}.payment-details .account-grid{margin-top:12px}
.account-server-title{display:flex;align-items:center;gap:9px;margin:0;padding:0 2px 10px;font-size:1.05rem;line-height:1.6;overflow-wrap:anywhere}.account-server-title .ui-icon{width:21px;height:21px;flex:0 0 21px;color:var(--accent,var(--blue))}
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
<div class="notice ok"><span data-ui-text="payment_success_body">پرداخت سفارش شما توسط درگاه تأیید شد.</span></div>
<?php if ($paidOrder): ?>
<section class="payment-details">
<h2 data-ui-text="payment_details_heading">مشخصات پرداخت</h2>
<div class="account-grid">
<div class="account-item"><small class="store-info-label"><?= storefront_info_icon("tracking") ?><span data-ui-text="payment_tracking_code">کد رهگیری</span></small><strong dir="ltr"><?= e($orderTrackingCode) ?></strong></div>
<div class="account-item"><small class="store-info-label"><?= storefront_info_icon("payment") ?><span data-ui-text="payment_gateway_reference">شناسه پرداخت</span></small><strong dir="ltr"><?= e($paymentReference !== '' ? $paymentReference : '—') ?></strong></div>
</div>
</section>
<h2 data-ui-text="status_account_heading">مشخصات اکانت</h2>
<?php $cardsToShow = $paidAccountCards ?: [['plan' => '']]; foreach ($cardsToShow as $accountCard): ?>
<section class="account-details">
<?php $serverTitle = trim((string)($accountCard['server'] ?? '')); ?>
<?php if ($serverTitle !== ''): ?><h3 class="account-server-title"><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="8" rx="2"/><rect x="3" y="13" width="18" height="8" rx="2"/><path d="M7 7h.01M7 17h.01"/></svg><?= e($serverTitle) ?></h3><?php endif; ?>
<div class="account-grid">
<div class="account-item"><small class="store-info-label"><?= storefront_info_icon("account") ?><span data-ui-text="status_username">نام کاربری</span></small><strong dir="ltr"><?= e((string)$paidOrder['username']) ?></strong></div>
<div class="account-item"><small class="store-info-label"><?= storefront_info_icon("password") ?><span data-ui-text="status_password">رمز عبور</span></small>
<?php if (is_string($paidAccountPassword)): ?>
<div class="password-line"><strong dir="ltr" class="password-value" data-password="<?= e($paidAccountPassword) ?>">••••••••</strong><button type="button" class="button primary icon-button" data-ui-password-toggle aria-label="نمایش رمز" aria-pressed="false"><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
<?php else: ?><strong data-ui-text="status_password_hidden">در حال حاضر قابل نمایش نیست</strong><?php endif; ?>
</div>
<?php if (trim((string)($accountCard['plan'] ?? '')) !== ''): ?><div class="account-item"><small class="store-info-label"><?= storefront_info_icon("plan") ?><span data-fa="سرویس" data-en="Service">سرویس</span></small><strong><?= e((string)$accountCard['plan']) ?></strong></div><?php endif; ?>
<?php if ((int)($accountCard['validity'] ?? 0) > 0): ?><div class="account-item"><small class="store-info-label"><?= storefront_info_icon("duration") ?><span data-ui-text="status_validity">مدت اعتبار</span></small><strong><?= e((string)$accountCard['validity']) ?> <span data-ui-text="status_days">روز</span></strong></div><?php endif; ?>
<?php if (array_key_exists('quota_bytes', $accountCard)): ?><div class="account-item"><small class="store-info-label"><?= storefront_info_icon("quota") ?><span data-ui-text="status_quota">محدودیت حجم</span></small><strong><?php if ($accountCard['quota_bytes'] === null): ?><span data-ui-text="status_unlimited">نامحدود</span><?php elseif ((int)$accountCard['quota_bytes'] > 0): ?><?= e(number_format((int)$accountCard['quota_bytes'] / 1073741824, 2)) ?> <span data-ui-text="status_gigabytes">گیگابایت</span><?php endif; ?></strong></div><?php endif; ?>
<?php if (trim((string)($accountCard['expiry'] ?? '')) !== ''): ?><div class="account-item"><small class="store-info-label"><?= storefront_info_icon("date") ?><span data-ui-text="status_expiry">تاریخ انقضا</span></small><strong><?= e((string)$accountCard['expiry']) ?></strong></div><?php endif; ?>
<?php if (is_bool($accountCard['expired'] ?? null)): ?><div class="account-item"><small class="store-info-label"><?= storefront_info_icon("status") ?><span data-ui-text="status_validity_state">وضعیت اعتبار</span></small><strong data-ui-status="<?= $accountCard['expired'] ? 'expired' : 'active' ?>"><?= $accountCard['expired'] ? 'منقضی شده' : 'فعال' ?></strong></div><?php endif; ?>
<div class="account-item"><small class="store-info-label"><?= storefront_info_icon("guide") ?><span data-fa="راهنمای استفاده از اکانت" data-en="Account usage guide">راهنمای استفاده از اکانت</span></small><strong><a class="button primary" href="<?= e(app_url('userguide.php')) ?>" data-fa="راهنمای استفاده از اکانت" data-en="Account usage guide">راهنمای استفاده از اکانت</a></strong></div>
</div>
</section>
<?php endforeach; ?>
<?php endif; ?>
<p data-ui-text="payment_provisioning_note">نتیجه فعال‌سازی سرویس جداگانه ثبت و پیگیری می‌شود.</p>
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
