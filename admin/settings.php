<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_admin();

$pdo = Database::connection();
$message = '';
$error = '';

function support_setting(PDO $pdo, string $key): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return is_string($value) ? $value : '';
}

function support_valid_telegram(string $value): bool
{
    return preg_match('/^[A-Za-z0-9_]{5,32}$/D', $value) === 1;
}

function support_valid_whatsapp_target(string $value): bool
{
    return $value !== '' && preg_match('/^[A-Za-z0-9._+-]{1,80}$/D', $value) === 1;
}

function support_valid_custom_url(string $value): bool
{
    if (filter_var($value, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    $parts = parse_url($value);
    return is_array($parts)
        && strtolower((string)($parts['scheme'] ?? '')) === 'https'
        && isset($parts['host'])
        && filter_var((string)$parts['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
        && !isset($parts['user']) && !isset($parts['pass']);
}

$renewalValues = [
    'ibsng_gift_days_first' => support_setting($pdo, 'ibsng_gift_days_first') ?: '1',
    'ibsng_gift_days_repeat' => support_setting($pdo, 'ibsng_gift_days_repeat') ?: '5',
];

$values = [
    'support_telegram_id' => support_setting($pdo, 'support_telegram_id'),
    'support_whatsapp_target' => support_setting($pdo, 'support_whatsapp_target'),
    'support_custom_label' => support_setting($pdo, 'support_custom_label'),
    'support_custom_url' => support_setting($pdo, 'support_custom_url'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        $error = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } else {
        $renewalValues = [
            'ibsng_gift_days_first' => trim((string)($_POST['ibsng_gift_days_first'] ?? '1')),
            'ibsng_gift_days_repeat' => trim((string)($_POST['ibsng_gift_days_repeat'] ?? '5')),
        ];
        $values = [
            'support_telegram_id' => trim((string)($_POST['telegram_id'] ?? '')),
            'support_whatsapp_target' => trim((string)($_POST['whatsapp_target'] ?? '')),
            'support_custom_label' => trim((string)($_POST['custom_label'] ?? '')),
            'support_custom_url' => trim((string)($_POST['custom_url'] ?? '')),
        ];
        foreach ($renewalValues as $giftKey => $giftValue) {
            if (!ctype_digit($giftValue) || (int)$giftValue > 3650) {
                $error = 'روزهای هدیه باید عدد صحیح بین صفر تا ۳۶۵۰ باشند.';
                break;
            }
        }
        if ($error === '' && $values['support_telegram_id'] !== '' && !support_valid_telegram($values['support_telegram_id'])) {
            $error = 'شناسه Telegram معتبر نیست؛ فقط حروف انگلیسی، عدد و زیرخط (۵ تا ۳۲ کاراکتر) وارد کنید.';
        } elseif ($error === '' && $values['support_whatsapp_target'] !== '' && !support_valid_whatsapp_target($values['support_whatsapp_target'])) {
            $error = 'مقصد WhatsApp معتبر نیست؛ فقط حروف، عدد و نشانه‌های . _ + - مجاز است.';
        } elseif ($error === '' && ($values['support_custom_label'] === '') !== ($values['support_custom_url'] === '')) {
            $error = 'برای لینک دلخواه، هم عنوان و هم نشانی را وارد کنید.';
        } elseif ($error === '' && $values['support_custom_url'] !== '' && (!support_valid_custom_url($values['support_custom_url']) || strlen($values['support_custom_label']) > 60)) {
            $error = 'لینک دلخواه باید HTTPS معتبر باشد و عنوان آن حداکثر ۶۰ کاراکتر باشد.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ($values as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            foreach ($renewalValues as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            $message = 'تنظیمات راه‌های پشتیبانی و هدیهٔ تمدید IBSng ذخیره شد.';
        }
    }
}

if ($error !== '') {
    // Keep entered values visible after a validation failure.
} else {
    foreach ($values as $key => $value) {
        $values[$key] = support_setting($pdo, $key);
    }
    foreach ($renewalValues as $key => $value) {
        $renewalValues[$key] = support_setting($pdo, $key) ?: ($key === 'ibsng_gift_days_first' ? '1' : '5');
    }
}
$config = app_config();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>راه‌های پشتیبانی | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.admin-shell{width:min(900px,calc(100% - 32px));margin:0 auto;padding-bottom:28px}.admin-top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:24px 0}.panel-card{padding:clamp(18px,4vw,30px);margin:18px 0}.field{display:grid;gap:8px;margin:16px 0}.field label{color:var(--muted)}.field input{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:#0d1426;color:var(--text);font:inherit}.hint{font-size:.85rem;line-height:1.8;color:var(--muted)}.notice{padding:13px 15px;border-radius:12px;margin:14px 0}.ok{background:#44d6a018;border:1px solid #44d6a044}.err{background:#ff718018;border:1px solid #ff718044}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}
</style>
</head>
<body>
<main class="admin-shell">
<header class="admin-top"><a class="brand" href="<?= e(app_url('admin/')) ?>">مدیریت فروشگاه</a><a class="button" href="<?= e(app_url('admin/')) ?>">بازگشت به داشبورد</a></header>
<section class="panel panel-card">
<div class="eyebrow">تنظیمات فروشگاه</div><h1>راه‌های پشتیبانی</h1>
<p class="hint">هر راه ارتباطی اختیاری است. گزینه‌ای که خالی بماند در فروشگاه نمایش داده نمی‌شود.</p>
<?php if ($message !== ''): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
<div class="field"><label for="telegram_id">شناسه Telegram</label><input id="telegram_id" name="telegram_id" value="<?= e($values['support_telegram_id']) ?>" maxlength="32" placeholder="im000ki6" autocomplete="off"><div class="hint">فقط شناسه را وارد کنید، بدون @ یا آدرس سایت. لینک t.me به‌صورت خودکار ساخته می‌شود.</div></div>
<div class="field"><label for="whatsapp_target">شناسه یا مقصد WhatsApp</label><input id="whatsapp_target" name="whatsapp_target" value="<?= e($values['support_whatsapp_target']) ?>" maxlength="80" placeholder="شناسه یا مقصد" autocomplete="off"><div class="hint">فقط مقصد را وارد کنید؛ دامنه و URL لازم نیست. این فیلد محدود به قالب شماره تلفن نیست.</div></div>
<div class="field"><label for="custom_label">عنوان لینک دلخواه</label><input id="custom_label" name="custom_label" value="<?= e($values['support_custom_label']) ?>" maxlength="60" placeholder="مثلاً پشتیبانی آنلاین"></div>
<div class="field"><label for="custom_url">نشانی کامل لینک دلخواه</label><input id="custom_url" name="custom_url" type="url" value="<?= e($values['support_custom_url']) ?>" placeholder="https://example.com/contact" autocomplete="url"><div class="hint">برای پیام‌رسان یا سرویس دیگر؛ فقط لینک HTTPS معتبر پذیرفته می‌شود.</div></div>
<hr>
<h2>سیاست هدیهٔ تمدید IBSng</h2>
<p class="hint">روزهای هدیه برای اولین تمدید موفق و تمدیدهای بعدی جداگانه تنظیم می‌شوند. مقدار پیش‌فرض مطابق مرجع: ۱ روز برای اولین و ۵ روز برای تمدیدهای بعدی.</p>
<div class="field"><label for="ibsng_gift_days_first">هدیهٔ اولین تمدید (روز)</label><input id="ibsng_gift_days_first" name="ibsng_gift_days_first" type="number" min="0" max="3650" required value="<?= e($renewalValues['ibsng_gift_days_first']) ?>"></div>
<div class="field"><label for="ibsng_gift_days_repeat">هدیهٔ تمدیدهای بعدی (روز)</label><input id="ibsng_gift_days_repeat" name="ibsng_gift_days_repeat" type="number" min="0" max="3650" required value="<?= e($renewalValues['ibsng_gift_days_repeat']) ?>"></div>
<div class="actions"><button class="button primary" type="submit">ذخیره تنظیمات</button><a class="button" href="<?= e(app_url()) ?>" target="_blank" rel="noopener noreferrer">مشاهده فروشگاه</a></div>
</form>
</section>
</main>
</body>
</html>
