<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Crypto.php';
require_once __DIR__ . '/app/OrderTrackingCode.php';
require_once __DIR__ . '/app/IBSngClient.php';
require_once __DIR__ . '/app/IBSngRenewalState.php';
require_once __DIR__ . '/app/StorefrontView.php';
require_once __DIR__ . '/app/StorefrontInfoIcons.php';
require_once __DIR__ . '/app/Payments/PaymentFlow.php';

$config = app_config();
$orderLookupValue = trim((string)($_GET['order'] ?? ''));
$isTrackingCode = (bool)preg_match('/^ATD-?[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{7}$/iD', $orderLookupValue);
$orderPublicId = strtolower($orderLookupValue);
$trackingCode = OrderTrackingCode::normalize($orderLookupValue);
$order = null;
$attempts = [];
$accountPassword = null;
$accountDetails = [];
$retryAvailable = false;
$retryProvider = '';
$retryMode = '';
$retryError = '';
if (empty($_SESSION['order_payment_retry_csrf'])) $_SESSION['order_payment_retry_csrf'] = bin2hex(random_bytes(32));
$productQuotaBytes = null;
$notFound = !$isTrackingCode && !preg_match('/^[a-f0-9]{32}$/D', $orderPublicId);

if (!$notFound) {
    try {
        $pdo = Database::connection();
        $lookupColumn = $isTrackingCode ? 'tracking_code' : 'public_id';
        $lookupValue = $isTrackingCode ? ('ATD-' . substr($trackingCode, 3)) : $orderPublicId;
        $stmt = $pdo->prepare(
            'SELECT o.id, o.public_id, o.tracking_code, o.product_id, o.product_name_snapshot, o.validity_days_snapshot, o.server_targets_snapshot, o.price_minor_snapshot, o.price_usdt_snapshot, o.currency, o.username, o.password_ciphertext, o.payment_status, o.order_status, o.created_at, p.quota_bytes
             FROM orders o LEFT JOIN products p ON p.id=o.product_id WHERE ' . $lookupColumn . ' = ? LIMIT 1'
        );
        $stmt->execute([$lookupValue]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$order) {
            $notFound = true;
        } elseif ($order['payment_status'] === 'pending' && $order['order_status'] === 'pending_payment') {
            $paymentStmt = $pdo->prepare('SELECT id,provider,status,provider_reference FROM payment_transactions WHERE order_id=? ORDER BY id DESC LIMIT 1');
            $paymentStmt->execute([(int)$order['id']]);
            $lastPayment = $paymentStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($lastPayment) {
                $retryProvider = strtolower(trim((string)$lastPayment['provider']));
                $available = PaymentFlow::availableProviders($pdo, [
                    'price_minor_snapshot' => $order['price_minor_snapshot'],
                    'price_usdt_snapshot' => $order['price_usdt_snapshot'],
                    'currency' => $order['currency'],
                ]);
                $providerAvailable = false;
                foreach ($available as $choice) {
                    if (($choice['code'] ?? '') === $retryProvider) {
                        $providerAvailable = true;
                        break;
                    }
                }

                if ($providerAvailable && $lastPayment['status'] === 'failed') {
                    // A definitive failed attempt can safely create a new provider session.
                    $retryAvailable = true;
                    $retryMode = 'new';
                } elseif ($providerAvailable && $lastPayment['status'] === 'pending'
                    && trim((string)($lastPayment['provider_reference'] ?? '')) !== '') {
                    // Resume the existing provider session through its adapter.
                    $retryAvailable = true;
                    $retryMode = 'resume';
                }
            } elseif (!$lastPayment) {
                // No attempt exists yet. Do not guess when multiple gateways are available.
                $available = PaymentFlow::availableProviders($pdo, [
                    'price_minor_snapshot' => $order['price_minor_snapshot'],
                    'price_usdt_snapshot' => $order['price_usdt_snapshot'],
                    'currency' => $order['currency'],
                ]);
                if (count($available) === 1) {
                    $retryProvider = (string)$available[0]['code'];
                    $retryAvailable = true;
                    $retryMode = 'new';
                }
            }
        } elseif ($order['payment_status'] === 'paid') {
            $attemptStmt = $pdo->prepare(
                'SELECT a.server_id, a.operation, a.status, a.remote_user_id, s.name AS server_name, s.service_name, s.host, s.port, s.username AS admin_username, s.password_ciphertext AS server_password
                 FROM provisioning_attempts a
                 JOIN ibsng_servers s ON s.id = a.server_id
                 WHERE a.order_id = ?
                 ORDER BY a.id ASC'
            );
            $attemptStmt->execute([(int)$order['id']]);
            $attempts = $attemptStmt->fetchAll(PDO::FETCH_ASSOC);
            $successfulAttempts = array_values(array_filter($attempts, static fn(array $attempt): bool => $attempt['status'] === 'succeeded'));
            if ($successfulAttempts) {
                try {
                    $accountPassword = app_decrypt_secret((string)$order['password_ciphertext']);
                } catch (Throwable $decryptError) {
                    error_log('Customer order credential decrypt failed: ' . $decryptError->getMessage());
                    $accountPassword = null;
                }
                foreach ($successfulAttempts as $attempt) {
                    $serverId = (int)$attempt['server_id'];
                    $accountDetails[$serverId] = [
                        'server' => trim((string)($attempt['service_name'] ?? '')) ?: (string)($attempt['server_name'] ?? ''),
                        'plan' => '',
                        'validity' => max(0, (int)$order['validity_days_snapshot']),
                        'quota_bytes' => $order['quota_bytes'] === null ? null : (int)$order['quota_bytes'],
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
                        $data = $client->lookupUser((string)$order['username']);
                        $groupName = trim((string)($data['group'] ?? ''));
                        if ($groupName !== '') {
                            try {
                                $planStmt = $pdo->prepare('SELECT plan_name FROM ibsng_groups WHERE server_id=? AND group_name=? AND is_active=1 LIMIT 1');
                                $planStmt->execute([$serverId, $groupName]);
                                $accountDetails[$serverId]['plan'] = trim((string)($planStmt->fetchColumn() ?: ''));
                            } catch (Throwable $planError) {
                                error_log('Customer order plan lookup failed for server ' . $serverId . ': ' . $planError->getMessage());
                            }
                        }
                        try {
                            $state = IBSngRenewalState::fromClient($client, (string)$order['username']);
                            $accountDetails[$serverId]['expiry'] = trim((string)($state['nearest_exp_date'] ?? ''));
                            $accountDetails[$serverId]['remaining'] = isset($state['remaining_days']) ? (int)$state['remaining_days'] : null;
                            $accountDetails[$serverId]['expired'] = isset($state['expired']) ? (bool)$state['expired'] : null;
                        } catch (Throwable $stateError) {
                            error_log('Customer order optional expiry lookup failed for server ' . $serverId . ': ' . $stateError->getMessage());
                        }
                    } catch (Throwable $detailError) {
                        error_log('Customer order IBSng detail lookup failed for server ' . $serverId . ': ' . $detailError->getMessage());
                    }
                }
            }
        }
    } catch (Throwable $e) {
        error_log('Customer order status lookup failed: ' . $e->getMessage());
        http_response_code(503);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retry_payment']) && $order && $retryAvailable) {
    $submittedCsrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals((string)$_SESSION['order_payment_retry_csrf'], $submittedCsrf)) {
        $retryError = 'درخواست نامعتبر است. صفحه را تازه‌سازی کنید و دوباره تلاش کنید.';
    } else {
        try {
            if ($retryMode === 'resume' && $lastPayment) {
                $resumeUrl = PaymentFlow::resumeTransaction($pdo, (int)$lastPayment['id']);
                if (!filter_var($resumeUrl, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($resumeUrl), 'https://')) {
                    throw new RuntimeException('Payment provider returned an invalid resume URL.');
                }
                header('Location: ' . $resumeUrl, true, 303);
                exit;
            }
            if ($retryMode !== 'new') {
                throw new RuntimeException('This payment attempt cannot be safely retried.');
            }
            $payment = PaymentFlow::initiate($pdo, (int)$order['id'], $retryProvider);
            header('Location: ' . $payment['redirect_url'], true, 303);
            exit;
        } catch (Throwable $retryException) {
            error_log('Order payment retry failed for order ' . (int)$order['id'] . ': ' . $retryException->getMessage());
            $retryError = 'شروع مجدد پرداخت انجام نشد. لطفاً کمی بعد دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.';
        }
    }
}

$statusLabels = [
    'pending_payment' => 'در انتظار پرداخت',
    'paid' => 'پرداخت تأیید شد',
    'provisioning' => 'در حال فعال‌سازی سرویس',
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
<link rel="icon" type="image/svg+xml" href="<?= e(app_asset_url('assets/icons/atd-app-icon.svg')) ?>"><link rel="apple-touch-icon" sizes="180x180" href="<?= e(app_asset_url('assets/icons/apple-touch-icon.png')) ?>"><link rel="manifest" href="<?= e(app_asset_url('site.webmanifest')) ?>">
<title data-ui-title-key="title_order_status">وضعیت سفارش | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.status-shell{width:min(820px,calc(100% - 28px));margin:0 auto}
.status-card{padding:clamp(18px,4vw,30px);margin:24px 0 36px;min-width:0}
.status-card h1{font-size:clamp(1.45rem,4vw,2rem);line-height:1.5;margin:8px 0 14px;overflow-wrap:anywhere}
.status-card h2{font-size:clamp(1.1rem,3vw,1.35rem);line-height:1.6;margin:24px 0 12px}
.notice{padding:16px;border:1px solid var(--border);border-radius:var(--ui-radius-lg,18px);margin:14px 0;line-height:1.95;background:var(--surface-2);color:var(--text);overflow-wrap:anywhere}
.muted{color:var(--muted);line-height:1.8}
.target-list{display:grid;gap:12px;margin:18px 0}
.target{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:clamp(14px,3vw,20px);border:1px solid var(--border);border-radius:var(--ui-radius-lg,18px);background:var(--surface-2);min-width:0;transition:border-color .18s ease}
.target:hover{border-color:color-mix(in srgb,var(--accent,var(--blue)) 30%,var(--border))}
.target strong{display:block;overflow-wrap:anywhere}
.target small{display:block;color:var(--muted);margin-top:4px}
.account-details{display:grid;gap:12px;margin-top:12px;min-width:0}
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
.account-details code{overflow-wrap:anywhere;word-break:break-word}
@media(max-width:560px){.account-grid{grid-template-columns:1fr}}
.state{white-space:nowrap;font-weight:700;padding:7px 12px;border-radius:999px;background:color-mix(in srgb,var(--success,var(--green)) 12%,transparent);color:var(--success,var(--green));border:1px solid color-mix(in srgb,var(--success,var(--green)) 28%,transparent);flex:0 0 auto}
.purchase-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:18px 0}
.purchase-fact{padding:15px 16px;border:1px solid var(--border);border-radius:var(--ui-radius-md,16px);background:var(--surface-2);min-width:0}
.purchase-fact span{display:block;color:var(--muted);font-size:.85rem}
.purchase-fact strong{display:block;margin-top:7px;overflow-wrap:anywhere;word-break:break-word;line-height:1.7}
.purchase-fact .paid-amount{color:var(--success,var(--green))}
.credential-panel{padding:14px;border:1px solid var(--border);border-radius:var(--ui-radius-md,16px);background:var(--surface);min-width:0}
.credential-row{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:8px 0;min-width:0}
.credential-row+.credential-row{border-top:1px solid var(--border)}
.credential-row>span{color:var(--muted)}
.credential-row code{overflow-wrap:anywhere;word-break:break-word;text-align:start}
.credential-row details{max-width:100%;min-width:0}
.target>div:first-child{min-width:0;overflow-wrap:anywhere}
@media(max-width:560px){.status-shell{width:calc(100% - 24px)}.target{align-items:flex-start;flex-direction:column}.state{white-space:normal}.purchase-summary,.account-specs{grid-template-columns:1fr}.credential-row{align-items:flex-start;flex-direction:column}}
@media(prefers-reduced-motion:reduce){.target{transition:none}}
</style>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</head>
<body>
<?php storefront_header((string)($config['site_title'] ?? 'ATD IBSng Store'), 'orders'); ?>
<main class="status-shell">
<section class="status-card panel">
<div class="eyebrow" data-ui-text="status_page_heading">پیگیری سفارش</div>
<?php if ($notFound || !$order): ?>
<h1 data-ui-text="status_not_found">سفارش یافت نشد</h1>
<p class="muted" data-ui-text="status_not_found_help">کد رهگیری یا شناسه سفارش معتبر نیست، یا سفارشی با این مشخصات پیدا نشد. لطفاً اطلاعات واردشده را بررسی کنید.</p>
<?php else: ?>
<h1><?= e((string)$order['product_name_snapshot']) ?></h1>
<div class="purchase-summary">
<div class="purchase-fact"><span class="store-info-label"><?= storefront_info_icon("tracking") ?><span data-ui-text="status_tracking_id">کد رهگیری</span></span><strong><code><?= e((string)$order['tracking_code']) ?></code></strong></div>
<div class="purchase-fact"><span class="store-info-label"><?= storefront_info_icon("status") ?><span data-ui-text="status_order_state">وضعیت سفارش</span></span><strong data-ui-status="<?= e((string)$order['order_status']) ?>"><?= e($attemptLabel((string)$order['order_status'])) ?></strong></div>
<div class="purchase-fact"><span class="store-info-label"><?= storefront_info_icon("payment") ?><span data-ui-text="status_paid_amount">مبلغ پرداخت‌شده</span></span><strong class="paid-amount"><?php if (strtoupper((string)$order['currency']) === 'USDT' && $order['price_usdt_snapshot'] !== null): ?><?= e(number_format((float)$order['price_usdt_snapshot'], 2)) ?> USDT<?php else: ?><?= e(number_format((int)$order['price_minor_snapshot'])) ?> <?= e((string)$order['currency']) ?><?php endif; ?></strong></div>
<div class="purchase-fact"><span class="store-info-label"><?= storefront_info_icon("date") ?><span data-ui-text="status_purchase_date">تاریخ خرید</span></span><strong><?= e((string)$order['created_at']) ?></strong></div>
<div class="purchase-fact"><span class="store-info-label"><?= storefront_info_icon("payment") ?><span data-ui-text="status_payment_state">وضعیت پرداخت</span></span><strong data-ui-status="<?= e((string)$order['payment_status']) ?>"><?= e($order['payment_status'] === 'paid' ? 'پرداخت تأیید شد' : ($order['payment_status'] === 'pending' ? 'در انتظار پرداخت' : (string)$order['payment_status'])) ?></strong>
<?php if ($retryAvailable && $order['payment_status'] !== 'paid'): ?>
<form method="post" style="display:grid;gap:8px;margin-top:12px">
<input type="hidden" name="csrf" value="<?= e((string)$_SESSION['order_payment_retry_csrf']) ?>">
<button class="button button-primary" type="submit" name="retry_payment" value="1">تلاش مجدد برای پرداخت</button>
</form>
<?php endif; ?>
<?php if ($retryError !== ''): ?><p class="muted" role="alert"><?= e($retryError) ?></p><?php endif; ?>
</div>
<div class="purchase-fact"><span class="store-info-label"><?= storefront_info_icon("service") ?><span data-ui-text="status_product">سرویس</span></span><strong><?= e((string)$order['product_name_snapshot']) ?></strong></div>
</div>
<?php if ($order['payment_status'] !== 'paid'): ?>
<p class="muted" data-ui-text="status_payment_unconfirmed">پرداخت این سفارش هنوز تأیید نشده است. تا زمان تأیید پرداخت، سرویس خریداری شده ایجاد نخواهد شد.</p>
<?php elseif (!$attempts): ?>
<p class="muted" data-ui-text="status_waiting_provision">پرداخت ثبت شده است، اما نتیجه فعال‌سازی هنوز آماده نیست. لطفاً چند دقیقه دیگر وضعیت سفارش را بررسی کنید.</p>
<?php else: ?>
<h2 data-ui-text="status_account_heading">مشخصات اکانت</h2>
<div class="target-list">
<?php foreach ($attempts as $attempt): ?>
<div class="target">
<div>
<?php if ($attempt['status'] === 'succeeded'): ?>
<?php $details = $accountDetails[(int)$attempt['server_id']] ?? null; ?>
<div class="account-details">
<?php $serverTitle = is_array($details) ? trim((string)($details['server'] ?? '')) : ''; ?>
<?php if ($serverTitle !== ''): ?><h3 class="account-server-title"><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="8" rx="2"/><rect x="3" y="13" width="18" height="8" rx="2"/><path d="M7 7h.01M7 17h.01"/></svg><?= e($serverTitle) ?></h3><?php endif; ?>
<div class="account-grid">
<div class="account-item"><small class="store-info-label"><?= storefront_info_icon("account") ?><span data-ui-text="status_username">نام کاربری</span></small><strong dir="ltr"><?= e((string)$order['username']) ?></strong></div>
<div class="account-item"><small class="store-info-label"><?= storefront_info_icon("password") ?><span data-ui-text="status_password">رمز عبور</span></small>
<?php if (is_string($accountPassword)): ?>
<div class="password-line"><strong dir="ltr" class="password-value" data-password="<?= e($accountPassword) ?>">••••••••</strong><button type="button" class="button primary icon-button" data-ui-password-toggle aria-label="نمایش رمز عبور" aria-pressed="false"><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
<?php else: ?><strong data-ui-text="status_password_hidden">در حال حاضر قابل نمایش نیست</strong><?php endif; ?>
</div>
<?php if (is_array($details) && trim((string)($details['plan'] ?? '')) !== ''): ?><div class="account-item"><small class="store-info-label"><?= storefront_info_icon("plan") ?><span data-fa="سرویس" data-en="Service">سرویس</span></small><strong><?= e((string)$details['plan']) ?></strong></div><?php endif; ?>
<?php if (is_array($details) && (int)($details['validity'] ?? 0) > 0): ?><div class="account-item"><small class="store-info-label"><?= storefront_info_icon("duration") ?><span data-ui-text="status_validity">مدت اعتبار</span></small><strong><?= e((string)$details['validity']) ?> <span data-ui-text="status_days">روز</span></strong></div><?php endif; ?>
<?php if (is_array($details) && array_key_exists('quota_bytes', $details)): ?><div class="account-item"><small class="store-info-label"><?= storefront_info_icon("quota") ?><span data-ui-text="status_quota">محدودیت حجم</span></small><strong><?php if ($details['quota_bytes'] === null): ?><span data-ui-text="status_unlimited">نامحدود</span><?php elseif ((int)$details['quota_bytes'] > 0): ?><?= e(number_format((int)$details['quota_bytes'] / 1073741824, 2)) ?> <span data-ui-text="status_gigabytes">گیگابایت</span><?php endif; ?></strong></div><?php endif; ?>
<?php if (is_array($details) && trim((string)($details['expiry'] ?? '')) !== ''): ?><div class="account-item"><small class="store-info-label"><?= storefront_info_icon("date") ?><span data-ui-text="status_expiry">تاریخ انقضا</span></small><strong><?= e((string)$details['expiry']) ?></strong></div><?php endif; ?>
<?php if (is_array($details) && is_bool($details['expired'] ?? null)): ?><div class="account-item"><small class="store-info-label"><?= storefront_info_icon("status") ?><span data-ui-text="status_validity_state">وضعیت اعتبار</span></small><strong data-ui-status="<?= $details['expired'] ? 'expired' : 'active' ?>"><?= $details['expired'] ? 'منقضی شده' : 'فعال' ?></strong></div><?php endif; ?>
<div class="account-item"><small class="store-info-label"><?= storefront_info_icon("guide") ?><span data-fa="راهنمای استفاده از اکانت" data-en="Account usage guide">راهنمای استفاده از اکانت</span></small><strong><a class="button primary" href="<?= e(app_url('userguide.php')) ?>" data-fa="راهنمای استفاده از اکانت" data-en="Account usage guide">راهنمای استفاده از اکانت</a></strong></div>
</div>
</div>
<?php endif; ?>
</div>
<div class="state" data-ui-status="<?= e((string)$attempt['status']) ?>"><?= e($attemptLabel((string)$attempt['status'])) ?></div>
</div>
<?php endforeach; ?>
</div>
<p class="muted" data-ui-text="status_support_review">اگر فعال‌سازی روی یکی از سرورها نیازمند بررسی باشد، پشتیبانی سایت وضعیت آن را در سرور بررسی می‌کند. نتیجه هر سرور جداگانه نمایش داده می‌شود.</p>
<?php endif; ?>
<p class="muted" data-ui-text="status_keep_tracking_private">رمز عبور فقط برای سرورهایی نمایش داده می‌شود که فعال‌سازی اکانت در آن‌ها با موفقیت تأیید شده است. کد رهگیری سفارش را محرمانه نگه دارید.</p>
<?php endif; ?>

</section>
<?php if ($order) storefront_support_card(Database::connection()); ?>
<?php storefront_footer((string)($config['site_title'] ?? 'ATD IBSng Store')); ?>
</main>
</body>
</html>
