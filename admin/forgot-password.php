<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_once dirname(__DIR__) . '/app/EmailSender.php';
$pdo = Database::connection();
$config = app_config();
$generic = app_message('admin_forgot_generic');
$error = '';
$ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        $error = app_message('admin_forgot_invalid_request');
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 190) {
            $error = app_message('admin_forgot_email_invalid');
        } else {
            try {
                $rate = $pdo->prepare('SELECT COUNT(*) FROM admin_password_resets WHERE request_ip_hash = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)');
                $rate->execute([$ipHash]);
                if ((int)$rate->fetchColumn() < 5) {
                    $find = $pdo->prepare('SELECT id, email FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1');
                    $find->execute([$email]);
                    $admin = $find->fetch(PDO::FETCH_ASSOC);
                    if ($admin) {
                        $token = bin2hex(random_bytes(32));
                        $hash = hash('sha256', $token);
                        $pdo->beginTransaction();
                        $pdo->prepare('UPDATE admin_password_resets SET used_at = NOW() WHERE admin_id = ? AND used_at IS NULL')->execute([(int)$admin['id']]);
                        $add = $pdo->prepare('INSERT INTO admin_password_resets (admin_id, token_hash, request_ip_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))');
                        $add->execute([(int)$admin['id'], $hash, $ipHash]);
                        $pdo->commit();
                        $url = app_url('admin/reset-password.php?token=' . rawurlencode($token));
                        $name = e((string)($config['site_title'] ?? 'ATD IBSng Store'));
                        $link = e($url);
                        $html = '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><body style="font-family:Arial;line-height:1.9"><h2>بازیابی رمز مدیریت</h2><p>برای ' . $name . ' لینک زیر تا ۳۰ دقیقه و فقط یک‌بار معتبر است.</p><p><a href="' . $link . '">تعیین رمز جدید</a></p><p>اگر شما درخواست نداده‌اید، ایمیل را نادیده بگیرید.</p></body></html>';
                        $text = "بازیابی رمز مدیریت\nلینک یک‌بارمصرف (۳۰ دقیقه):\n" . $url . "\nاگر درخواست نداده‌اید این پیام را نادیده بگیرید.";
                        try {
                            if (!EmailSender::send($pdo, (string)$admin['email'], 'بازیابی رمز عبور مدیریت', $html, $text)) {
                                error_log('Admin recovery mail transport returned false.');
                            }
                        } catch (Throwable $mailError) {
                            error_log('Admin recovery email failed: ' . $mailError->getMessage());
                        }
                    }
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Admin recovery request failed: ' . $e->getMessage());
            }
        }
    }
}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title data-ui-title-key="title_admin_forgot_password">فراموشی رمز مدیریت | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.auth-card{width:min(480px,calc(100% - 24px));margin:clamp(24px,8vh,72px) auto;padding:clamp(22px,5vw,40px);min-width:0}
.auth-card h1{font-size:clamp(1.5rem,4vw,2rem);line-height:1.5;overflow-wrap:anywhere}
.auth-card>p{line-height:1.9}
.field{display:grid;gap:8px;margin:18px 0;min-width:0}
.field label{color:var(--muted);font-weight:600}
.field input{width:100%;min-width:0;min-height:46px;padding:12px 14px;border:1px solid var(--border);border-radius:var(--ui-radius-md,12px);background:var(--control-bg,var(--surface-2));color:var(--text);font:inherit;box-sizing:border-box;transition:border-color .16s ease,box-shadow .16s ease}
.field input:focus-visible{outline:none;border-color:var(--accent,var(--blue));box-shadow:0 0 0 3px var(--ui-focus-ring,rgba(87,149,255,.15))}
.field input::placeholder{color:var(--muted)}
.notice{padding:13px 15px;border:1px solid var(--notice-border,var(--border));background:var(--notice-bg,var(--surface-2));color:var(--text);border-radius:var(--ui-radius-md,12px);margin:16px 0;line-height:1.85;overflow-wrap:anywhere}
.auth-card .button{min-height:46px;max-width:100%;white-space:normal}
.auth-card a:not(.button){color:var(--accent,var(--blue));text-underline-offset:3px}
.auth-card a:focus-visible{outline:2px solid var(--accent,var(--blue));outline-offset:3px;border-radius:4px}
@media(max-width:480px){.auth-card{border-radius:18px}.auth-card form>.button{width:100%}}
@media(prefers-reduced-motion:reduce){.field input{transition:none}}
</style><script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</head><body><main class="auth-card panel"><div class="auth-toolbar"><div id="atd-preferences-host" class="atd-preferences-slot" aria-label="ظاهر و زبان"></div></div><div class="eyebrow" data-ui-text="forgot_panel">پنل مدیریت</div><h1 data-ui-text="forgot_title">فراموشی رمز عبور</h1><p class="muted" data-ui-text="forgot_intro">ایمیل ثبت‌شده برای مدیر را وارد کنید.</p>
<?php if ($error !== ''): ?><div class="notice"><?= e($error) ?></div><?php else: ?><div class="notice"><?= e($generic) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><div class="field"><label for="email" data-ui-text="forgot_email">ایمیل مدیر</label><input id="email" name="email" type="email" required maxlength="190" autocomplete="email"></div><button class="button primary" type="submit" data-ui-text="forgot_submit">ارسال لینک بازیابی</button></form><p class="muted utility-mt"><a href="<?= e(app_url('admin/login.php')) ?>" data-ui-text="forgot_back_login">بازگشت به ورود</a></p></main></body></html>
