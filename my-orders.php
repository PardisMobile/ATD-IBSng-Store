<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/EmailSender.php';
require_once __DIR__ . '/app/EmailTemplates.php';
require_once __DIR__ . '/app/StorefrontView.php';
require_once __DIR__ . '/app/messages.php';

$config = app_config();
if (empty($_SESSION['my_orders_csrf'])) {
    $_SESSION['my_orders_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['my_orders_csrf'];
$notice = '';
$stage = isset($_SESSION['my_orders_email']) && (int)($_SESSION['my_orders_verified_until'] ?? 0) > time()
    ? 'orders'
    : (isset($_SESSION['my_orders_email_pending']) ? 'verify' : 'request');
$email = strtolower(trim((string)($_SESSION['my_orders_email'] ?? '')));
$pdo = null;
$orders = [];

if (isset($_POST['action'])) {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        $notice = app_message('order_recovery_invalid_request');
    } else {
        try {
            $pdo = Database::connection();
            $action = (string)$_POST['action'];
            if ($action === 'request') {
                // A new request replaces any stale pending challenge in this browser session.
                unset($_SESSION['my_orders_email_pending']);
                $stage = 'request';
                $email = strtolower(trim((string)($_POST['email'] ?? '')));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                    $notice = app_message('order_recovery_email_invalid');
                } else {
                    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
                    $ipHash = hash('sha256', $ip);
                    $throttle = $pdo->prepare('SELECT COUNT(*) FROM customer_order_recovery_codes WHERE (email_normalized = ? OR request_ip_hash = ?) AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
                    $throttle->execute([$email, $ipHash]);
                    if ((int)$throttle->fetchColumn() >= 5) {
                        $notice = app_message('order_recovery_rate_limited');
                    } else {
                        $orders = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE LOWER(TRIM(buyer_email)) = ?');
                        $orders->execute([$email]);
                        if ((int)$orders->fetchColumn() < 1) {
                            // Do not issue or send a recovery code when there is no purchase history.
                            $notice = app_message('order_recovery_code_sent_generic');
                        } else {
                            $invalidate = $pdo->prepare('UPDATE customer_order_recovery_codes SET consumed_at = NOW() WHERE email_normalized = ? AND consumed_at IS NULL');
                            $invalidate->execute([$email]);
                            $code = (string)random_int(100000, 999999);
                            $hash = password_hash($code, PASSWORD_DEFAULT);
                            $insert = $pdo->prepare('INSERT INTO customer_order_recovery_codes (email_normalized, code_hash, request_ip_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
                            $insert->execute([$email, $hash, $ipHash]);
                            $challengeId = (int)$pdo->lastInsertId();
                            $template = EmailTemplates::get($pdo, 'order_recovery');
                            $vars = ['site_name' => (string)($config['site_title'] ?? 'ATD IBSng Store'), 'verification_code' => $code, 'expires_in' => '۱۰ دقیقه'];
                            $sent = EmailSender::send($pdo, $email, EmailTemplates::render($template['subject'], $vars), EmailTemplates::render($template['html'], $vars), EmailTemplates::render($template['text'], $vars));
                            if ($sent) {
                                $_SESSION['my_orders_email_pending'] = $email;
                                $stage = 'verify';
                                $notice = app_message('order_recovery_code_sent_generic');
                            } else {
                                $pdo->prepare('UPDATE customer_order_recovery_codes SET consumed_at = NOW() WHERE id = ?')->execute([$challengeId]);
                                $notice = app_message('order_recovery_unavailable');
                            }
                        }
                    }
                }
            } elseif ($action === 'verify') {
                $email = strtolower(trim((string)($_SESSION['my_orders_email_pending'] ?? '')));
                $code = trim((string)($_POST['code'] ?? ''));
                $stmt = $pdo->prepare('SELECT id, code_hash, attempts FROM customer_order_recovery_codes WHERE email_normalized = ? AND consumed_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
                $stmt->execute([$email]);
                $challenge = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$challenge || (int)$challenge['attempts'] >= 5 || !preg_match('/^\d{6}$/D', $code) || !password_verify($code, (string)$challenge['code_hash'])) {
                    if ($challenge) {
                        $pdo->prepare('UPDATE customer_order_recovery_codes SET attempts = LEAST(attempts + 1, 5) WHERE id = ? AND attempts < 5')->execute([(int)$challenge['id']]);
                    }
                    $stage = 'verify';
                    $notice = app_message('order_recovery_code_invalid');
                } else {
                    $pdo->prepare('UPDATE customer_order_recovery_codes SET consumed_at = NOW() WHERE id = ?')->execute([(int)$challenge['id']]);
                    session_regenerate_id(true);
                    $_SESSION['my_orders_email'] = $email;
                    $_SESSION['my_orders_verified_until'] = time() + 1800;
                    unset($_SESSION['my_orders_email_pending']);
                    $stage = 'orders';
                    $notice = app_message('order_recovery_email_verified');
                }
            } elseif ($action === 'logout') {
                unset($_SESSION['my_orders_email'], $_SESSION['my_orders_verified_until'], $_SESSION['my_orders_email_pending']);
                $email = '';
                $stage = 'request';
                $notice = app_message('order_recovery_logged_out');
            }
        } catch (Throwable $error) {
            error_log('Customer order recovery failed: ' . $error->getMessage());
            if (($action ?? '') === 'request') {
                unset($_SESSION['my_orders_email_pending']);
                $stage = 'request';
            }
            $notice = app_message('order_recovery_unavailable');
        }
    }
}

if ($stage === 'orders' && $email !== '') {
    try {
        $pdo = $pdo ?? Database::connection();
        $stmt = $pdo->prepare('SELECT tracking_code, product_name_snapshot, payment_status, order_status, created_at, currency, price_minor_snapshot, price_usdt_snapshot FROM orders WHERE LOWER(buyer_email) = ? ORDER BY id DESC LIMIT 100');
        $stmt->execute([$email]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $error) {
        error_log('Customer order list failed: ' . $error->getMessage());
        $notice = app_message('order_recovery_list_unavailable');
    }
}
$statusLabels = ['pending_payment' => 'در انتظار پرداخت', 'paid' => 'پرداخت تأیید شد', 'provisioning' => 'در حال فعال‌سازی', 'active' => 'فعال', 'partial' => 'فعال‌سازی ناقص', 'failed' => 'ناموفق'];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title data-ui-title-key="title_orders">سفارش‌های من | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.orders-shell{width:min(900px,calc(100% - 32px));margin:0 auto}
.orders-card{padding:clamp(20px,4vw,34px);margin:24px 0}
.orders-list{display:grid;gap:14px;margin-top:20px}
.order-row{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:18px;padding:clamp(16px,3vw,22px);border:1px solid var(--border);border-radius:var(--ui-radius-lg,20px);background:var(--surface-2);min-width:0}
.order-row strong{display:block;font-size:1.08rem;line-height:1.65;overflow-wrap:anywhere}
.order-row .muted{margin:7px 0 0;line-height:1.9}
.order-row .button{white-space:nowrap;align-self:center}
.muted{color:var(--muted);line-height:1.8}
.form-field{display:block;width:100%;min-height:var(--ui-control-height,46px);padding:12px 14px;border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);margin:8px 0 16px;background:var(--control-bg,var(--surface-2));color:var(--text);font:inherit}
.form-field:focus-visible{outline:2px solid var(--accent);outline-offset:2px;box-shadow:0 0 0 4px var(--ui-focus-ring)}
.orders-card form>label{display:block;margin-top:8px;color:var(--muted);font-size:.9rem;font-weight:600;line-height:1.6}
.order-row code{direction:ltr;unicode-bidi:isolate;display:inline-block;max-width:100%;overflow-wrap:anywhere;font-variant-numeric:tabular-nums}
.form-field[dir="ltr"]{text-align:left;unicode-bidi:plaintext}
#code.form-field{max-width:280px;text-align:center;letter-spacing:.22em;font-variant-numeric:tabular-nums;font-weight:700}
.notice{padding:14px 16px;border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);margin:14px 0;background:var(--surface-2);color:var(--text);line-height:1.8}
.history-facts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:14px}
.history-facts>span{padding:12px 14px;border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);background:var(--surface)}
.history-facts small{display:block;color:var(--muted);font-size:.82rem}
.history-facts strong{display:block;margin-top:6px;overflow-wrap:anywhere}
.history-facts .history-amount{color:var(--success,var(--green))}
.order-row form{min-width:0}
@media(max-width:600px){.orders-shell{width:calc(100% - 24px)}.order-row{grid-template-columns:minmax(0,1fr);align-items:stretch}.order-row .button{width:100%}.history-facts{grid-template-columns:1fr}}
/* Order recovery: reinforce card hierarchy and make lookup actions easier to scan. */
.order-row{box-shadow:0 1px 0 color-mix(in srgb,var(--border) 55%,transparent);transition:border-color .18s ease,background-color .18s ease}
.order-row:hover{border-color:color-mix(in srgb,var(--accent) 38%,var(--border))}
.order-row .button{min-height:44px;display:inline-flex;align-items:center;justify-content:center;padding-inline:16px;white-space:normal;text-align:center}
.order-row form .form-field{max-width:100%;box-sizing:border-box}
.order-row form button{margin-top:2px}
.history-facts>span{min-width:0;line-height:1.7}
.history-facts strong{line-height:1.65}
.orders-card>form button{min-height:44px}
.orders-card h1{line-height:1.45;overflow-wrap:anywhere}
@media(max-width:600px){.order-row .button{width:100%}.orders-list{gap:12px}}
@media(prefers-reduced-motion:reduce){.order-row{transition:none}}
</style>
</head>
<body>
<?php storefront_header((string)($config['site_title'] ?? 'ATD IBSng Store'), 'orders'); ?>
<main class="orders-shell">
<section class="orders-card panel order-tracking-card">
<header class="orders-page-heading">
<div class="eyebrow" data-ui-text="orders_tracking_recovery">پیگیری و بازیابی خریدها</div>
<h1 data-ui-text="orders_title">سفارش‌های من</h1>
<p class="muted" data-ui-text="orders_intro">برای مشاهده جزئیات یک سفارش، کد رهگیری یا شناسه سفارش را وارد کنید. برای مشاهده همه سفارش‌های ثبت‌شده با ایمیل خود، از روش تأیید ایمیل استفاده کنید.</p>
</header>
<h2 data-ui-text="orders_lookup_heading">پیگیری با کد رهگیری یا شناسه سفارش</h2>
<p class="muted" data-ui-text="orders_lookup_help">کد رهگیری یا شناسه سفارش خود را وارد کنید.</p>
<form method="get" action="<?= e(app_url('order-status.php')) ?>">
<label for="order" data-ui-text="orders_order_id">کد رهگیری یا شناسه سفارش</label>
<input class="form-field" id="order" name="order" dir="ltr" inputmode="text" maxlength="40" required placeholder="کد رهگیری ATD-XXXXXXX یا شناسه سفارش" data-ui-placeholder="orders_order_placeholder">
<button class="button primary" type="submit" data-ui-text="orders_track_submit">پیگیری سفارش</button>
</form>
</section>

<section class="orders-card panel order-email-card">
<div class="eyebrow" data-ui-text="orders_email_recovery_heading">مشاهده سفارش‌ها با ایمیل</div>
<h2 data-ui-text="orders_email_at_purchase">ایمیلی که هنگام خرید ثبت کرده‌اید</h2>
<?php if ($notice !== ''): ?><div class="notice" role="status" aria-live="polite"><?= e($notice) ?></div><?php endif; ?>

<?php if ($stage === 'verify'): ?>
<p class="muted"><span data-ui-text="orders_verification_sent_to">کد تأیید به این ایمیل ارسال شده است:</span> <strong dir="ltr"><?= e((string)($_SESSION['my_orders_email_pending'] ?? '')) ?></strong></p>
<form method="post" class="orders-email-change-form">
<input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="request">
<label for="email" data-ui-text="orders_change_email">ارسال کد به ایمیل دیگر یا ارسال مجدد</label>
<input class="form-field" type="email" id="email" name="email" maxlength="190" required autocomplete="email" value="<?= e((string)($_SESSION['my_orders_email_pending'] ?? '')) ?>">
<button class="button primary" type="submit" data-ui-text="orders_resend_code">ارسال مجدد / تغییر ایمیل</button>
</form>
<form method="post" class="orders-email-code-form">
<input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="verify">
<label for="code" data-ui-text="orders_verification_code">کد ۶ رقمی ارسال‌شده</label>
<input class="form-field" id="code" name="code" dir="ltr" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code">
<button class="button primary" type="submit" data-ui-text="orders_verify_submit">تأیید و مشاهده سفارش‌ها</button>
</form>
<?php elseif ($stage === 'orders'): ?>
<p class="muted"><span data-ui-text="orders_verified_email">ایمیل تأییدشده:</span> <strong dir="ltr"><?= e($email) ?></strong></p>
<form method="post" class="orders-email-logout-form"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="logout"><button class="button primary" type="submit" data-ui-text="orders_logout">خروج از فهرست سفارش‌ها</button></form>
<?php if (!$orders): ?><p class="muted" data-ui-text="orders_none_for_email">سفارشی برای این ایمیل پیدا نشد.</p><?php else: ?>
<div class="orders-list"><?php foreach ($orders as $order): ?>
<article class="order-row"><div><strong><?= e((string)$order['product_name_snapshot']) ?></strong>
<p class="muted"><span data-ui-text="orders_tracking_id">کد رهگیری:</span> <code><?= e((string)$order['tracking_code']) ?></code><br><span data-ui-text="orders_purchase_date">تاریخ خرید:</span> <?= e((string)$order['created_at']) ?></p>
<div class="history-facts"><span><small data-ui-text="orders_paid_amount">مبلغ پرداخت‌شده</small><strong class="history-amount"><?php if (strtoupper((string)$order['currency']) === 'USDT' && $order['price_usdt_snapshot'] !== null): ?><?= e(number_format((float)$order['price_usdt_snapshot'], 2)) ?> USDT<?php else: ?><?= e(number_format((int)$order['price_minor_snapshot'])) ?> <?= e((string)$order['currency']) ?><?php endif; ?></strong></span><span><small data-ui-text="orders_status">وضعیت سفارش</small><strong data-ui-status="<?= e((string)$order['order_status']) ?>"><?= e($statusLabels[(string)$order['order_status']] ?? 'در حال بررسی') ?></strong></span></div>
</div><a class="button primary" href="<?= e(app_url('order-status.php?order=' . rawurlencode((string)$order['tracking_code']))) ?>" data-ui-text="orders_details">جزئیات سفارش</a></article>
<?php endforeach; ?></div><?php endif; ?>
<?php else: ?>
<form method="post" class="orders-email-request-form">
<input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="request">
<label for="email" data-ui-text="orders_email_at_purchase">ایمیلی که هنگام خرید ثبت کرده‌اید</label>
<input class="form-field" type="email" id="email" name="email" maxlength="190" required autocomplete="email">
<button class="button primary" type="submit" data-ui-text="orders_send_code">ارسال کد تأیید</button>
</form>
<?php endif; ?>
</section>
</main>
<?php storefront_footer((string)($config['site_title'] ?? 'ATD IBSng Store')); ?>
<style>
.orders-page-heading{margin:0 0 22px}
.orders-page-heading h1{line-height:1.45;margin:8px 0 12px}
.orders-page-heading p{max-width:78ch;margin-bottom:0}
.orders-card{box-sizing:border-box;width:100%;padding:clamp(20px,4vw,34px);margin:20px 0}
.orders-card h2{font-size:clamp(1.12rem,2.5vw,1.4rem);line-height:1.55;margin:0 0 10px}
.orders-card form>label{display:block;margin-top:8px;color:var(--muted);font-size:.9rem;font-weight:600;line-height:1.6}
.orders-card .form-field{box-sizing:border-box;width:100%;max-width:100%;min-height:var(--ui-control-height,46px);padding:12px 14px;border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);margin:8px 0 16px;background:var(--control-bg,var(--surface-2));color:var(--text);font:inherit}
.orders-card .form-field:focus-visible{outline:2px solid var(--accent);outline-offset:2px;box-shadow:0 0 0 4px var(--ui-focus-ring)}
.orders-card .button{font:inherit;text-decoration:none}
.orders-card .orders-email-code-form,.orders-card .orders-email-change-form{margin-top:16px;padding-top:14px;border-top:1px solid var(--border)}
.orders-card .orders-email-logout-form{margin:12px 0}
.orders-email-code-form #code{max-width:280px;text-align:center;letter-spacing:.22em;font-weight:700;font-variant-numeric:tabular-nums}
.orders-card .notice{padding:14px 16px;border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);margin:14px 0;background:var(--surface-2);color:var(--text);line-height:1.8;overflow-wrap:anywhere}
.orders-list{display:grid;gap:14px;margin-top:20px}
.order-row{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:18px;padding:clamp(16px,3vw,22px);border:1px solid var(--border);border-radius:var(--ui-radius-lg,20px);background:var(--surface-2);min-width:0;box-shadow:0 1px 0 color-mix(in srgb,var(--border) 55%,transparent);transition:border-color .18s ease,background-color .18s ease}
.order-row:hover{border-color:color-mix(in srgb,var(--accent) 38%,var(--border))}
.order-row strong{display:block;font-size:1.08rem;line-height:1.65;overflow-wrap:anywhere}
.order-row .muted{margin:7px 0 0;line-height:1.9}
.order-row .button{white-space:normal;align-self:center;text-align:center}
.order-row code{direction:ltr;unicode-bidi:isolate;display:inline-block;max-width:100%;overflow-wrap:anywhere;font-variant-numeric:tabular-nums}
.history-facts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:14px}
.history-facts>span{padding:12px 14px;border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);background:var(--surface);min-width:0;line-height:1.7}
.history-facts small{display:block;color:var(--muted);font-size:.82rem}
.history-facts strong{display:block;margin-top:6px;overflow-wrap:anywhere;line-height:1.65}
.history-facts .history-amount{color:var(--success,var(--green))}
.muted{color:var(--muted);line-height:1.8}
@media(max-width:600px){.orders-shell{width:calc(100% - 24px)}.orders-card{padding:18px}.order-row{grid-template-columns:minmax(0,1fr);align-items:stretch}.order-row .button{width:100%}.history-facts{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){.order-row{transition:none}}
</style>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script></body></html>
