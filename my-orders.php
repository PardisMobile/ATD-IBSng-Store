<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/EmailSender.php';
require_once __DIR__ . '/app/EmailTemplates.php';

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
        $notice = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } else {
        try {
            $pdo = Database::connection();
            $action = (string)$_POST['action'];
            if ($action === 'request') {
                $email = strtolower(trim((string)($_POST['email'] ?? '')));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                    $notice = 'ایمیل واردشده معتبر نیست.';
                } else {
                    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
                    $ipHash = hash('sha256', $ip);
                    $throttle = $pdo->prepare('SELECT COUNT(*) FROM customer_order_recovery_codes WHERE (email_normalized = ? OR request_ip_hash = ?) AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
                    $throttle->execute([$email, $ipHash]);
                    if ((int)$throttle->fetchColumn() >= 5) {
                        $notice = 'درخواست‌های زیادی ثبت شده است. کمی بعد دوباره تلاش کنید.';
                    } else {
                        $hasOrders = $pdo->prepare('SELECT 1 FROM orders WHERE LOWER(TRIM(buyer_email)) = ? LIMIT 1');
                        $hasOrders->execute([$email]);
                        if ($hasOrders->fetchColumn()) {
                            // Only the newest email challenge remains usable.
                            $invalidate = $pdo->prepare('UPDATE customer_order_recovery_codes SET consumed_at = NOW() WHERE email_normalized = ? AND consumed_at IS NULL');
                            $invalidate->execute([$email]);
                            $code = (string)random_int(100000, 999999);
                            $hash = password_hash($code, PASSWORD_DEFAULT);
                            $insert = $pdo->prepare('INSERT INTO customer_order_recovery_codes (email_normalized, code_hash, request_ip_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
                            $insert->execute([$email, $hash, $ipHash]);
                            $template = EmailTemplates::get($pdo, 'order_recovery');
                            $vars = ['site_name' => (string)($config['site_title'] ?? 'ATD IBSng Store'), 'verification_code' => $code, 'expires_in' => '۱۰ دقیقه'];
                            EmailSender::send($pdo, $email, EmailTemplates::render($template['subject'], $vars), EmailTemplates::render($template['html'], $vars), EmailTemplates::render($template['text'], $vars));
                        }
                        $_SESSION['my_orders_email_pending'] = $email;
                        $stage = 'verify';
                        $notice = 'اگر این ایمیل سفارش ثبت‌شده‌ای داشته باشد، کد تأیید برای آن ارسال می‌شود.';
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
                    $notice = 'کد معتبر نیست یا منقضی شده است.';
                } else {
                    $pdo->prepare('UPDATE customer_order_recovery_codes SET consumed_at = NOW() WHERE id = ?')->execute([(int)$challenge['id']]);
                    session_regenerate_id(true);
                    $_SESSION['my_orders_email'] = $email;
                    $_SESSION['my_orders_verified_until'] = time() + 1800;
                    unset($_SESSION['my_orders_email_pending']);
                    $stage = 'orders';
                    $notice = 'ایمیل با موفقیت تأیید شد.';
                }
            } elseif ($action === 'logout') {
                unset($_SESSION['my_orders_email'], $_SESSION['my_orders_verified_until'], $_SESSION['my_orders_email_pending']);
                $email = '';
                $stage = 'request';
                $notice = 'از بخش سفارش‌های من خارج شدید.';
            }
        } catch (Throwable $error) {
            error_log('Customer order recovery failed: ' . $error->getMessage());
            $notice = 'در حال حاضر امکان انجام درخواست وجود ندارد. لطفاً بعداً تلاش کنید.';
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
        $notice = 'دریافت فهرست سفارش‌ها موقتاً ممکن نیست.';
    }
}
$statusLabels = ['pending_payment' => 'در انتظار پرداخت', 'paid' => 'پرداخت تأیید شد', 'provisioning' => 'در حال فعال‌سازی', 'active' => 'فعال', 'partial' => 'فعال‌سازی ناقص', 'failed' => 'ناموفق'];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>سفارش‌های من | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
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
.form-field[dir="ltr"]{text-align:left;unicode-bidi:plaintext}
.notice{padding:14px 16px;border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);margin:14px 0;background:var(--surface-2);color:var(--text);line-height:1.8}
.history-facts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:14px}
.history-facts>span{padding:12px 14px;border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);background:var(--surface)}
.history-facts small{display:block;color:var(--muted);font-size:.82rem}
.history-facts strong{display:block;margin-top:6px;overflow-wrap:anywhere}
.history-facts .history-amount{color:var(--success,var(--green))}
.order-row form{min-width:0}
@media(max-width:600px){.orders-shell{width:calc(100% - 24px)}.order-row{grid-template-columns:minmax(0,1fr);align-items:stretch}.order-row .button{width:100%}.history-facts{grid-template-columns:1fr}}
</style>
</head>
<body><main class="orders-shell">
<header class="topbar"><a class="brand" href="<?= e(app_url()) ?>"><?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></a><a class="button" href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a></header>
<section class="orders-card panel">
<div class="eyebrow">پیگیری و بازیابی خریدها</div><h1>سفارش‌های من</h1>
<p class="muted">برای مشاهده سفارش‌های مرتبط با ایمیل خرید، کد تأیید ایمیل را وارد کنید. همچنین می‌توانید هر سفارش را با شناسه پیگیری در صفحه پیگیری سفارش مشاهده کنید.</p>
<?php if ($notice !== ''): ?><div class="notice" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if ($stage === 'verify'): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="verify"><label for="code">کد ۶ رقمی ارسال‌شده</label><input class="form-field" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"><button class="button button-primary" type="submit">تأیید و مشاهده سفارش‌ها</button></form>
<?php elseif ($stage === 'orders'): ?>
<p class="muted">ایمیل تأییدشده: <?= e($email) ?></p>
<form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="logout"><button class="button" type="submit">خروج از فهرست سفارش‌ها</button></form>
<?php if (!$orders): ?><p class="muted">سفارشی برای این ایمیل پیدا نشد.</p><?php else: ?><div class="orders-list"><?php foreach ($orders as $order): ?><article class="order-row"><div><strong><?= e((string)$order['product_name_snapshot']) ?></strong><p class="muted">شناسه پیگیری: <code><?= e((string)$order['tracking_code']) ?></code><br>تاریخ خرید: <?= e((string)$order['created_at']) ?></p><div class="history-facts"><span><small>مبلغ پرداخت‌شده</small><strong class="history-amount"><?php if (strtoupper((string)$order['currency']) === 'USDT' && $order['price_usdt_snapshot'] !== null): ?><?= e(number_format((float)$order['price_usdt_snapshot'], 2)) ?> USDT<?php else: ?><?= e(number_format((int)$order['price_minor_snapshot'])) ?> <?= e((string)$order['currency']) ?><?php endif; ?></strong></span><span><small>وضعیت سفارش</small><strong><?= e($statusLabels[(string)$order['order_status']] ?? 'در حال بررسی') ?></strong></span></div></div><a class="button" href="<?= e(app_url('order-status.php?order=' . rawurlencode((string)$order['tracking_code']))) ?>">جزئیات سفارش</a></article><?php endforeach; ?></div><?php endif; ?>
<?php else: ?>
<div class="orders-list" style="margin-bottom:22px"><article class="order-row"><div><strong>پیگیری با شناسه سفارش</strong><p class="muted">شناسه پیگیری درج‌شده در رسید خرید را وارد کنید.</p><form method="get" action="<?= e(app_url('order-status.php')) ?>"><label for="order">شناسه سفارش</label><input class="form-field" id="order" name="order" dir="ltr" inputmode="text" maxlength="40" required placeholder="ATD-XXXXXXX یا شناسه سفارش"><button class="button button-primary" type="submit">پیگیری سفارش</button></form></div></article></div>
<div class="eyebrow">یا بازیابی همه خریدها با ایمیل</div>
<form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="request"><label for="email">ایمیل زمان خرید</label><input class="form-field" type="email" id="email" name="email" maxlength="190" required autocomplete="email"><button class="button button-primary" type="submit">ارسال کد تأیید</button></form>
<?php endif; ?>
</section></main><script src="<?= e(app_url('assets/js/ui-preferences.js')) ?>" defer></script></body></html>
