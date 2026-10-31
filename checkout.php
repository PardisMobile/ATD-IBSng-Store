<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Crypto.php';

$config = app_config();
$pdo = Database::connection();
$error = '';
$successOrder = null;

if (empty($_SESSION['checkout_csrf'])) {
    $_SESSION['checkout_csrf'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['checkout_idempotency'])) {
    $_SESSION['checkout_idempotency'] = bin2hex(random_bytes(24));
}

$productId = filter_var($_GET['product_id'] ?? $_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
$product = null;
if ($productId !== false && $productId !== null && $productId > 0) {
    $stmt = $pdo->prepare('SELECT p.id,p.name,p.duration_seconds,p.price_minor,p.currency,m.server_id,m.ibsng_group_id,g.group_name FROM products p JOIN product_server_groups m ON m.product_id=p.id JOIN ibsng_servers s ON s.id=m.server_id JOIN ibsng_groups g ON g.id=m.ibsng_group_id WHERE p.id=? AND p.is_active=1 AND s.is_active=1 AND g.is_active=1 LIMIT 1');
    $stmt->execute([$productId]);
    $product = $stmt->fetch() ?: null;
}
if (!$product) {
    http_response_code(404);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $product) {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!isset($_SESSION['checkout_csrf']) || !hash_equals((string)$_SESSION['checkout_csrf'], $csrf)) {
        $error = 'نشست فرم معتبر نیست. صفحه را دوباره باز کنید.';
    } else {
        $name = trim((string)($_POST['buyer_name'] ?? ''));
        $phone = trim((string)($_POST['buyer_phone'] ?? ''));
        $email = trim((string)($_POST['buyer_email'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['account_password'] ?? '');

        if ($name === '' || strlen($name) > 150) {
            $error = 'نام خریدار الزامی است.';
        } elseif ($phone === '' || strlen($phone) > 40 || !preg_match('/^[+0-9() .-]{7,40}$/', $phone)) {
            $error = 'شماره تماس معتبر وارد کنید.';
        } elseif ($email !== '' && (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $error = 'ایمیل واردشده معتبر نیست.';
        } elseif ($username === '' || strlen($username) > 150 || !preg_match('/^[A-Za-z0-9_.-]+$/', $username)) {
            $error = 'نام کاربری باید از حروف لاتین، عدد و . _ - تشکیل شود.';
        } elseif (strlen($password) < 8 || strlen($password) > 200) {
            $error = 'رمز حساب باید حداقل ۸ کاراکتر باشد.';
        } else {
            try {
                $pdo->beginTransaction();
                $existing = $pdo->prepare('SELECT id,public_id FROM orders WHERE idempotency_key=? LIMIT 1');
                $existing->execute([(string)$_SESSION['checkout_idempotency']]);
                $prior = $existing->fetch();
                if ($prior) {
                    $pdo->commit();
                    $successOrder = (string)$prior['public_id'];
                } else {
                    $fresh = $pdo->prepare('SELECT p.id,p.name,p.duration_seconds,p.price_minor,p.currency,m.server_id,m.ibsng_group_id FROM products p JOIN product_server_groups m ON m.product_id=p.id JOIN ibsng_servers s ON s.id=m.server_id JOIN ibsng_groups g ON g.id=m.ibsng_group_id WHERE p.id=? AND p.is_active=1 AND s.is_active=1 AND g.is_active=1 LIMIT 1 FOR UPDATE');
                    $fresh->execute([$productId]);
                    $current = $fresh->fetch();
                    if (!$current) {
                        throw new RuntimeException('این پلن دیگر برای فروش فعال نیست.');
                    }
                    $publicId = bin2hex(random_bytes(16));
                    $encryptedPassword = app_encrypt_secret($password);
                    $insert = $pdo->prepare('INSERT INTO orders (public_id,buyer_name,buyer_phone,buyer_email,product_id,product_name_snapshot,duration_seconds_snapshot,price_minor_snapshot,currency,username,password_ciphertext,server_policy,payment_status,order_status,idempotency_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,\'selected\',\'pending\',\'pending_payment\',?)');
                    $insert->execute([
                        $publicId, $name, $phone, $email, (int)$current['id'], (string)$current['name'],
                        (int)$current['duration_seconds'], (int)$current['price_minor'], (string)$current['currency'],
                        $username, $encryptedPassword, (string)$_SESSION['checkout_idempotency'],
                    ]);
                    $pdo->commit();
                    $successOrder = $publicId;
                    $_SESSION['checkout_idempotency'] = bin2hex(random_bytes(24));
                    $_SESSION['checkout_csrf'] = bin2hex(random_bytes(32));
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = $e instanceof RuntimeException ? $e->getMessage() : 'ثبت سفارش انجام نشد. لطفاً دوباره تلاش کنید.';
            }
        }
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
</head>
<body>
<main class="checkout-shell">
<header class="topbar"><a class="brand" href="<?= e(app_url()) ?>"><?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></a><a class="button" href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a></header>
<section class="checkout-card panel">
<div class="eyebrow">ثبت سفارش</div>
<?php if ($successOrder !== null): ?>
<h1>درخواست سفارش ثبت شد</h1>
<div class="notice success"><strong>شماره پیگیری:</strong><br><code><?= e($successOrder) ?></code><p>این سفارش هنوز پرداخت نشده و هیچ حسابی در IBSng ایجاد نشده است. مرحله پرداخت و فعال‌سازی پس از اتصال درگاه معتبر تکمیل می‌شود.</p></div>
<a class="button primary" href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a>
<?php elseif (!$product): ?>
<h1>این پلن در دسترس نیست</h1><p class="muted">پلن انتخاب‌شده فعال نیست یا نگاشت سرور/گروه معتبر ندارد.</p><a class="button" href="<?= e(app_url()) ?>">بازگشت</a>
<?php else: ?>
<h1><?= e((string)$product['name']) ?></h1>
<div class="price"><?= number_format((int)$product['price_minor']) ?> <?= e((string)$product['currency']) ?></div>
<p class="muted">مدت: <?= number_format((int)$product['duration_seconds']) ?> ثانیه</p>
<?php if ($error !== ''): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<form method="post" autocomplete="on">
<input type="hidden" name="csrf" value="<?= e((string)$_SESSION['checkout_csrf']) ?>"><input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
<div class="field"><label for="buyer_name">نام خریدار</label><input id="buyer_name" name="buyer_name" maxlength="150" required value="<?= e((string)($_POST['buyer_name'] ?? '')) ?>"></div>
<div class="field"><label for="buyer_phone">شماره تماس</label><input id="buyer_phone" name="buyer_phone" maxlength="40" type="tel" required value="<?= e((string)($_POST['buyer_phone'] ?? '')) ?>"></div>
<div class="field"><label for="buyer_email">ایمیل (اختیاری)</label><input id="buyer_email" name="buyer_email" maxlength="190" type="email" value="<?= e((string)($_POST['buyer_email'] ?? '')) ?>"></div>
<div class="field"><label for="username">نام کاربری IBSng</label><input id="username" name="username" maxlength="150" pattern="[A-Za-z0-9_.-]+" required autocomplete="username" value="<?= e((string)($_POST['username'] ?? '')) ?>"></div>
<div class="field"><label for="account_password">رمز حساب IBSng</label><input id="account_password" name="account_password" type="password" minlength="8" maxlength="200" required autocomplete="new-password"></div>
<div class="notice">ثبت این فرم فقط سفارش پرداخت‌نشده می‌سازد. اطلاعات رمز در دیتابیس به‌صورت رمزنگاری‌شده نگهداری می‌شود. تا تأیید پرداخت، provisioning انجام نمی‌شود.</div>
<button class="button primary" type="submit">ثبت سفارش</button>
</form>
<?php endif; ?>
</section>
</main>
</body>
</html>
