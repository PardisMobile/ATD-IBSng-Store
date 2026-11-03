<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_once dirname(__DIR__) . '/app/EmailSender.php';
$pdo = Database::connection();
$config = app_config();
$generic = 'اگر ایمیل متعلق به مدیر فعال باشد، لینک بازیابی برای آن ارسال می‌شود.';
$error = '';
$ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        $error = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 190) {
            $error = 'ایمیل معتبر وارد کنید.';
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
<title>فراموشی رمز مدیریت | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title><link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>.auth-card{max-width:480px;margin:8vh auto;padding:clamp(24px,5vw,40px)}.field{display:grid;gap:8px;margin:18px 0}.field label{color:var(--muted)}.field input{width:100%;padding:13px;border:1px solid var(--border);border-radius:12px;background:#0d1426;color:var(--text);font:inherit}.notice{padding:13px;border:1px solid #ff718044;background:#ff718018;border-radius:12px;margin:16px 0}</style></head><body><main class="auth-card panel"><div class="eyebrow">پنل مدیریت</div><h1>فراموشی رمز عبور</h1><p class="muted">ایمیل ثبت‌شده برای مدیر را وارد کنید.</p>
<?php if ($error !== ''): ?><div class="notice"><?= e($error) ?></div><?php else: ?><div class="notice"><?= e($generic) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><div class="field"><label for="email">ایمیل مدیر</label><input id="email" name="email" type="email" required maxlength="190" autocomplete="email"></div><button class="button primary" type="submit">ارسال لینک بازیابی</button></form><p class="muted" style="margin-top:20px"><a href="<?= e(app_url('admin/login.php')) ?>">بازگشت به ورود</a></p></main></body></html>