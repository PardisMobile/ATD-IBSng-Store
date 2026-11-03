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

final class UsernameAlreadyExistsException extends RuntimeException
{
}

$config = app_config();
$pdo = Database::connection();
$priceDisplayUnit = store_price_display_unit($pdo);
$credentialSettingsStmt = $pdo->prepare('SELECT setting_key,setting_value FROM app_settings WHERE setting_key IN (?,?,?,?,?)');
$credentialSettingsStmt->execute(['credential_mode','username_prefix','username_suffix_length','password_length','password_charset']);
$credentialSettings = [];
foreach ($credentialSettingsStmt->fetchAll(PDO::FETCH_ASSOC) as $setting) {
    $credentialSettings[(string)$setting['setting_key']] = (string)$setting['setting_value'];
}
$credentialPolicy = $credentialSettings['credential_mode'] ?? 'customer';
$credentialPrefix = $credentialSettings['username_prefix'] ?? 'atd';
$credentialSuffixLength = max(4, min(12, (int)($credentialSettings['username_suffix_length'] ?? 6)));
$generatedPasswordLength = max(4, min(12, (int)($credentialSettings['password_length'] ?? 12)));
$generatedPasswordCharset = ($credentialSettings['password_charset'] ?? 'mixed') === 'alphanumeric'
    ? 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'
    : 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%*-_';
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

$productId = filter_var($_GET['product_id'] ?? $_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
$product = null;
$productTargets = [];
if ($productId !== false && $productId !== null && $productId > 0) {
    $stmt = $pdo->prepare('SELECT p.id,p.name,p.duration_seconds,p.price_minor,p.price_usdt,p.currency FROM products p WHERE p.id=? AND p.is_active=1 LIMIT 1');
    $stmt->execute([$productId]);
    $product = $stmt->fetch() ?: null;
    if ($product) {
        $targetStmt = $pdo->prepare('SELECT s.id AS server_id,s.host,s.port,s.username AS admin_username,s.password_ciphertext AS server_password,m.ibsng_group_id,g.group_name FROM product_server_groups m JOIN ibsng_servers s ON s.id=m.server_id JOIN ibsng_groups g ON g.id=m.ibsng_group_id WHERE m.product_id=? AND s.is_active=1 AND g.is_active=1 ORDER BY s.is_default DESC,s.id');
        $targetStmt->execute([$productId]);
        $productTargets = $targetStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$productTargets) $product = null;
    }
}
if (!$product) {
    http_response_code(404);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $product) {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!isset($_SESSION['checkout_csrf']) || !hash_equals((string)$_SESSION['checkout_csrf'], $csrf)) {
        $error = app_message('checkout_session_invalid');
    } else {
        $name = trim((string)($_POST['buyer_name'] ?? ''));
        $phone = trim((string)($_POST['buyer_phone'] ?? ''));
        $email = trim((string)($_POST['buyer_email'] ?? ''));
        $requestedCredentialMethod = (string)($_POST['credential_method'] ?? 'customer');
        $credentialMethod = $credentialPolicy === 'automatic'
            ? 'automatic'
            : ($credentialPolicy === 'choice' && $requestedCredentialMethod === 'automatic' ? 'automatic' : 'customer');
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['account_password'] ?? '');

        $credentialGenerationFailed = false;
        if ($credentialMethod === 'automatic') {
            $username = '';
            $password = '';
            $usernameAlphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
            try {
                for ($attempt = 0; $attempt < 12 && $username === ''; $attempt++) {
                    $candidate = $credentialPrefix . checkout_random_string($usernameAlphabet, $credentialSuffixLength);
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

        if ($name === '' || strlen($name) > 150) {
            $error = app_message('buyer_name_required');
        } elseif ($phone === '' || strlen($phone) > 40 || !preg_match('/^[+0-9() .-]{7,40}$/', $phone)) {
            $error = app_message('buyer_phone_invalid');
        } elseif ($email !== '' && (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $error = app_message('buyer_email_invalid');
        } elseif ($credentialMethod === 'automatic' && ($credentialGenerationFailed || $username === '' || $password === '')) {
            $error = app_message('username_available_check_failed');
        } elseif ($credentialMethod === 'customer' && ($username === '' || strlen($username) > 150 || !preg_match('/^[A-Za-z0-9_.-]+$/', $username))) {
            $error = app_message('username_format_invalid');
        } elseif ($credentialMethod === 'customer' && (strlen($password) < 4 || strlen($password) > 12)) {
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

                if (!$existingOrder) {
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
                    $fresh = $pdo->prepare('SELECT p.id,p.name,p.duration_seconds,p.price_minor,p.price_usdt,p.currency FROM products p WHERE p.id=? AND p.is_active=1 LIMIT 1 FOR UPDATE');
                    $fresh->execute([$productId]);
                    $current = $fresh->fetch();
                    if (!$current) throw new RuntimeException(app_message('product_unavailable'));
                    $freshTargets = $pdo->prepare('SELECT s.id AS server_id,m.ibsng_group_id,g.group_name FROM product_server_groups m JOIN ibsng_servers s ON s.id=m.server_id JOIN ibsng_groups g ON g.id=m.ibsng_group_id WHERE m.product_id=? AND s.is_active=1 AND g.is_active=1 ORDER BY s.is_default DESC,s.id FOR UPDATE');
                    $freshTargets->execute([$productId]);
                    $currentTargets = $freshTargets->fetchAll(PDO::FETCH_ASSOC);
                    if (!$currentTargets) throw new RuntimeException(app_message('product_unavailable'));
                    $publicId = bin2hex(random_bytes(16));
                    $trackingCode = OrderTrackingCode::generate($pdo);
                    $encryptedPassword = app_encrypt_secret($password);
                    $serverPolicy = count($currentTargets) > 1 ? 'configured_set' : 'selected';
                    $targetSnapshot = array_map(static fn(array $target): array => [
                        'server_id' => (int)$target['server_id'],
                        'ibsng_group_id' => (int)$target['ibsng_group_id'],
                        'group_name' => (string)$target['group_name'],
                    ], $currentTargets);
                    $insert = $pdo->prepare('INSERT INTO orders (public_id,tracking_code,buyer_name,buyer_phone,buyer_email,product_id,product_name_snapshot,duration_seconds_snapshot,price_minor_snapshot,price_usdt_snapshot,currency,username,password_ciphertext,server_policy,server_targets_snapshot,payment_status,order_status,idempotency_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'pending\',\'pending_payment\',?)');
                    $insert->execute([
                        $publicId, $trackingCode, $name, $phone, $email, (int)$current['id'], (string)$current['name'],
                        (int)$current['duration_seconds'], (int)$current['price_minor'], $current['price_usdt'] !== null ? (string)$current['price_usdt'] : null, (string)$current['currency'],
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

        $payment = PaymentFlow::initiate($pdo, $orderId);
        header('Location: ' . $payment['redirect_url'], true, 303);
        exit;
    } catch (Throwable $e) {
        error_log('Checkout payment initiation failed: ' . $e->getMessage());
        $error = 'سفارش ثبت شد، اما شروع پرداخت در حال حاضر ممکن نیست. شناسه سفارش را نگه دارید و با پشتیبانی تماس بگیرید.';
    }
}

?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>ثبت سفارش | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.checkout-shell{width:min(760px,calc(100% - 32px));margin:0 auto}.checkout-card{padding:clamp(20px,4vw,34px);margin:24px 0}.field{display:grid;gap:8px;margin:16px 0}.field label{color:var(--muted)}.field input{width:100%;padding:13px 14px;border:1px solid var(--border);border-radius:12px;background:#0d1426;color:var(--text);font:inherit}.notice{padding:15px;border:1px solid var(--border);border-radius:14px;margin:16px 0;line-height:1.85}.error{border-color:#ff718055;background:#ff718012}.success{border-color:#44d6a055;background:#44d6a012}.price{font-size:1.6rem;font-weight:800}.muted{color:var(--muted);line-height:1.8}
</style>
<script src="<?= e(app_url('assets/js/ui-preferences.js')) ?>" defer></script>
</head>
<body>
<main class="checkout-shell">
<header class="topbar"><a class="brand" href="<?= e(app_url()) ?>"><?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></a><a class="button" href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a></header>
<section class="checkout-card panel">
<div class="eyebrow">ثبت سفارش</div>
<?php if ($successOrder !== null): ?>
<h1>درخواست سفارش ثبت شد</h1>
<?php if ($error !== ''): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<div class="notice success"><strong>کد پیگیری:</strong><br><code><?= e($successTrackingCode !== '' ? $successTrackingCode : $successOrder) ?></code><p><?= e(app_message('order_pending_payment_notice')) ?></p></div>
<?php if ($successTrackingCode !== ''): ?><a class="button primary" href="<?= e(app_url('order-status.php?order=' . rawurlencode($successTrackingCode))) ?>">پیگیری وضعیت سفارش</a><?php endif; ?>
<a class="button" href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a>
<?php elseif (!$product): ?>
<h1><?= e(app_message('product_not_found')) ?></h1><a class="button" href="<?= e(app_url()) ?>">بازگشت</a>
<?php else: ?>
<h1><?= e((string)$product['name']) ?></h1>
<?php $displayPrice = store_format_price((int)$product['price_minor'], (string)$product['currency'], $priceDisplayUnit); ?>
<div class="price"><?= e($displayPrice['amount']) ?> <?= e($displayPrice['label']) ?></div>
<p class="muted">مدت: <?= number_format((int)$product['duration_seconds']) ?> ثانیه</p>
<?php if ($error !== ''): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<form method="post" autocomplete="on">
<input type="hidden" name="csrf" value="<?= e((string)$_SESSION['checkout_csrf']) ?>"><input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
<div class="field"><label for="buyer_name">نام خریدار</label><input id="buyer_name" name="buyer_name" maxlength="150" required value="<?= e((string)($_POST['buyer_name'] ?? '')) ?>"></div>
<div class="field"><label for="buyer_phone">شماره تماس</label><input id="buyer_phone" name="buyer_phone" maxlength="40" type="tel" required value="<?= e((string)($_POST['buyer_phone'] ?? '')) ?>"></div>
<div class="field"><label for="buyer_email">ایمیل (اختیاری)</label><input id="buyer_email" name="buyer_email" maxlength="190" type="email" value="<?= e((string)($_POST['buyer_email'] ?? '')) ?>"></div><p class="muted">با ثبت ایمیل، پس از پرداخت موفق اطلاعات سفارش برای شما ارسال می‌شود. اگر بدون ایمیل خرید کنید، بازیابی از طریق ایمیل ممکن نیست؛ کد پیگیری سفارش را حتماً نگه دارید.</p>
<?php if ($credentialPolicy === 'choice'): ?>
<div class="field"><label>روش دریافت اطلاعات اکانت</label><label><input type="radio" name="credential_method" value="customer"<?= (($_POST['credential_method'] ?? 'automatic') === 'customer') ? ' checked' : '' ?>> نام کاربری و رمز عبور دلخواه</label><label><input type="radio" name="credential_method" value="automatic"<?= (($_POST['credential_method'] ?? 'automatic') === 'automatic') ? ' checked' : '' ?>> نام کاربری و رمز عبور اتوماتیک</label></div>
<?php endif; ?>
<?php if ($credentialPolicy !== 'automatic'): ?>
<div id="manual-credentials" style="<?= $credentialPolicy === 'choice' && (($_POST['credential_method'] ?? 'automatic') === 'automatic') ? 'display:none' : '' ?>">
<div class="field"><label for="username">نام کاربری</label><input id="username" name="username" maxlength="150" pattern="[A-Za-z0-9_.-]+" required autocomplete="username" value="<?= e((string)($_POST['username'] ?? '')) ?>"></div>
<div class="field"><label for="account_password">رمز اکانت</label><input id="account_password" name="account_password" type="password" minlength="4" maxlength="12" required autocomplete="new-password"></div>
</div>
<?php endif; ?>
<?php if ($credentialPolicy === 'automatic' || $credentialPolicy === 'choice'): ?>
<p id="automatic-credentials-note" class="notice" style="<?= $credentialPolicy === 'choice' && (($_POST['credential_method'] ?? 'automatic') !== 'automatic') ? 'display:none' : '' ?>">نام کاربری و رمز اکانت به‌صورت امن و خودکار تولید می‌شوند و پس از پرداخت موفق در اطلاعات سفارش نمایش داده می‌شوند.</p>
<?php endif; ?>
<div class="notice"><?= e(app_message('order_submitted_notice')) ?> اطلاعات رمز در دیتابیس به‌صورت رمزنگاری‌شده نگهداری می‌شود.</div>
<button class="button primary" type="submit">ثبت سفارش</button>
</form>
<?php endif; ?>
</section>
</main>
<script>
(function () {
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
