<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';

if (admin_is_authenticated()) {
    header('Location: ' . app_url('admin/'));
    exit;
}

$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $token = (string)($_POST['csrf'] ?? '');

    if (!admin_csrf_valid($token)) {
        $error = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } else {
        $tries = (int)($_SESSION['admin_login_tries'] ?? 0);
        $lastTry = (int)($_SESSION['admin_login_last_try'] ?? 0);
        if ($lastTry > 0 && time() - $lastTry >= 900) {
            $tries = 0;
            unset($_SESSION['admin_login_tries'], $_SESSION['admin_login_last_try']);
        }
        if ($tries >= 8 && time() - $lastTry < 900) {
            $error = 'تلاش‌های ورود بیش از حد بوده است. ۱۵ دقیقه دیگر دوباره تلاش کنید.';
        } else {
            try {
                if (admin_login($username, $password)) {
                    unset($_SESSION['admin_login_tries'], $_SESSION['admin_login_last_try']);
                    header('Location: ' . app_url('admin/'));
                    exit;
                }
                $_SESSION['admin_login_tries'] = $tries + 1;
                $_SESSION['admin_login_last_try'] = time();
                $error = 'نام کاربری یا رمز عبور صحیح نیست.';
            } catch (Throwable $e) {
                $error = 'ورود در حال حاضر ممکن نیست. وضعیت نصب و دیتابیس را بررسی کنید.';
            }
        }
    }
}
$config = app_config();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود مدیر | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.auth-card{max-width:480px;margin:8vh auto;padding:clamp(24px,5vw,40px)}.field{display:grid;gap:8px;margin:18px 0}.field label{color:var(--muted)}.field input{width:100%;padding:13px 14px;border:1px solid var(--border);border-radius:12px;background:#0d1426;color:var(--text);font:inherit}.notice{padding:13px;border:1px solid #ff718044;background:#ff718018;border-radius:12px;margin:16px 0}.auth-card button{width:100%;border:0;cursor:pointer;font:inherit}.auth-card h1{font-size:1.8rem}
</style>
</head>
<body>
<main class="auth-card panel">
<div class="eyebrow">پنل مدیریت</div>
<h1>ورود مدیر</h1>
<p class="muted">برای مدیریت فروشگاه وارد شوید.</p>
<?php if ($error !== ''): ?><div class="notice"><?= e($error) ?></div><?php endif; ?>
<form method="post" autocomplete="on">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
<div class="field"><label for="username">نام کاربری</label><input id="username" name="username" required autocomplete="username" value="<?= e($username) ?>"></div>
<div class="field"><label for="password">رمز عبور</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
<button class="button primary" type="submit">ورود به پنل</button>
</form>
<p class="muted" style="margin-top:22px"><a href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a></p>
</main>
</body>
</html>
