<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Crypto.php';
require_once __DIR__ . '/app/OrderTrackingCode.php';
require_once __DIR__ . '/app/IBSngClient.php';
require_once __DIR__ . '/app/IBSngRenewalState.php';
require_once __DIR__ . '/app/StorefrontView.php';

$config = app_config();
$orderLookupValue = trim((string)($_GET['order'] ?? ''));
$isTrackingCode = (bool)preg_match('/^ATD-?[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{7}$/iD', $orderLookupValue);
$orderPublicId = strtolower($orderLookupValue);
$trackingCode = OrderTrackingCode::normalize($orderLookupValue);
$order = null;
$attempts = [];
$accountPassword = null;
$accountDetails = [];
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
                    try {
                        $client = new IBSngClient(
                            (string)$attempt['host'],
                            (int)$attempt['port'],
                            (string)$attempt['admin_username'],
                            app_decrypt_secret((string)$attempt['server_password'])
                        );
                        $data = $client->lookupUser((string)$order['username']);
                        $state = IBSngRenewalState::fromClient($client, (string)$order['username']);
                        $groupName = trim((string)($data['group'] ?? ''));
                        $planStmt = $pdo->prepare('SELECT g.plan_name FROM product_server_groups psg JOIN ibsng_groups g ON g.id=psg.ibsng_group_id AND g.server_id=psg.server_id WHERE psg.product_id=? AND psg.server_id=? AND g.group_name=? AND g.is_active=1 LIMIT 1');
                        $planStmt->execute([(int)$order['product_id'], (int)$attempt['server_id'], $groupName]);
                        $planName = (string)($planStmt->fetchColumn() ?: 'نامشخص');
                        $targetSnapshots = json_decode((string)($order['server_targets_snapshot'] ?? '[]'), true);
                        $targetValidity = (int)$order['validity_days_snapshot'];
                        if (is_array($targetSnapshots)) foreach ($targetSnapshots as $targetSnapshot) {
                            if ((int)($targetSnapshot['server_id'] ?? 0) === (int)$attempt['server_id'] && (int)($targetSnapshot['validity_days'] ?? 0) > 0) { $targetValidity = (int)$targetSnapshot['validity_days']; break; }
                        }
                        $accountDetails[(int)$attempt['server_id']] = [
                            'server' => trim((string)($attempt['service_name'] ?? '')) ?: (string)$attempt['server_name'],
                            'plan' => $planName,
                            'validity' => $targetValidity,
                            'quota_bytes' => $order['quota_bytes'] === null ? null : (int)$order['quota_bytes'],
                            'expiry' => (string)($state['nearest_exp_date'] ?? ''),
                            'remaining' => (int)($state['remaining_days'] ?? 0),
                            'expired' => (bool)($state['expired'] ?? false),
                        ];
                    } catch (Throwable $detailError) {
                        error_log('Customer order account detail lookup failed for server ' . (int)$attempt['server_id'] . ': ' . $detailError->getMessage());
                    }
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
<title data-ui-title-key="title_order_status">وضعیت سفارش | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.status-shell{width:min(820px,calc(100% - 28px));margin:0 auto}
.status-card{padding:clamp(18px,4vw,30px);margin:22px 0 36px;min-width:0}
.status-card h1{font-size:clamp(1.45rem,4vw,2rem);line-height:1.5;margin:8px 0 14px;overflow-wrap:anywhere}
.status-card h2{font-size:clamp(1.1rem,3vw,1.35rem);line-height:1.6;margin:24px 0 12px}
.notice{padding:16px;border:1px solid var(--border);border-radius:var(--ui-radius-lg,18px);margin:14px 0;line-height:1.95;background:var(--surface-2);color:var(--text);overflow-wrap:anywhere}
.muted{color:var(--muted);line-height:1.8}
.target-list{display:grid;gap:12px;margin:18px 0}
.target{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:clamp(14px,3vw,20px);border:1px solid var(--border);border-radius:var(--ui-radius-lg,18px);background:var(--surface-2);min-width:0;transition:border-color .18s ease}
.target:hover{border-color:color-mix(in srgb,var(--accent,var(--blue)) 30%,var(--border))}
.target strong{display:block;overflow-wrap:anywhere}
.target small{display:block;color:var(--muted);margin-top:4px}
.account-details{display:grid;gap:8px;margin-top:10px;min-width:0}
.account-specs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:12px}
.account-spec{padding:13px;border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);background:var(--surface);min-width:0}
.account-spec span{display:block;color:var(--muted);font-size:.85rem}
.account-spec strong{display:block;margin-top:6px;overflow-wrap:anywhere;line-height:1.7}
.account-specs .full{grid-column:1/-1}
.account-details code{overflow-wrap:anywhere;word-break:break-word}
.account-details details{padding:8px 10px;border:1px solid var(--border);border-radius:var(--ui-radius-sm,10px);background:var(--surface);min-width:0}
.account-details summary{cursor:pointer;line-height:1.7}
.account-details summary:focus-visible{outline:2px solid var(--accent,var(--blue));outline-offset:3px;border-radius:4px}
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
<div class="purchase-fact"><span data-ui-text="status_tracking_id">شناسه پیگیری</span><strong><code><?= e((string)$order['tracking_code']) ?></code></strong></div>
<div class="purchase-fact"><span data-ui-text="status_order_state">وضعیت سفارش</span><strong data-ui-status="<?= e((string)$order['order_status']) ?>"><?= e($attemptLabel((string)$order['order_status'])) ?></strong></div>
<div class="purchase-fact"><span data-ui-text="status_paid_amount">مبلغ پرداخت‌شده</span><strong class="paid-amount"><?php if (strtoupper((string)$order['currency']) === 'USDT' && $order['price_usdt_snapshot'] !== null): ?><?= e(number_format((float)$order['price_usdt_snapshot'], 2)) ?> USDT<?php else: ?><?= e(number_format((int)$order['price_minor_snapshot'])) ?> <?= e((string)$order['currency']) ?><?php endif; ?></strong></div>
<div class="purchase-fact"><span data-ui-text="status_purchase_date">تاریخ خرید</span><strong><?= e((string)$order['created_at']) ?></strong></div>
<div class="purchase-fact"><span data-ui-text="status_payment_state">وضعیت پرداخت</span><strong data-ui-status="<?= e((string)$order['payment_status']) ?>"><?= e($order['payment_status'] === 'paid' ? 'پرداخت تأیید شد' : ($order['payment_status'] === 'pending' ? 'در انتظار پرداخت' : (string)$order['payment_status'])) ?></strong></div>
<div class="purchase-fact"><span data-ui-text="status_product">محصول</span><strong><?= e((string)$order['product_name_snapshot']) ?></strong></div>
</div>
<?php if ($order['payment_status'] !== 'paid'): ?>
<p class="muted" data-ui-text="status_payment_unconfirmed">پرداخت این سفارش هنوز تأیید نشده است؛ تا پیش از تأیید پرداخت، حسابی در IBSng ایجاد نمی‌شود.</p>
<?php elseif (!$attempts): ?>
<p class="muted" data-ui-text="status_waiting_provision">پرداخت ثبت شده است، اما هنوز نتیجه‌ای برای مقصدهای فعال‌سازی در دسترس نیست. لطفاً کمی بعد دوباره بررسی کنید.</p>
<?php else: ?>
<h2 data-ui-text="status_account_heading">مشخصات اکانت</h2>
<div class="target-list">
<?php foreach ($attempts as $attempt): ?>
<div class="target">
<div><?php if (trim((string)($attempt['service_name'] ?? '')) !== ''): ?><strong><?= e((string)$attempt['service_name']) ?></strong><?php endif; ?><small><?= e((string)$attempt['operation']) ?></small>
<?php if ($attempt['status'] === 'succeeded'): ?>
<?php $details = $accountDetails[(int)$attempt['server_id']] ?? null; ?>
<div class="account-details credential-panel">
<div class="account-specs">
<div class="account-spec"><span data-ui-text="status_username">نام کاربری</span><strong><?= e((string)$order['username']) ?></strong></div>
<?php if (is_array($details)): ?>
<div class="account-spec"><span data-ui-text="status_plan">پلن</span><strong><?= e((string)$details['plan']) ?></strong></div>
<div class="account-spec"><span data-ui-text="status_server">سرور</span><strong><?= e((string)$details['server']) ?></strong></div>
<div class="account-spec"><span data-ui-text="status_validity">مدت اعتبار</span><strong><?= e((string)$details['validity']) ?> <span data-ui-text="status_days">روز</span></strong></div>
<div class="account-spec"><span data-ui-text="status_quota">محدودیت حجم</span><strong><?php if ($details['quota_bytes'] === null): ?><span data-ui-text="status_unlimited">نامحدود</span><?php else: ?><?= e(number_format((int)$details['quota_bytes'] / 1073741824, 2)) ?> <span data-ui-text="status_gigabytes">گیگابایت</span><?php endif; ?></strong></div>
<div class="account-spec"><span data-ui-text="status_expiry">تاریخ انقضا</span><strong><?= e((string)$details['expiry'] ?: 'نامشخص') ?></strong></div>
<div class="account-spec"><span data-ui-text="status_validity_state">وضعیت اعتبار</span><strong data-ui-status="<?= $details['expired'] ? 'expired' : 'active' ?>"><?= $details['expired'] ? 'منقضی شده' : 'فعال' ?></strong></div>
<?php else: ?>
<div class="account-spec"><span data-ui-text="status_service_details">مشخصات سرویس</span><strong data-ui-text="status_unavailable">در حال حاضر قابل دریافت نیست</strong></div>
<?php endif; ?>
</div>
<?php if (is_string($accountPassword)): ?>
<div class="credential-row"><span data-ui-text="status_password">رمز عبور</span><details><summary data-ui-text="status_show_password">نمایش رمز عبور</summary><code><?= e($accountPassword) ?></code></details></div>
<?php else: ?>
<div class="credential-row"><span data-ui-text="status_password">رمز عبور</span><span data-ui-text="status_password_hidden">در حال حاضر قابل نمایش نیست</span></div>
<?php endif; ?>
<p><a class="button" href="<?= e(app_url('userguide.php')) ?>" data-fa="راهنمای استفاده از اکانت" data-en="Account usage guide">راهنمای استفاده از اکانت</a></p>
</div>
<?php endif; ?>
</div>
<div class="state" data-ui-status="<?= e((string)$attempt['status']) ?>"><?= e($attemptLabel((string)$attempt['status'])) ?></div>
</div>
<?php endforeach; ?>
</div>
<p class="muted" data-ui-text="status_support_review">اگر مقصدی نیازمند بررسی باشد، پشتیبانی نتیجه را از پنل IBSng تطبیق می‌دهد. وضعیت هر سرور مستقل ثبت می‌شود.</p>
<?php endif; ?>
<p class="muted" data-ui-text="status_keep_tracking_private">رمز عبور فقط برای مقصدهایی نمایش داده می‌شود که فعال‌سازی آن‌ها با موفقیت تأیید شده است. کد پیگیری را محرمانه نگه دارید.</p>
<?php endif; ?>

</section>
<?php storefront_footer((string)($config['site_title'] ?? 'ATD IBSng Store')); ?>
</main>
</body>
</html>
