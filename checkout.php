<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Crypto.php';
require_once __DIR__ . '/app/IBSngClient.php';
require_once __DIR__ . '/app/messages.php';
require_once __DIR__ . '/app/Pricing.php';
require_once __DIR__ . '/app/OrderTrackingCode.php';
require_once __DIR__ . '/app/Payments/PaymentFlow.php';
require_once __DIR__ . '/app/StorefrontView.php';
require_once __DIR__ . '/app/StorefrontInfoIcons.php';

final class UsernameAlreadyExistsException extends RuntimeException
{
}

$config = app_config();
$pdo = Database::connection();
$priceDisplayUnit = store_price_display_unit($pdo);
$credentialSettingsStmt = $pdo->prepare('SELECT setting_key,setting_value FROM app_settings WHERE setting_key IN (?,?,?,?,?,?)');
$credentialSettingsStmt->execute(['credential_mode','username_prefix','username_suffix_length','username_suffix_charset','password_length','password_charset']);
$credentialSettings = [];
foreach ($credentialSettingsStmt->fetchAll(PDO::FETCH_ASSOC) as $setting) {
    $credentialSettings[(string)$setting['setting_key']] = (string)$setting['setting_value'];
}
$credentialPolicy = $credentialSettings['credential_mode'] ?? 'customer';
$credentialPrefix = $credentialSettings['username_prefix'] ?? 'atd';
$credentialSuffixLength = max(4, min(11, (int)($credentialSettings['username_suffix_length'] ?? 5)));
$generatedUsernameAlphabet = match ($credentialSettings['username_suffix_charset'] ?? 'digits') {
    'digits' => '23456789',
    'letters' => 'abcdefghijklmnopqrstuvwxyz',
    'alphanumeric' => 'abcdefghijklmnopqrstuvwxyz0123456789',
    default => 'abcdefghijklmnopqrstuvwxyz',
};
$generatedPasswordLength = max(4, min(11, (int)($credentialSettings['password_length'] ?? 5)));
$generatedPasswordCharset = match ($credentialSettings['password_charset'] ?? 'alphanumeric') {
    'digits' => '23456789',
    'letters' => 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz',
    default => 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789',
};
function checkout_random_string(string $alphabet, int $length): string
{
    if ($alphabet === '' || $length < 1) throw new InvalidArgumentException('Invalid random string settings.');
    $result = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) $result .= $alphabet[random_int(0, $max)];
    return $result;
}
$error = '';
$successOrder = null;
$successTrackingCode = null;

if (empty($_SESSION['checkout_csrf'])) {
    $_SESSION['checkout_csrf'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['checkout_idempotency'])) {
    $_SESSION['checkout_idempotency'] = bin2hex(random_bytes(24));
}

$operation = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'create') === 'renew' ? 'renew' : 'create';
$renewServerId = filter_var($_GET['server_id'] ?? $_POST['server_id'] ?? null, FILTER_VALIDATE_INT);
$customerAuth = $_SESSION['customer_account_auth'] ?? null;
$renewAuthorized = $operation === 'renew' && is_array($customerAuth)
    && $renewServerId !== false && $renewServerId !== null && $renewServerId > 0
    && in_array((int)$renewServerId, array_map('intval', (array)($customerAuth['server_ids'] ?? [])), true);
if ($operation === 'renew' && !$renewAuthorized) {
    header('Location: ' . app_url('customer-account.php'), true, 303);
    exit;
}
$productId = filter_var($_GET['product_id'] ?? $_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
$product = null;
$productTargets = [];
if ($productId !== false && $productId !== null && $productId > 0) {
    $stmt = $pdo->prepare('SELECT p.id,p.name,p.price_minor,p.price_usdt FROM products p WHERE p.id=? AND p.is_active=1 LIMIT 1');
    $stmt->execute([$productId]);
    $product = $stmt->fetch() ?: null;
    if ($product) {
        $targetSql = 'SELECT s.id AS server_id,s.host,s.port,s.username AS admin_username,s.password_ciphertext AS server_password,m.ibsng_group_id,g.group_name,g.validity_days FROM product_server_groups m JOIN ibsng_servers s ON s.id=m.server_id JOIN ibsng_groups g ON g.id=m.ibsng_group_id WHERE m.product_id=? AND s.is_active=1 AND g.is_active=1';
        if ($operation === 'renew') $targetSql .= ' AND s.id=?';
        $targetSql .= ' ORDER BY s.is_default DESC,s.id';
        $targetStmt = $pdo->prepare($targetSql);
        $targetStmt->execute($operation === 'renew' ? [$productId, (int)$renewServerId] : [$productId]);
        $productTargets = $targetStmt->fetchAll(PDO::FETCH_ASSOC);
        if ($productTargets) $product['validity_days'] = (int)$productTargets[0]['validity_days'];
        if (!$productTargets) $product = null;
    }
}
if (!$product) {
    http_response_code(404);
}

$checkoutPriceSnapshot = $product ? [
    'price_minor_snapshot' => store_price_for_unit((int)$product['price_minor'], $priceDisplayUnit),
    'price_usdt_snapshot' => $product['price_usdt'] !== null ? (string)$product['price_usdt'] : null,
    'currency' => $priceDisplayUnit,
] : [];
$availablePaymentProviders = $product ? PaymentFlow::availableProviders($pdo, $checkoutPriceSnapshot) : [];
$selectedPaymentProvider = strtolower(trim((string)($_POST['payment_provider'] ?? ($availablePaymentProviders[0]['code'] ?? ''))));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $product) {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!isset($_SESSION['checkout_csrf']) || !hash_equals((string)$_SESSION['checkout_csrf'], $csrf)) {
        $error = app_message('checkout_session_invalid');
    } else {
        $name = trim((string)($_POST['buyer_name'] ?? ''));
        $phone = trim((string)($_POST['buyer_phone'] ?? ''));
        $email = trim((string)($_POST['buyer_email'] ?? ''));
        $orderLanguage = (($_POST['site_language'] ?? '') === 'en') ? 'en' : 'fa';
        $requestedCredentialMethod = (string)($_POST['credential_method'] ?? 'customer');
        $credentialMethod = $credentialPolicy === 'automatic'
            ? 'automatic'
            : ($credentialPolicy === 'choice' && $requestedCredentialMethod === 'automatic' ? 'automatic' : 'customer');
        $username = $operation === 'renew' ? (string)($customerAuth['username'] ?? '') : trim((string)($_POST['username'] ?? ''));
        $password = $operation === 'renew' ? '' : (string)($_POST['account_password'] ?? '');

        $credentialGenerationFailed = false;
        if ($operation === 'create' && $credentialMethod === 'automatic') {
            $username = '';
            $password = '';
            try {
                for ($attempt = 0; $attempt < 12 && $username === ''; $attempt++) {
                    $candidate = $credentialPrefix . checkout_random_string($generatedUsernameAlphabet, $credentialSuffixLength);
                    if (strlen($candidate) <= 150 && preg_match('/^[A-Za-z0-9_.-]+$/D', $candidate)) {
                        $available = true;
                        foreach ($productTargets as $target) {
                            $ibsngCheck = new IBSngClient((string)$target['host'], (int)$target['port'], (string)$target['admin_username'], app_decrypt_secret((string)$target['server_password']));
                            if (!$ibsngCheck->isUsernameAvailable($candidate)) { $available = false; break; }
                        }
                        if ($available) $username = $candidate;
                    }
                }
                if ($username !== '') $password = checkout_random_string($generatedPasswordCharset, $generatedPasswordLength);
            } catch (Throwable $generationError) {
                $credentialGenerationFailed = true;
            }
        }

        $availableProviderCodes = array_column($availablePaymentProviders, 'code');
        if ($availablePaymentProviders === []) {
            $error = 'در حال حاضر هیچ درگاه پرداخت سازگار و آماده‌ای برای این سفارش وجود ندارد.';
        } elseif (!in_array($selectedPaymentProvider, $availableProviderCodes, true)) {
            $error = 'لطفاً یکی از درگاه‌های پرداخت فعال را انتخاب کنید.';
        } elseif ($name === '' || strlen($name) > 150) {
            $error = app_message('buyer_name_required');
        } elseif ($phone === '' || strlen($phone) > 40 || !preg_match('/^[+0-9() .-]{7,40}$/', $phone)) {
            $error = app_message('buyer_phone_invalid');
        } elseif ($email !== '' && (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $error = app_message('buyer_email_invalid');
        } elseif ($operation === 'create' && $credentialMethod === 'automatic' && ($credentialGenerationFailed || $username === '' || $password === '')) {
            $error = app_message('username_available_check_failed');
        } elseif ($operation === 'create' && $credentialMethod === 'customer' && ($username === '' || strlen($username) > 150 || !preg_match('/^[A-Za-z0-9_.-]+$/', $username))) {
            $error = app_message('username_format_invalid');
        } elseif ($operation === 'create' && $credentialMethod === 'customer' && (strlen($password) < 4 || strlen($password) > 12)) {
            $error = app_message('password_length_invalid');
        } else {
            try {
                // Check the customer-chosen username against the mapped IBSng server
                // before accepting a new order. A lookup failure is never treated
                // as proof that the username is available.
                $priorCheck = $pdo->prepare('SELECT public_id,tracking_code FROM orders WHERE idempotency_key=? LIMIT 1');
                $priorCheck->execute([(string)$_SESSION['checkout_idempotency']]);
                $existingOrder = $priorCheck->fetch();
                if ($existingOrder) $successTrackingCode = (string)($existingOrder['tracking_code'] ?? '');

                if (!$existingOrder && $operation === 'create') {
                    if (!$productTargets) throw new RuntimeException(app_message('ibsng_server_not_found'));
                    foreach ($productTargets as $target) {
                        $ibsng = new IBSngClient(
                            (string)$target['host'],
                            (int)$target['port'],
                            (string)$target['admin_username'],
                            app_decrypt_secret((string)$target['server_password'])
                        );
                        if (!$ibsng->isUsernameAvailable($username)) {
                            if ($credentialMethod === 'automatic') {
                                throw new RuntimeException('Generated username collision; retry checkout.');
                            }
                            throw new UsernameAlreadyExistsException('IBSng username already exists on a configured product target.');
                        }
                    }
                }

                $pdo->beginTransaction();
                $existing = $pdo->prepare('SELECT id,public_id,tracking_code FROM orders WHERE idempotency_key=? LIMIT 1');
                $existing->execute([(string)$_SESSION['checkout_idempotency']]);
                $prior = $existing->fetch();
                if ($prior) {
                    $pdo->commit();
                    $successOrder = (string)$prior['public_id'];
                    $successTrackingCode = (string)($prior['tracking_code'] ?? '');
                } else {
                    $fresh = $pdo->prepare('SELECT p.id,p.name,p.price_minor,p.price_usdt FROM products p WHERE p.id=? AND p.is_active=1 LIMIT 1 FOR UPDATE');
                    $fresh->execute([$productId]);
                    $current = $fresh->fetch();
                    if (!$current) throw new RuntimeException(app_message('product_unavailable'));
                    $freshTargetSql = 'SELECT s.id AS server_id,m.ibsng_group_id,g.group_name,g.validity_days FROM product_server_groups m JOIN ibsng_servers s ON s.id=m.server_id JOIN ibsng_groups g ON g.id=m.ibsng_group_id WHERE m.product_id=? AND s.is_active=1 AND g.is_active=1';
                    if ($operation === 'renew') $freshTargetSql .= ' AND s.id=?';
                    $freshTargetSql .= ' ORDER BY s.is_default DESC,s.id FOR UPDATE';
                    $freshTargets = $pdo->prepare($freshTargetSql);
                    $freshTargets->execute($operation === 'renew' ? [$productId, (int)$renewServerId] : [$productId]);
                    $currentTargets = $freshTargets->fetchAll(PDO::FETCH_ASSOC);
                    if (!$currentTargets) throw new RuntimeException(app_message('product_unavailable'));
                    $publicId = bin2hex(random_bytes(16));
                    $trackingCode = OrderTrackingCode::generate($pdo);
                    $encryptedPassword = $operation === 'create' ? app_encrypt_secret($password) : null;
                    $serverPolicy = count($currentTargets) > 1 ? 'configured_set' : 'selected';
                    $targetSnapshot = array_map(static fn(array $target): array => [
                        'server_id' => (int)$target['server_id'],
                        'ibsng_group_id' => (int)$target['ibsng_group_id'],
                        'group_name' => (string)$target['group_name'],
                        'operation' => $operation,
                        'validity_days' => (int)$target['validity_days'],
                    ], $currentTargets);
                    $insert = $pdo->prepare('INSERT INTO orders (public_id,tracking_code,buyer_name,buyer_phone,buyer_email,language,product_id,product_name_snapshot,validity_days_snapshot,price_minor_snapshot,price_usdt_snapshot,currency,username,password_ciphertext,server_policy,server_targets_snapshot,payment_status,order_status,idempotency_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'pending\',\'pending_payment\',?)');
                    $insert->execute([
                        $publicId, $trackingCode, $name, $phone, $email, $orderLanguage, (int)$current['id'], (string)$current['name'],
                        (int)($currentTargets[0]['validity_days'] ?? 30), store_price_for_unit((int)$current['price_minor'], $priceDisplayUnit), $current['price_usdt'] !== null ? (string)$current['price_usdt'] : null, $priceDisplayUnit,
                        $username, $encryptedPassword, $serverPolicy, json_encode($targetSnapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), (string)$_SESSION['checkout_idempotency'],
                    ]);
                    $pdo->commit();
                    $successOrder = $publicId;
                    $successTrackingCode = $trackingCode;
                    $_SESSION['checkout_idempotency'] = bin2hex(random_bytes(24));
                    $_SESSION['checkout_csrf'] = bin2hex(random_bytes(32));
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $diagnostic = $e->getMessage();
                $error = match (true) {
                    $e instanceof UsernameAlreadyExistsException => app_message('username_taken'),
                    str_contains($diagnostic, 'نام کاربری یا رمز عبور مدیر را نپذیرفت') => app_message('ibsng_authentication_failed'),
                    str_contains($diagnostic, 'پاسخ غیرمنتظره از Web Panel') => app_message('ibsng_invalid_response'),
                    str_contains($diagnostic, 'پاسخ اطلاعات کاربر از IBSng قابل تشخیص نبود') => app_message('ibsng_invalid_response'),
                    $e instanceof InvalidArgumentException => app_message('username_available_check_failed'),
                    default => app_message('ibsng_connection_failed'),
                };
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $successOrder !== null && $error === '') {
    try {
        $orderLookup = $pdo->prepare('SELECT id FROM orders WHERE public_id = ? LIMIT 1');
        $orderLookup->execute([$successOrder]);
        $orderId = (int)$orderLookup->fetchColumn();
        if ($orderId < 1) {
            throw new RuntimeException('The persisted checkout order could not be resolved.');
        }

        $payment = PaymentFlow::initiate($pdo, $orderId, $selectedPaymentProvider);
        header('Location: ' . $payment['redirect_url'], true, 303);
        exit;
    } catch (Throwable $e) {
        error_log('Checkout payment initiation failed: ' . $e->getMessage());
        $error = 'ارتباط با درگاه پرداخت با اشکال مواجه شد. لطفاً از صفحه پیگیری سفارش، مجدداً برای پرداخت تلاش کنید.';
    }
}

?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="<?= e(app_asset_url('assets/icons/atd-app-icon.svg')) ?>"><link rel="apple-touch-icon" sizes="180x180" href="<?= e(app_asset_url('assets/icons/apple-touch-icon.png')) ?>"><link rel="manifest" href="<?= e(app_asset_url('site.webmanifest')) ?>">
<title data-ui-title-key="title_checkout">ثبت سفارش | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.checkout-shell{width:min(760px,calc(100% - 32px));margin:0 auto}
.checkout-card{padding:clamp(20px,4vw,34px);margin:24px 0}
.checkout-card h1{margin:8px 0 14px;line-height:1.45;overflow-wrap:anywhere}
.checkout-card .eyebrow{font-size:.82rem;font-weight:800;letter-spacing:.02em}
.checkout-card form{display:grid;gap:4px}
.checkout-card .form-section{min-width:0;margin:16px 0;padding:clamp(14px,3vw,20px);border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);background:color-mix(in srgb,var(--surface-2) 46%,transparent)}
.checkout-card .form-section legend{padding-inline:8px;color:var(--text);font-weight:800}
.checkout-card .field{display:grid;gap:8px;margin:12px 0}
.checkout-card .field>label{color:var(--muted);font-weight:700}
.checkout-card .field input{box-sizing:border-box;width:100%;min-width:0;min-height:var(--ui-control-height,46px);padding:12px 14px;border:1px solid var(--border);border-radius:var(--ui-radius-md,12px);background:var(--control-bg,var(--surface-2));color:var(--text);font:inherit;line-height:1.6;transition:border-color var(--ui-transition,180ms ease),box-shadow var(--ui-transition,180ms ease)}
.checkout-card .field input:focus-visible{outline:2px solid var(--accent);outline-offset:1px;box-shadow:0 0 0 3px var(--ui-focus-ring)}
.checkout-card .field input::placeholder{color:var(--muted);opacity:.75}
.checkout-card .field input[type="email"],.checkout-card .field input[type="tel"],.checkout-card #username,.checkout-card #account_password{direction:ltr;text-align:left;unicode-bidi:plaintext;font-variant-numeric:tabular-nums}
.checkout-card .notice code{display:inline-block;max-width:100%;direction:ltr;text-align:left;unicode-bidi:isolate;overflow-wrap:anywhere;font-variant-numeric:tabular-nums}
.checkout-card .field:has(input[type="radio"]){gap:10px}
.checkout-card .field:has(input[type="radio"])>label:first-child{margin-bottom:2px}
.checkout-card .field input[type="radio"]:focus-visible{outline:2px solid var(--accent);outline-offset:3px;box-shadow:0 0 0 4px var(--ui-focus-ring)}
.checkout-card .field input[type=radio]{width:18px;min-height:18px;height:18px;flex:0 0 18px;margin:0;accent-color:var(--accent)}
.checkout-card .field:has(input[type=radio])>label:not(:first-child){display:flex;align-items:center;gap:10px;padding:11px 13px;border:1px solid var(--border);border-radius:var(--ui-radius-md,12px);background:var(--surface-2);color:var(--text);font-weight:600}
.checkout-card .notice{padding:14px 16px;border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);margin:14px 0;line-height:1.85;overflow-wrap:anywhere}
.checkout-card .error{border-color:color-mix(in srgb,var(--danger,#dc2626) 35%,transparent);background:color-mix(in srgb,var(--danger,#dc2626) 7%,transparent)}
.checkout-card .success{border-color:color-mix(in srgb,var(--success,#16a34a) 35%,transparent);background:color-mix(in srgb,var(--success,#16a34a) 7%,transparent)}
.checkout-card .price{display:inline-flex;align-items:baseline;flex-wrap:wrap;gap:8px;font-size:clamp(1.45rem,5vw,1.8rem);font-weight:800;line-height:1.4;color:var(--text);overflow-wrap:anywhere}
.checkout-card .muted{color:var(--muted);line-height:1.85}
.checkout-card form>.button[type=submit]{width:100%;justify-content:center;margin-top:8px}
.checkout-card .topbar{gap:12px}
@media(max-width:560px){
 .checkout-shell{width:calc(100% - 20px)}
 .checkout-card{margin:24px 0;padding:clamp(16px,5vw,22px);border-radius:16px}
 .checkout-card .notice{padding:12px 14px}
 .checkout-card form>.button[type=submit]{min-height:48px}
}
@media(prefers-reduced-motion:reduce){.checkout-card *{transition:none!important}}
</style>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</head>
<body>
<?php storefront_header((string)($config['site_title'] ?? 'ATD IBSng Store'), 'home'); ?>
<main class="checkout-shell">
<section class="checkout-card panel">
<div class="eyebrow" data-ui-text="checkout_heading">ثبت سفارش</div>
<?php if ($successOrder !== null): ?>
<h1 data-ui-text="checkout_success_title">درخواست سفارش ثبت شد</h1>
<?php if ($error !== ''): ?><div class="notice error" role="alert" aria-live="assertive"><?= e($error) ?></div><?php endif; ?>
<div class="notice success" role="status" aria-live="polite"><strong class="store-info-label"><?= storefront_info_icon("tracking") ?><span data-ui-text="checkout_tracking_code">کد پیگیری:</span></strong><br><code><?= e($successTrackingCode !== '' ? $successTrackingCode : $successOrder) ?></code><p><?= e(app_message('order_pending_payment_notice')) ?></p></div>
<?php if ($successTrackingCode !== ''): ?><a class="button primary" href="<?= e(app_url('order-status.php?order=' . rawurlencode($successTrackingCode))) ?>" data-fa="پیگیری سفارش و تلاش مجدد برای پرداخت" data-en="Track order and retry payment">پیگیری سفارش و تلاش مجدد برای پرداخت</a><?php endif; ?>
<a class="button" href="<?= e(app_url('userguide.php')) ?>" data-fa="راهنمای استفاده از اکانت" data-en="Account usage guide">راهنمای استفاده از اکانت</a>
<a class="button" href="<?= e(app_url('/')) ?>" data-ui-text="checkout_back_store">بازگشت به فروشگاه</a>
<?php elseif (!$product): ?>
<h1><?= e(app_message('product_not_found')) ?></h1><a class="button" href="<?= e(app_url('/')) ?>" data-ui-text="checkout_back">بازگشت</a>
<?php else: ?>
<h1><?= e((string)$product['name']) ?></h1>
<?php $displayPrice = store_format_price((int)$product['price_minor'], $priceDisplayUnit); ?>
<div class="price store-info-value"><?= storefront_info_icon("price") ?><span><?= e($displayPrice['amount']) ?> <?= e($displayPrice['label']) ?></span></div>
<?php if ($operation === 'renew' || count($productTargets) === 1): ?><p class="muted store-info-inline"><?= storefront_info_icon("duration") ?><span data-ui-text="checkout_duration">مدت اعتبار:</span> <span data-ui-validity-days="<?= (int)$product['validity_days'] ?>"><?= e(storefront_validity_label((int)$product['validity_days'])) ?></span></p><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<form method="post" autocomplete="on">
<input type="hidden" name="site_language" id="checkout-site-language" value="fa"><input type="hidden" name="csrf" value="<?= e((string)$_SESSION['checkout_csrf']) ?>"><input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>"><input type="hidden" name="mode" value="<?= e($operation) ?>"><input type="hidden" name="server_id" value="<?= $operation === 'renew' ? (int)$renewServerId : '' ?>">
<fieldset class="form-section"><legend data-ui-text="checkout_contact_section">اطلاعات خریدار</legend>
<div class="field"><label for="buyer_name" data-ui-text="checkout_buyer_name">نام خریدار</label><input id="buyer_name" name="buyer_name" maxlength="150" required value="<?= e((string)($_POST['buyer_name'] ?? '')) ?>"></div>
<div class="field"><label for="buyer_phone" data-ui-text="checkout_phone">شماره تماس</label><input id="buyer_phone" name="buyer_phone" maxlength="40" type="tel" required value="<?= e((string)($_POST['buyer_phone'] ?? '')) ?>"></div>
<div class="field"><label for="buyer_email" data-ui-text="checkout_email_optional">ایمیل (اختیاری)</label><input id="buyer_email" name="buyer_email" maxlength="190" type="email" value="<?= e((string)($_POST['buyer_email'] ?? '')) ?>"></div><p class="muted" data-ui-text="checkout_email_help">در صورت ثبت ایمیل، اطلاعات سفارش پس از پرداخت موفق برای شما ارسال می‌شود. اگر ایمیل وارد نکنید، امکان بازیابی سفارش از طریق ایمیل وجود ندارد؛ بنابراین کد رهگیری را حتماً نگه دارید.</p>
</fieldset>
<?php if ($operation === 'renew'): ?><div class="notice"><span data-ui-text="checkout_renew_notice_before">این سفارش برای تمدید اکانت</span> <strong dir="ltr"><?= e($username) ?></strong> <span data-ui-text="checkout_renew_notice_after">ثبت می‌شود. برای ثبت درخواست تمدید، نیازی به واردکردن رمز اکانت نیست.</span></div><?php else: ?><fieldset class="form-section"><legend data-ui-text="checkout_credentials_section">اطلاعات ورود اکانت</legend>
<?php if ($credentialPolicy === 'choice'): ?>
<div class="field"><label data-ui-text="checkout_credentials_method">روش دریافت اطلاعات اکانت</label><label><input type="radio" name="credential_method" value="customer"<?= (($_POST['credential_method'] ?? 'automatic') === 'customer') ? ' checked' : '' ?>> <span data-ui-text="checkout_custom_credentials">نام کاربری و رمز عبور دلخواه</span></label><label><input type="radio" name="credential_method" value="automatic"<?= (($_POST['credential_method'] ?? 'automatic') === 'automatic') ? ' checked' : '' ?>> <span data-ui-text="checkout_auto_credentials">تولید خودکار نام کاربری و رمز عبور</span></label></div>
<?php endif; ?>
<?php if ($credentialPolicy !== 'automatic'): ?>
<div id="manual-credentials" style="<?= $credentialPolicy === 'choice' && (($_POST['credential_method'] ?? 'automatic') === 'automatic') ? 'display:none' : '' ?>">
<div class="field"><label for="username" data-ui-text="checkout_username">نام کاربری</label><input id="username" name="username" maxlength="150" pattern="[A-Za-z0-9_.-]+" required autocomplete="username" value="<?= e((string)($_POST['username'] ?? '')) ?>"></div>
<div class="field"><label for="account_password" data-ui-text="checkout_password">رمز اکانت</label><input id="account_password" name="account_password" type="password" minlength="4" maxlength="12" required autocomplete="new-password"></div>
</div>
<?php endif; ?>
<?php if ($credentialPolicy === 'automatic' || $credentialPolicy === 'choice'): ?>
<p id="automatic-credentials-note" class="notice" data-ui-text="checkout_auto_note" style="<?= $credentialPolicy === 'choice' && (($_POST['credential_method'] ?? 'automatic') !== 'automatic') ? 'display:none' : '' ?>">نام کاربری و رمز عبور به‌صورت خودکار ایجاد می‌شوند و پس از تأیید پرداخت، در صفحه جزئیات سفارش نمایش داده خواهند شد.</p>
<?php endif; ?>
</fieldset><?php endif; ?>
<fieldset class="form-section payment-method-section"><legend data-ui-text="checkout_payment_method">روش پرداخت</legend>
<?php if ($availablePaymentProviders === []): ?>
<p class="notice error" data-ui-text="checkout_no_payment_methods">در حال حاضر درگاه پرداخت آماده‌ای برای این سفارش وجود ندارد. لطفاً با پشتیبانی تماس بگیرید.</p>
<?php else: ?>
<p class="muted" data-ui-text="checkout_choose_payment_method">یکی از درگاه‌های پرداخت را انتخاب کنید.</p>
<div class="field">
<?php foreach ($availablePaymentProviders as $paymentChoice): ?>
<label><input type="radio" name="payment_provider" value="<?= e($paymentChoice['code']) ?>"<?= $selectedPaymentProvider === $paymentChoice['code'] ? ' checked' : '' ?> required>
<span><?= e($paymentChoice['label']) ?> — <?= e($paymentChoice['currency']) ?> (<?php if ($paymentChoice['currency'] === 'USDT'): ?><?= e(rtrim(rtrim(number_format($paymentChoice['amount_minor'] / 100000000, 8, '.', ''), '0'), '.')) ?> USDT<?php else: ?><?= e(store_format_price((int)$paymentChoice['amount_minor'], $paymentChoice['currency'])['amount']) ?> <?= e(store_format_price((int)$paymentChoice['amount_minor'], $paymentChoice['currency'])['label']) ?><?php endif; ?>)</span></label>
<?php endforeach; ?>
</div>
<?php endif; ?>
</fieldset>
<div class="notice"><?= e(app_message('order_submitted_notice')) ?> <span data-ui-text="checkout_password_storage">اطلاعات رمز در دیتابیس به‌صورت رمزنگاری‌شده نگهداری می‌شود.</span></div>
<?php $checkoutRules = ''; try { $rulesQuery = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1'); $rulesQuery->execute(['storefront_rules']); $checkoutRules = trim((string)$rulesQuery->fetchColumn()); } catch (Throwable) {} ?>
<?php if ($checkoutRules !== ''): ?><button class="store-rules-checkout-link" type="button" id="open-store-rules" aria-haspopup="dialog" aria-controls="store-rules-dialog"><?= storefront_info_icon('guide') ?><span data-fa="مطالعه قوانین خرید و تمدید" data-en="Read purchase and renewal terms">مطالعه قوانین خرید و تمدید</span></button><?php endif; ?>
<button class="button primary" type="submit" data-ui-text="checkout_submit"<?= $availablePaymentProviders === [] ? ' disabled' : '' ?>>ثبت سفارش و پرداخت</button>
</form>
<?php if ($checkoutRules !== ''): ?>
<dialog class="store-rules-dialog" id="store-rules-dialog" aria-labelledby="store-rules-dialog-title">
  <div class="store-rules-dialog__panel">
    <header class="store-rules-dialog__header">
      <h2 id="store-rules-dialog-title" data-fa="قوانین خرید و تمدید" data-en="Purchase and renewal terms">قوانین خرید و تمدید</h2>
      <button class="store-rules-dialog__close" type="button" aria-label="بستن پنجره" data-fa-aria="بستن پنجره" data-en-aria="Close dialog" data-ui-aria="store_rules_close" id="close-store-rules" autofocus>&times;</button>
    </header>
    <div class="store-rules-dialog__body"><?= nl2br(e($checkoutRules)) ?></div>
    <footer class="store-rules-dialog__footer">
      <button class="button primary" type="button" id="dismiss-store-rules" data-fa="متوجه شدم، بستن" data-en="Got it, close">متوجه شدم، بستن</button>
    </footer>
  </div>
</dialog>
<style>
.store-rules-checkout-link{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;margin:12px 0 16px;padding:12px 14px;border:1px solid var(--border);border-radius:var(--ui-radius-md,12px);background:var(--surface-2);color:var(--text);font:inherit;font-weight:700;text-decoration:none;cursor:pointer}
.store-rules-checkout-link:hover{border-color:var(--accent);color:var(--accent)}
.store-rules-checkout-link .store-info-icon{width:20px;height:20px;flex:0 0 20px}
.store-rules-dialog{width:min(620px,calc(100% - 28px));max-width:620px;max-height:min(82dvh,760px);padding:0;border:1px solid color-mix(in srgb,var(--accent) 40%,var(--border));border-radius:20px;background:var(--surface);color:var(--text);box-shadow:0 24px 80px #0009;overflow:hidden}
.store-rules-dialog::backdrop{background:rgba(2,6,23,.72);backdrop-filter:blur(5px)}
.store-rules-dialog__panel{display:flex;flex-direction:column;max-height:min(82dvh,760px)}
.store-rules-dialog__header{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:18px 20px;border-bottom:1px solid var(--border)}
.store-rules-dialog__header h2{margin:0;font-size:1.08rem;line-height:1.6}
.store-rules-dialog__close{display:grid;place-items:center;width:38px;height:38px;flex:0 0 38px;border:1px solid var(--border);border-radius:12px;background:var(--surface-2);color:var(--text);font-size:25px;line-height:1;cursor:pointer}
.store-rules-dialog__body{padding:20px;overflow:auto;white-space:normal;overflow-wrap:anywhere;line-height:2}
.store-rules-dialog__footer{padding:14px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end}
.store-rules-dialog__footer .button{min-width:140px}
@media(max-width:560px){.store-rules-dialog{width:calc(100% - 20px);border-radius:16px}.store-rules-dialog__header,.store-rules-dialog__body{padding:16px}.store-rules-dialog__footer{padding:12px 16px}}
</style>
<script>
(function(){
  const dialog=document.getElementById('store-rules-dialog');
  const open=document.getElementById('open-store-rules');
  if(!dialog||!open||typeof dialog.showModal!=='function')return;
  const close=()=>{if(dialog.open)dialog.close();};
  open.addEventListener('click',()=>{if(!dialog.open)dialog.showModal();});
  document.getElementById('close-store-rules')?.addEventListener('click',close);
  document.getElementById('dismiss-store-rules')?.addEventListener('click',close);
  dialog.addEventListener('click',event=>{if(event.target===dialog)close();});
  dialog.addEventListener('close',()=>open.focus());
})();
</script>
<?php endif; ?>
<script>(function(){const field=document.getElementById("checkout-site-language");const form=field&&field.form;if(!form)return;const sync=()=>{field.value=document.documentElement.lang==="en"?"en":"fa";};sync();form.addEventListener("submit",sync);document.addEventListener("atd:language-change",sync);})();</script>
<?php endif; ?>
</section>
<?php storefront_footer((string)($config['site_title'] ?? 'ATD IBSng Store')); ?>
</main>
<script>
(function () {
  if (<?= $operation === 'renew' ? 'true' : 'false' ?>) return;
  const radios = document.querySelectorAll('input[name="credential_method"]');
  const manual = document.getElementById('manual-credentials');
  const automaticNote = document.getElementById('automatic-credentials-note');
  function updateCredentialFields() {
    const automatic = document.querySelector('input[name="credential_method"]:checked')?.value === 'automatic';
    if (manual) {
      manual.style.display = automatic ? 'none' : '';
      manual.querySelectorAll('input').forEach((input) => { input.required = !automatic; });
    }
    if (automaticNote) automaticNote.style.display = automatic ? '' : 'none';
  }
  radios.forEach((radio) => radio.addEventListener('change', updateCredentialFields));
  updateCredentialFields();
})();
</script>
</body>
</html>
