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
$renewalColumnCheck->execute(['products', 'ibsng_validity_days']);
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
$groupInfoResult = null;
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
            'SELECT p.id,p.name,p.ibsng_validity_days,g.group_name
             FROM products p
             JOIN product_server_groups m ON m.product_id=p.id AND m.server_id=?
             JOIN ibsng_groups g ON g.id=m.ibsng_group_id AND g.server_id=m.server_id
             WHERE p.is_active=1 AND g.is_active=1 AND p.ibsng_validity_days IS NOT NULL
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
                    'SELECT p.id,p.ibsng_validity_days,g.group_name
                     FROM products p
                     JOIN product_server_groups m ON m.product_id=p.id AND m.server_id=?
                     JOIN ibsng_groups g ON g.id=m.ibsng_group_id AND g.server_id=m.server_id
                     WHERE p.id=? AND p.is_active=1 AND g.is_active=1
                       AND p.ibsng_validity_days IS NOT NULL LIMIT 1'
                );
                $planStmt->execute([$selectedServerId, $productId]);
                $renewalPlan = $planStmt->fetch(PDO::FETCH_ASSOC);
                if (!is_array($renewalPlan) || (int)$renewalPlan['ibsng_validity_days'] < 1) {
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
                    (int)$renewalPlan['ibsng_validity_days'],
                    $giftFirst,
                    $giftRepeat,
                    $renewalRequestKey,
                    $productId,
                    'ATD IBSng Store admin renewal ' . $renewalRequestKey
                );
                $message = 'تمدید از پاسخ Web Panel IBSng تأیید شد.';
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
                $userResult = $client->lookupUser($username);
            } elseif ($action === 'group_info') {
                $groupId = (int)($_POST['group_id'] ?? 0);
                $groupStmt = $pdo->prepare('SELECT group_name FROM ibsng_groups WHERE id=? AND server_id=? AND is_active=1');
                $groupStmt->execute([$groupId, $selectedServerId]);
                $groupName = (string)$groupStmt->fetchColumn();
                if ($groupName === '') {
                    throw new InvalidArgumentException('از نگاشت دستی همین سرور یک گروه فعال انتخاب کنید.');
                }
                $groupInfoResult = ['name' => $groupName, 'details' => $client->getGroupInfo($groupName)];
            } elseif ($action === 'credentials') {
                if ($username === '') {
                    throw new InvalidArgumentException('نام کاربری برای دریافت اطلاعات حساب الزامی است.');
                }
                $credentialResult = $client->getCredentials($username);
                $userResult = $credentialResult;
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
                $message = 'درخواست ایجاد حساب در IBSng با پاسخ موفق تأیید شد.';
                $userResult = ['username' => $username, 'group' => $groupName, 'summary' => 'حساب در Web Panel ایجاد شد.'];
            } elseif ($action === 'change_group') {
                $groupId = (int)($_POST['group_id'] ?? 0);
                $groupStmt = $pdo->prepare('SELECT group_name FROM ibsng_groups WHERE id=? AND server_id=? AND is_active=1');
                $groupStmt->execute([$groupId, $selectedServerId]);
                $groupName = (string)$groupStmt->fetchColumn();
                if ($groupName === '' || $username === '') {
                    throw new InvalidArgumentException('کاربر و گروه مقصد از نگاشت فعال همین سرور را انتخاب کنید.');
                }
                $client->changeUserGroup($username, $groupName);
                $message = 'درخواست تغییر گروه کاربر موجود از مسیر Edit User ارسال شد.';
                $userResult = ['username' => $username, 'group' => $groupName, 'summary' => 'تغییر گروه از Web Panel تأیید شد.'];
            } elseif ($action === 'delete') {
                if ($username === '' || (string)($_POST['confirm_delete'] ?? '') !== 'yes') {
                    throw new InvalidArgumentException('برای حذف حساب، تأیید حذف الزامی است.');
                }
                $client->deleteUser($username, true, true);
                $message = 'پاسخ موفق حذف حساب از IBSng دریافت شد.';
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
                    $error = 'نتیجه عملیات در IBSng قطعی نیست؛ وضعیت حساب را بررسی کنید و عملیات را دوباره اجرا نکنید.';
                } else {
                    $error = 'عملیات IBSng انجام نشد؛ اتصال، تنظیمات سرور و وضعیت حساب را بررسی کنید.';
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
<title>مدیریت حساب‌های IBSng | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto}.admin-top{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:24px 0}.panel-card{padding:24px;margin-top:20px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 16px}.field{display:grid;gap:8px;margin:14px 0}.field label{color:var(--muted);font-size:.9rem}.field input,.field select{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:#0d1426;color:var(--text);font:inherit}.notice{padding:13px 15px;border-radius:12px;margin:14px 0;line-height:1.8}.ok{background:#44d6a018;border:1px solid #44d6a044}.err{background:#ff718018;border:1px solid #ff718044}.result{margin-top:16px;padding:18px;overflow-wrap:anywhere}.actions{display:flex;gap:10px;flex-wrap:wrap}.danger{color:#ff8e9a}.muted{color:var(--muted);line-height:1.8}@media(max-width:600px){.grid{grid-template-columns:1fr}.admin-top{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<main class="admin-shell">
<header class="admin-top">
<a class="brand" href="<?= e(app_url('admin/')) ?>">ATD IBSng Store · مدیریت</a>
<nav><a class="button" href="<?= e(app_url('admin/ibsng-servers.php')) ?>" data-fa="سرورهای IBSng" data-en="IBSng Servers">سرورهای IBSng</a> <a class="button" href="<?= e(app_url('admin/ibsng-groups.php')) ?>" data-fa="نگاشت پلن/گروه" data-en="Plan / Group Mapping">نگاشت پلن/گروه</a> <button class="button lang-toggle" id="langToggle" type="button" aria-label="Switch language">English</button></nav>
</header>
<section class="panel panel-card">
<div class="eyebrow">IBSng · User Management</div>
<h1 style="font-size:2rem">مدیریت حساب‌های IBSng</h1>
<p class="muted">عملیات روی Web Panel سرور انتخاب‌شده انجام می‌شود. نام گروه از نگاشت دستی Store گرفته می‌شود و باید دقیقاً با نام گروه IBSng مطابقت داشته باشد. تمدید از پلن فعال و مدت اعتبار مخصوص IBSng استفاده می‌کند؛ اگر نتیجهٔ تمدید نامشخص بماند، عملیات برای بررسی ثبت می‌شود و دوباره اجرا نخواهد شد.</p>
<?php if ($message !== ''): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
<?php if (!$servers): ?><div class="notice err">سرور فعال IBSng ثبت نشده است. ابتدا از صفحه سرورها یک سرور فعال اضافه کنید.</div><?php else: ?>
<form method="get">
<div class="field"><label for="server_id">سرور IBSng</label><select id="server_id" name="server_id" onchange="this.form.submit()">
<?php foreach ($servers as $server): ?><option value="<?= (int)$server['id'] ?>"<?= (int)$server['id'] === $selectedServerId ? ' selected' : '' ?>><?= e((string)$server['name']) ?> · <?= e((string)$server['host']) ?></option><?php endforeach; ?>
</select></div>
</form>
<?php endif; ?>
</section>
<?php if ($selectedServerId > 0 && $servers): ?>
<section class="panel panel-card">
<h2>تمدید حساب موجود در IBSng</h2>
<p class="muted">نام کاربری حساب موجود را وارد کنید و پلن تمدید را انتخاب کنید. مدت روزهای تمدید و هدیه از تنظیمات Store خوانده می‌شود؛ Absolute Expiration تغییر نمی‌کند.</p>
<?php if (!$renewalSchemaReady): ?><div class="notice err">ساختار دیتابیس برای تمدید IBSng آماده نیست. <a href="<?= e(app_url('admin/ibsng-renewal-migrate.php')) ?>">ارتقای دیتابیس را انجام دهید</a>.</div><?php elseif (!$renewalProducts): ?><div class="notice err">برای این سرور پلن فعالی با مدت اعتبار IBSng تنظیم‌شده وجود ندارد. ابتدا در مدیریت محصولات، «مدت تمدید IBSng (روز)» را مشخص کنید.</div><?php else: ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>"><input type="hidden" name="action" value="renew">
<input type="hidden" name="renewal_key" value="<?= e($renewalRequestKey) ?>">
<div class="grid">
<div class="field"><label for="renew_username">Username حساب موجود</label><input id="renew_username" name="username" required autocomplete="off" value="<?= e((string)($_POST['username'] ?? '')) ?>"></div>
<div class="field"><label for="renewal_product_id">پلن تمدید</label><select id="renewal_product_id" name="renewal_product_id" required><option value="">انتخاب پلن</option><?php foreach ($renewalProducts as $renewalProduct): ?><option value="<?= (int)$renewalProduct['id'] ?>"<?= (int)($_POST['renewal_product_id'] ?? 0)===(int)$renewalProduct['id']?' selected':'' ?>><?= e((string)$renewalProduct['name']) ?> · <?= (int)$renewalProduct['ibsng_validity_days'] ?> روز · <?= e((string)$renewalProduct['group_name']) ?></option><?php endforeach; ?></select></div>
</div>
<button class="button primary" type="submit" onclick="return confirm('تمدید این حساب در IBSng انجام شود؟ در صورت پاسخ نامشخص، تمدید خودکار تکرار نمی‌شود.');">تمدید حساب در IBSng</button>
</form>
<?php endif; ?>
</section>
<section class="panel panel-card">
<h2>جست‌وجو و دریافت اطلاعات حساب</h2>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>"><input type="hidden" name="action" value="lookup">
<div class="field"><label for="lookup_username">Username</label><input id="lookup_username" name="username" required autocomplete="off" value="<?= e((string)($_POST['username'] ?? '')) ?>"></div>
<div class="actions"><button class="button primary" type="submit">جست‌وجوی حساب</button></div>
</form>
<form method="post" style="margin-top:10px">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>"><input type="hidden" name="action" value="credentials">
<div class="field"><label for="credentials_username">Username برای نمایش اطلاعات ورود</label><input id="credentials_username" name="username" required autocomplete="off"></div>
<button class="button" type="submit">دریافت username/password از IBSng</button>
</form>
<?php if (is_array($userResult)): ?>
<div class="result panel">
<strong>اطلاعات حساب</strong>
<div class="muted">Username: <?= e((string)($userResult['username'] ?? '')) ?></div>
<div class="muted">IBSng User ID: <?= e((string)($userResult['user_id'] ?? 'نامشخص')) ?></div>
<div class="muted">Group: <?= e((string)($userResult['group'] ?? 'نامشخص')) ?></div>
<?php if (array_key_exists('password', $userResult)): ?><div class="muted">Password: <code><?= e((string)$userResult['password']) ?></code></div><?php endif; ?>
<?php if (!empty($userResult['summary'])): ?><p class="muted"><?= e((string)$userResult['summary']) ?></p><?php endif; ?>
</div>
<?php endif; ?>
</section>
<section class="panel panel-card">
<h2>اطلاعات گروه IBSng</h2>
<p class="muted">گروه از نگاشت دستی همین سرور انتخاب می‌شود؛ فهرست گروه‌ها از IBSng واکشی نمی‌شود.</p>
<?php if (!$groups): ?><p class="muted">برای این سرور گروه فعالی ثبت نشده است.</p><?php else: ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>"><input type="hidden" name="action" value="group_info">
<div class="field"><label for="group_info_id">گروه IBSng</label><select id="group_info_id" name="group_id" required><?php foreach ($groups as $group): ?><option value="<?= (int)$group['id'] ?>"><?= e((string)$group['plan_name']) ?> — <?= e((string)$group['group_name']) ?></option><?php endforeach; ?></select></div>
<button class="button" type="submit">خواندن اطلاعات گروه</button>
</form>
<?php endif; ?>
<?php if (is_array($groupInfoResult)): ?><div class="result panel"><strong><?= e((string)$groupInfoResult['name']) ?></strong><p class="muted"><?= e((string)$groupInfoResult['details']) ?></p></div><?php endif; ?>
</section>
<section class="panel panel-card">
<h2>ایجاد حساب</h2>
<?php if (!$groups): ?><p class="muted">برای این سرور نگاشت فعال ثبت نشده است؛ ابتدا از صفحه نگاشت پلن/گروه، نام دقیق گروه IBSng را دستی ثبت کنید.</p><?php else: ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>"><input type="hidden" name="action" value="create">
<div class="grid">
<div class="field"><label for="group_id">گروه IBSng (از نگاشت دستی)</label><select id="group_id" name="group_id" required><?php foreach ($groups as $group): ?><option value="<?= (int)$group['id'] ?>"><?= e((string)$group['plan_name']) ?> — <?= e((string)$group['group_name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="create_username">Username</label><input id="create_username" name="username" required autocomplete="off"></div>
<div class="field"><label for="new_password">Password</label><input id="new_password" name="new_password" type="password" required autocomplete="new-password"></div>
<div class="field"><label for="credit">Credit</label><input id="credit" name="credit" value="0" inputmode="decimal" required></div>
</div>
<button class="button primary" type="submit">ایجاد حساب در IBSng</button>
</form>
<?php endif; ?>
</section>
<section class="panel panel-card">
<h2>تغییر گروه کاربر موجود</h2>
<p class="muted">کاربر در IBSng باید از قبل وجود داشته باشد. گروه مقصد فقط از نگاشت دستی و فعال همین سرور انتخاب می‌شود؛ تغییر از فرم Edit User انجام می‌شود.</p>
<?php if (!$groups): ?><p class="muted">برای این سرور گروه فعالی در نگاشت دستی وجود ندارد.</p><?php else: ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>"><input type="hidden" name="action" value="change_group">
<div class="grid">
<div class="field"><label for="change_group_username">Username کاربر موجود</label><input id="change_group_username" name="username" required autocomplete="off"></div>
<div class="field"><label for="change_group_id">گروه مقصد</label><select id="change_group_id" name="group_id" required><?php foreach ($groups as $group): ?><option value="<?= (int)$group['id'] ?>"><?= e((string)$group['plan_name']) ?> — <?= e((string)$group['group_name']) ?></option><?php endforeach; ?></select></div>
</div>
<button class="button primary" type="submit">تغییر گروه در IBSng</button>
</form>
<?php endif; ?>
</section>
<section class="panel panel-card">
<h2>حذف حساب</h2>
<p class="muted">حذف از IBSng عملیات برگشت‌ناپذیر است. این عملیات طبق رفتار مرجع، گزینه حذف connection logs و audit logs را نیز ارسال می‌کند.</p>
<form method="post" onsubmit="return confirm('حساب واقعاً از IBSng حذف شود؟ این عملیات برگشت‌پذیر نیست.');">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="confirm_delete" value="yes">
<div class="field"><label for="delete_username">Username</label><input id="delete_username" name="username" required autocomplete="off"></div>
<button class="button danger" type="submit">حذف حساب از IBSng</button>
</form>
</section>
<?php endif; ?>
<footer><a href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a></footer>
</main>

<script>
(function () {
  const pairs = {
    "ATD IBSng Store · مدیریت":"ATD IBSng Store · Admin",
    "سرورهای IBSng":"IBSng Servers",
    "نگاشت پلن/گروه":"Plan / Group Mapping",
    "مدیریت حساب‌های IBSng":"IBSng User Management",
    "مدیریت حساب‌های IBSng":"IBSng User Management",
    "IBSng · User Management":"IBSng · User Management",
    "عملیات روی Web Panel سرور انتخاب‌شده انجام می‌شود. نام گروه از نگاشت دستی Store گرفته می‌شود و باید دقیقاً با نام گروه IBSng مطابقت داشته باشد. تمدید از پلن فعال و مدت اعتبار مخصوص IBSng استفاده می‌کند؛ اگر نتیجهٔ تمدید نامشخص بماند، عملیات برای بررسی ثبت می‌شود و دوباره اجرا نخواهد شد.":"Operations run against the selected IBSng Web Panel. The manually mapped group must exactly match IBSng. Renewal uses the active plan's explicit IBSng validity; uncertain outcomes are ledgered for review and are not retried.",
    "درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.":"Invalid request. Please refresh the page.",
    "ابتدا یک سرور فعال IBSng انتخاب کنید.":"Select an active IBSng server first.",
    "نام کاربری برای جست‌وجو الزامی است.":"Username is required for lookup.",
    "از نگاشت دستی همین سرور یک گروه فعال انتخاب کنید.":"Select an active group from this server's manual mapping.",
    "نام کاربری برای دریافت اطلاعات حساب الزامی است.":"Username is required to retrieve account details.",
    "گروه، نام کاربری و رمز عبور الزامی هستند.":"Group, username and password are required.",
    "Credit باید یک مقدار عددی معتبر باشد.":"Credit must be a valid number.",
    "درخواست ایجاد حساب در IBSng با پاسخ موفق تأیید شد.":"IBSng confirmed the account creation request.",
    "برای حذف حساب، تأیید حذف الزامی است.":"Deletion confirmation is required.",
    "این نام کاربری در IBSng وجود ندارد.":"This username does not exist in IBSng.",
    "عملیات نامعتبر است.":"Invalid operation.",
    "عملیات IBSng انجام نشد؛ وضعیت اتصال و پاسخ Web Panel را بررسی کنید.":"IBSng operation failed. Check connectivity and the Web Panel response.",
    "سرور فعال IBSng ثبت نشده است. ابتدا از صفحه سرورها یک سرور فعال اضافه کنید.":"No active IBSng server is configured. Add and activate a server first.",
    "سرور IBSng":"IBSng Server",
    "ساختار دیتابیس برای تمدید IBSng آماده نیست.":"The database schema is not ready for IBSng renewal.",
    "ارتقای دیتابیس را انجام دهید":"Apply the database upgrade",
    "ابتدا ارتقای ساختار تمدید IBSng را از بخش مربوطه انجام دهید.":"Apply the IBSng renewal schema upgrade first.",
    "تمدید حساب موجود در IBSng":"Renew Existing IBSng Account",
    "نام کاربری، پلن تمدید و کلید درخواست معتبر الزامی است.":"Username, renewal plan, and request key are required.",
    "پلن تمدید فعال و دارای مدت اعتبار مخصوص IBSng انتخاب نشده است.":"An active renewal plan with explicit IBSng validity has not been selected.",
    "تمدید از پاسخ Web Panel IBSng تأیید شد.":"Renewal was confirmed by the IBSng Web Panel response.",
    "نام کاربری حساب موجود را وارد کنید و پلن تمدید را انتخاب کنید. مدت روزهای تمدید و هدیه از تنظیمات Store خوانده می‌شود؛ Absolute Expiration تغییر نمی‌کند.":"Enter the existing account username and choose a renewal plan. Renewal validity and gift days come from Store settings; Absolute Expiration is not changed.",
    "برای این سرور پلن فعالی با مدت اعتبار IBSng تنظیم‌شده وجود ندارد. ابتدا در مدیریت محصولات، «مدت تمدید IBSng (روز)» را مشخص کنید.":"No active plan with configured IBSng validity exists for this server. Set the IBSng renewal validity in product management first.",
    "Username حساب موجود":"Existing account username",
    "پلن تمدید":"Renewal plan",
    "تمدید حساب در IBSng":"Renew Account in IBSng",
    "تمدید این حساب در IBSng انجام شود؟ در صورت پاسخ نامشخص، تمدید خودکار تکرار نمی‌شود.":"Renew this account in IBSng? If the result is uncertain, the operation will not be automatically repeated.",
    "جست‌وجو و دریافت اطلاعات حساب":"Lookup and Account Details",
    "Username":"Username",
    "جست‌وجوی حساب":"Search Account",
    "Username برای نمایش اطلاعات ورود":"Username to retrieve login credentials",
    "دریافت username/password از IBSng":"Get username/password from IBSng",
    "اطلاعات حساب":"Account Details",
    "IBSng User ID:":"IBSng User ID:",
    "Group:":"Group:",
    "Password:":"Password:",
    "اطلاعات گروه IBSng":"IBSng Group Information",
    "گروه از نگاشت دستی همین سرور انتخاب می‌شود؛ فهرست گروه‌ها از IBSng واکشی نمی‌شود.":"The group is selected from this server's manual mapping; IBSng groups are not fetched automatically.",
    "برای این سرور گروه فعالی ثبت نشده است.":"No active group is mapped for this server.",
    "گروه IBSng":"IBSng Group",
    "خواندن اطلاعات گروه":"Read Group Information",
    "ایجاد حساب":"Create Account",
    "برای این سرور نگاشت فعال ثبت نشده است؛ ابتدا از صفحه نگاشت پلن/گروه، نام دقیق گروه IBSng را دستی ثبت کنید.":"No active mapping exists for this server. Add the exact IBSng group name manually in Plan / Group Mapping first.",
    "گروه IBSng (از نگاشت دستی)":"IBSng Group (manual mapping)",
    "Password":"Password",
    "Credit":"Credit",
    "ایجاد حساب در IBSng":"Create Account in IBSng",
    "حذف حساب":"Delete Account",
    "حذف از IBSng عملیات برگشت‌ناپذیر است. این عملیات طبق رفتار مرجع، گزینه حذف connection logs و audit logs را نیز ارسال می‌کند.":"Deleting an account from IBSng cannot be undone. Following the reference behavior, this also requests deletion of connection and audit logs.",
    "حساب واقعاً از IBSng حذف شود؟ این عملیات برگشت‌پذیر نیست.":"Really delete this account from IBSng? This cannot be undone.",
    "حذف حساب از IBSng":"Delete Account from IBSng",
    "بازگشت به فروشگاه":"Back to Store",
    "تست نشده":"Not tested",
    "نامشخص":"Unknown",
    "حساب در Web Panel ایجاد شد.":"Account created in the Web Panel.",
    "هنوز سروری اضافه نشده است.":"No servers have been added yet.",
    "ابتدا از بخش سرورهای IBSng یک سرور اضافه کنید.":"Add an IBSng server first.",
    "افزودن نگاشت":"Add Mapping",
    "ثبت نگاشت":"Save Mapping",
    "نگاشت‌های ثبت‌شده":"Saved Mappings",
    "ویرایش نگاشت":"Edit Mapping",
    "ذخیره تغییرات":"Save Changes",
    "حذف":"Delete",
    "فعال":"Active",
    "غیرفعال":"Inactive",
    "افزودن سرور IBSng":"Add IBSng Server",
    "نام سرور":"Server Name",
    "Host":"Host",
    "پورت":"Port",
    "ISP name":"ISP Name",
    "نام کاربری":"Username",
    "رمز عبور":"Password",
    "تست اتصال و ذخیره تغییرات":"Test Connection & Save Changes",
    "Test Connection":"Test Connection",
    "انتخاب به‌عنوان پیش‌فرض":"Set as Default",
    "غیرفعال‌سازی":"Disable",
    "فعال‌سازی":"Enable",
    "حذف سرور":"Delete Server",
    "ویرایش تنظیمات":"Edit Settings",
    "نام کاربری جدید (خالی = حفظ قبلی)":"New Username (blank = keep current)",
    "رمز جدید (خالی = حفظ قبلی)":"New Password (blank = keep current)",
    "نگاشت پلن فروشگاه به گروه IBSng":"Store Plan to IBSng Group Mapping",
    "نام گروه باید دقیقاً مطابق گروه موجود در IBSng وارد شود. در این بخش نگاشت به‌صورت دستی تعریف می‌شود؛ دریافت خودکار فهرست گروه‌ها هنوز اضافه نشده است.":"Enter the exact name of an existing IBSng group. Mappings are managed manually; automatic group-list fetching is not implemented.",
    "نام پلن فروشگاه":"Store Plan Name",
    "نام دقیق گروه":"Exact Group Name",
    "مثلاً: یک ماهه":"For example: One month",
    "نام گروه در IBSng":"IBSng group name",
    "برای این سرور هنوز نگاشتی ثبت نشده است.":"No mappings have been added for this server yet."
  };
  const reverse = Object.fromEntries(Object.entries(pairs).map(([fa,en]) => [en,fa]));
  const root = document.documentElement;
  const toggle = document.getElementById('langToggle');
  function translateNode(node, map) {
    if (node.nodeType === Node.TEXT_NODE) {
      const raw = node.nodeValue || '';
      const trimmed = raw.trim();
      if (map[trimmed]) node.nodeValue = raw.replace(trimmed, map[trimmed]);
      return;
    }
    if (node.nodeType !== Node.ELEMENT_NODE) return;
    ['placeholder','title','aria-label'].forEach(attr => {
      const value = node.getAttribute(attr);
      if (value && map[value]) node.setAttribute(attr, map[value]);
    });
    if (node.matches('input[type="submit"],input[type="button"]') && node.value && map[node.value]) {
      node.value = map[node.value];
    }
    node.childNodes.forEach(child => translateNode(child, map));
  }
  function setLanguage(lang) {
    const map = lang === 'en' ? pairs : reverse;
    translateNode(document.body, map);
    root.lang = lang;
    root.dir = lang === 'fa' ? 'rtl' : 'ltr';
    document.body.style.fontFamily = lang === 'fa'
      ? 'Vazirmatn, Tahoma, sans-serif'
      : '"Segoe UI", Arial, sans-serif';
    if (toggle) toggle.textContent = lang === 'en' ? 'فارسی' : 'English';
    try { localStorage.setItem('atd-ibsng-language', lang); } catch (e) {}
  }
  let initial = 'en';
  try { initial = localStorage.getItem('atd-ibsng-language') || 'en'; } catch (e) {}
  if (toggle) toggle.addEventListener('click', () => setLanguage(root.lang === 'fa' ? 'en' : 'fa'));
  setLanguage(initial);
})();
</script>

</body>
</html>
