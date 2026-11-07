<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/messages.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Crypto.php';
require_once __DIR__ . '/app/IBSngClient.php';
require_once __DIR__ . '/app/IBSngRenewalState.php';
require_once __DIR__ . '/app/StorefrontView.php';

$action = (string)($_POST['account_action'] ?? 'lookup');
if (empty($_SESSION['customer_account_csrf'])) {
    $_SESSION['customer_account_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['customer_account_csrf'];
$error = '';
$success = (string)($_SESSION['customer_account_flash'] ?? '');
unset($_SESSION['customer_account_flash']);
$account = null;
$username = trim((string)($_POST['username'] ?? ($_SESSION['customer_account_auth']['username'] ?? '')));
$accountPassword = (string)($_POST['account_password'] ?? '');
$auth = $_SESSION['customer_account_auth'] ?? null;

if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
        $error = app_message('customer_account_invalid_request');
    } else {
        unset($_SESSION['customer_account_auth']);
        $auth = null;
        $username = '';
        $account = null;
    }
}

if (empty($_SESSION['account_lookup_captcha']) || (int)($_SESSION['account_lookup_captcha_exp'] ?? 0) < time()) {
    $_SESSION['account_lookup_captcha'] = (string)random_int(1000, 9999);
    $_SESSION['account_lookup_captcha_exp'] = time() + 600;
}
$captcha = (string)$_SESSION['account_lookup_captcha'];

$loadAccountDetails = static function (PDO $pdo, string $username, array $serverIds): array {
    $serverIds = array_values(array_unique(array_filter(array_map('intval', $serverIds), static fn($id) => $id > 0)));
    if ($serverIds === []) return [];
    $marks = implode(',', array_fill(0, count($serverIds), '?'));
    $stmt = $pdo->prepare("SELECT id,name,service_name,host,port,utc_offset,username,password_ciphertext FROM ibsng_servers WHERE is_active=1 AND id IN ($marks) ORDER BY is_default DESC,id DESC");
    $stmt->execute($serverIds);
    $accounts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $server) {
        $client = new IBSngClient((string)$server['host'], (int)$server['port'], (string)$server['username'], app_decrypt_secret((string)$server['password_ciphertext']));
        $credentials = $client->getCredentials($username);
        $state = IBSngRenewalState::fromClient($client, $username, isset($server['utc_offset']) ? (string)$server['utc_offset'] : null);
        // Presentation-only extraction of the Multi Login attribute from IBSng user-info HTML.
        $multiLogin = '';
        $accountHtml = (string)($credentials['html'] ?? '');
        if ($accountHtml !== '' && preg_match('/<tr\\b[^>]*>(?:(?!<tr\\b).)*attr_edit_checkbox_[^>]*value=["\\'](?:multi_login|multi_login_instances|multi_login_instance)["\\'](?:(?!<tr\\b).)*<\\/tr>/is', $accountHtml, $multiLoginRow)) {
            if (preg_match('/<td\\b[^>]*class=["\\'][^"\\']*Form_Content_Row_Right[^"\\']*["\\'][^>]*>(.*?)<\\/td>/is', $multiLoginRow[0], $multiLoginCell)) {
                $cellHtml = $multiLoginCell[1];
                if (preg_match('/<option\\b[^>]*selected[^>]*>(.*?)<\\/option>/is', $cellHtml, $selectedOption)) {
                    $multiLogin = trim(html_entity_decode(strip_tags($selectedOption[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                } elseif (preg_match('/<input\\b[^>]*value=["\\']([^"\\']*)["\\'][^>]*>/is', $cellHtml, $inputValue)) {
                    $multiLogin = trim(html_entity_decode($inputValue[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                } else {
                    $multiLogin = trim(html_entity_decode(strip_tags($cellHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }
            }
        }
        if ($multiLogin === '' || preg_match('/^-+$/u', $multiLogin)) $multiLogin = 'نامشخص';
        $groupName = trim((string)($credentials['group'] ?? ''));
        $planStmt = $pdo->prepare('SELECT plan_name FROM ibsng_groups WHERE server_id=? AND group_name=? AND is_active=1 LIMIT 1');
        $planStmt->execute([(int)$server['id'], $groupName]);
        $planName = (string)($planStmt->fetchColumn() ?: '');
        $accounts[] = [
            'server_id' => (int)$server['id'],
            'server' => (string)($server['service_name'] ?: $server['name']),
            'username' => $username,
            'plan' => $planName !== '' ? $planName : 'نامشخص',
            'multi_login' => $multiLogin,
            'expiry' => (string)($state['nearest_exp_date'] ?? ''),
            'remaining' => (int)($state['remaining_days'] ?? 0),
            'expired' => (bool)($state['expired'] ?? false),
            'password' => (string)($credentials['password'] ?? ''),
        ];
    }
    return $accounts;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'lookup') {
    $provided = trim((string)($_POST['captcha'] ?? ''));
    if (!hash_equals($captcha, $provided)) {
        $error = app_message('customer_account_captcha_invalid');
    } elseif ($username === '' || strlen($username) > 128 || $accountPassword === '') {
        $error = app_message('customer_account_credentials_required');
    } else {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->query("SELECT id,name,service_name,host,port,username,password_ciphertext FROM ibsng_servers WHERE is_active=1 ORDER BY is_default DESC,id DESC");
            $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$servers) throw new RuntimeException('customer_account_servers_unavailable');
            $matchedIds = [];
            foreach ($servers as $server) {
                $client = new IBSngClient((string)$server['host'], (int)$server['port'], (string)$server['username'], app_decrypt_secret((string)$server['password_ciphertext']));
                try {
                    $credentials = $client->getCredentials($username);
                    if (hash_equals((string)($credentials['password'] ?? ''), $accountPassword)) {
                        $matchedIds[] = (int)$server['id'];
                    }
                } catch (RuntimeException $e) {
                    if (str_contains($e->getMessage(), 'وجود ندارد')) continue;
                    throw $e;
                }
            }
            if ($matchedIds === []) throw new RuntimeException('customer_account_not_found');
            $_SESSION['customer_account_auth'] = ['username' => $username, 'server_ids' => $matchedIds, 'created_at' => time()];
            $auth = $_SESSION['customer_account_auth'];
            $account = $loadAccountDetails($pdo, $username, $matchedIds);
            unset($_SESSION['account_lookup_captcha'], $_SESSION['account_lookup_captcha_exp']);
        } catch (Throwable $e) {
            $error = app_message($e->getMessage() && isset($GLOBALS['app_message_catalog']['fa'][$e->getMessage()]) ? $e->getMessage() : 'customer_account_lookup_failed');
        }
    }
    $_SESSION['account_lookup_captcha'] = (string)random_int(1000, 9999);
    $_SESSION['account_lookup_captcha_exp'] = time() + 600;
    $captcha = (string)$_SESSION['account_lookup_captcha'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'change_password') {
    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'درخواست معتبر نیست. صفحه را تازه‌سازی کنید.';
    } elseif (!is_array($auth) || !in_array((int)($_POST['server_id'] ?? 0), array_map('intval', (array)($auth['server_ids'] ?? [])), true)) {
        $error = app_message('customer_account_session_invalid');
    } else {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $error = app_message('customer_account_password_fields_required');
        } elseif (!hash_equals($newPassword, $confirmPassword)) {
            $error = app_message('customer_account_password_mismatch');
        } elseif (strlen($newPassword) > 128) {
            $error = app_message('customer_account_password_too_long');
        } else {
            try {
                $serverId = (int)$_POST['server_id'];
                $pdo = Database::connection();
                $stmt = $pdo->prepare("SELECT id,name,service_name,host,port,username,password_ciphertext FROM ibsng_servers WHERE is_active=1 AND id=? LIMIT 1");
                $stmt->execute([$serverId]);
                $server = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$server) throw new RuntimeException('customer_account_server_unavailable');
                $client = new IBSngClient((string)$server['host'], (int)$server['port'], (string)$server['username'], app_decrypt_secret((string)$server['password_ciphertext']));
                $credentials = $client->getCredentials((string)$auth['username']);
                if (!hash_equals((string)($credentials['password'] ?? ''), $currentPassword)) {
                    throw new RuntimeException('customer_account_current_password_invalid');
                }
                $client->setUserPassword((string)$auth['username'], $newPassword);
                $_SESSION['customer_account_flash'] = app_message('customer_account_password_changed');
                unset($_SESSION['customer_account_auth']);
                header('Location: ' . app_url('customer-account.php'), true, 303);
                exit;
            } catch (Throwable $e) {
                $error = app_message($e->getMessage() && isset($GLOBALS['app_message_catalog']['fa'][$e->getMessage()]) ? $e->getMessage() : 'customer_account_password_change_failed');
            }
        }
    }
}

if (is_array($auth) && $account === null && $action !== 'logout') {
    try {
        $pdo = Database::connection();
        $account = $loadAccountDetails($pdo, (string)$auth['username'], (array)$auth['server_ids']);
        if ($account === []) {
            unset($_SESSION['customer_account_auth']);
            $auth = null;
            $username = '';
            $error = app_message('customer_account_account_unavailable');
        }
    } catch (Throwable $e) {
        $error = app_message('customer_account_account_unavailable');
        $account = null;
    }
}
$isLoggedIn = is_array($auth) && is_array($account) && $account !== [];
$config = app_config();
$title = (string)($config['site_title'] ?? 'ATD IBSng Store');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/svg+xml" href="<?= e(app_asset_url('assets/icons/atd-app-icon.svg')) ?>"><link rel="apple-touch-icon" sizes="180x180" href="<?= e(app_asset_url('assets/icons/apple-touch-icon.png')) ?>"><link rel="manifest" href="<?= e(app_asset_url('site.webmanifest')) ?>">
<title data-ui-title-key="title_customer_account">پنل حساب | <?= e($title) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.account-shell{width:min(900px,calc(100% - 28px));margin:0 auto;padding:clamp(16px,3vw,28px) 0}
.account-card{padding:clamp(18px,4vw,30px);margin-top:20px;min-width:0}
.account-card h1,.account-card h2{line-height:1.5;overflow-wrap:anywhere}
.account-card h1[data-ui-text="customer_view_account"]{font-size:clamp(1.65rem,3.2vw,2.25rem);line-height:1.45}
.account-form{display:grid;grid-template-columns:minmax(0,1fr) minmax(150px,auto);gap:14px;align-items:end}
.account-fields{display:grid;gap:12px;min-width:0}
.field{display:grid;gap:8px;min-width:0}
.field label{color:var(--muted);font-size:.92rem}
.field input{box-sizing:border-box;width:100%;min-height:46px;padding:12px 14px;border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);background:var(--control-bg,var(--surface-2));color:var(--text);font:inherit;transition:border-color .18s ease,box-shadow .18s ease}
.field input::placeholder{color:var(--muted);opacity:.8}
.field input:focus-visible{outline:0;border-color:var(--accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 22%,transparent)}
.captcha-row{display:flex;align-items:stretch;gap:10px;min-width:0}
.captcha-row input{flex:1;min-width:0}
.captcha-code{direction:ltr;text-align:center;font-weight:800;letter-spacing:4px;padding:10px 14px;border-radius:var(--ui-radius-md,14px);background:var(--surface-2);border:1px dashed var(--border);font-size:1.15rem;font-variant-numeric:tabular-nums;display:flex;align-items:center;justify-content:center;min-width:92px}
.account-form>.button{min-height:46px;white-space:normal}
.account-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,210px),1fr));gap:12px;margin-top:18px}
.account-item{padding:clamp(13px,2.5vw,17px);border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);background:var(--surface-2);min-width:0;transition:border-color .18s ease,background-color .18s ease}
.account-item small{display:block;color:var(--muted);margin-bottom:8px;line-height:1.6}
.account-item strong{overflow-wrap:anywhere;line-height:1.7}
.account-item strong[dir="ltr"],.password-value[dir="ltr"]{direction:ltr;unicode-bidi:isolate;text-align:left;display:inline-block;max-width:100%;font-variant-numeric:tabular-nums}
.field input[dir="ltr"]{direction:ltr;text-align:left;unicode-bidi:isolate}
.account-error{padding:13px 15px;border:1px solid color-mix(in srgb,var(--danger,var(--red)) 35%,var(--border));background:color-mix(in srgb,var(--danger,var(--red)) 9%,var(--surface));color:var(--text);border-radius:var(--ui-radius-md,12px);margin:15px 0;line-height:1.8;overflow-wrap:anywhere}
.password-line{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.password-line button{font:inherit;cursor:pointer;min-height:40px}
.password-line button:focus-visible,.account-form>.button:focus-visible,.account-shell .topbar nav .button:focus-visible{outline:2px solid var(--accent);outline-offset:3px;box-shadow:0 0 0 4px var(--ui-focus-ring)}
.account-logout-form{margin:0}.account-success{padding:13px 15px;border:1px solid color-mix(in srgb,var(--accent) 35%,var(--border));background:color-mix(in srgb,var(--accent) 9%,var(--surface));color:var(--text);border-radius:var(--ui-radius-md,12px);margin:15px 0;line-height:1.8}.account-actions-card{display:grid;gap:16px}.account-action-buttons{display:flex;flex-wrap:wrap;gap:10px}.password-change-form{padding-top:14px;border-top:1px solid var(--border)}.icon-button{width:42px;height:42px;padding:8px;flex:0 0 42px}.icon-button .ui-icon{width:21px;height:21px}.password-line{align-items:center}.password-line .button{display:inline-flex;align-items:center;justify-content:center}.account-shell .button{font:inherit;text-decoration:none}.account-shell .button.primary{background:var(--accent);color:var(--accent-foreground,#fff);border:1px solid color-mix(in srgb,var(--accent) 35%,var(--border));box-shadow:0 8px 22px color-mix(in srgb,var(--accent) 18%,transparent)}.account-shell .button.primary:hover{background:var(--accent-strong,var(--accent));filter:brightness(1.06)}.password-change-form[hidden]{display:none}@media(max-width:560px){.account-action-buttons{display:grid}.account-logout-form,.account-logout-form .button{width:100%}.account-action-buttons .button{width:100%}}
.account-shell .topbar nav{display:flex;flex-wrap:wrap;align-items:center;gap:10px;min-width:0}
.account-shell .topbar nav .button{white-space:normal;text-align:center}
.account-item strong{font-variant-numeric:tabular-nums}
@media(max-width:560px){.account-shell .topbar nav{width:100%}.account-shell .topbar nav .button{flex:1 1 auto}.password-line{align-items:flex-start}.password-line button{flex:0 0 auto}}
.muted-note{color:var(--muted);line-height:1.85}
@media(max-width:560px){.account-form{grid-template-columns:minmax(0,1fr)}.captcha-row{gap:8px}.account-form>.button{width:100%}}
@media(prefers-reduced-motion:reduce){.field input,.account-item{transition:none}}
</style>
</head>
<body>
<?php storefront_header($title, 'account'); ?>
<main class="account-shell">
<?php if ($success !== ''): ?><div class="account-success" role="status" aria-live="polite"><?= e($success) ?></div><?php endif; ?>
<?php if (!$isLoggedIn): ?>
<section class="panel account-card">
<div class="eyebrow" data-ui-text="customer_panel">پنل مشتری</div><h1 data-ui-text="customer_view_account">مشاهده مشخصات اکانت</h1>
<p class="muted-note" data-ui-text="customer_intro">نام کاربری و رمز عبور خود را وارد کنید. کد چهاررقمی فقط برای جلوگیری از ورود ربات‌هاست.</p>
<?php if ($error !== ''): ?><div class="account-error" role="alert" aria-live="assertive"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="account-form">
<input type="hidden" name="account_action" value="lookup"><div class="account-fields">
<div class="field"><label for="username" data-ui-text="customer_username">نام کاربری</label><input id="username" name="username" dir="ltr" value="<?= e($username) ?>" required autocomplete="username"></div>
<div class="field"><label for="account_password" data-ui-text="customer_password">رمز عبور</label><input id="account_password" name="account_password" type="password" dir="ltr" required autocomplete="current-password"></div>
<div class="field"><label for="captcha" data-ui-text="customer_captcha">کد چهاررقمی</label><div class="captcha-row"><span class="captcha-code" dir="ltr" aria-label="کد ضدربات" data-ui-aria="customer_captcha_aria"><?= e($captcha) ?></span><input id="captcha" name="captcha" dir="ltr" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="کد را وارد کنید" data-ui-placeholder="customer_captcha_placeholder" required></div></div>
</div><button class="button primary" type="submit" data-ui-text="customer_show_details">نمایش مشخصات</button>
</form>
</section>
<?php else: ?>
<?php if ($error !== ''): ?><div class="account-error" role="alert" aria-live="assertive"><?= e($error) ?></div><?php endif; ?>
<?php endif; ?>
<?php if (is_array($account)): ?>
<?php foreach ($account as $item): ?>
<section class="panel account-card">
<h2 data-ui-text="customer_account_details">مشخصات حساب</h2>
<div class="account-grid">
<div class="account-item"><small data-ui-text="customer_username">نام کاربری</small><strong dir="ltr"><?= e($item['username']) ?></strong></div>
<div class="account-item"><small data-ui-text="customer_service">سرویس</small><strong><?= e($item['server']) ?></strong></div>
<div class="account-item"><small data-ui-text="customer_plan">پلن</small><strong><?= e($item['plan']) ?></strong></div>
                <div class="account-item"><small data-fa="تعداد کاربران هم‌زمان" data-en="Simultaneous Users">تعداد کاربران هم‌زمان</small><strong><?php if ($item['multi_login'] === 'نامشخص'): ?><span data-fa="نامشخص" data-en="Unknown">نامشخص</span><?php elseif (preg_match('/^([0-9]+)\\s+instances?$/i', $item['multi_login'], $multiLoginMatch)): ?><span data-fa="<?= e(strtr($multiLoginMatch[1], '0123456789', '۰۱۲۳۴۵۶۷۸۹')) ?> کاربر" data-en="<?= e($multiLoginMatch[1]) ?> users"><?= e(strtr($multiLoginMatch[1], '0123456789', '۰۱۲۳۴۵۶۷۸۹')) ?> کاربر</span><?php else: ?><?= e($item['multi_login']) ?><?php endif; ?></strong></div>
<div class="account-item"><small data-ui-text="customer_validity">وضعیت اعتبار</small><strong data-ui-status="<?= $item['expired'] ? 'expired' : 'active' ?>"><?= $item['expired'] ? 'منقضی شده' : 'فعال' ?></strong></div>
<div class="account-item"><small data-ui-text="customer_expiry">تاریخ انقضا</small><strong><?php if ($item['expiry'] !== ''): ?><?= e($item['expiry']) ?><?php else: ?><span data-ui-text="customer_expiry_unknown">بدون تاریخ انقضای قابل نمایش</span><?php endif; ?></strong></div>
<div class="account-item"><small data-ui-text="customer_remaining">روزهای باقی‌مانده</small><strong><?= $item['expired'] ? '۰' : e((string)$item['remaining']) ?> <span data-ui-text="customer_days">روز</span></strong></div>
<div class="account-item"><small data-ui-text="customer_password_label">رمز عبور</small><div class="password-line"><strong dir="ltr" class="password-value" data-password="<?= e($item['password']) ?>">••••••••</strong><button type="button" class="button primary icon-button" data-ui-password-toggle aria-label="نمایش رمز" aria-pressed="false"><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
</div>
</section>
<section class="panel account-card account-actions-card">
<h2 data-fa="مدیریت حساب" data-en="Account management">مدیریت حساب</h2>
<div class="account-action-buttons">
<a class="button primary" href="<?= e(app_url('index.php?mode=renew&server_id=' . (int)$item['server_id'])) ?>" data-fa="تمدید سرویس" data-en="Renew service">تمدید سرویس</a>
<a class="button primary" href="<?= e(app_url('userguide.php')) ?>" data-fa="راهنمای استفاده از اکانت" data-en="Account usage guide">راهنمای استفاده از اکانت</a>
<button class="button primary" type="button" data-toggle-password-form="change-password-<?= (int)$item['server_id'] ?>" aria-expanded="false" data-fa="تغییر رمز عبور" data-en="Change password">تغییر رمز عبور</button>
<form method="post" class="account-logout-form"><input type="hidden" name="account_action" value="logout"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><button class="button primary" type="submit" data-fa="خروج از حساب" data-en="Log out">خروج از حساب</button></form>
</div>
<form method="post" class="account-fields password-change-form" id="change-password-<?= (int)$item['server_id'] ?>" hidden>
<input type="hidden" name="account_action" value="change_password"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="server_id" value="<?= (int)$item['server_id'] ?>">
<div class="field"><label for="current-password-<?= (int)$item['server_id'] ?>">رمز عبور فعلی</label><input id="current-password-<?= (int)$item['server_id'] ?>" name="current_password" type="password" dir="ltr" autocomplete="current-password" required></div>
<div class="field"><label for="new-password-<?= (int)$item['server_id'] ?>">رمز عبور جدید</label><input id="new-password-<?= (int)$item['server_id'] ?>" name="new_password" type="password" dir="ltr" autocomplete="new-password" maxlength="128" required></div>
<div class="field"><label for="confirm-password-<?= (int)$item['server_id'] ?>">تکرار رمز عبور جدید</label><input id="confirm-password-<?= (int)$item['server_id'] ?>" name="confirm_password" type="password" dir="ltr" autocomplete="new-password" maxlength="128" required></div>
<button class="button primary" type="submit" data-fa="ذخیره رمز عبور جدید" data-en="Save new password">ذخیره رمز عبور جدید</button>
</form>
</section>
<?php endforeach; ?>
<?php endif; ?>
<?php storefront_footer($title); ?>
</main>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</body></html>
