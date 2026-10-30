<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_once dirname(__DIR__) . '/app/Crypto.php';
require_once dirname(__DIR__) . '/app/IBSngClient.php';
require_admin();

$config = app_config();
$pdo = Database::connection();
$message = '';
$error = '';
$userResult = null;
$credentialResult = null;
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

            if ($action === 'lookup') {
                if ($username === '') {
                    throw new InvalidArgumentException('نام کاربری برای جست‌وجو الزامی است.');
                }
                $userResult = $client->lookupUser($username);
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
            $error = $e instanceof InvalidArgumentException || $e instanceof RuntimeException
                ? $e->getMessage()
                : 'عملیات IBSng انجام نشد؛ وضعیت اتصال و پاسخ Web Panel را بررسی کنید.';
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
<nav><a class="button" href="<?= e(app_url('admin/ibsng-servers.php')) ?>">سرورهای IBSng</a> <a class="button" href="<?= e(app_url('admin/ibsng-groups.php')) ?>">نگاشت پلن/گروه</a></nav>
</header>
<section class="panel panel-card">
<div class="eyebrow">IBSng · User Management</div>
<h1 style="font-size:2rem">مدیریت حساب‌های IBSng</h1>
<p class="muted">عملیات روی Web Panel سرور انتخاب‌شده انجام می‌شود. نام گروه از نگاشت دستی Store گرفته می‌شود و باید دقیقاً با نام گروه IBSng مطابقت داشته باشد. تمدید و تغییر گروه در این صفحه فعال نشده‌اند.</p>
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
</body>
</html>
