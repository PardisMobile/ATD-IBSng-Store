<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_admin();

$pdo = Database::connection();
$message = '';
$error = '';

function atd_ibsng_validity_column_exists(PDO $pdo): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute(['products', 'ibsng_validity_days']);
    return (int)$stmt->fetchColumn() === 1;
}

$ready = atd_ibsng_validity_column_exists($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        $error = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } else {
        try {
            if (!atd_ibsng_validity_column_exists($pdo)) {
                $pdo->exec('ALTER TABLE products ADD COLUMN ibsng_validity_days INT UNSIGNED NULL AFTER duration_seconds');
            }
            $settings = $pdo->prepare(
                'INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (?, ?)'
            );
            $settings->execute(['ibsng_gift_days_first', '1']);
            $settings->execute(['ibsng_gift_days_repeat', '5']);
            $ready = atd_ibsng_validity_column_exists($pdo);
            if (!$ready) {
                throw new RuntimeException('Renewal schema verification did not pass.');
            }
            $message = 'ارتقای ساختار تمدید IBSng با موفقیت انجام شد.';
        } catch (Throwable $e) {
            error_log('IBSng renewal schema upgrade failed: ' . $e->getMessage());
            $error = 'ارتقای دیتابیس انجام نشد. دسترسی ALTER و INSERT دیتابیس را بررسی کنید.';
        }
    }
}
$config = app_config();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>ارتقای تمدید IBSng | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.admin-shell{width:min(900px,calc(100% - 32px));margin:0 auto;padding-bottom:28px}.admin-top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:24px 0}.panel-card{padding:clamp(18px,4vw,30px);margin:18px 0}.notice{padding:14px 16px;border-radius:13px;margin:14px 0;line-height:1.8}.ok{background:#44d6a018;border:1px solid #44d6a044}.err{background:#ff718018;border:1px solid #ff718044}.muted{color:var(--muted);line-height:1.8}
</style>
</head>
<body>
<main class="admin-shell">
<header class="admin-top"><a class="brand" href="<?= e(app_url('admin/')) ?>">ATD IBSng Store · مدیریت</a><a class="button" href="<?= e(app_url('admin/products.php')) ?>">بازگشت به محصولات</a></header>
<section class="panel panel-card">
<div class="eyebrow">Database · Renewal Upgrade</div>
<h1>ارتقای ساختار تمدید IBSng</h1>
<p class="muted">این مرحله فقط ستون مستقل مدت اعتبار IBSng و تنظیمات پیش‌فرض هدیه را اضافه می‌کند. مدت عمومی محصولات به‌صورت خودکار کپی یا تغییر داده نمی‌شود؛ بنابراین برای هر محصول باید مدت تمدید IBSng را جداگانه مشخص کنید.</p>
<?php if ($message !== ''): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
<?php if ($ready): ?>
<div class="notice ok">ساختار دیتابیس برای تمدید IBSng آماده است.</div>
<a class="button primary" href="<?= e(app_url('admin/products.php')) ?>">تنظیم مدت تمدید محصولات</a>
<?php else: ?>
<div class="notice err">ساختار جدید تمدید هنوز روی دیتابیس این نصب اعمال نشده است.</div>
<form method="post" onsubmit="return confirm('ارتقای ساختار تمدید IBSng روی دیتابیس انجام شود؟');">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
<button class="button primary" type="submit">اعمال ارتقای دیتابیس</button>
</form>
<?php endif; ?>
</section>
</main>
</body>
</html>
