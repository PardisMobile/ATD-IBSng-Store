<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/AdminAuth.php';
$pdo = Database::connection();
$config = app_config();
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$tokenHash = preg_match('/^[a-f0-9]{64}$/D', $token) === 1 ? hash('sha256', $token) : '';
$error = '';
$success = false;
$adminId = 0;
if ($tokenHash !== '') {
    $find = $pdo->prepare('SELECT admin_id FROM admin_password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
    $find->execute([$tokenHash]);
    $adminId = (int)($find->fetchColumn() ?: 0);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        $error = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } elseif ($adminId < 1) {
        $error = 'لینک بازیابی نامعتبر یا منقضی شده است. دوباره درخواست دهید.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');
        if (strlen($password) < 12 || strlen($password) > 200) {
            $error = 'رمز جدید باید بین ۱۲ تا ۲۰۰ کاراکتر باشد.';
        } elseif (!hash_equals($password, $confirm)) {
            $error = 'تکرار رمز با رمز جدید یکسان نیست.';
        } else {
            try {
                $pdo->beginTransaction();
                $lock = $pdo->prepare('SELECT id, admin_id FROM admin_password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE');
                $lock->execute([$tokenHash]);
                $record = $lock->fetch(PDO::FETCH_ASSOC);
                if (!$record || (int)$record['admin_id'] !== $adminId) {
                    $pdo->rollBack();
                    $error = 'لینک بازیابی نامعتبر یا منقضی شده است. دوباره درخواست دهید.';
                } else {
                    $change = $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ? AND is_active = 1');
                    $change->execute([password_hash($password, PASSWORD_DEFAULT), $adminId]);
                    if ($change->rowCount() !== 1) throw new RuntimeException('Admin account inactive or missing.');
                    $pdo->prepare('UPDATE admin_password_resets SET used_at = NOW() WHERE admin_id = ? AND used_at IS NULL')->execute([$adminId]);
                    $pdo->commit();
                    $success = true;
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Admin password reset failed: ' . $e->getMessage());
                $error = 'ذخیره رمز جدید انجام نشد. دوباره تلاش کنید.';
            }
        }
    }
}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>تعیین رمز جدید مدیریت | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title><link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>.auth-card{max-width:480px;margin:8vh auto;padding:clamp(24px,5vw,40px)}.field{display:grid;gap:8px;margin:18px 0}.field label{color:var(--muted)}.field input{width:100%;padding:13px;border:1px solid var(--border);border-radius:12px;background:#0d1426;color:var(--text);font:inherit}.notice{padding:13px;border:1px solid #ff718044;background:#ff718018;border-radius:12px;margin:16px 0}</style></head><body><main class="auth-card panel"><div class="eyebrow">پنل مدیریت</div><h1>تعیین رمز جدید</h1>
<?php if ($success): ?><div class="notice">رمز عبور با موفقیت تغییر کرد.</div><a class="button primary" href="<?= e(app_url('admin/login.php')) ?>">ورود به پنل</a>
<?php elseif ($error !== ''): ?><div class="notice"><?= e($error) ?></div><a href="<?= e(app_url('admin/forgot-password.php')) ?>">درخواست لینک جدید</a>
<?php elseif ($adminId > 0): ?><form method="post"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="token" value="<?= e($token) ?>"><div class="field"><label for="password">رمز جدید (حداقل ۱۲ کاراکتر)</label><input id="password" name="password" type="password" required minlength="12" maxlength="200" autocomplete="new-password"></div><div class="field"><label for="password_confirm">تکرار رمز جدید</label><input id="password_confirm" name="password_confirm" type="password" required minlength="12" maxlength="200" autocomplete="new-password"></div><button class="button primary" type="submit">ذخیره رمز جدید</button></form>
<?php else: ?><div class="notice">لینک بازیابی نامعتبر یا منقضی شده است.</div><a href="<?= e(app_url('admin/forgot-password.php')) ?>">درخواست لینک جدید</a><?php endif; ?></main></body></html>