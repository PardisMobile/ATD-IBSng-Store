<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_once dirname(__DIR__) . '/app/EmailTemplates.php';
require_admin();

$pdo = Database::connection();
$event = (string)($_GET['event'] ?? $_POST['event'] ?? 'purchase_success');
if (!isset(EmailTemplates::EVENTS[$event])) {
    $event = 'purchase_success';
}
$template = EmailTemplates::get($pdo, $event);
$message = '';
$error = '';
$previewHtml = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        $error = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } else {
        $subject = trim((string)($_POST['subject'] ?? ''));
        $html = (string)($_POST['html'] ?? '');
        $plain = (string)($_POST['text'] ?? '');
        if ($subject === '' || trim($html) === '' || trim($plain) === '') {
            $error = 'موضوع، قالب HTML و نسخه متنی باید تکمیل شوند.';
        } elseif (mb_strlen($subject) > 250 || strlen($html) > 200000 || strlen($plain) > 50000) {
            $error = 'حجم موضوع یا محتوای قالب بیش از حد مجاز است.';
        } else {
            $save = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            $save->execute(['email_template_' . $event . '_subject', $subject]);
            $save->execute(['email_template_' . $event . '_html', $html]);
            $save->execute(['email_template_' . $event . '_text', $plain]);
            $template = ['subject' => $subject, 'html' => $html, 'text' => $plain];
            $message = 'قالب ایمیل ذخیره شد.';
        }
    }
    if (isset($_POST['preview'])) {
        $previewHtml = EmailTemplates::render((string)($template['html'] ?? ''), [
            'site_name' => 'نمونه فروشگاه',
            'customer_name' => 'مشتری نمونه',
            'order_id' => '0123456789abcdef0123456789abcdef',
            'order_url' => '#',
            'verification_code' => '123456',
            'expires_in' => '۱۰ دقیقه',
            'verification_url' => '#',
            'expiration_date' => '۱۴۰۵/۰۹/۱۰',
            'service_url' => '#',
        ]);
    }
}
$config = app_config();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>قالب‌های ایمیل | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto;padding-bottom:32px}.admin-top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;padding:24px 0}.panel-card{padding:clamp(18px,4vw,28px);margin:18px 0}.field{display:grid;gap:8px;margin:16px 0}.field label{color:var(--muted)}.field input,.field textarea,.field select{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:#0d1426;color:var(--text);font:inherit}.field textarea{min-height:240px;direction:ltr;text-align:left;font-family:ui-monospace,monospace;font-size:.9rem}.hint{font-size:.85rem;line-height:1.8;color:var(--muted)}.notice{padding:13px 15px;border-radius:12px;margin:14px 0}.ok{background:#44d6a018;border:1px solid #44d6a044}.err{background:#ff718018;border:1px solid #ff718044}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}iframe{width:100%;height:450px;background:white;border:1px solid var(--border);border-radius:12px}
</style>
</head>
<body>
<main class="admin-shell">
<header class="admin-top"><a class="brand" href="<?= e(app_url('admin/')) ?>">مدیریت فروشگاه</a><a class="button" href="<?= e(app_url('admin/')) ?>">بازگشت به داشبورد</a></header>
<section class="panel panel-card">
<div class="eyebrow">ارتباط با مشتری</div><h1>مدیریت قالب‌های ایمیل</h1>
<p class="hint">قالب‌ها مستقل از روش ارسال هستند. متغیرهای قابل استفاده در قالب: {{site_name}}، {{customer_name}}، {{order_id}}، {{order_url}}، {{verification_code}}، {{expires_in}}، {{verification_url}}، {{expiration_date}} و {{service_url}}. متغیرهای نامرتبط با یک رویداد در آن رویداد مقدار ندارند.</p>
<?php if ($message !== ''): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
<form method="get" class="field"><label for="event">رویداد ایمیل</label><select id="event" name="event" onchange="this.form.submit()"><?php foreach (EmailTemplates::EVENTS as $key => $label): ?><option value="<?= e($key) ?>" <?= $event === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></form>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="event" value="<?= e($event) ?>">
<div class="field"><label for="subject">موضوع ایمیل</label><input id="subject" name="subject" maxlength="250" required value="<?= e($template['subject']) ?>"></div>
<div class="field"><label for="html">قالب HTML</label><textarea id="html" name="html" required><?= e($template['html']) ?></textarea><div class="hint">HTML ایمیل را ویرایش کنید. پیش‌نمایش با داده‌های نمونه در همین صفحه ارائه می‌شود.</div></div>
<div class="field"><label for="text">نسخه متنی جایگزین (Plain text)</label><textarea id="text" name="text" required><?= e($template['text']) ?></textarea></div>
<div class="actions"><button class="button primary" type="submit" name="save" value="1">ذخیره قالب</button><button class="button" type="submit" name="preview" value="1">ذخیره و پیش‌نمایش</button></div>
</form>
</section>
<?php if ($previewHtml !== ''): ?><section class="panel panel-card"><h2>پیش‌نمایش</h2><iframe title="پیش‌نمایش قالب ایمیل" sandbox="" srcdoc="<?= e($previewHtml) ?>"></iframe></section><?php endif; ?>
<footer><a href="<?= e(app_url('admin/')) ?>">بازگشت به داشبورد</a></footer>
</main>
</body>
</html>
