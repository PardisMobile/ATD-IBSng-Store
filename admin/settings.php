<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_once dirname(__DIR__) . '/app/Payments/PaymentManager.php';
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

function support_uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf('%s-%s-%s-%s-%s',
        substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4),
        substr($hex, 16, 4), substr($hex, 20, 12)
    );
}

function support_is_uuid_v4(string $value): bool
{
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $value) === 1;
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

$priceDisplayUnit = support_setting($pdo, 'price_display_unit') ?: 'IRT';
$cryptoDisplayEnabled = support_setting($pdo, 'crypto_gateway_enabled') === '1';
$paymentProvider = support_setting($pdo, 'payment_provider');
$enabledProvidersRaw = support_setting($pdo, 'payment_enabled_providers');
$decodedEnabledProviders = $enabledProvidersRaw !== '' ? json_decode($enabledProvidersRaw, true) : null;
$enabledProviders = is_array($decodedEnabledProviders)
    ? array_values(array_filter($decodedEnabledProviders, 'is_string'))
    : ($paymentProvider !== '' ? [$paymentProvider] : []);
$gatewayPaymentTypes = [];
$gatewayCodes = PaymentManager::registeredCodes();
$gatewayManager = $gatewayCodes !== [] ? new PaymentManager($gatewayCodes[0]) : null;
$gatewayFields = [];
$gatewaySupportedCurrencies = [];
foreach ($gatewayCodes as $gatewayCode) {
    $gatewayPaymentTypes[$gatewayCode] = support_setting($pdo, 'payment_' . $gatewayCode . '_type') ?: 'fiat';
    try {
        $gatewayFields[$gatewayCode] = $gatewayManager?->configurationFields($gatewayCode) ?? [];
        $gatewaySupportedCurrencies[$gatewayCode] = $gatewayManager?->supportedCurrencies($gatewayCode) ?? [];
    } catch (Throwable) {
        $gatewayFields[$gatewayCode] = [];
        $gatewaySupportedCurrencies[$gatewayCode] = [];
    }
}
$gatewayValues = [];
foreach ($gatewayFields as $gatewayCode => $fields) {
    foreach ($fields as $fieldKey => $field) {
        $settingKey = 'payment_' . $gatewayCode . '_' . $fieldKey;
        $gatewayValues[$gatewayCode][$fieldKey] = support_setting($pdo, $settingKey);
        if (($field['type'] ?? '') === 'checkbox' && $gatewayValues[$gatewayCode][$fieldKey] === '') {
            $gatewayValues[$gatewayCode][$fieldKey] = '0';
        }
    }
}

$renewalValues = [
    'ibsng_gift_days_first' => support_setting($pdo, 'ibsng_gift_days_first') ?: '1',
    'ibsng_gift_days_repeat' => support_setting($pdo, 'ibsng_gift_days_repeat') ?: '5',
];

$credentialValues = [
    'credential_mode' => support_setting($pdo, 'credential_mode') ?: 'customer',
    'username_prefix' => support_setting($pdo, 'username_prefix') ?: 'atd',
    'username_suffix_length' => support_setting($pdo, 'username_suffix_length') ?: '5',
    'password_length' => support_setting($pdo, 'password_length') ?: '5',
    'password_charset' => in_array(support_setting($pdo, 'password_charset'), ['digits', 'letters', 'alphanumeric'], true) ? support_setting($pdo, 'password_charset') : 'alphanumeric',
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
        $priceDisplayUnit = strtoupper(trim((string)($_POST['price_display_unit'] ?? 'IRT')));
        $cryptoDisplayEnabled = (($_POST['crypto_gateway_enabled'] ?? '') === '1');
        $enabledProviders = array_values(array_unique(array_filter(array_map(
            static fn($code): string => strtolower(trim((string)$code)),
            is_array($_POST['enabled_providers'] ?? null) ? $_POST['enabled_providers'] : []
        ))));
        $paymentProvider = $enabledProviders[0] ?? '';
        $gatewayPaymentTypes = [];
        foreach ($gatewayCodes as $gatewayCode) {
            $gatewayPaymentTypes[$gatewayCode] = strtolower(trim((string)($_POST['gateway_' . $gatewayCode . '_type'] ?? 'fiat')));
        }
        $gatewayValues = [];
        foreach ($gatewayFields as $gatewayCode => $fields) {
            foreach ($fields as $fieldKey => $field) {
                $inputName = 'gateway_' . $gatewayCode . '_' . $fieldKey;
                $gatewayValues[$gatewayCode][$fieldKey] = ($field['type'] ?? '') === 'checkbox'
                    ? (isset($_POST[$inputName]) && (string)$_POST[$inputName] === '1' ? '1' : '0')
                    : trim((string)($_POST[$inputName] ?? ''));
            }
        }
        if (($gatewayValues['zarinpal']['sandbox_mode'] ?? '0') === '1') {
            $sandboxUuid = trim((string)($gatewayValues['zarinpal']['sandbox_merchant_id'] ?? ''));
            if (!support_is_uuid_v4($sandboxUuid)) {
                $gatewayValues['zarinpal']['sandbox_merchant_id'] = support_uuid_v4();
            }
        }
        if (!in_array($priceDisplayUnit, ['IRT', 'IRR'], true)) {
            $error = 'واحد نمایش قیمت معتبر نیست.';
        } elseif (array_diff($gatewayPaymentTypes, ['fiat', 'crypto']) !== []) {
            $error = 'نوع ارز درگاه پرداخت معتبر نیست.';
        } elseif (array_diff($enabledProviders, $gatewayCodes) !== []) {
            $error = 'فهرست درگاه‌های فعال معتبر نیست.';
        } else {
            if (in_array('zarinpal', $enabledProviders, true)) {
                $sandboxMode = ($gatewayValues['zarinpal']['sandbox_mode'] ?? '0') === '1';
                $requiredMerchant = $sandboxMode
                    ? ($gatewayValues['zarinpal']['sandbox_merchant_id'] ?? '')
                    : ($gatewayValues['zarinpal']['merchant_id'] ?? '');
                if (!$sandboxMode && trim((string)$requiredMerchant) === '') {
                    $error = 'برای پرداخت زرین‌پال، Merchant ID واقعی را وارد کنید.';
                }
            }
            foreach ($enabledProviders as $enabledProvider) {
                $configuredType = $gatewayPaymentTypes[$enabledProvider] ?? 'fiat';
                $supportedCurrencies = $gatewayManager?->supportedCurrencies($enabledProvider) ?? [];
                $typeSupported = $configuredType === 'crypto'
                    ? in_array('USDT', $supportedCurrencies, true)
                    : array_intersect(['IRR', 'IRT'], $supportedCurrencies) !== [];
                if (!$typeSupported) {
                    $error = 'نوع ارز انتخاب‌شده با قابلیت‌های درگاه ' . $enabledProvider . ' سازگار نیست.';
                    break;
                }
                foreach (($gatewayFields[$enabledProvider] ?? []) as $fieldKey => $field) {
                    $fieldValue = (string)($gatewayValues[$enabledProvider][$fieldKey] ?? '');
                    if (($field['required'] ?? false) && $fieldValue === '') {
                        $error = 'فیلد الزامی درگاه ' . $enabledProvider . ' تکمیل نشده است: ' . (string)($field['label'] ?? $fieldKey);
                        break 2;
                    }
                    if (strlen($fieldValue) > 2048) {
                        $error = 'مقدار تنظیمات درگاه بیش از حد طولانی است.';
                        break 2;
                    }
                }
            }
        }
        $renewalValues = [
            'ibsng_gift_days_first' => trim((string)($_POST['ibsng_gift_days_first'] ?? '1')),
            'ibsng_gift_days_repeat' => trim((string)($_POST['ibsng_gift_days_repeat'] ?? '5')),
        ];
        $credentialValues = [
            'credential_mode' => trim((string)($_POST['credential_mode'] ?? 'customer')),
            'username_prefix' => trim((string)($_POST['username_prefix'] ?? 'atd')),
            'username_suffix_length' => trim((string)($_POST['username_suffix_length'] ?? '6')),
            'password_length' => trim((string)($_POST['password_length'] ?? '12')),
            'password_charset' => trim((string)($_POST['password_charset'] ?? 'mixed')),
        ];
        $values = [
            'support_telegram_id' => trim((string)($_POST['telegram_id'] ?? '')),
            'support_whatsapp_target' => trim((string)($_POST['whatsapp_target'] ?? '')),
            'support_custom_label' => trim((string)($_POST['custom_label'] ?? '')),
            'support_custom_url' => trim((string)($_POST['custom_url'] ?? '')),
        ];
        if (!in_array($credentialValues['credential_mode'], ['customer', 'automatic', 'choice'], true)
            || !preg_match('/^[A-Za-z0-9_.-]{1,24}$/D', $credentialValues['username_prefix'])
            || !ctype_digit($credentialValues['username_suffix_length'])
            || (int)$credentialValues['username_suffix_length'] < 4 || (int)$credentialValues['username_suffix_length'] > 12
            || !ctype_digit($credentialValues['password_length'])
            || (int)$credentialValues['password_length'] < 4 || (int)$credentialValues['password_length'] > 11
            || !in_array($credentialValues['password_charset'], ['digits', 'letters', 'alphanumeric'], true)) {
            $error = 'تنظیمات تولید اطلاعات اکانت معتبر نیست.';
        }
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
        } elseif ($error === '') {
            $stmt = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ($values as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            foreach ($renewalValues as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            foreach ($credentialValues as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            $stmt->execute(['price_display_unit', $priceDisplayUnit]);
            $stmt->execute(['crypto_gateway_enabled', $cryptoDisplayEnabled ? '1' : '0']);
            $stmt->execute(['payment_provider', $paymentProvider]);
            $stmt->execute(['payment_enabled_providers', json_encode($enabledProviders, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
            foreach ($gatewayPaymentTypes as $gatewayCode => $paymentType) {
                $stmt->execute(['payment_' . $gatewayCode . '_type', $paymentType]);
            }
            foreach ($gatewayFields as $gatewayCode => $fields) {
                foreach ($fields as $fieldKey => $field) {
                    $stmt->execute(['payment_' . $gatewayCode . '_' . $fieldKey, (string)($gatewayValues[$gatewayCode][$fieldKey] ?? '')]);
                }
            }
            $message = 'تنظیمات فروشگاه ذخیره شد.';
        }
    }
}

if ($error !== '') {
    // Keep entered values visible after a validation failure.
} else {
    $priceDisplayUnit = support_setting($pdo, 'price_display_unit') ?: 'IRT';
    $cryptoDisplayEnabled = support_setting($pdo, 'crypto_gateway_enabled') === '1';
    $paymentProvider = support_setting($pdo, 'payment_provider');
    foreach ($gatewayCodes as $gatewayCode) {
        $gatewayPaymentTypes[$gatewayCode] = support_setting($pdo, 'payment_' . $gatewayCode . '_type') ?: 'fiat';
    }
    foreach ($gatewayFields as $gatewayCode => $fields) {
        foreach ($fields as $fieldKey => $field) {
            $gatewayValues[$gatewayCode][$fieldKey] = support_setting($pdo, 'payment_' . $gatewayCode . '_' . $fieldKey);
        }
    }
    foreach ($values as $key => $value) {
        $values[$key] = support_setting($pdo, $key);
    }
    foreach ($renewalValues as $key => $value) {
        $renewalValues[$key] = support_setting($pdo, $key) ?: ($key === 'ibsng_gift_days_first' ? '1' : '5');
    }
    foreach ($credentialValues as $key => $value) {
        $credentialValues[$key] = support_setting($pdo, $key) ?: match ($key) { 'credential_mode' => 'customer', 'username_prefix' => 'atd', 'username_suffix_length' => '6', 'password_length' => '12', default => 'mixed' };
    }
}
$config = app_config();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="<?= e(app_asset_url('assets/icons/atd-app-icon.svg')) ?>"><link rel="apple-touch-icon" sizes="180x180" href="<?= e(app_asset_url('assets/icons/apple-touch-icon.png')) ?>"><link rel="manifest" href="<?= e(app_asset_url('site.webmanifest')) ?>">
<title data-ui-title-key="title_admin_settings">راه‌های پشتیبانی | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.admin-shell{width:min(1040px,calc(100% - 32px));margin:0 auto;padding-bottom:32px;min-width:0}.settings-section{margin:16px 0}.gateway-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr));gap:16px;align-items:start}.gateway-grid>fieldset{margin:0;height:100%}.admin-top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:24px 0}.panel-card{padding:clamp(18px,4vw,30px);margin:18px 0;min-width:0}.admin-shell>section.panel-card{box-shadow:var(--ui-shadow-sm,none)}.field{display:grid;gap:8px;margin:16px 0;min-width:0}.field label{color:var(--text);font-weight:600;line-height:1.6}.field label:has(input[type=checkbox]){display:flex;align-items:center;gap:10px}.field input[type=checkbox]{width:18px;height:18px;min-height:18px;flex:0 0 18px;accent-color:var(--accent)}.field input,.field select,.field textarea{box-sizing:border-box;width:100%;min-width:0;padding:12px 14px;border:1px solid var(--border);border-radius:var(--ui-radius-md);background:var(--control-bg);color:var(--text);font:inherit;min-height:var(--ui-control-height)}.field input::placeholder,.field textarea::placeholder{color:var(--muted);opacity:.8}.field input:focus-visible,.field select:focus-visible,.field textarea:focus-visible{outline:2px solid var(--accent);outline-offset:1px;box-shadow:0 0 0 3px var(--ui-focus-ring)}.field input[type=url],.field input[type=email],.field input[type=number],fieldset input{direction:ltr;text-align:left;unicode-bidi:isolate;font-variant-numeric:lining-nums,tabular-nums}.field input[type=number]{-moz-appearance:textfield;appearance:textfield;font-variant-numeric:tabular-nums}.field input[type=number]::-webkit-inner-spin-button,.field input[type=number]::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}fieldset.panel-card{min-width:0;margin:20px 0;padding:clamp(16px,3vw,24px);border:1px solid var(--border);border-radius:var(--ui-radius-lg);background:var(--surface-2,transparent)}fieldset.panel-card legend{padding:0 10px;font-weight:700;color:var(--accent)}fieldset .hint{margin-top:10px}fieldset .hint code{display:inline-block;max-width:100%;direction:ltr;text-align:left;unicode-bidi:isolate;overflow-wrap:anywhere;padding:3px 7px;border-radius:var(--ui-radius-sm);background:var(--surface);border:1px solid var(--border)}.admin-shell h2{margin:26px 0 8px;font-size:1.2rem;line-height:1.6}.admin-shell hr{border:0;border-top:1px solid var(--border);margin:26px 0}.hint{font-size:.875rem;line-height:1.85;color:var(--muted);max-width:78ch}.notice{padding:13px 15px;border-radius:var(--ui-radius-md);margin:14px 0;line-height:1.75;overflow-wrap:anywhere}.ok{background:var(--success-bg,#44d6a018);border:1px solid var(--success-border,#44d6a044)}.err{background:var(--danger-bg,#ff718018);border:1px solid var(--danger-border,#ff718044)}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:24px}.actions .button{min-height:var(--ui-control-height);display:inline-flex;align-items:center;justify-content:center}@media(max-width:560px){.admin-shell{width:min(100% - 24px,1040px)}.admin-top{padding:18px 0}.panel-card{padding:18px}.actions{display:grid;grid-template-columns:1fr}.actions .button{width:100%}}@media(prefers-reduced-motion:reduce){.admin-shell *{scroll-behavior:auto!important;transition-duration:.01ms!important;animation-duration:.01ms!important;animation-iteration-count:1!important}}
</style>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</head>
<body>
<main class="admin-shell">
<?php require __DIR__ . '/_navigation.php'; ?>
<section class="panel panel-card settings-section">
<div class="eyebrow" data-ui-text="settings_title">تنظیمات فروشگاه</div><h1 data-ui-text="settings_title">تنظیمات فروشگاه</h1>
<?php if ($message !== ''): ?><div class="notice ok" role="status" aria-live="polite"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err" role="alert" aria-live="assertive"><?= e($error) ?></div><?php endif; ?>
</section>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
<section class="panel panel-card settings-section">
<h2 data-ui-text="settings_support_title">راه‌های پشتیبانی</h2>
<p class="hint" data-ui-text="settings_support_intro">هر راه ارتباطی اختیاری است. گزینه‌ای که خالی بماند در فروشگاه نمایش داده نمی‌شود.</p>
<div class="field"><label for="telegram_id" data-ui-text="settings_telegram_id">شناسه Telegram</label><input id="telegram_id" name="telegram_id" value="<?= e($values['support_telegram_id']) ?>" maxlength="32" placeholder="شناسه یا شماره تلگرام" data-ui-placeholder="settings_telegram_placeholder" autocomplete="off"><div class="hint" data-ui-text="settings_telegram_hint">فقط شناسه را وارد کنید، بدون @ یا آدرس سایت. لینک t.me به‌صورت خودکار ساخته می‌شود.</div></div>
<div class="field"><label for="whatsapp_target" data-ui-text="settings_whatsapp_target">شناسه یا مقصد WhatsApp</label><input id="whatsapp_target" name="whatsapp_target" value="<?= e($values['support_whatsapp_target']) ?>" maxlength="80" placeholder="شناسه یا شماره واتساپ" data-ui-placeholder="settings_whatsapp_placeholder" autocomplete="off"><div class="hint" data-ui-text="settings_whatsapp_hint">فقط مقصد را وارد کنید؛ دامنه و URL لازم نیست. این فیلد محدود به قالب شماره تلفن نیست.</div></div>
<div class="field"><label for="custom_label" data-ui-text="settings_custom_link_label">عنوان لینک دلخواه</label><input id="custom_label" name="custom_label" value="<?= e($values['support_custom_label']) ?>" maxlength="60" placeholder="مثلاً پشتیبانی آنلاین" data-ui-placeholder="settings_custom_label_placeholder"></div>
<div class="field"><label for="custom_url" data-ui-text="settings_custom_link_url">نشانی کامل لینک دلخواه</label><input id="custom_url" name="custom_url" type="url" value="<?= e($values['support_custom_url']) ?>" placeholder="https://example.com/contact" autocomplete="url"><div class="hint" data-ui-text="settings_custom_link_hint">برای پیام‌رسان یا سرویس دیگر؛ فقط لینک HTTPS معتبر پذیرفته می‌شود.</div></div>
</section>
<section class="panel panel-card settings-section">
<h2 data-ui-text="settings_price_unit_title">واحد نمایش قیمت</h2>
<p class="hint" data-ui-text="settings_price_unit_hint">قیمت پایه در دیتابیس و snapshot سفارش تغییر نمی‌کند. فقط نمایش مبلغ بین تومان و ریال تبدیل می‌شود؛ ۱ تومان = ۱۰ ریال. مقدار پیش‌فرض نصب تازه تومان است.</p>
<div class="field"><label for="price_display_unit" data-ui-text="settings_display_unit">واحد نمایش</label><select id="price_display_unit" name="price_display_unit" class="form-control"><option value="IRT"<?= $priceDisplayUnit === 'IRT' ? ' selected' : '' ?> data-ui-text="settings_toman">تومان</option><option value="IRR"<?= $priceDisplayUnit === 'IRR' ? ' selected' : '' ?> data-ui-text="settings_rial">ریال</option></select></div>
</section>
<section class="panel panel-card settings-section">
<h2 data-ui-text="settings_payment_title">درگاه پرداخت</h2>
<p class="hint" data-ui-text="settings_payment_intro">درگاه‌ها از ماژول‌های ثبت‌شده در سیستم دریافت می‌شوند؛ هیچ ارائه‌دهنده‌ای به‌صورت پیش‌فرض فعال نیست. تغییر درگاه روی تراکنش‌های قبلی اثر نمی‌گذارد.</p>
<p class="hint" data-ui-text="settings_multi_gateway_intro">هر تعداد درگاه که پیکربندی معتبر دارند می‌توانند هم‌زمان فعال باشند. مشتری هنگام ثبت سفارش یکی از درگاه‌های فعال را انتخاب می‌کند.</p>
<div class="gateway-grid"><?php foreach ($gatewayFields as $gatewayCode => $fields): ?><fieldset class="panel-card"><legend><?= e(ucfirst($gatewayCode)) ?></legend><div class="field"><label><input type="checkbox" name="enabled_providers[]" value="<?= e($gatewayCode) ?>"<?= in_array($gatewayCode, $enabledProviders, true) ? ' checked' : '' ?>> <span data-ui-text="settings_gateway_enable">فعال‌سازی این درگاه</span></label></div><div class="field"><label for="gateway_<?= e($gatewayCode) ?>_type" data-ui-text="settings_currency_type">نوع ارز درگاه</label><select id="gateway_<?= e($gatewayCode) ?>_type" name="gateway_<?= e($gatewayCode) ?>_type" class="form-control"><?php if (array_intersect(['IRR', 'IRT'], $gatewaySupportedCurrencies[$gatewayCode] ?? []) !== []): ?><option value="fiat"<?= ($gatewayPaymentTypes[$gatewayCode] ?? 'fiat') === 'fiat' ? ' selected' : '' ?> data-ui-text="settings_fiat_type">ریالی / تومانی</option><?php endif; ?><?php if (in_array('USDT', $gatewaySupportedCurrencies[$gatewayCode] ?? [], true)): ?><option value="crypto"<?= ($gatewayPaymentTypes[$gatewayCode] ?? 'fiat') === 'crypto' ? ' selected' : '' ?> data-ui-text="settings_crypto_type">کریپتو (USDT)</option><?php endif; ?></select><div class="hint" data-ui-text="settings_currency_type_hint">این گزینه فقط نوع ارز و قیمت ارسالی به همین درگاه را تعیین می‌کند. سازگاری نوع انتخاب‌شده با درگاه باید رعایت شود.</div></div><?php foreach ($fields as $fieldKey => $field): $inputName = 'gateway_' . $gatewayCode . '_' . $fieldKey; ?>
<?php if (($field['type'] ?? '') === 'checkbox'): ?>
<div class="field"><label for="<?= e($inputName) ?>"><input id="<?= e($inputName) ?>" name="<?= e($inputName) ?>" type="checkbox" value="1"<?= ($gatewayValues[$gatewayCode][$fieldKey] ?? '0') === '1' ? ' checked' : '' ?>> <span<?php if ($gatewayCode === 'zarinpal' && $fieldKey === 'sandbox_mode'): ?> data-ui-text="settings_zarinpal_sandbox_mode"<?php endif; ?>><?= e((string)($field['label'] ?? $fieldKey)) ?></span></label></div>
<?php if ($gatewayCode === 'zarinpal' && $fieldKey === 'sandbox_mode'): ?><p class="hint" data-ui-text="settings_zarinpal_sandbox_hint">در حالت آزمایشی، درخواست و تأیید پرداخت به محیط Sandbox زرین‌پال ارسال می‌شود و مبلغ واقعی دریافت نمی‌شود. پس از تأیید آزمایشی، فعال‌سازی اکانت روی سرورهای واقعی IBSng انجام خواهد شد.</p><?php endif; ?>
<?php else: ?>
<div class="field"><label for="<?= e($inputName) ?>"><?= e((string)($field['label'] ?? $fieldKey)) ?></label><input id="<?= e($inputName) ?>" name="<?= e($inputName) ?>" type="<?= !empty($field['secret']) ? 'password' : 'text' ?>" value="<?= e((string)($gatewayValues[$gatewayCode][$fieldKey] ?? '')) ?>" maxlength="2048" autocomplete="off"<?= $gatewayCode === 'zarinpal' && $fieldKey === 'sandbox_merchant_id' ? ' readonly dir="ltr"' : '' ?>></div>
<?php endif; ?>
<?php endforeach; ?><?php if ($gatewayCode === 'ircrypto'): ?><div class="hint"><strong data-ui-text="settings_webhook_title">راهنمای Webhook:</strong> <span data-ui-text="settings_webhook_intro">این نشانی را در پنل IRCrypto به‌عنوان Webhook ثبت کنید:</span> <code><?= e(app_url('webhook/ircrypto.php')) ?></code>. <span data-ui-text="settings_webhook_validation">آدرس بر اساس دامنه و مسیر نصب تنظیم‌شده فروشگاه ساخته می‌شود. endpoint امضای درخواست را بررسی می‌کند و وضعیت، مبلغ و ارز فاکتور را از سمت سرور اعتبارسنجی می‌کند.</span></div><?php endif; ?></fieldset><?php endforeach; ?></div>
</section>
<section class="panel panel-card settings-section">
<h2 data-ui-text="settings_crypto_display_title">نمایش قیمت کریپتو</h2>
<p class="hint" data-ui-text="settings_crypto_display_hint">این گزینه نمایش قیمت مستقل USDT را در فروشگاه مجاز می‌کند؛ اما قیمت فقط زمانی نمایش داده می‌شود که دست‌کم یک درگاه فعال و پیکربندی‌شده از USDT پشتیبانی کند و حالت پرداخت آن روی USDT باشد. این گزینه به‌تنهایی پرداخت کریپتویی را فعال نمی‌کند.</p>
<div class="field"><label><input type="checkbox" name="crypto_gateway_enabled" value="1"<?= $cryptoDisplayEnabled ? ' checked' : '' ?>> <span data-ui-text="settings_show_usdt">نمایش قیمت USDT (این گزینه پرداخت کریپتو را فعال نمی‌کند)</span></label></div>
</section>
<section class="panel panel-card settings-section">
<h2 data-ui-text="settings_credentials_title">روش ساخت اطلاعات اکانت</h2>
<p class="hint" data-ui-text="settings_credentials_hint">این سیاست به‌صورت سراسری برای سفارش‌های جدید اعمال می‌شود. سفارش‌های قبلی و اطلاعات ثبت‌شده در آن‌ها تغییر نمی‌کنند.</p>
<div class="field"><label for="credential_mode" data-ui-text="settings_credential_method">روش ورود اطلاعات</label><select id="credential_mode" name="credential_mode" class="form-control"><option value="customer"<?= $credentialValues['credential_mode'] === 'customer' ? ' selected' : '' ?> data-ui-text="settings_customer_credentials">مشتری نام کاربری و رمز را وارد کند</option><option value="automatic"<?= $credentialValues['credential_mode'] === 'automatic' ? ' selected' : '' ?> data-ui-text="settings_auto_credentials">تولید خودکار توسط فروشگاه</option><option value="choice"<?= $credentialValues['credential_mode'] === 'choice' ? ' selected' : '' ?> data-ui-text="settings_choose_credentials">مشتری بین خودکار و دستی انتخاب کند</option></select></div>
<div class="field"><label for="username_prefix" data-ui-text="settings_username_prefix">پیشوند نام کاربری</label><input id="username_prefix" name="username_prefix" maxlength="24" pattern="[A-Za-z0-9_.-]+" required value="<?= e($credentialValues['username_prefix']) ?>"><div class="hint" data-ui-text="settings_username_prefix_hint">فقط حروف لاتین، عدد و . _ -؛ برای مثال atd</div></div>
<div class="field"><label for="username_suffix_length" data-ui-text="settings_username_suffix">طول بخش تصادفی نام کاربری</label><input id="username_suffix_length" name="username_suffix_length" type="number" min="4" max="12" required value="<?= e($credentialValues['username_suffix_length']) ?>"></div>
<div class="field"><label for="password_length" data-ui-text="settings_password_length">طول رمز تولیدشده</label><input id="password_length" name="password_length" type="number" min="4" max="11" required value="<?= e($credentialValues['password_length']) ?>"></div>
<div class="field"><label for="password_charset" data-ui-text="settings_password_charset">ترکیب کاراکتر رمز</label><select id="password_charset" name="password_charset" class="form-control"><option value="digits"<?= $credentialValues['password_charset'] === 'digits' ? ' selected' : '' ?> data-ui-text="settings_charset_digits">فقط اعداد</option><option value="letters"<?= $credentialValues['password_charset'] === 'letters' ? ' selected' : '' ?> data-ui-text="settings_charset_letters">فقط حروف</option><option value="alphanumeric"<?= $credentialValues['password_charset'] === 'alphanumeric' ? ' selected' : '' ?> data-ui-text="settings_charset_alnum">ترکیب حروف و اعداد</option></select></div>
</section>
<section class="panel panel-card settings-section">
<h2 data-ui-text="settings_renewal_gift_title">تنظیمات هدیه تمدید اکانت</h2>
<p class="hint" data-ui-text="settings_renewal_gift_hint">روزهای هدیه برای اولین تمدید موفق و تمدیدهای بعدی جداگانه تنظیم می‌شوند. مقدار پیش‌فرض مطابق مرجع: ۱ روز برای اولین و ۵ روز برای تمدیدهای بعدی.</p>
<div class="field"><label for="ibsng_gift_days_first" data-ui-text="settings_gift_first">هدیهٔ اولین تمدید (روز)</label><input id="ibsng_gift_days_first" name="ibsng_gift_days_first" type="number" min="0" max="3650" required value="<?= e($renewalValues['ibsng_gift_days_first']) ?>"></div>
<div class="field"><label for="ibsng_gift_days_repeat" data-ui-text="settings_gift_repeat">هدیهٔ تمدیدهای بعدی (روز)</label><input id="ibsng_gift_days_repeat" name="ibsng_gift_days_repeat" type="number" min="0" max="3650" required value="<?= e($renewalValues['ibsng_gift_days_repeat']) ?>"></div>
</section>
<div class="actions"><button class="button primary" type="submit" data-ui-text="settings_save">ذخیره تنظیمات</button><a class="button" href="<?= e(app_url('/')) ?>" target="_blank" rel="noopener noreferrer" data-ui-text="settings_visit_store">مشاهده فروشگاه</a></div>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
</main>
</body>
</html>
