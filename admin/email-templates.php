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
$preferredLanguage = (string)($_COOKIE['atd-ui-language'] ?? $_POST['language'] ?? $_GET['language'] ?? 'fa');
$language = $preferredLanguage === 'en' ? 'en' : 'fa';
$template = EmailTemplates::get($pdo, $event, $language);
$message = '';
$error = '';
$previewHtml = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        $error = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } elseif (isset($_POST['reset_template'])) {
        $templatePrefix = 'email_template_' . $event . ($language === 'en' ? '_en' : '');
        $delete = $pdo->prepare('DELETE FROM app_settings WHERE setting_key IN (?, ?, ?)');
        $delete->execute([
            $templatePrefix . '_subject',
            $templatePrefix . '_html',
            $templatePrefix . '_text',
        ]);
        $template = EmailTemplates::get($pdo, $event, $language);
        $message = 'قالب به تنظیمات پیش‌فرض بازنشانی شد.';
    } else {
        $subject = trim((string)($_POST['subject'] ?? ''));
        $html = (string)($_POST['html'] ?? '');
        $plain = (string)($_POST['text'] ?? '');
        $subjectLength = preg_match_all('/./us', $subject, $subjectCharacters);
        if ($subject === '' || trim($html) === '' || trim($plain) === '') {
            $error = 'موضوع، قالب HTML و نسخه متنی باید تکمیل شوند.';
        } elseif ($subjectLength === false) {
            $error = 'موضوع ایمیل باید دارای متن UTF-8 معتبر باشد.';
        } elseif ($subjectLength > 250 || strlen($html) > 200000 || strlen($plain) > 50000) {
            $error = 'حجم موضوع یا محتوای قالب بیش از حد مجاز است.';
        } else {
            $save = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            $templatePrefix = 'email_template_' . $event . ($language === 'en' ? '_en' : '');
            $save->execute([$templatePrefix . '_subject', $subject]);
            $save->execute([$templatePrefix . '_html', $html]);
            $save->execute([$templatePrefix . '_text', $plain]);
            $template = EmailTemplates::get($pdo, $event, $language);
            $message = 'قالب ایمیل ذخیره شد.';
        }
    }
    if (isset($_POST['preview'])) {
        $previewHtml = EmailTemplates::render((string)($template['html'] ?? ''), [
            'site_name' => 'نمونه فروشگاه',
            'customer_name' => $language === 'en' ? 'Sample Customer' : 'مشتری نمونه',
            'order_id' => '0123456789abcdef0123456789abcdef',
            'order_url' => '#',
            'verification_code' => '123456',
            'expires_in' => $language === 'en' ? '10 minutes' : '۱۰ دقیقه',
            'verification_url' => '#',
            'expiration_date' => $language === 'en' ? '2026/11/30' : '۱۴۰۵/۰۹/۱۰',
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
<link rel="icon" type="image/svg+xml" href="<?= e(app_asset_url('assets/icons/atd-app-icon.svg')) ?>"><link rel="apple-touch-icon" sizes="180x180" href="<?= e(app_asset_url('assets/icons/apple-touch-icon.png')) ?>"><link rel="manifest" href="<?= e(app_asset_url('site.webmanifest')) ?>">
<title data-ui-title-key="title_admin_email_templates">قالب‌های ایمیل | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto;padding-bottom:32px}.admin-top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;padding:24px 0}.panel-card{padding:clamp(18px,4vw,28px);margin:18px 0}.field{display:grid;gap:8px;margin:16px 0}.field label{color:var(--muted)}.field input,.field textarea,.field select{width:100%;min-width:0;box-sizing:border-box;padding:12px 14px;border:1px solid var(--border);border-radius:var(--ui-radius-md);background:var(--control-bg);color:var(--text);font:inherit;min-height:var(--ui-control-height)}.field input:focus-visible,.field textarea:focus-visible,.field select:focus-visible{outline:2px solid var(--accent);outline-offset:1px;box-shadow:0 0 0 3px var(--ui-focus-ring)}.field input::placeholder,.field textarea::placeholder{color:var(--muted)}.field textarea{min-height:240px;direction:ltr;text-align:left;font-family:ui-monospace,monospace;font-size:.9rem;line-height:1.65;resize:vertical}.hint{font-size:.85rem;line-height:1.8;color:var(--muted)}.notice{padding:13px 15px;border-radius:12px;margin:14px 0}.ok{background:#44d6a018;border:1px solid #44d6a044}.err{background:#ff718018;border:1px solid #ff718044}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}iframe{display:block;width:100%;max-width:100%;height:clamp(320px,65vh,650px);background:white;border:1px solid var(--border);border-radius:var(--ui-radius-md)}
</style>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
<script>
document.addEventListener('atd:language-change', function () {
  window.location.reload();
}, { once: true });
</script>
</head>
<body>
<main class="admin-shell">
<?php require __DIR__ . '/_navigation.php'; ?>
<section class="panel panel-card">
<div class="eyebrow" data-ui-text="email_templates_customer">ارتباط با مشتری</div><h1 data-ui-text="email_templates_title">مدیریت قالب‌های ایمیل</h1>
<p class="hint" data-ui-text="email_templates_variables">قالب‌ها مستقل از روش ارسال هستند. متغیرهای قابل استفاده در قالب: {{site_name}}، {{customer_name}}، {{order_id}}، {{order_url}}، {{verification_code}}، {{expires_in}}، {{verification_url}}، {{expiration_date}} و {{service_url}}. متغیرهای نامرتبط با یک رویداد در آن رویداد مقدار ندارند.</p>
<?php if ($message !== ''): ?><div class="notice ok" role="status" aria-live="polite"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err" role="alert" aria-live="assertive"><?= e($error) ?></div><?php endif; ?>
<form method="get" class="field"><label for="event" data-ui-text="email_templates_event">رویداد ایمیل</label><select id="event" name="event" onchange="this.form.submit()"><?php foreach (EmailTemplates::EVENTS as $key => $label): ?><option value="<?= e($key) ?>" data-ui-event="<?= e($key) ?>" <?= $event === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></form>
<form method="post" id="email-template-form">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="event" value="<?= e($event) ?>"><input type="hidden" name="language" value="<?= e($language) ?>">
<div class="field"><label for="subject" data-ui-text="email_templates_subject">موضوع ایمیل</label><input id="subject" name="subject" maxlength="250" required value="<?= e($template['subject']) ?>"></div>
<div class="field"><label for="html" data-ui-text="email_templates_html">قالب HTML</label><textarea id="html" name="html" required><?= e($template['html']) ?></textarea><div class="hint" data-ui-text="email_templates_html_hint">HTML ایمیل را ویرایش کنید. پیش‌نمایش با داده‌های نمونه در همین صفحه ارائه می‌شود.</div></div>
<div class="field"><label for="text" data-ui-text="email_templates_text">نسخه متنی جایگزین (Plain text)</label><textarea id="text" name="text" required><?= e($template['text']) ?></textarea></div>
</form>
<div class="actions">
<button class="button primary" type="submit" form="email-template-form" name="save" value="1" data-ui-text="email_templates_save">ذخیره قالب</button>
<button class="button" type="submit" form="email-template-form" name="preview" value="1" data-ui-text="email_templates_preview_save">ذخیره و پیش‌نمایش</button>
<form method="post" data-confirm-fa="قالب این رویداد و زبان به نسخه پیش‌فرض بازنشانی شود؟" data-confirm-en="Reset this event and language template to its default version?">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
<input type="hidden" name="event" value="<?= e($event) ?>">
<input type="hidden" name="language" value="<?= e($language) ?>">
<button class="button danger" type="submit" name="reset_template" value="1" formnovalidate data-ui-text="email_templates_reset">بازنشانی تنظیمات</button>
</form>
</div>
</section>
<?php if ($previewHtml !== ''): ?><section class="panel panel-card"><h2 data-ui-text="email_templates_preview">پیش‌نمایش</h2><iframe title="پیش‌نمایش قالب ایمیل" data-ui-aria="email_templates_preview_title" sandbox="" srcdoc="<?= e($previewHtml) ?>"></iframe></section><?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
</main>
</body>
</html>
