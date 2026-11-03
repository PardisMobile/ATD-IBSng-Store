<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_once dirname(__DIR__) . '/app/EmailSender.php';
require_once dirname(__DIR__) . '/app/EmailTemplates.php';
require_admin();

$pdo = Database::connection();
$config = app_config();
$message = '';
$error = '';
$settings = [
    'email_from_address' => '',
    'email_from_name' => (string)($config['site_title'] ?? 'ATD IBSng Store'),
];
$stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
foreach (array_keys($settings) as $key) {
    $stmt->execute([$key]);
    $stored = $stmt->fetchColumn();
    if (is_string($stored)) {
        $settings[$key] = $stored;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        $error = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } else {
        $settings['email_from_address'] = trim((string)($_POST['from_address'] ?? ''));
        $settings['email_from_name'] = trim((string)($_POST['from_name'] ?? ''));
        $action = (string)($_POST['action'] ?? 'save');
        if (filter_var($settings['email_from_address'], FILTER_VALIDATE_EMAIL) === false) {
            $error = 'ایمیل فرستنده باید یک نشانی معتبر باشد.';
        } elseif ($settings['email_from_name'] === '' || strlen($settings['email_from_name']) > 150
            || preg_match('/[\\r\\n]/', $settings['email_from_name'])) {
            $error = 'نام نمایشی فرستنده الزامی است و نباید بیش از ۱۵۰ کاراکتر باشد.';
        } else {
            $save = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            $save->execute(['email_from_address', $settings['email_from_address']]);
            $save->execute(['email_from_name', $settings['email_from_name']]);
            if ($action === 'test') {
                $testTo = trim((string)($_POST['test_to'] ?? ''));
                if (filter_var($testTo, FILTER_VALIDATE_EMAIL) === false) {
                    $error = 'ایمیل مقصد تست معتبر نیست.';
                } else {
                    try {
                        $tpl = EmailTemplates::get($pdo, 'email_verification');
                        $vars = [
                            'site_name' => (string)($config['site_title'] ?? 'ATD IBSng Store'),
                            'verification_url' => app_url(),
                            'expires_in' => '۳۰ دقیقه',
                        ];
                        $sent = EmailSender::send(
                            $pdo,
                            $testTo,
                            EmailTemplates::render($tpl['subject'], $vars),
                            EmailTemplates::render($tpl['html'], $vars),
                            EmailTemplates::render($tpl['text'], $vars)
                        );
                        $message = $sent
                            ? 'درخواست ارسال ایمیل تست به mail service هاست تحویل داده شد. تحویل نهایی به صندوق مقصد تضمین‌شده نیست.'
                            : 'mail() هاست ارسال را نپذیرفت. تنظیمات سرویس ایمیل هاست را بررسی کنید.';
                    } catch (Throwable $e) {
                        error_log('Email test send failed: ' . $e->getMessage());
                        $error = 'ارسال تست انجام نشد؛ تنظیمات فرستنده را بررسی کنید.';
                    }
                }
            } else {
                $message = 'تنظیمات ایمیل ذخیره شد.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>تنظیمات ایمیل | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.admin-shell{width:min(850px,calc(100% - 32px));margin:0 auto;padding-bottom:30px}.admin-top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;padding:24px 0}.panel-card{padding:clamp(18px,4vw,30px);margin:18px 0}.field{display:grid;gap:8px;margin:16px 0}.field label{color:var(--muted)}.field input{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:#0d1426;color:var(--text);font:inherit}.hint{font-size:.88rem;line-height:1.8;color:var(--muted)}.notice{padding:13px 15px;border-radius:12px;margin:14px 0}.ok{background:#44d6a018;border:1px solid #44d6a044}.err{background:#ff718018;border:1px solid #ff718044}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}
</style>
<script src="<?= e(app_url('assets/js/ui-preferences.js')) ?>" defer></script>
</head>
<body>
<main class="admin-shell">
<header class="admin-top"><a class="brand" href="<?= e(app_url('admin/')) ?>">مدیریت فروشگاه</a><a class="button" href="<?= e(app_url('admin/')) ?>">بازگشت به داشبورد</a></header>
<section class="panel panel-card">
<div class="eyebrow">ارتباط با مشتری</div><h1>تنظیمات ارسال ایمیل</h1>
<p class="hint">ارسال با mail() سرویس ایمیل هاست انجام می‌شود؛ تنظیم SMTP جداگانه لازم نیست. برای تحویل بهتر، دامنه و فرستنده باید در هاست مجاز و دارای تنظیمات DNS ایمیل مناسب باشند. این صفحه ایمیل تست می‌فرستد و تحویل نهایی را تضمین نمی‌کند.</p>
<?php if ($message !== ''): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
<div class="field"><label for="from_address">ایمیل فرستنده</label><input id="from_address" type="email" name="from_address" required maxlength="190" value="<?= e($settings['email_from_address']) ?>" placeholder="noreply@example.com"></div>
<div class="field"><label for="from_name">نام نمایشی فرستنده</label><input id="from_name" name="from_name" required maxlength="150" value="<?= e($settings['email_from_name']) ?>" placeholder="نام فروشگاه"></div>
<div class="actions"><button class="button" type="submit" name="action" value="save">ذخیره تنظیمات</button></div>
<hr>
<h2>ارسال ایمیل تست</h2>
<div class="field"><label for="test_to">ارسال به این ایمیل</label><input id="test_to" type="email" name="test_to" maxlength="190" placeholder="you@example.com"></div>
<div class="actions"><button class="button" type="submit" name="action" value="test">ذخیره و ارسال تست</button><a class="button" href="<?= e(app_url('admin/email-templates.php')) ?>">مدیریت قالب‌ها</a></div>
</form>
</section>
</main>
</body>
</html>
