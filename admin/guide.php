<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_admin();
require_once dirname(__DIR__) . '/app/GuideIcons.php';
require_once dirname(__DIR__) . '/app/OpenVpnManager.php';
require_once dirname(__DIR__) . '/app/StorefrontView.php';
require_once dirname(__DIR__) . '/app/EmailTemplates.php';
require_once dirname(__DIR__) . '/app/EmailSender.php';

$config = app_config();
$pdo = Database::connection();
$settingKey = 'general_account_guide';
$systems = [
    'android' => ['fa' => 'Android', 'en' => 'Android'],
    'ios' => ['fa' => 'iPhone / iPad (iOS)', 'en' => 'iPhone / iPad (iOS)'],
    'windows' => ['fa' => 'Windows', 'en' => 'Windows'],
    'macos' => ['fa' => 'macOS', 'en' => 'macOS'],
    'linux' => ['fa' => 'Linux', 'en' => 'Linux'],
];
$defaultGuide = [
    'intro' => 'روش اتصال و سیستم‌عامل موردنظر خود را انتخاب کنید.',
    'methods' => [[
        'name' => 'OpenVPN',
        'active' => 0,
        'systems' => [],
    ]],
];
$readGuide = static function () use ($pdo, $settingKey, $defaultGuide): array {
    $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1');
    $stmt->execute([$settingKey]);
    $raw = $stmt->fetchColumn();
    if (!is_string($raw) || $raw === '') return $defaultGuide;
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return $defaultGuide;
    $decoded['intro'] = is_string($decoded['intro'] ?? null) ? $decoded['intro'] : $defaultGuide['intro'];
    $decoded['methods'] = is_array($decoded['methods'] ?? null) ? array_values($decoded['methods']) : [];
    return $decoded;
};
$guide = $readGuide();
$error = '';
$message = isset($_GET['saved']) ? 'راهنمای عمومی ذخیره شد.' : '';
$ovpnMessage = '';
$ovpnError = '';
$ovpnResult = '';
$ovpnGeneratedLinks = [];
$ovpnKeepProfilesOpen = false;
$ovpnKeepLinksOpen = false;
$ovpnManager = new OpenVpnManager($pdo);
$ovpnSearch = trim((string)($_GET['ovpn_order_search'] ?? ''));
$ovpnOrders = [];
if ($ovpnSearch !== '') {
    $find = $pdo->prepare("SELECT id, username, buyer_name, buyer_email, tracking_code FROM orders WHERE payment_status='paid' AND (username LIKE ? OR buyer_email LIKE ? OR tracking_code LIKE ?) ORDER BY id DESC LIMIT 30");
    $term = '%' . $ovpnSearch . '%'; $find->execute([$term,$term,$term]); $ovpnOrders = $find->fetchAll(PDO::FETCH_ASSOC);
}

$cleanUrl = static function (string $value): string {
    $value = trim($value);
    if ($value === '') return '';
    if (strlen($value) > 2048 || !filter_var($value, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('یکی از نشانی‌ها معتبر نیست.');
    }
    $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException('نشانی‌ها فقط می‌توانند با HTTP یا HTTPS باشند.');
    }
    return $value;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && str_starts_with((string)($_POST['action'] ?? ''), 'openvpn_')) {
    if (!admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        $ovpnError = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } else {
        try {
            $action = (string)$_POST['action'];
            $ovpnKeepProfilesOpen = in_array($action, ['openvpn_save_profile','openvpn_toggle_profile','openvpn_delete_profile'], true);
            $ovpnKeepLinksOpen = in_array($action, ['openvpn_toggle_link','openvpn_delete_link','openvpn_create_link'], true);
            if ($action === 'openvpn_save_profile') {
                $name = trim((string)($_POST['profile_name'] ?? ''));
                $id = (int)($_POST['profile_id'] ?? 0) ?: null;
                if (isset($_FILES['profile_file']) && (int)($_FILES['profile_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    if ((int)$_FILES['profile_file']['error'] !== UPLOAD_ERR_OK || (int)$_FILES['profile_file']['size'] > 1048576) throw new RuntimeException('بارگذاری فایل ناموفق بود یا حجم فایل بیش از ۱ مگابایت است.');
                    if (strtolower(pathinfo((string)($_FILES['profile_file']['name'] ?? ''), PATHINFO_EXTENSION)) !== 'ovpn') throw new InvalidArgumentException('فقط فایل با پسوند .ovpn پذیرفته می‌شود.');
                    $profileContent = file_get_contents((string)$_FILES['profile_file']['tmp_name']);
                    if (!is_string($profileContent)) throw new RuntimeException('خواندن فایل بارگذاری‌شده ناموفق بود.');
                } else {
                    $url = trim((string)($_POST['profile_url'] ?? ''));
                    if ($url === '' && $id) $profileContent = '';
                    elseif ($url === '') throw new InvalidArgumentException('نشانی HTTPS یا فایل پروفایل را وارد کنید.');
                    else $profileContent = OpenVpnManager::fetchProfileUrl($url);
                }
                $savedId = $ovpnManager->saveProfile($id, $name, $profileContent);
                $ovpnMessage = 'پروفایل OpenVPN با موفقیت ذخیره شد. شناسه: ' . $savedId;
            } elseif ($action === 'openvpn_toggle_profile') {
                $ovpnManager->setProfileActive((int)$_POST['profile_id'], (string)($_POST['active'] ?? '') === '1');
                $ovpnMessage = 'وضعیت پروفایل تغییر کرد.';
            } elseif ($action === 'openvpn_toggle_link') {
                $ovpnManager->setLinkActive((int)$_POST['link_id'], (string)($_POST['active'] ?? '') === '1');
                $ovpnMessage = 'وضعیت لینک تغییر کرد.';
            } elseif ($action === 'openvpn_delete_link') {
                $ovpnManager->deleteLink((int)($_POST['link_id'] ?? 0));
                $ovpnMessage = 'لینک حذف شد.';
            } elseif ($action === 'openvpn_delete_profile') {
                $ovpnManager->deleteProfile((int)$_POST['profile_id']);
                $ovpnMessage = 'پروفایل حذف شد.';
            } elseif ($action === 'openvpn_create_link') {
                $kind = (string)($_POST['link_type'] ?? '');
                $orderId = (int)($_POST['order_id'] ?? 0) ?: null;
                $email = trim((string)($_POST['recipient_email'] ?? ''));
                $link = $ovpnManager->createLink((int)($_POST['profile_id'] ?? 0), $kind, $orderId, $email);
                $ovpnResult = $kind === 'public' ? $link['public_url'] : $link['url'];
                $ovpnGeneratedLinks = ['public' => (string)$link['public_url']];
                if ($kind === 'personalized') $ovpnGeneratedLinks['personalized'] = (string)$link['url'];
                if ($kind === 'personalized') {
                    $orderStmt = $pdo->prepare('SELECT buyer_name, language FROM orders WHERE id=? LIMIT 1');
                    $orderStmt->execute([$orderId]); $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
                    $language = (string)($order['language'] ?? 'fa') === 'en' ? 'en' : 'fa';
                    $template = EmailTemplates::get($pdo, 'openvpn_profile', $language);
                    $profileStmt = $pdo->prepare('SELECT name FROM openvpn_profiles WHERE id=?');
                    $profileStmt->execute([(int)$_POST['profile_id']]);
                    $variables = ['site_name'=>(string)($config['site_title'] ?? 'ATD IBSng Store'),'customer_name'=>(string)($order['buyer_name'] ?: ($language==='en'?'Customer':'مشتری')),'profile_name'=>(string)$profileStmt->fetchColumn(),'profile_url'=>$link['url'],'order_id'=>(string)$orderId];
                    $html = EmailTemplates::render($template['html'], array_map(static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'), $variables));
                    $plain = EmailTemplates::render($template['text'], $variables);
                    EmailSender::send($pdo, $email, EmailTemplates::render($template['subject'], $variables), $html, $plain);
                    $ovpnMessage = 'لینک اختصاصی ساخته و به نشانی واردشده ارسال شد.';
                } else {
                    $ovpnMessage = 'لینک عمومی ساخته شد؛ نشانی آن را در محتوای راهنمای عمومی OpenVPN قرار دهید.';
                }
            }
        } catch (Throwable $e) {
            error_log('OpenVPN admin action failed: ' . $e->getMessage());
            $ovpnError = $e instanceof InvalidArgumentException ? $e->getMessage() : 'عملیات OpenVPN انجام نشد. جزئیات در گزارش خطای سرور ثبت شد.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        $error = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } else {
        try {
            $intro = trim((string)($_POST['intro'] ?? ''));
            if (strlen($intro) > 3000) throw new InvalidArgumentException('توضیح راهنما بیش از حد طولانی است.');
            $rawMethods = $_POST['methods'] ?? [];
            if (!is_array($rawMethods) || count($rawMethods) > 30) throw new InvalidArgumentException('فهرست روش‌های اتصال معتبر نیست.');
            $methods = [];
            foreach ($rawMethods as $rawMethod) {
                if (!is_array($rawMethod)) continue;
                $name = trim((string)($rawMethod['name'] ?? ''));
                if ($name === '') continue;
                if (strlen($name) > 100) throw new InvalidArgumentException('نام روش اتصال نباید بیشتر از ۱۰۰ کاراکتر باشد.');
                $rawSystems = is_array($rawMethod['systems'] ?? null) ? $rawMethod['systems'] : [];
                $methodSystems = [];
                foreach ($systems as $systemKey => $systemLabel) {
                    $raw = is_array($rawSystems[$systemKey] ?? null) ? $rawSystems[$systemKey] : [];
                    $body = trim((string)($raw['content'] ?? ''));
                    if (strlen($body) > 20000) throw new InvalidArgumentException('متن یکی از راهنماها بیش از حد طولانی است.');
                    $image = $cleanUrl((string)($raw['image_url'] ?? ''));
                    $software = $cleanUrl((string)($raw['software_url'] ?? ''));
                    $download = $cleanUrl((string)($raw['download_url'] ?? ''));
                    $downloadLabel = trim((string)($raw['download_label'] ?? ''));
                    if (strlen($downloadLabel) > 160) throw new InvalidArgumentException('عنوان فایل دانلودی بیش از حد طولانی است.');
                    if ($body !== '' || $image !== '' || $software !== '' || $download !== '') {
                        $methodSystems[$systemKey] = [
                            'content' => $body,
                            'image_url' => $image,
                            'software_url' => $software,
                            'download_url' => $download,
                            'download_label' => $downloadLabel,
                        ];
                    }
                }
                $icon = strtolower(trim((string)($rawMethod['icon'] ?? '')));
                if (!in_array($icon, ['openvpn', 'v2ray', 'wireguard', 'generic'], true)) $icon = guide_method_icon_key(['name' => $name]);
                $methods[] = [
                    'name' => $name,
                    'icon' => $icon,
                    'active' => isset($rawMethod['active']) ? 1 : 0,
                    'systems' => $methodSystems,
                ];
            }
            $payload = json_encode(['intro' => $intro, 'methods' => $methods], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (strlen($payload) > 60000) throw new InvalidArgumentException('حجم محتوای راهنما بیش از ظرفیت ذخیره تنظیمات است؛ متن یا تعداد روش‌ها را کاهش دهید.');
            $stmt = $pdo->prepare('INSERT INTO app_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
            $stmt->execute([$settingKey, $payload]);
            header('Location: ' . app_url('admin/guide.php?saved=1'));
            exit;
        } catch (Throwable $e) {
            $error = $e instanceof InvalidArgumentException ? $e->getMessage() : 'ذخیره راهنما انجام نشد.';
            $guide = ['intro' => (string)($_POST['intro'] ?? ''), 'methods' => is_array($_POST['methods'] ?? null) ? array_values($_POST['methods']) : []];
        }
    }
}
$ovpnMessageEn = match ($ovpnMessage) {
    'لینک حذف شد.' => 'Link deleted.',
    'لینک عمومی ساخته شد؛ نشانی آن را در محتوای راهنمای عمومی OpenVPN قرار دهید.' => 'Public link created. Add its URL to the public OpenVPN guide.',
    'لینک اختصاصی ساخته و به نشانی واردشده ارسال شد.' => 'Personalized link created and emailed.',
    'پروفایل حذف شد.' => 'Profile deleted.',
    'وضعیت لینک تغییر کرد.' => 'Link status updated.',
    'وضعیت پروفایل تغییر کرد.' => 'Profile status updated.',
    default => 'OpenVPN profile saved successfully.',
};
$guideMethods = is_array($guide['methods'] ?? null) ? array_values($guide['methods']) : [];
if (!$guideMethods) $guideMethods = $defaultGuide['methods'];
$esc = static fn($value): string => e((string)$value);
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title data-ui-title-key="title_admin_guide">مدیریت راهنمای استفاده | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.guide-admin-method{padding:18px;margin:16px 0;border:1px solid var(--border);border-radius:var(--ui-radius-lg,18px);background:var(--surface-2)}
.guide-admin-method-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.guide-admin-method>.guide-admin-method-head{display:grid;grid-template-columns:minmax(180px,1fr) minmax(230px,1.3fr) auto auto;align-items:end;gap:12px}
.guide-admin-method>.guide-admin-method-head>.field{margin:0;min-width:0}
.guide-admin-method>.guide-admin-method-head .check-toggle{align-self:end;min-height:45px;white-space:nowrap}
.guide-admin-method>.guide-admin-method-head>[data-remove-guide-method]{align-self:end;min-height:45px}
.ovpn-search-form{display:grid;grid-template-columns:minmax(220px,420px) auto;align-items:end;column-gap:10px;row-gap:8px;max-width:620px}
.ovpn-search-form>label{grid-column:1/-1}
.ovpn-search-form>input{grid-column:1;min-width:0;margin:0 !important}
.ovpn-search-form>button{grid-column:2;align-self:end;min-height:45px;margin:0 0 0 !important;white-space:nowrap}
@media(max-width:760px){.guide-admin-method>.guide-admin-method-head{grid-template-columns:minmax(0,1fr) minmax(0,1.2fr)}.guide-admin-method>.guide-admin-method-head .check-toggle{grid-column:1}.guide-admin-method>.guide-admin-method-head>[data-remove-guide-method]{grid-column:2;justify-self:end}}
@media(max-width:420px){.ovpn-search-form{grid-template-columns:minmax(0,1fr)}.ovpn-search-form>label,.ovpn-search-form>input,.ovpn-search-form>button{grid-column:1}.ovpn-search-form>button{margin:0;justify-self:start}}
.guide-admin-method .system-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.guide-admin-method textarea{min-height:120px;resize:vertical}
.guide-admin-method .field-full{grid-column:1/-1}
.guide-admin-method .check-toggle{display:flex;align-items:center;gap:8px}
.guide-admin-note{color:var(--muted);line-height:1.9}

/* Page-scoped disclosures; shared theme tokens and surfaces remain authoritative. */
.guide-system-section{min-width:0}
.guide-system-summary,.guide-admin-disclosure-summary{
 display:flex;align-items:center;gap:10px;min-width:0;max-width:100%;
 list-style:none;cursor:pointer;line-height:1.5;
}
.guide-system-summary::-webkit-details-marker,.guide-admin-disclosure-summary::-webkit-details-marker{display:none}
.guide-system-summary::marker,.guide-admin-disclosure-summary::marker{content:""}
.guide-system-summary::after,.guide-admin-disclosure-summary::after{
 content:"";width:8px;height:8px;flex:0 0 8px;margin-inline-start:auto;
 border-inline-end:2px solid var(--muted);border-block-end:2px solid var(--muted);
 transform:rotate(45deg);transition:transform .16s ease;
}
.guide-system-section[open]>.guide-system-summary::after,
.guide-admin-disclosure[open]>.guide-admin-disclosure-summary::after{transform:rotate(225deg)}
.guide-system-icon{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;flex:0 0 22px;min-width:22px}
.guide-system-icon .guide-icon{display:block;width:20px;height:20px;min-width:20px;max-width:20px;flex:0 0 20px}
.guide-system-title{display:block;min-width:0;white-space:nowrap;word-break:keep-all;overflow-wrap:normal}
.guide-admin-disclosure-summary{gap:12px}
.guide-admin-disclosure-summary .eyebrow{margin:0}
.guide-admin-disclosure-summary strong{font-size:1.15rem;line-height:1.5}
.guide-admin-disclosure-content{min-width:0;margin-top:16px}
.guide-admin-disclosure-content>.form-section{margin-top:16px}
@media(prefers-reduced-motion:reduce){.guide-system-summary::after,.guide-admin-disclosure-summary::after{transition:none}}
.ovpn-management-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);align-items:start;gap:16px}
.ovpn-management-column{min-width:0}
.ovpn-management-column>.form-section{margin-block:0 16px}
.ovpn-management-column .table-wrap{max-width:100%;overflow-x:auto}
.ovpn-management-column table{min-width:620px}
.ovpn-generated-links{grid-column:1/-1;width:100%;max-width:100%;min-width:0;clear:both}
.ovpn-generated-links table{width:100%;min-width:760px}
.ovpn-admin-url{flex:1;min-width:220px;direction:ltr;text-align:left}
@media(max-width:1050px){.ovpn-management-grid{grid-template-columns:minmax(0,1fr)}}
.ovpn-copy-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;white-space:nowrap}
.ovpn-copy-button .ui-icon{width:18px;height:18px;flex:0 0 18px}
.ovpn-link-actions{display:flex;align-items:center;justify-content:flex-start;gap:6px;flex-wrap:nowrap;white-space:nowrap}
.ovpn-link-actions form{display:inline-flex;margin:0;flex:0 0 auto}
.ovpn-link-actions .button{min-height:34px;padding:6px 10px;font-size:.82rem;line-height:1.2}
.ovpn-link-copy{display:inline-flex;align-items:center;gap:5px}
.ovpn-link-copy .ui-icon{width:15px;height:15px;flex:0 0 15px}
.ovpn-link-copy[disabled]{opacity:.45;cursor:not-allowed}
@media(max-width:650px){.guide-admin-method .system-fields{grid-template-columns:1fr}}
</style>
</head>
<body>
<main class="admin-shell">
<?php require __DIR__ . '/_navigation.php'; ?>
<section class="panel panel-card">
<div class="eyebrow" data-fa="محتوای عمومی مشتریان" data-en="Customer-facing general content">محتوای عمومی مشتریان</div>
<h1 data-fa="راهنمای استفاده از اکانت" data-en="Account usage guide">راهنمای استفاده از اکانت</h1>
<p class="guide-admin-note" data-fa="این راهنما برای همه اکانت‌های فروشگاه مشترک است. روش‌های اتصال را تعریف کنید و برای هرکدام، آموزش سیستم‌عامل‌های موردنظر را وارد کنید. هیچ راهنمایی به محصول یا نوع سرویس فروشگاه متصل نمی‌شود." data-en="This guide is shared by all Store accounts. Define connection methods and add instructions for the operating systems you need. Guides are not linked to products or store service types.">یک راهنمای واحد برای همه اکانت‌هاست. روش‌های اتصال را تعریف کنید و برای هرکدام، آموزش سیستم‌عامل‌های موردنظر را وارد کنید. هیچ راهنمایی به محصول یا نوع سرویس فروشگاه متصل نمی‌شود.</p>
<?php if ($message !== ''): ?><div class="notice" role="status"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
<div class="field field-full">
<label for="guide-intro" data-fa="توضیح ابتدای راهنما" data-en="Guide introduction">توضیح ابتدای راهنما</label>
<textarea id="guide-intro" name="intro" rows="3" placeholder="روش اتصال و سیستم‌عامل موردنظر خود را انتخاب کنید." data-fa-placeholder="روش اتصال و سیستم‌عامل موردنظر خود را انتخاب کنید." data-en-placeholder="Select your connection method and operating system."></textarea>
</div>
<div class="guide-admin-method-head"><h2 data-fa="روش‌های اتصال" data-en="Connection methods">روش‌های اتصال</h2><button class="button" type="button" id="add-guide-method" data-fa="افزودن روش اتصال" data-en="Add connection method">افزودن روش اتصال</button></div>
<div id="guide-method-list">
<?php foreach ($guideMethods as $index => $method): $method = is_array($method) ? $method : []; ?>
<article class="guide-admin-method" data-guide-method>
<div class="guide-admin-method-head">
<div class="field guide-method-name-field"><label><span class="guide-icon-slot" data-method-icon-preview><?= guide_icon_svg(guide_method_icon_key($method)) ?></span><span data-fa="نام روش اتصال" data-en="Connection method name">نام روش اتصال</span></label><input name="methods[<?= (int)$index ?>][name]" maxlength="100" value="<?= $esc($method['name'] ?? '') ?>" placeholder="OpenVPN / L2TP / Cisco"></div>
<div class="field guide-method-icon-picker"><label data-fa="آیکون روش" data-en="Method icon" for="guide-method-icon-<?= (int)$index ?>">آیکون روش</label><select id="guide-method-icon-<?= (int)$index ?>" name="methods[<?= (int)$index ?>][icon]" data-method-icon-select><?php foreach (['openvpn' => 'OpenVPN', 'v2ray' => 'V2Ray', 'wireguard' => 'WireGuard', 'generic' => 'Generic'] as $iconKey => $iconLabel): ?><option value="<?= e($iconKey) ?>"<?= guide_method_icon_key($method) === $iconKey ? ' selected' : '' ?>><?= e($iconLabel) ?></option><?php endforeach; ?></select></div>
<label class="check-toggle"><input type="checkbox" name="methods[<?= (int)$index ?>][active]" value="1"<?= !empty($method['active']) ? ' checked' : '' ?>> <span data-fa="فعال و قابل نمایش برای مشتری" data-en="Enabled and visible to customers">فعال و قابل نمایش برای مشتری</span></label>
<button class="button danger" type="button" data-remove-guide-method data-fa="حذف روش" data-en="Remove method">حذف روش</button>
</div>
<?php foreach ($systems as $systemKey => $labels): $system = is_array($method['systems'][$systemKey] ?? null) ? $method['systems'][$systemKey] : []; ?>
<details class="form-section guide-system-section">
<summary class="guide-system-summary"><span class="guide-system-icon"><?= guide_icon_svg($systemKey) ?></span><span class="guide-system-title" data-fa="<?= e($labels['fa']) ?>" data-en="<?= e($labels['en']) ?>"><?= e($labels['fa']) ?></span></summary>
<div class="system-fields">
<div class="field field-full"><label data-fa="مراحل و توضیحات" data-en="Instructions">مراحل و توضیحات</label><textarea name="methods[<?= (int)$index ?>][systems][<?= e($systemKey) ?>][content]" rows="5"><?= $esc($system['content'] ?? '') ?></textarea></div>
<div class="field"><label data-fa="نشانی تصویر آموزشی (اختیاری)" data-en="Instruction image URL (optional)">نشانی تصویر آموزشی (اختیاری)</label><input type="url" name="methods[<?= (int)$index ?>][systems][<?= e($systemKey) ?>][image_url]" value="<?= $esc($system['image_url'] ?? '') ?>" placeholder="https://..."></div>
<div class="field"><label data-fa="لینک دریافت نرم‌افزار (اختیاری)" data-en="Software link (optional)">لینک دریافت نرم‌افزار (اختیاری)</label><input type="url" name="methods[<?= (int)$index ?>][systems][<?= e($systemKey) ?>][software_url]" value="<?= $esc($system['software_url'] ?? '') ?>" placeholder="https://..."></div>
<div class="field"><label data-fa="لینک فایل راهنما (اختیاری)" data-en="Guide file URL (optional)">لینک فایل راهنما (اختیاری)</label><input type="url" name="methods[<?= (int)$index ?>][systems][<?= e($systemKey) ?>][download_url]" value="<?= $esc($system['download_url'] ?? '') ?>" placeholder="https://..."></div>
<div class="field"><label data-fa="عنوان لینک فایل" data-en="Download link label">عنوان لینک فایل</label><input name="methods[<?= (int)$index ?>][systems][<?= e($systemKey) ?>][download_label]" maxlength="160" value="<?= $esc($system['download_label'] ?? '') ?>"></div>
</div>
</details>
<?php endforeach; ?>
</article>
<?php endforeach; ?>
</div>
<div class="actions"><button class="button primary" type="submit" data-fa="ذخیره راهنمای عمومی" data-en="Save general guide">ذخیره راهنمای عمومی</button><a class="button" href="<?= e(app_url('userguide.php')) ?>" target="_blank" rel="noopener" data-fa="مشاهده صفحه راهنما" data-en="Open customer guide">مشاهده صفحه راهنما</a></div>
</form>
</section>
<?php
$ovpnProfiles = [];
$ovpnLinks = [];
$ovpnLoadError = '';
try {
    $ovpnProfiles = $ovpnManager->profiles();
    $ovpnLinks = $ovpnManager->links();
} catch (Throwable $e) {
    error_log('OpenVPN admin data loading failed: ' . $e->getMessage());
    $ovpnLoadError = 'جداول OpenVPN در دیتابیس آماده نیستند. از بخش همگام‌سازی دیتابیس در مدیریت، تغییرات معوق را اعمال کنید.';
}
$editProfileId = (int)($_GET['edit_ovpn_profile'] ?? 0);
$editProfile = null;
foreach ($ovpnProfiles as $profileRow) {
    if ((int)$profileRow['id'] === $editProfileId) $editProfile = $profileRow;
}
?>
<section class="panel panel-card" id="openvpn-management">
<?php if ($ovpnMessage !== ''): ?><div class="notice" role="status" data-fa="<?= e($ovpnMessage) ?>" data-en="<?= e($ovpnMessageEn) ?>"><?= e($ovpnMessage) ?></div><?php endif; ?>
<?php if ($ovpnError !== ''): ?><div class="notice err" role="alert" data-fa="<?= e($ovpnError) ?>" data-en="OpenVPN operation failed. Check the server error log for details."><?= e($ovpnError) ?></div><?php endif; ?>
<?php if ($ovpnLoadError !== ''): ?><div class="notice err" role="alert" data-fa="جداول OpenVPN در دیتابیس آماده نیستند. از بخش همگام‌سازی دیتابیس در مدیریت، تغییرات معوق را اعمال کنید." data-en="The OpenVPN database tables are not ready. Apply the pending changes from the Database Synchronization section in the admin panel."><?= e($ovpnLoadError) ?></div><?php endif; ?>
<div class="guide-admin-disclosure-content">
<p class="guide-admin-note" data-fa="مدیریت پروفایل‌ها و ساخت لینک‌های عمومی یا اختصاصی در همین کارت انجام می‌شود. پروفایل‌های ذخیره‌شده رمزنگاری می‌شوند." data-en="Manage profiles and create public or personalized links in this card. Stored profiles are encrypted.">مدیریت پروفایل‌ها و ساخت لینک‌های عمومی یا اختصاصی در همین کارت انجام می‌شود. پروفایل‌های ذخیره‌شده رمزنگاری می‌شوند.</p>
<?php if ($ovpnGeneratedLinks): ?>
<div class="form-section" aria-live="polite">
<h3 data-fa="لینک‌های Import ساخته‌شده" data-en="Generated import URLs">لینک‌های Import ساخته‌شده</h3>
<?php if (isset($ovpnGeneratedLinks['personalized'])): ?>
<div class="field">
<label data-fa="لینک اختصاصی Import" data-en="Personalized import URL">لینک اختصاصی Import</label>
<div class="actions"><input class="ovpn-admin-url" readonly dir="ltr" value="<?= e($ovpnGeneratedLinks['personalized']) ?>"><button class="button ovpn-copy-button" type="button" data-copy-value="<?= e($ovpnGeneratedLinks['personalized']) ?>"><?= storefront_icon_svg('copy') ?><span data-fa="کپی لینک اختصاصی" data-en="Copy personalized URL">کپی لینک اختصاصی</span></button></div>
</div>
<?php endif; ?>
<div class="field">
<label data-fa="لینک عمومی Import" data-en="Public import URL">لینک عمومی Import</label>
<div class="actions"><input class="ovpn-admin-url" readonly dir="ltr" value="<?= e($ovpnGeneratedLinks['public']) ?>"><button class="button ovpn-copy-button" type="button" data-copy-value="<?= e($ovpnGeneratedLinks['public']) ?>"><?= storefront_icon_svg('copy') ?><span data-fa="کپی لینک عمومی" data-en="Copy public URL">کپی لینک عمومی</span></button></div>
</div>
</div>
<?php endif; ?>
<div class="ovpn-management-grid">
<div class="ovpn-management-column">
<details class="form-section"<?= ($editProfile || $ovpnKeepProfilesOpen) ? ' open' : '' ?>>
<summary><strong data-fa="مدیریت پروفایل‌های OpenVPN" data-en="Manage OpenVPN profiles">مدیریت پروفایل‌های OpenVPN</strong></summary>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="openvpn_save_profile">
<input type="hidden" name="profile_id" value="<?= $editProfile ? (int)$editProfile['id'] : 0 ?>">
<div class="field"><label for="ovpn-profile-name" data-fa="نام نمایشی پروفایل" data-en="Profile display name">نام نمایشی پروفایل</label><input id="ovpn-profile-name" name="profile_name" maxlength="120" required value="<?= e((string)($editProfile['name'] ?? '')) ?>" placeholder="سرور ترکیه" data-fa-placeholder="سرور ترکیه" data-en-placeholder="Turkey Server"></div>
<div class="field"><label for="ovpn-profile-url" data-fa="نشانی فایل پروفایل (HTTPS)" data-en="Profile file URL (HTTPS)">نشانی فایل پروفایل (HTTPS)</label><input id="ovpn-profile-url" name="profile_url" type="url" dir="ltr" placeholder="https://example.com/profile.ovpn" data-fa-placeholder="https://example.com/profile.ovpn" data-en-placeholder="https://example.com/profile.ovpn"></div>
<div class="field"><label for="ovpn-profile-file" data-fa="یا بارگذاری فایل .ovpn" data-en="Or upload an .ovpn file">یا بارگذاری فایل .ovpn</label><input id="ovpn-profile-file" name="profile_file" type="file" accept=".ovpn,application/x-openvpn-profile"></div>
<p class="hint" data-fa="یکی از دو روش نشانی HTTPS یا بارگذاری فایل را انتخاب کنید. در روش نشانی، نسخه دریافت‌شده ذخیره می‌شود و تغییرات بعدی فایل راه دور به‌صورت خودکار اعمال نخواهد شد. برای جایگزینی، پروفایل را از فهرست انتخاب کنید." data-en="Choose either an HTTPS URL or a file upload. URL imports are stored as a snapshot; later remote changes are not applied automatically. Select a profile below to replace it.">یکی از دو روش نشانی HTTPS یا بارگذاری فایل را انتخاب کنید. در روش نشانی، نسخه دریافت‌شده ذخیره می‌شود و تغییرات بعدی فایل راه دور به‌صورت خودکار اعمال نخواهد شد. برای جایگزینی، پروفایل را از فهرست انتخاب کنید.</p>
<div class="actions"><button class="button primary" type="submit" data-fa="ذخیره پروفایل" data-en="Save profile">ذخیره پروفایل</button><?php if ($editProfile): ?><a class="button" href="<?= e(app_url('admin/guide.php#openvpn-management')) ?>" data-fa="لغو ویرایش" data-en="Cancel edit">لغو ویرایش</a><?php endif; ?></div>
</form>
<div class="table-wrap"><table><thead><tr><th data-fa="نام پروفایل" data-en="Profile">نام پروفایل</th><th data-fa="وضعیت" data-en="Status">وضعیت</th><th data-fa="عملیات" data-en="Actions">عملیات</th></tr></thead><tbody>
<?php if (!$ovpnProfiles): ?><tr><td colspan="3" data-fa="هنوز پروفایلی ثبت نشده است." data-en="No profiles have been added yet.">هنوز پروفایلی ثبت نشده است.</td></tr><?php endif; ?>
<?php foreach ($ovpnProfiles as $profileRow): ?><tr><td><?= e((string)$profileRow['name']) ?></td><td><?= !empty($profileRow['is_active']) ? '<span data-fa="فعال" data-en="Active">فعال</span>' : '<span data-fa="غیرفعال" data-en="Inactive">غیرفعال</span>' ?></td><td class="actions"><a class="button" href="<?= e(app_url('admin/guide.php?edit_ovpn_profile='.(int)$profileRow['id'].'#openvpn-management')) ?>" data-fa="جایگزینی فایل" data-en="Replace file">جایگزینی فایل</a><form method="post"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="openvpn_toggle_profile"><input type="hidden" name="profile_id" value="<?= (int)$profileRow['id'] ?>"><input type="hidden" name="active" value="<?= !empty($profileRow['is_active']) ? '0' : '1' ?>"><button class="button" type="submit"><?= !empty($profileRow['is_active']) ? '<span data-fa="غیرفعال‌سازی" data-en="Disable">غیرفعال‌سازی</span>' : '<span data-fa="فعال‌سازی" data-en="Enable">فعال‌سازی</span>' ?></button></form><form method="post" data-confirm-fa="این پروفایل و لینک‌های وابسته به آن حذف شوند؟" data-confirm-en="Delete this profile and its associated links?"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="openvpn_delete_profile"><input type="hidden" name="profile_id" value="<?= (int)$profileRow['id'] ?>"><button class="button danger" type="submit" data-fa="حذف" data-en="Delete">حذف</button></form></td></tr><?php endforeach; ?>
</tbody></table></div>
</details>
</div>
<div class="ovpn-management-column">
<details class="form-section"<?= $ovpnKeepLinksOpen ? ' open' : '' ?>>
<summary><strong data-fa="مدیریت لینک‌های OpenVPN" data-en="Manage OpenVPN links">مدیریت لینک‌های OpenVPN</strong></summary>
<form method="get" class="field ovpn-search-form"><label for="ovpn-order-search" data-fa="جست‌وجوی سفارش / حساب مشتری" data-en="Search order / customer account">جست‌وجوی سفارش / حساب مشتری</label><input id="ovpn-order-search" name="ovpn_order_search" value="<?= e($ovpnSearch) ?>" placeholder="نام کاربری / ایمیل / کد رهگیری" data-fa-placeholder="نام کاربری / ایمیل / کد رهگیری" data-en-placeholder="Username / Email / Tracking code" dir="auto"><button class="button primary" type="submit" data-fa="جست‌وجو" data-en="Search">جست‌وجو</button></form>
<form method="post" id="ovpn-link-form">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="openvpn_create_link">
<div class="field"><label for="ovpn-order-id" data-fa="انتخاب سفارش / حساب مشتری" data-en="Select order / customer account">انتخاب سفارش / حساب مشتری</label><select id="ovpn-order-id" name="order_id"><option value="" data-fa="انتخاب کنید" data-en="Select">انتخاب کنید</option><?php foreach ($ovpnOrders as $orderRow): ?><option value="<?= (int)$orderRow['id'] ?>" data-email="<?= e((string)$orderRow['buyer_email']) ?>"><?= e((string)$orderRow['username'].' · '.$orderRow['tracking_code'].' · '.$orderRow['buyer_name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="ovpn-link-profile" data-fa="انتخاب پروفایل OpenVPN" data-en="Select OpenVPN profile">انتخاب پروفایل OpenVPN</label><select id="ovpn-link-profile" name="profile_id" required><option value="" data-fa="انتخاب پروفایل" data-en="Select profile">انتخاب پروفایل</option><?php foreach ($ovpnProfiles as $profileRow): if (empty($profileRow['is_active'])) continue; ?><option value="<?= (int)$profileRow['id'] ?>"><?= e((string)$profileRow['name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="ovpn-recipient-email" data-fa="نشانی ایمیل مشتری" data-en="Customer email address">نشانی ایمیل مشتری</label><input id="ovpn-recipient-email" type="email" name="recipient_email" maxlength="190" placeholder="customer@example.com" data-fa-placeholder="ایمیل مشتری" data-en-placeholder="Customer email" dir="ltr"><p class="hint" data-fa="با انتخاب سفارش، ایمیل ثبت‌شده به‌صورت پیش‌فرض قرار می‌گیرد و قابل تغییر است. برای لینک عمومی نیازی به سفارش یا ایمیل نیست." data-en="The order email is prefilled when available and can be changed. Public links do not require an order or email.">با انتخاب سفارش، ایمیل ثبت‌شده به‌صورت پیش‌فرض قرار می‌گیرد و قابل تغییر است. برای لینک عمومی نیازی به سفارش یا ایمیل نیست.</p></div>
<div class="actions"><button class="button primary" type="submit" name="link_type" value="personalized" data-fa="ساخت لینک اختصاصی" data-en="Create personalized link">ساخت لینک اختصاصی</button><button class="button" type="submit" name="link_type" value="public" data-fa="ساخت لینک عمومی" data-en="Create public link">ساخت لینک عمومی</button></div>
</form>
</details>
</div>
<div class="table-wrap ovpn-generated-links" style="margin-top:18px"><h3 data-fa="لینک‌های ساخته‌شده" data-en="Generated links">لینک‌های ساخته‌شده</h3><table><thead><tr><th data-fa="پروفایل" data-en="Profile">پروفایل</th><th data-fa="نوع" data-en="Type">نوع</th><th data-fa="سفارش" data-en="Order">سفارش</th><th data-fa="ایمیل گیرنده" data-en="Recipient">ایمیل گیرنده</th><th data-fa="دانلود" data-en="Downloads">دانلود</th><th data-fa="وضعیت" data-en="Status">وضعیت</th><th data-fa="عملیات" data-en="Actions">عملیات</th></tr></thead><tbody>
<?php if (!$ovpnLinks): ?><tr><td colspan="7" data-fa="هنوز لینکی ساخته نشده است." data-en="No links have been generated.">هنوز لینکی ساخته نشده است.</td></tr><?php endif; ?>
<?php foreach ($ovpnLinks as $linkRow): ?><tr><td><?= e((string)$linkRow['profile_name']) ?></td><td><?= $linkRow['link_type']==='public' ? '<span data-fa="عمومی" data-en="Public">عمومی</span>' : '<span data-fa="اختصاصی" data-en="Personalized">اختصاصی</span>' ?></td><td><?= $linkRow['order_id'] ? '#'.(int)$linkRow['order_id'] : '—' ?></td><td><?= e((string)($linkRow['recipient_email'] ?? '—')) ?></td><td><?= (int)$linkRow['download_count'] ?></td><td><?= !empty($linkRow['is_active']) ? '<span data-fa="فعال" data-en="Active">فعال</span>' : '<span data-fa="غیرفعال" data-en="Inactive">غیرفعال</span>' ?></td><td><div class="ovpn-link-actions">
<button class="button ovpn-link-copy ovpn-copy-button" type="button" data-copy-value="<?= e((string)($linkRow['url'] ?? '')) ?>"<?= empty($linkRow['url']) ? ' disabled' : '' ?> data-fa-title="کپی لینک" data-en-title="Copy link"><?= storefront_icon_svg('copy') ?><span data-fa="کپی" data-en="Copy">کپی</span></button>
<form method="post"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="openvpn_toggle_link"><input type="hidden" name="link_id" value="<?= (int)$linkRow['id'] ?>"><input type="hidden" name="active" value="<?= !empty($linkRow['is_active']) ? '0' : '1' ?>"><button class="button" type="submit"><?= !empty($linkRow['is_active']) ? '<span data-fa="لغو لینک" data-en="Revoke link">لغو لینک</span>' : '<span data-fa="فعال‌سازی مجدد" data-en="Reactivate">فعال‌سازی مجدد</span>' ?></button></form>
<form method="post" data-confirm-fa="این لینک برای همیشه از دیتابیس حذف شود؟" data-confirm-en="Permanently delete this link from the database?"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="openvpn_delete_link"><input type="hidden" name="link_id" value="<?= (int)$linkRow['id'] ?>"><button class="button danger" type="submit" data-fa="حذف لینک" data-en="Delete link">حذف لینک</button></form>
</div></td></tr><?php endforeach; ?>
</tbody></table></div>
</div>
</section>
<script>
(function(){const order=document.getElementById('ovpn-order-id'),email=document.getElementById('ovpn-recipient-email'),form=document.getElementById('ovpn-link-form');if(order&&email)order.addEventListener('change',()=>{const option=order.selectedOptions[0];email.value=option?.dataset.email||'';});if(form)form.addEventListener('submit',event=>{const button=event.submitter;if(button?.value==='personalized'){if(!order.value){event.preventDefault();alert(document.documentElement.lang==='en'?'Select an order first.':'ابتدا سفارش مشتری را انتخاب کنید.');return;}if(!email.value){event.preventDefault();alert(document.documentElement.lang==='en'?'Enter a recipient email address.':'نشانی ایمیل گیرنده را وارد کنید.');}}});})();
</script>
<script>
document.addEventListener('click', async function(event) {
 const button = event.target.closest('[data-copy-value]');
 if (!button) return;
 const value = button.getAttribute('data-copy-value') || '';
 try {
  if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(value);
  else { const field=document.createElement('textarea'); field.value=value; field.style.position='fixed'; field.style.opacity='0'; document.body.appendChild(field); field.select(); document.execCommand('copy'); field.remove(); }
  const label=button.querySelector('span');
  if(label){const old=label.textContent;label.textContent=document.documentElement.lang==='en'?'Copied':'کپی شد';setTimeout(()=>label.textContent=old,1800);}
 } catch (_) { const input=button.parentElement?.querySelector('input'); if(input){input.focus();input.select();} }
});
</script>
<?php require __DIR__ . '/_footer.php'; ?>
</main>
<script>
(function(){
 const list=document.getElementById('guide-method-list'),add=document.getElementById('add-guide-method');
 if(!list||!add)return;
 let nextIndex=<?= count($guideMethods) ?>;
 const icons={openvpn:<?= json_encode(guide_icon_svg('openvpn')) ?>,v2ray:<?= json_encode(guide_icon_svg('v2ray')) ?>,wireguard:<?= json_encode(guide_icon_svg('wireguard')) ?>,generic:<?= json_encode(guide_icon_svg('generic')) ?>};
 function refresh(card){const select=card.querySelector('[data-method-icon-select]'),preview=card.querySelector('[data-method-icon-preview]');if(select&&preview)preview.innerHTML=icons[select.value]||icons.generic}
 list.addEventListener('change',event=>{if(event.target.matches('[data-method-icon-select]'))refresh(event.target.closest('[data-guide-method]'))});
 list.querySelectorAll('[data-guide-method]').forEach(refresh);
 add.addEventListener('click',()=>{
  const source=list.querySelector('[data-guide-method]');if(!source)return;
  const clone=source.cloneNode(true);
  clone.querySelectorAll('[name]').forEach(field=>{field.name=field.name.replace(/methods\[\d+\]/,'methods['+nextIndex+']');if(field.matches('input[type="checkbox"]'))field.checked=false;else if(field.matches('input,textarea'))field.value='';else if(field.matches('select'))field.value='generic'});
  clone.querySelectorAll('[id],[for]').forEach(field=>{if(field.id)field.id=field.id.replace(/guide-method-icon-\d+/,'guide-method-icon-'+nextIndex);if(field.htmlFor)field.htmlFor=field.htmlFor.replace(/guide-method-icon-\d+/,'guide-method-icon-'+nextIndex)});
  refresh(clone);list.append(clone);nextIndex++;
 });
 list.addEventListener('click',event=>{const button=event.target.closest('[data-remove-guide-method]');if(button)button.closest('[data-guide-method]')?.remove()});
})();
</script>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</body></html>
