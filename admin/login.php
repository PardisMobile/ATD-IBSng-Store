<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_once dirname(__DIR__) . '/app/messages.php';

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
        $error = app_message('admin_login_csrf_invalid');
    } else {
        $tries = (int)($_SESSION['admin_login_tries'] ?? 0);
        $lastTry = (int)($_SESSION['admin_login_last_try'] ?? 0);
        if ($lastTry > 0 && time() - $lastTry >= 900) {
            $tries = 0;
            unset($_SESSION['admin_login_tries'], $_SESSION['admin_login_last_try']);
        }
        if ($tries >= 8 && time() - $lastTry < 900) {
            $error = app_message('admin_login_throttled');
        } else {
            try {
                if (admin_login($username, $password)) {
                    unset($_SESSION['admin_login_tries'], $_SESSION['admin_login_last_try']);
                    header('Location: ' . app_url('admin/'));
                    exit;
                }
                $_SESSION['admin_login_tries'] = $tries + 1;
                $_SESSION['admin_login_last_try'] = time();
                $error = app_message('admin_login_invalid_credentials');
            } catch (Throwable $e) {
                $error = app_message('admin_login_unavailable');
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
<title data-ui-title-key="title_admin_login">ورود مدیر | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_url('assets/css/unified.css')) ?>">
<style>
.auth-card{width:min(480px,calc(100% - 32px));margin:clamp(28px,8vh,80px) auto;padding:clamp(24px,5vw,40px);box-sizing:border-box}.field{display:grid;gap:8px;margin:18px 0}.field label{color:var(--muted)}.field input{display:block;width:100%;min-width:0;box-sizing:border-box;min-height:var(--ui-control-height);padding:12px 14px;border:1px solid var(--border);border-radius:var(--ui-radius-md);background:var(--control-bg);color:var(--text);font:inherit}.field input:focus-visible{outline:2px solid var(--accent);outline-offset:1px;box-shadow:0 0 0 3px var(--ui-focus-ring)}.field input::placeholder{color:var(--muted)}.notice{padding:13px;border:1px solid #ff718044;background:#ff718018;border-radius:12px;margin:16px 0}.auth-card button{width:100%;border:0;cursor:pointer;font:inherit}.auth-card h1{font-size:1.8rem}
.auth-card .field input[name="username"],.auth-card .field input[name="password"]{direction:ltr;text-align:left;unicode-bidi:plaintext;font-variant-numeric:tabular-nums}
.auth-card button:focus-visible,.auth-card a:focus-visible{outline:2px solid var(--accent);outline-offset:3px;box-shadow:0 0 0 4px var(--ui-focus-ring)}
.auth-card .notice{border-color:color-mix(in srgb,var(--danger,#dc2626) 35%,var(--border));background:color-mix(in srgb,var(--danger,#dc2626) 8%,var(--surface));color:var(--text);line-height:1.8;overflow-wrap:anywhere}
.auth-card .button{min-height:var(--ui-control-height,46px);display:flex;align-items:center;justify-content:center}
@media(max-width:480px){.auth-card{width:calc(100% - 20px);padding:clamp(18px,5vw,26px);margin:24px auto}.auth-card h1{font-size:clamp(1.4rem,6vw,1.8rem)}}
</style>
<script src="<?= e(app_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_url('assets/js/ui-components.js')) ?>" defer></script>
</head>
<body>
<main class="auth-card panel">
<div class="auth-toolbar"><div class="atd-preferences-slot" aria-label="ظاهر و زبان"></div></div>
<div class="eyebrow" data-ui-text="login_admin_panel">پنل مدیریت</div>
<h1 data-ui-text="login_title">ورود مدیر</h1>
<p class="muted" data-ui-text="login_intro">برای مدیریت فروشگاه وارد شوید.</p>
<?php if ($error !== ''): ?><div class="notice"><?= e($error) ?></div><?php endif; ?>
<form method="post" autocomplete="on">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
<div class="field"><label for="username" data-ui-text="login_username">نام کاربری</label><input id="username" name="username" required autocomplete="username" value="<?= e($username) ?>"></div>
<div class="field"><label for="password" data-ui-text="login_password">رمز عبور</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
<button class="button primary" type="submit" data-ui-text="login_submit">ورود به پنل</button>
<p class="muted utility-mt-sm text-center"><a href="<?= e(app_url('admin/forgot-password.php')) ?>" data-ui-text="login_forgot">فراموشی رمز عبور؟</a></p>
</form>
<p class="muted utility-mt"><a href="<?= e(app_url()) ?>" data-ui-text="login_back_store">بازگشت به فروشگاه</a></p>
</main>
</body>
</html>
