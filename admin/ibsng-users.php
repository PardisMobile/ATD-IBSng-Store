<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_once dirname(__DIR__) . '/app/Crypto.php';
require_once dirname(__DIR__) . '/app/IBSngClient.php';
require_once dirname(__DIR__) . '/app/IBSngRenewalCoordinator.php';
require_once dirname(__DIR__) . '/app/IBSngRenewalService.php';
require_admin();

$config = app_config();
$pdo = Database::connection();
$renewalColumnCheck = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
);
$renewalColumnCheck->execute(['ibsng_groups', 'validity_days']);
$renewalColumnReady = (int)$renewalColumnCheck->fetchColumn() === 1;
$renewalLedgerCheck = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = ?'
);
$renewalLedgerCheck->execute(['ibsng_renewals']);
$renewalLedgerReady = (int)$renewalLedgerCheck->fetchColumn() === 1;
$renewalSchemaReady = $renewalColumnReady && $renewalLedgerReady;
$message = '';
$error = '';
$userResult = null;
$credentialResult = null;
$renewalResult = null;
$renewalRequestKey = bin2hex(random_bytes(24));
$servers = $pdo->query('SELECT id,name,host,port,username,password_ciphertext FROM ibsng_servers WHERE is_active=1 ORDER BY is_default DESC,id')->fetchAll();
$serverIds = array_map(static fn(array $row): int => (int)$row['id'], $servers);
$selectedServerId = (int)($_GET['server_id'] ?? $_POST['server_id'] ?? ($servers[0]['id'] ?? 0));
if ($selectedServerId > 0 && !in_array($selectedServerId, $serverIds, true)) {
    $selectedServerId = (int)($servers[0]['id'] ?? 0);
}
$groups = [];
if ($selectedServerId > 0) {
    $stmt = $pdo->prepare('SELECT id,plan_name,group_name FROM ibsng_groups WHERE server_id=? AND is_active=1 ORDER BY plan_name');
    $stmt->execute([$selectedServerId]);
    $groups = $stmt->fetchAll();
    if ($renewalSchemaReady) {
        $renewalProductsStmt = $pdo->prepare(
            'SELECT p.id,p.name,g.validity_days,g.group_name
             FROM products p
             JOIN product_server_groups m ON m.product_id=p.id AND m.server_id=?
             JOIN ibsng_groups g ON g.id=m.ibsng_group_id AND g.server_id=m.server_id
             WHERE p.is_active=1 AND g.is_active=1 AND g.validity_days IS NOT NULL
             ORDER BY p.sort_order,p.name'
        );
        $renewalProductsStmt->execute([$selectedServerId]);
        $renewalProducts = $renewalProductsStmt->fetchAll();
    } else {
        $renewalProducts = [];
    }
} else {
    $renewalProducts = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        $error = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $username = trim((string)($_POST['username'] ?? ''));
        try {
            $server = null;
            foreach ($servers as $candidate) {
                if ((int)$candidate['id'] === $selectedServerId) {
                    $server = $candidate;
                    break;
                }
            }
            if (!$server) {
                throw new RuntimeException('ابتدا یک سرور فعال IBSng انتخاب کنید.');
            }
            $client = new IBSngClient(
                (string)$server['host'],
                (int)$server['port'],
                (string)$server['username'],
                app_decrypt_secret((string)$server['password_ciphertext'])
            );

            if ($action === 'renew') {
                if (!$renewalSchemaReady) {
                    throw new RuntimeException('ابتدا ارتقای ساختار تمدید IBSng را از بخش مربوطه انجام دهید.');
                }
                $renewalRequestKey = (string)($_POST['renewal_key'] ?? '');
                $productId = (int)($_POST['renewal_product_id'] ?? 0);
                if ($username === '' || $productId < 1
                    || strlen($renewalRequestKey) !== 48
                    || !ctype_xdigit($renewalRequestKey)) {
                    throw new InvalidArgumentException('نام کاربری، پلن تمدید و کلید درخواست معتبر الزامی است.');
                }
                $planStmt = $pdo->prepare(
                    'SELECT p.id,g.validity_days,g.group_name
                     FROM products p
                     JOIN product_server_groups m ON m.product_id=p.id AND m.server_id=?
                     JOIN ibsng_groups g ON g.id=m.ibsng_group_id AND g.server_id=m.server_id
                     WHERE p.id=? AND p.is_active=1 AND g.is_active=1
                       AND g.validity_days IS NOT NULL LIMIT 1'
                );
                $planStmt->execute([$selectedServerId, $productId]);
                $renewalPlan = $planStmt->fetch(PDO::FETCH_ASSOC);
                if (!is_array($renewalPlan) || (int)$renewalPlan['validity_days'] < 1) {
                    throw new InvalidArgumentException('پلن تمدید فعال و دارای مدت اعتبار مخصوص IBSng انتخاب نشده است.');
                }

                $giftStmt = $pdo->prepare(
                    "SELECT setting_key,setting_value FROM app_settings
                     WHERE setting_key IN ('ibsng_gift_days_first','ibsng_gift_days_repeat')"
                );
                $giftStmt->execute();
                $giftSettings = [];
                foreach ($giftStmt->fetchAll(PDO::FETCH_ASSOC) as $giftSetting) {
                    $giftSettings[(string)$giftSetting['setting_key']] = (int)$giftSetting['setting_value'];
                }
                $giftFirst = max(0, $giftSettings['ibsng_gift_days_first'] ?? 1);
                $giftRepeat = max(0, $giftSettings['ibsng_gift_days_repeat'] ?? 5);

                $coordinator = new IBSngRenewalCoordinator($pdo);
                $service = new IBSngRenewalService($pdo, $client, $coordinator);
                $renewalResult = $service->renew(
                    $selectedServerId,
                    $username,
                    (string)$renewalPlan['group_name'],
                    (int)$renewalPlan['validity_days'],
                    $giftFirst,
                    $giftRepeat,
                    $renewalRequestKey,
                    $productId,
                    'ATD IBSng Store admin renewal ' . $renewalRequestKey
                );
                $message = !empty($renewalResult['reused_existing'])
                    ? 'نتیجه تمدید قبلی از Ledger بازیابی شد؛ درخواست جدیدی به IBSng ارسال نشد.'
                    : 'تمدید از پاسخ Web Panel IBSng تأیید شد.';
                $renewalPayload = is_array($renewalResult['result'] ?? null) ? $renewalResult['result'] : [];
                $relativeAfter = isset($renewalPayload['relative_expiration_after'])
                    ? (int)$renewalPayload['relative_expiration_after']
                    : null;
                $userResult = [
                    'username' => $username,
                    'group' => (string)$renewalPlan['group_name'],
                    'summary' => $relativeAfter !== null
                        ? 'اعتبار جدید: ' . $relativeAfter . ' روز نسبی.'
                        : 'تمدید قبلی از Ledger تأیید شد.',
                ];
            } elseif ($action === 'lookup') {
                if ($username === '') {
                    throw new InvalidArgumentException('نام کاربری برای جست‌وجو الزامی است.');
                }
                // One Search Account action returns the complete management
                // record, including credentials; no separate credentials form.
                $userResult = $client->getCredentials($username);
            } elseif ($action === 'create') {
                $groupId = (int)($_POST['group_id'] ?? 0);
                $groupStmt = $pdo->prepare('SELECT group_name FROM ibsng_groups WHERE id=? AND server_id=? AND is_active=1');
                $groupStmt->execute([$groupId, $selectedServerId]);
                $groupName = (string)$groupStmt->fetchColumn();
                $password = (string)($_POST['new_password'] ?? '');
                $credit = trim((string)($_POST['credit'] ?? '0'));
                if ($groupName === '' || $username === '' || $password === '') {
                    throw new InvalidArgumentException('گروه، نام کاربری و رمز عبور الزامی هستند.');
                }
                if (!preg_match('/^\d+(?:\.\d+)?$/', $credit)) {
                    throw new InvalidArgumentException('Credit باید یک مقدار عددی معتبر باشد.');
                }
                $client->createUser($groupName, $username, $password, $credit);
                $message = 'درخواست ایجاد اکانت در IBSng با پاسخ موفق تأیید شد.';
                $userResult = ['username' => $username, 'group' => $groupName, 'summary' => 'اکانت در Web Panel ایجاد شد.'];
            } elseif ($action === 'change_group') {
                $groupId = (int)($_POST['group_id'] ?? 0);
                $groupStmt = $pdo->prepare('SELECT group_name FROM ibsng_groups WHERE id=? AND server_id=? AND is_active=1');
                $groupStmt->execute([$groupId, $selectedServerId]);
                $groupName = (string)$groupStmt->fetchColumn();
                if ($groupName === '' || $username === '') {
                    throw new InvalidArgumentException('کاربر و گروه مقصد را از گروه‌های فعال همین سرور انتخاب کنید.');
                }
                $client->changeUserGroup($username, $groupName);
                $message = 'درخواست تغییر گروه کاربر موجود از مسیر Edit User ارسال شد.';
                $userResult = ['username' => $username, 'group' => $groupName, 'summary' => 'تغییر گروه از Web Panel تأیید شد.'];
            } elseif ($action === 'delete') {
                if ($username === '' || (string)($_POST['confirm_delete'] ?? '') !== 'yes') {
                    throw new InvalidArgumentException('برای حذف اکانت، تأیید حذف الزامی است.');
                }
                $client->deleteUser($username, true, true);
                $message = 'پاسخ موفق حذف اکانت از IBSng دریافت شد.';
            } else {
                throw new RuntimeException('عملیات نامعتبر است.');
            }
        } catch (Throwable $e) {
            // Only deliberate, locally raised validation messages are safe to
            // show verbatim. IBSng/transport/runtime exceptions are mapped to
            // stable UI copy; never expose exception details or remote content.
            if ($e instanceof InvalidArgumentException) {
                $error = $e->getMessage();
            } else {
                $exceptionMessage = $e->getMessage();
                if (str_contains($exceptionMessage, 'این نام کاربری در IBSng وجود ندارد.')) {
                    $error = 'این نام کاربری در IBSng وجود ندارد.';
                } elseif (str_contains($exceptionMessage, 'از قبل در IBSng وجود دارد')
                    || str_contains($exceptionMessage, 'نام کاربری تکراری')) {
                    $error = 'این نام کاربری از قبل در IBSng وجود دارد.';
                } elseif (str_contains($exceptionMessage, 'نام کاربری یا رمز عبور مدیر را نپذیرفت')) {
                    $error = 'IBSng اطلاعات ورود مدیر را نپذیرفت؛ تنظیمات سرور را بررسی کنید.';
                } elseif (str_contains($exceptionMessage, 'تمدید را با پاسخ قابل‌تأیید تأیید نکرد')
                    || str_contains($exceptionMessage, 'برای بررسی ثبت')
                    || str_contains($exceptionMessage, 'نتیجه') && str_contains($exceptionMessage, 'نامشخص')) {
                    $error = 'نتیجه عملیات در IBSng قطعی نیست؛ وضعیت اکانت را بررسی کنید و عملیات را دوباره اجرا نکنید.';
                } else {
                    $error = 'عملیات IBSng انجام نشد؛ اتصال، تنظیمات سرور و وضعیت اکانت را بررسی کنید.';
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="<?= e(app_asset_url('assets/icons/atd-app-icon.svg')) ?>"><link rel="apple-touch-icon" sizes="180x180" href="<?= e(app_asset_url('assets/icons/apple-touch-icon.png')) ?>"><link rel="manifest" href="<?= e(app_asset_url('site.webmanifest')) ?>">
<title data-ui-title-key="title_admin_ibsng_users">مدیریت اکانت‌های IBSng | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.ibsng-users-page .admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto}.ibsng-users-page .admin-top{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:24px 0}.ibsng-users-page .panel-card{padding:24px;margin-top:20px}.ibsng-users-page .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 16px}.ibsng-users-page .field{display:grid;gap:8px;margin:14px 0}.ibsng-users-page .field label{color:var(--muted);font-size:.9rem}.ibsng-users-page .field input,.ibsng-users-page .field select{width:100%;min-width:0;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:var(--control-bg);color:var(--text);font:inherit;transition:border-color .18s,box-shadow .18s}.ibsng-users-page .field input:focus-visible,.ibsng-users-page .field select:focus-visible{outline:2px solid var(--accent);outline-offset:2px;border-color:var(--accent)}.ibsng-users-page .field input::placeholder{color:var(--muted)}.ibsng-users-page .field input[name=username],.ibsng-users-page .field input[name=new_password],.ibsng-users-page .field input[name=remote_user_id],.ibsng-users-page .field input[name=credit]{direction:ltr;text-align:left;unicode-bidi:isolate;font-variant-numeric:tabular-nums}.ibsng-users-page .notice{padding:13px 15px;border-radius:12px;margin:14px 0;line-height:1.8}.ibsng-users-page .ok{background:#44d6a018;border:1px solid #44d6a044}.ibsng-users-page .err{background:#ff718018;border:1px solid #ff718044}.ibsng-users-page .result{margin-top:16px;padding:18px;overflow-wrap:anywhere}.ibsng-users-page .result .technical-value{display:inline-block;max-width:100%;direction:ltr;text-align:left;unicode-bidi:isolate;font-variant-numeric:tabular-nums;overflow-wrap:anywhere}.ibsng-users-page .result code{display:inline-block;max-width:100%;direction:ltr;text-align:left;unicode-bidi:isolate;white-space:pre-wrap;overflow-wrap:anywhere;font-variant-numeric:tabular-nums}.ibsng-users-page .actions{display:flex;gap:10px;flex-wrap:wrap}ibsng-users-page .muted{color:var(--muted);line-height:1.8}@media(max-width:600px){.ibsng-users-page .grid{grid-template-columns:1fr}.ibsng-users-page .admin-top{align-items:flex-start;flex-direction:column}.ibsng-users-page .panel-card{padding:18px}}
.ibsng-users-page .account-result{display:grid;gap:16px}
.ibsng-users-page .account-result-title{display:block;font-size:1.05rem;line-height:1.6}
.ibsng-users-page .account-groups{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.ibsng-users-page .account-group{min-width:0;padding:16px;border:1px solid var(--border);border-radius:16px;background:var(--surface,transparent)}
.ibsng-users-page .account-group-title{margin:0 0 12px;font-size:.94rem;font-weight:700}
.ibsng-users-page .account-group-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.ibsng-users-page .account-item{display:grid;align-content:start;gap:7px;min-width:0;margin:0;padding:12px;border:1px solid var(--border);border-radius:12px;background:var(--control-bg)}
.ibsng-users-page .account-item-label{color:var(--muted);font-size:.82rem;line-height:1.5}
.ibsng-users-page .account-item .technical-value{min-width:0;font-size:.94rem;line-height:1.65;overflow-wrap:anywhere}
@media(max-width:760px){.ibsng-users-page .account-groups{grid-template-columns:1fr}}
@media(max-width:420px){.ibsng-users-page .account-group-fields{grid-template-columns:1fr}.ibsng-users-page .account-group{padding:13px}}

</style>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</head>
<body class="ibsng-users-page">
<main class="admin-shell">
<?php require __DIR__ . '/_navigation.php'; ?>
<section class="panel panel-card">
<div class="eyebrow">IBSng · User Management</div>
<h1 data-fa="مدیریت اکانت‌های IBSng" data-en="IBSng account management">مدیریت اکانت‌های IBSng</h1>
<p class="muted" data-fa="عملیات روی Web Panel سرور انتخاب‌شده انجام می‌شود. گروه انتخاب‌شده در فروشگاه باید دقیقاً با نام گروه IBSng مطابقت داشته باشد. تمدید از پلن فعال و مدت اعتبار مخصوص IBSng استفاده می‌کند؛ اگر نتیجهٔ تمدید نامشخص بماند، عملیات برای بررسی ثبت می‌شود و دوباره اجرا نخواهد شد." data-en="Operations run against the selected IBSng Web Panel. The group selected in the Store must exactly match the IBSng group name. Renewal uses the active plan's explicit IBSng validity. If the result is uncertain, the request is recorded for review and is not automatically retried.">عملیات روی Web Panel سرور انتخاب‌شده انجام می‌شود. گروه انتخاب‌شده در فروشگاه باید دقیقاً با نام گروه IBSng مطابقت داشته باشد. تمدید از پلن فعال و مدت اعتبار مخصوص IBSng استفاده می‌کند؛ اگر نتیجهٔ تمدید نامشخص بماند، عملیات برای بررسی ثبت می‌شود و دوباره اجرا نخواهد شد.</p>
<?php if ($message !== ''): ?><div class="notice ok" role="status" aria-live="polite"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err" role="alert" aria-live="assertive"><?= e($error) ?></div><?php endif; ?>
<?php if (!$servers): ?><div class="notice err">سرور فعال IBSng ثبت نشده است. ابتدا از صفحه سرورها یک سرور فعال اضافه کنید.</div><?php else: ?>
<form method="get">
<div class="field"><label for="server_id" data-fa="سرور IBSng" data-en="IBSng server">سرور IBSng</label><select id="server_id" name="server_id" onchange="this.form.submit()">
<?php foreach ($servers as $server): ?><option value="<?= (int)$server['id'] ?>"<?= (int)$server['id'] === $selectedServerId ? ' selected' : '' ?>><?= e((string)$server['name']) ?> · <?= e((string)$server['host']) ?></option><?php endforeach; ?>
</select></div>
</form>
<?php endif; ?>
</section>
<?php if ($selectedServerId > 0 && $servers): ?>
<section class="panel panel-card">
<h2 data-fa="تمدید اکانت موجود در IBSng" data-en="Renew an existing IBSng account">تمدید اکانت موجود در IBSng</h2>
<p class="muted" data-fa="نام کاربری اکانت موردنظر را وارد کنید و پلن تمدید را انتخاب کنید. مدت اعتبار و روزهای هدیه بر اساس تنظیمات فروشگاه و وضعیت اعتبار فعلی اکانت محاسبه می‌شوند. تاریخ انقضای مطلق اکانت (Absolute Expiration) تغییر نمی‌کند." data-en="Enter the account username and select a renewal plan. Validity and gift days are calculated based on Store settings and the account's current validity. The account's Absolute Expiration date does not change.">نام کاربری اکانت موردنظر را وارد کنید و پلن تمدید را انتخاب کنید. مدت اعتبار و روزهای هدیه بر اساس تنظیمات فروشگاه و وضعیت اعتبار فعلی اکانت محاسبه می‌شوند. تاریخ انقضای مطلق اکانت (Absolute Expiration) تغییر نمی‌کند.</p>
<?php if (!$renewalSchemaReady): ?><div class="notice err"><span data-fa="ساختار دیتابیس برای تمدید IBSng آماده نیست." data-en="The database schema for IBSng renewal is not ready.">ساختار دیتابیس برای تمدید IBSng آماده نیست.</span> <a href="<?= e(app_url('admin/upgrade.php')) ?>" data-fa="ارتقای دیتابیس را انجام دهید" data-en="Run the database upgrade">ارتقای دیتابیس را انجام دهید</a>.</div><?php elseif (!$renewalProducts): ?><div class="notice err" data-fa="برای این سرور پلن فعالی با مدت اعتبار IBSng تنظیم‌شده وجود ندارد. ابتدا مدت اعتبار را برای گروه IBSng در بخش تعریف پلن و گروه مشخص کنید." data-en="No active plan with an IBSng validity duration is configured for this server. Set the IBSng validity duration for the group in Plan & Group Setup.">برای این سرور پلن فعالی با مدت اعتبار IBSng تنظیم‌شده وجود ندارد. ابتدا مدت اعتبار را برای گروه IBSng در بخش تعریف پلن و گروه مشخص کنید.</div><?php else: ?>
<form method="post" data-confirm-fa="تمدید این اکانت در IBSng انجام شود؟ در صورت پاسخ نامشخص، تمدید خودکار تکرار نمی‌شود." data-confirm-en="Renew this account in IBSng? If the result is uncertain, the operation will not be automatically repeated.">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>"><input type="hidden" name="action" value="renew">
<input type="hidden" name="renewal_key" value="<?= e($renewalRequestKey) ?>">
<div class="grid">
<div class="field"><label for="renew_username" data-fa="نام کاربری اکانت موجود" data-en="Existing account username">نام کاربری اکانت موجود</label><input id="renew_username" name="username" required autocomplete="off" value="<?= e((string)($_POST['username'] ?? '')) ?>"></div>
<div class="field"><label for="renewal_product_id" data-fa="پلن تمدید" data-en="Renewal plan">پلن تمدید</label><select id="renewal_product_id" name="renewal_product_id" required><option value="" data-ui-text="users_select_plan">انتخاب پلن</option><?php foreach ($renewalProducts as $renewalProduct): ?><option value="<?= (int)$renewalProduct['id'] ?>"<?= (int)($_POST['renewal_product_id'] ?? 0)===(int)$renewalProduct['id']?' selected':'' ?>><?= e((string)$renewalProduct['name']) ?> · <?= (int)$renewalProduct['validity_days'] ?> <span data-ui-text="users_days_unit">روز</span> · <?= e((string)$renewalProduct['group_name']) ?></option><?php endforeach; ?></select></div>
</div>
<button class="button primary" type="submit" data-fa="تمدید اکانت در IBSng" data-en="Renew account in IBSng">تمدید اکانت در IBSng</button>
</form>
<?php endif; ?>
</section>
<section class="panel panel-card">
<h2 data-fa="جست‌وجو و دریافت اطلاعات اکانت" data-en="Lookup and account details">جست‌وجو و دریافت اطلاعات اکانت</h2>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>"><input type="hidden" name="action" value="lookup">
<div class="field"><label for="lookup_username" data-fa="نام کاربری اکانت" data-en="Account username">نام کاربری اکانت</label><input id="lookup_username" name="username" required autocomplete="off" value="<?= e((string)($_POST['username'] ?? '')) ?>"></div>
<div class="actions"><button class="button primary" type="submit" data-fa="جست‌وجوی اکانت" data-en="Search account">جست‌وجوی اکانت</button></div>
</form>
<?php if (is_array($userResult)): ?>
<?php

$accountHtml = (string)($userResult['html'] ?? '');
$cleanAccountText = static function (string $value): string {
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
};
/* Pure-PHP IBSng A1.24 HTML parser, transferred from the supplied working test file. */
$ibsngHtmlAttrs = static function (string $tag): array {
    $attrs = [];
    if (preg_match_all('/([a-zA-Z_:][a-zA-Z0-9_:.-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/s', $tag, $m, PREG_SET_ORDER)) {
        foreach ($m as $a) {
            $attrs[strtolower($a[1])] = html_entity_decode($a[2] !== '' ? $a[2] : ($a[3] !== '' ? $a[3] : ($a[4] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    return $attrs;
};
$ibsngTagTokens = static function (string $html): array {
    preg_match_all('/<!--.*?-->|<![^>]*>|<\?[^>]*\?>|<\/?[a-zA-Z][^>]*>/s', $html, $m, PREG_OFFSET_CAPTURE);
    return $m[0] ?? [];
};
$ibsngElementBlocks = static function (string $html, string $wantedTag) use (&$ibsngElementBlocks, $ibsngTagTokens, $ibsngHtmlAttrs): array {
    $tokens = $ibsngTagTokens($html); $stack = []; $blocks = [];
    foreach ($tokens as [$token, $offset]) {
        if (!preg_match('/^<\s*(\/?)\s*([a-zA-Z0-9]+)/', $token, $m)) continue;
        $closing = $m[1] === '/'; $tag = strtolower($m[2]);
        if ($closing) {
            for ($i = count($stack) - 1; $i >= 0; $i--) {
                if ($stack[$i]['tag'] === $tag) {
                    $opened = $stack[$i];
                    if ($tag === strtolower($wantedTag)) {
                        $blocks[] = ['attrs'=>$opened['attrs'],'inner'=>substr($html,$opened['inner_start'],$offset-$opened['inner_start']),
                            'outer'=>substr($html,$opened['start'],($offset+strlen($token))-$opened['start'])];
                    }
                    $stack = array_slice($stack, 0, $i); break;
                }
            }
            continue;
        }
        $attrs = $ibsngHtmlAttrs($token);
        $void = preg_match('/\/\s*>$/', $token) || in_array($tag, ['area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr'], true);
        if (!$void) $stack[] = ['tag'=>$tag,'attrs'=>$attrs,'start'=>$offset,'inner_start'=>$offset+strlen($token)];
    }
    return $blocks;
};
$ibsngDirectCells = static function (string $rowHtml) use ($ibsngTagTokens, $ibsngHtmlAttrs): array {
    $tokens = $ibsngTagTokens($rowHtml); $stack = []; $cells = [];
    foreach ($tokens as [$token, $offset]) {
        if (!preg_match('/^<\s*(\/?)\s*([a-zA-Z0-9]+)/', $token, $m)) continue;
        $closing = $m[1] === '/'; $tag = strtolower($m[2]);
        if ($closing) {
            for ($i=count($stack)-1; $i>=0; $i--) {
                if ($stack[$i]['tag'] === $tag) {
                    $opened=$stack[$i];
                    if ($tag === 'td' && $opened['direct']) {
                        $cells[]=['attrs'=>$opened['attrs'],'html'=>substr($rowHtml,$opened['inner_start'],$offset-$opened['inner_start'])];
                    }
                    $stack=array_slice($stack,0,$i); break;
                }
            }
            continue;
        }
        $attrs=$ibsngHtmlAttrs($token); $nearestTr=null;
        foreach (array_reverse($stack) as $ancestor) if ($ancestor['tag']==='tr') { $nearestTr=$ancestor; break; }
        $direct=$tag==='td' && $nearestTr!==null && (($nearestTr['depth']??0)+1)===count($stack);
        $void=preg_match('/\/\s*>$/',$token)||in_array($tag,['area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr'],true);
        if (!$void) $stack[]=['tag'=>$tag,'attrs'=>$attrs,'inner_start'=>$offset+strlen($token),'direct'=>$direct,'depth'=>count($stack)];
    }
    return $cells;
};
$ibsngExtractControl = static function (string $cellHtml) use ($cleanAccountText, $ibsngHtmlAttrs): ?string {
    if (preg_match('/<textarea\b[^>]*>(.*?)<\/textarea\s*>/is',$cellHtml,$m)) return $cleanAccountText($m[1]);
    if (preg_match('/<select\b[^>]*>(.*?)<\/select\s*>/is',$cellHtml,$m)) {
        if (preg_match('/<option\b[^>]*\bselected\b[^>]*>(.*?)<\/option\s*>/is',$m[1],$o)) return $cleanAccountText($o[1]);
        if (preg_match('/<option\b[^>]*>(.*?)<\/option\s*>/is',$m[1],$o)) return $cleanAccountText($o[1]);
        return $cleanAccountText($m[1]);
    }
    if (preg_match('/<input\b(?![^>]*\btype\s*=\s*["\']?(?:checkbox|hidden))[^>]*>/is',$cellHtml,$m)) {
        $a=$ibsngHtmlAttrs($m[0]); return isset($a['value']) ? trim($a['value']) : '';
    }
    return $cleanAccountText($cellHtml);
};
$ibsngDisplayedValue = static function (string $html, array $labels) use ($ibsngElementBlocks, $ibsngDirectCells, $cleanAccountText, $ibsngExtractControl): ?string {
    foreach ($ibsngElementBlocks($html,'tr') as $row) {
        $cells=$ibsngDirectCells($row['outer']);
        for ($i=0;$i<count($cells);$i++) {
            $label=trim($cleanAccountText($cells[$i]['html'])," :");
            foreach ($labels as $wanted) {
                if (strcasecmp($label,$wanted)===0) {
                    for ($j=$i+1;$j<count($cells);$j++) {
                        $class=$cells[$j]['attrs']['class']??'';
                        if (strpos($class,'Form_Content_Row_Right')!==false || strpos($class,'Form_Content_Row_Right_Textarea')!==false) {
                            return $ibsngExtractControl($cells[$j]['html']);
                        }
                    }
                }
            }
        }
    }
    return null;
};
$ibsngAttributeValue = static function (string $html, array $names) use ($ibsngElementBlocks, $ibsngDirectCells, $ibsngHtmlAttrs, $ibsngExtractControl): ?string {
    foreach ($ibsngElementBlocks($html,'tr') as $row) {
        $cells=$ibsngDirectCells($row['outer']); $checkboxCell=-1;
        foreach ($cells as $i=>$cell) {
            if (!preg_match_all('/<input\b[^>]*>/is',$cell['html'],$inputs)) continue;
            foreach ($inputs[0] as $inputTag) {
                $a=$ibsngHtmlAttrs($inputTag);
                if (strpos(strtolower($a['name']??''),'attr_edit_checkbox_')===0
                    && in_array(trim($a['value']??''),$names,true)) { $checkboxCell=$i; break 2; }
            }
        }
        if ($checkboxCell<0) continue;
        for ($j=$checkboxCell+1;$j<count($cells);$j++) {
            $class=$cells[$j]['attrs']['class']??'';
            if (strpos($class,'Form_Content_Row_Right')!==false || strpos($class,'Form_Content_Row_Right_Textarea')!==false) {
                return $ibsngExtractControl($cells[$j]['html']);
            }
        }
    }
    return null;
};
$accountDisplay = static function (?string $value) use ($cleanAccountText): string {
    $v = trim((string)$value);
    return $v === '' || preg_match('/^-+$/u', $v) ? 'نامشخص' : $cleanAccountText($v);
};
$multiLoginRaw = $ibsngAttributeValue($accountHtml, ['multi_login', 'multi_login_instances', 'multi_login_instance']);
$multiLoginDisplay = trim((string)$multiLoginRaw);
if ($multiLoginDisplay === '' || preg_match('/^-+$/u', $multiLoginDisplay)) {
    $multiLoginDisplay = 'تک‌کاربره';
}

// Store quota is resolved through the exact active IBSng group mapping.
// Multiple active products on one group make the quota ambiguous, so do not guess.
$quotaBytes = null;
$consumedBytes = null;
$remainingBytes = null;
if ($accountHtml !== '' && trim((string)($userResult['group'] ?? '')) !== '') {
    $quotaStmt = $pdo->prepare(
        'SELECT p.quota_bytes
         FROM ibsng_groups g
         JOIN product_server_groups m ON m.ibsng_group_id=g.id AND m.server_id=g.server_id
         JOIN products p ON p.id=m.product_id AND p.is_active=1
         WHERE g.server_id=? AND g.group_name=? AND g.is_active=1
         ORDER BY p.id'
    );
    $quotaStmt->execute([$selectedServerId, trim((string)$userResult['group'])]);
    $quotaRows = $quotaStmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($quotaRows) === 1 && $quotaRows[0] !== null && (int)$quotaRows[0] > 0) {
        $quotaBytes = (int)$quotaRows[0];
    }
    // This attribute identifies Daily Traffic usage. Do not use Traffic Limit,
    // Monthly Traffic, period length, reset date or any Edit User form submission.
    $trafficRaw = trim((string)($ibsngAttributeValue($accountHtml, ['daily_traffic_paccounting_usage']) ?? ''));
    if ($trafficRaw !== '' && !preg_match('/^(?:-+|not applicable)$/iu', $trafficRaw)
        && preg_match('/^([0-9]+(?:\\.[0-9]+)?)\\s*([KMGT])(?:i?B)?$/i', $trafficRaw, $trafficMatch)) {
        $trafficPower = ['K'=>1,'M'=>2,'G'=>3,'T'=>4][strtoupper($trafficMatch[2])] ?? 0;
        $consumedBytes = (int)round((float)$trafficMatch[1] * (1024 ** $trafficPower));
    }
    if ($quotaBytes !== null && $consumedBytes !== null) {
        $remainingBytes = max(0, $quotaBytes - $consumedBytes);
    }
}
$formatTrafficBytes = static function (?int $bytes): string {
    if ($bytes === null) return 'نامحدود';
    $bytes = max(0, $bytes);
    $value = $bytes / 1048576;
    $unit = 'MB';
    foreach (['GB','TB'] as $nextUnit) {
        if ($value >= 1024) { $value /= 1024; $unit = $nextUnit; } else break;
    }
    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . ' ' . $unit;
};

// Store-owned presentation title only; keep the extracted IBSng value untouched.
$multiLoginDisplayFa = $multiLoginDisplay;
$multiLoginDisplayEn = $multiLoginDisplay;
if (preg_match('/^([0-9]+)\\s+instances?$/i', $multiLoginDisplay, $multiLoginMatch)) {
    $multiLoginCount = $multiLoginMatch[1];
    $persianDigits = strtr($multiLoginCount, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    $multiLoginDisplayFa = $persianDigits . ' کاربره';
    $multiLoginDisplayEn = $multiLoginCount . ' users';
}
$accountFields = [
    'Username' => (string)($userResult['username'] ?? 'نامشخص'),
    'IBSng User ID' => (string)($userResult['user_id'] ?? 'نامشخص'),
    'Group' => (string)($userResult['group'] ?? 'نامشخص'),
    'Status' => $accountDisplay($ibsngDisplayedValue($accountHtml, ['Status', 'User Status'])),
    'First Login' => $accountDisplay($ibsngAttributeValue($accountHtml, ['first_login'])),
    'Relative Expiration' => $accountDisplay($ibsngAttributeValue($accountHtml, ['rel_exp'])),
    'Nearest Expiration Date' => $accountDisplay($ibsngDisplayedValue($accountHtml, ['Nearest Expiration Date'])),
    'Absolute Expiration Date' => $accountDisplay($ibsngAttributeValue($accountHtml, ['abs_exp', 'absolute_exp', 'absolute_expiration']) ?? $ibsngDisplayedValue($accountHtml, ['Absolute Expiration Date'])),
    'User Is Locked' => $accountDisplay($ibsngAttributeValue($accountHtml, ['user_is_locked', 'is_locked', 'locked']) ?? $ibsngDisplayedValue($accountHtml, ['User Is Locked', 'User Locked'])),
    'Creation Date' => $accountDisplay($ibsngDisplayedValue($accountHtml, ['Creation Date', 'Create Date'])),
    'Multi Login raw value' => $multiLoginDisplay,
    'Password' => (string)($userResult['password'] ?? 'نامشخص'),
    'Consumed Traffic' => $formatTrafficBytes($consumedBytes),
    'Remaining Traffic' => $formatTrafficBytes($remainingBytes),
];

// Presentation-only localization for the IBSng lock flag; retain the raw value above.
$lockedRaw = strtolower(trim((string)($accountFields['User Is Locked'] ?? '')));
$lockedPresentation = match ($lockedRaw) {
    'no', '0', 'false' => ['fa' => 'قفل نیست', 'en' => 'Not locked'],
    'yes', '1', 'true' => ['fa' => 'قفل است', 'en' => 'Locked'],
    default => null,
};

// Translate field labels only. IBSng values remain exactly as received.
$accountFieldLabelsFa = [
    'Username' => 'نام کاربری',
    'IBSng User ID' => 'شناسه اکانت',
    'Group' => 'نام گروه',
    'Status' => 'وضعیت',
    'First Login' => 'اولین ورود',
    'Relative Expiration' => 'مدت (انقضای نسبی)',
    'Nearest Expiration Date' => 'تاریخ انقضا (نزدیک‌ترین)',
    'Absolute Expiration Date' => 'تاریخ انقضا (مطلق)',
    'User Is Locked' => 'وضعیت قفل اکانت',
    'Creation Date' => 'زمان ساخت اکانت',
    'Multi Login raw value' => 'تعداد کاربران هم‌زمان',
    'Password' => 'رمز عبور',
    'Consumed Traffic' => 'حجم مصرف‌شده',
    'Remaining Traffic' => 'حجم باقی‌مانده',
];

?>
<?php
// Presentation-only grouping. The IBSng values and their extraction remain unchanged.
$accountFieldGroups = [
    'identity' => [
        'title_fa' => 'مشخصات اکانت',
        'title_en' => 'Account Details',
        'fields' => ['Username', 'IBSng User ID', 'Group', 'Password'],
    ],
    'activity' => [
        'title_fa' => 'وضعیت اکانت',
        'title_en' => 'Account Status',
        'fields' => ['Status', 'First Login', 'User Is Locked', 'Creation Date', 'Multi Login raw value'],
    ],
    'validity' => [
        'title_fa' => 'اطلاعات انقضا',
        'title_en' => 'Expiration Details',
        'fields' => ['Relative Expiration', 'Nearest Expiration Date', 'Absolute Expiration Date'],
    ],
    'capacity' => [
        'title_fa' => 'حجم سرویس',
        'title_en' => 'Data Usage',
        'fields' => ['Consumed Traffic', 'Remaining Traffic'],
    ],
];
?>
<div class="result panel account-result">
<strong class="account-result-title" data-fa="اطلاعات اکانت IBSng" data-en="IBSng account details">اطلاعات اکانت IBSng</strong>
<div class="account-groups">
<?php foreach ($accountFieldGroups as $group): ?>
<section class="account-group">
<h3 class="account-group-title" data-fa="<?= e($group['title_fa']) ?>" data-en="<?= e($group['title_en']) ?>"><?= e($group['title_fa']) ?></h3>
<div class="account-group-fields">
<?php foreach ($group['fields'] as $fieldLabel): ?>
<?php $fieldValue = $accountFields[$fieldLabel]; ?>
<div class="account-item">
<span class="account-item-label" data-fa="<?= e($accountFieldLabelsFa[$fieldLabel] ?? $fieldLabel) ?>" data-en="<?= e(match ($fieldLabel) { 'Group' => 'Group Name', 'Creation Date' => 'Account Created', 'Multi Login raw value' => 'Simultaneous Users', default => $fieldLabel }) ?>"><?= e($accountFieldLabelsFa[$fieldLabel] ?? $fieldLabel) ?></span>
<strong class="technical-value"><?php if ($fieldLabel === 'Multi Login raw value'): ?><span data-fa="<?= e($multiLoginDisplayFa) ?>" data-en="<?= e($multiLoginDisplayEn) ?>"><?= e($multiLoginDisplayFa) ?></span><?php elseif ($fieldValue === 'نامشخص'): ?><span data-fa="نامشخص" data-en="Unknown">نامشخص</span><?php elseif ($fieldLabel === 'User Is Locked' && $lockedPresentation !== null): ?><span data-fa="<?= e($lockedPresentation['fa']) ?>" data-en="<?= e($lockedPresentation['en']) ?>"><?= e($lockedPresentation['fa']) ?></span><?php elseif ($fieldValue === 'تک‌کاربره'): ?><span data-fa="تک‌کاربره" data-en="Single-user">تک‌کاربره</span><?php else: ?><?= e($fieldValue) ?><?php endif; ?></strong>
</div>
<?php endforeach; ?>
</div>
</section>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
</section>
<section class="panel panel-card">
<h2 data-fa="ایجاد اکانت" data-en="Create account">ایجاد اکانت</h2>
<?php if (!$groups): ?><p class="muted" data-fa="برای این سرور گروه فعالی ثبت نشده است؛ ابتدا از صفحه تعریف پلن و گروه، نام دقیق گروه IBSng را ثبت کنید." data-en="No active group is available for this server. First add the exact IBSng group name in Plan & Group Setup.">برای این سرور گروه فعالی ثبت نشده است؛ ابتدا از صفحه تعریف پلن و گروه، نام دقیق گروه IBSng را ثبت کنید.</p><?php else: ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>"><input type="hidden" name="action" value="create">
<div class="grid">
<div class="field"><label for="group_id" data-fa="گروه IBSng (از گروه‌های فعال)" data-en="IBSng group (active groups)">گروه IBSng (از گروه‌های فعال)</label><select id="group_id" name="group_id" required><?php foreach ($groups as $group): ?><option value="<?= (int)$group['id'] ?>"><?= e((string)$group['plan_name']) ?> — <?= e((string)$group['group_name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="create_username" data-fa="نام کاربری" data-en="Username">نام کاربری</label><input id="create_username" name="username" required autocomplete="off"></div>
<div class="field"><label for="new_password" data-fa="رمز عبور" data-en="Password">رمز عبور</label><input id="new_password" name="new_password" type="password" required autocomplete="new-password"></div>
<div class="field"><label for="credit" data-fa="اعتبار (Credit)" data-en="Credit">اعتبار (Credit)</label><input id="credit" name="credit" value="0" inputmode="decimal" required></div>
</div>
<button class="button primary" type="submit" data-fa="ایجاد اکانت در IBSng" data-en="Create account in IBSng">ایجاد اکانت در IBSng</button>
</form>
<?php endif; ?>
</section>
<section class="panel panel-card">
<h2 data-ui-text="users_change_group_title">تغییر گروه اکانت موجود</h2>
<p class="muted" data-ui-text="users_change_group_intro">اکانت باید از قبل در IBSng وجود داشته باشد. گروه مقصد فقط از میان گروه‌های فعال ثبت‌شده برای همین سرور انتخاب می‌شود؛ تغییر از فرم Edit User انجام می‌شود.</p>
<?php if (!$groups): ?><p class="muted" data-ui-text="users_change_group_empty">برای این سرور گروه فعالی ثبت نشده است.</p><?php else: ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>"><input type="hidden" name="action" value="change_group">
<div class="grid">
<div class="field"><label for="change_group_username" data-ui-text="users_existing_username">نام کاربری اکانت موجود</label><input id="change_group_username" name="username" required autocomplete="off"></div>
<div class="field"><label for="change_group_id" data-ui-text="users_destination_group">گروه مقصد</label><select id="change_group_id" name="group_id" required><?php foreach ($groups as $group): ?><option value="<?= (int)$group['id'] ?>"><?= e((string)$group['plan_name']) ?> — <?= e((string)$group['group_name']) ?></option><?php endforeach; ?></select></div>
</div>
<button class="button primary" type="submit" data-ui-text="users_change_group_submit">تغییر گروه در IBSng</button>
</form>
<?php endif; ?>
</section>
<section class="panel panel-card">
<h2 data-fa="حذف اکانت" data-en="Delete account">حذف اکانت</h2>
<p class="muted" data-fa="حذف از IBSng عملیات برگشت‌ناپذیر است. این عملیات طبق رفتار مرجع، گزینه حذف connection logs و audit logs را نیز ارسال می‌کند." data-en="Deleting an account from IBSng cannot be undone. Following the reference behavior, this also requests deletion of connection and audit logs.">حذف از IBSng عملیات برگشت‌ناپذیر است. این عملیات طبق رفتار مرجع، گزینه حذف connection logs و audit logs را نیز ارسال می‌کند.</p>
<form method="post" data-confirm-fa="اکانت واقعاً از IBSng حذف شود؟ این عملیات برگشت‌پذیر نیست." data-confirm-en="Really delete this account from IBSng? This cannot be undone.">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="confirm_delete" value="yes">
<div class="field"><label for="delete_username" data-fa="نام کاربری اکانت" data-en="Account username">نام کاربری اکانت</label><input id="delete_username" name="username" required autocomplete="off"></div>
<button class="button danger" type="submit" data-fa="حذف اکانت از IBSng" data-en="Delete account from IBSng">حذف اکانت از IBSng</button>
</form>
</section>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
</main>


</body>
</html>
