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

function ibsng_server_input(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function ibsng_validate_server_fields(): array
{
    $name = ibsng_server_input('name');
    $host = ibsng_server_input('host');
    $port = (int)($_POST['port'] ?? 80);
    $isp = ibsng_server_input('isp_name', 'Main') ?: 'Main';
    $username = ibsng_server_input('username');
    $password = (string)($_POST['password'] ?? '');

    if ($name === '' || $host === '' || $username === '' || $password === '') {
        throw new InvalidArgumentException('نام، Host، نام کاربری و رمز عبور IBSng الزامی است.');
    }
    if (!in_array($port, [80, 443], true)) {
        throw new InvalidArgumentException('پورت باید 80 یا 443 باشد.');
    }
    return compact('name', 'host', 'port', 'isp', 'username', 'password');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    if (!admin_csrf_valid($token)) {
        $error = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        $ibsngRemoteFailure = false;
        try {
            if ($action === 'add') {
                $fields = ibsng_validate_server_fields();
                try {
                    (new IBSngClient($fields['host'], $fields['port'], $fields['username'], $fields['password']))->testConnection();
                } catch (Throwable $remoteError) {
                    $ibsngRemoteFailure = true;
                    throw $remoteError;
                }

                $stmt = $pdo->prepare('INSERT INTO ibsng_servers (name, host, port, scheme, isp_name, username, password_ciphertext, is_active, is_default, last_test_at, last_error) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, NOW(), NULL)');
                $stmt->execute([
                    $fields['name'], $fields['host'], $fields['port'], $fields['port'] === 443 ? 'https' : 'http',
                    $fields['isp'], $fields['username'], app_encrypt_secret($fields['password'])
                ]);
                $message = 'اتصال IBSng با موفقیت تأیید و سرور ذخیره شد.';
            } elseif ($action === 'update') {
                $q = $pdo->prepare('SELECT * FROM ibsng_servers WHERE id = ?');
                $q->execute([$id]);
                $existing = $q->fetch();
                if (!$existing) {
                    throw new RuntimeException('سرور موردنظر پیدا نشد.');
                }

                $name = ibsng_server_input('name');
                $host = ibsng_server_input('host');
                $port = (int)($_POST['port'] ?? 80);
                $isp = ibsng_server_input('isp_name') ?: (string)$existing['isp_name'];
                $username = ibsng_server_input('username') ?: (string)$existing['username'];
                $password = (string)($_POST['password'] ?? '');
                $password = $password !== '' ? $password : app_decrypt_secret((string)$existing['password_ciphertext']);

                if ($name === '' || $host === '' || !in_array($port, [80, 443], true)) {
                    throw new InvalidArgumentException('نام، Host و پورت معتبر الزامی هستند.');
                }
                try {
                    (new IBSngClient($host, $port, $username, $password))->testConnection();
                } catch (Throwable $remoteError) {
                    $ibsngRemoteFailure = true;
                    throw $remoteError;
                }

                $stmt = $pdo->prepare('UPDATE ibsng_servers SET name=?, host=?, port=?, scheme=?, isp_name=?, username=?, password_ciphertext=?, last_test_at=NOW(), last_error=NULL WHERE id=?');
                $stmt->execute([
                    $name, $host, $port, $port === 443 ? 'https' : 'http', $isp, $username,
                    app_encrypt_secret($password), $id
                ]);
                $message = 'تنظیمات سرور به‌روزرسانی شد و تست اتصال موفق بود.';
            } elseif ($action === 'test') {
                $q = $pdo->prepare('SELECT * FROM ibsng_servers WHERE id = ?');
                $q->execute([$id]);
                $server = $q->fetch();
                if (!$server) {
                    throw new RuntimeException('سرور موردنظر پیدا نشد.');
                }
                try {
                    (new IBSngClient(
                        (string)$server['host'], (int)$server['port'], (string)$server['username'],
                        app_decrypt_secret((string)$server['password_ciphertext'])
                    ))->testConnection();
                } catch (Throwable $remoteError) {
                    $ibsngRemoteFailure = true;
                    throw $remoteError;
                }
                $pdo->prepare('UPDATE ibsng_servers SET last_test_at=NOW(), last_error=NULL WHERE id=?')->execute([$id]);
                $message = 'اتصال موفق است؛ ورود مدیر IBSng تأیید شد.';
            } elseif ($action === 'default') {
                $active = $pdo->prepare('SELECT is_active FROM ibsng_servers WHERE id=?');
                $active->execute([$id]);
                if ((int)$active->fetchColumn() !== 1) {
                    throw new RuntimeException('برای انتخاب سرور پیش‌فرض، ابتدا آن را فعال کنید.');
                }
                $pdo->beginTransaction();
                $pdo->exec('UPDATE ibsng_servers SET is_default=0');
                $pdo->prepare('UPDATE ibsng_servers SET is_default=1 WHERE id=?')->execute([$id]);
                $pdo->commit();
                $message = 'سرور پیش‌فرض فروشگاه تغییر کرد.';
            } elseif ($action === 'toggle') {
                $state = $pdo->prepare('SELECT is_active,is_default FROM ibsng_servers WHERE id=?');
                $state->execute([$id]);
                $current = $state->fetch();
                if (!$current) {
                    throw new RuntimeException('سرور موردنظر پیدا نشد.');
                }
                if ((int)$current['is_active'] === 1 && (int)$current['is_default'] === 1) {
                    $pdo->prepare('UPDATE ibsng_servers SET is_active=0,is_default=0 WHERE id=?')->execute([$id]);
                } else {
                    $pdo->prepare('UPDATE ibsng_servers SET is_active = IF(is_active=1,0,1) WHERE id=?')->execute([$id]);
                }
                $message = 'وضعیت فعال‌بودن سرور تغییر کرد.';
            } elseif ($action === 'delete') {
                $defaultCheck = $pdo->prepare('SELECT is_default FROM ibsng_servers WHERE id=?');
                $defaultCheck->execute([$id]);
                if ((int)$defaultCheck->fetchColumn() === 1) {
                    $otherActive = $pdo->prepare('SELECT COUNT(*) FROM ibsng_servers WHERE id<>? AND is_active=1');
                    $otherActive->execute([$id]);
                    if ((int)$otherActive->fetchColumn() > 0) {
                        throw new RuntimeException('این سرور پیش‌فرض است؛ ابتدا سرور دیگری را به‌عنوان پیش‌فرض انتخاب کنید.');
                    }
                }
                $checks = [
                    'subscriptions' => 'SELECT COUNT(*) FROM subscriptions WHERE server_id=?',
                    'product mappings' => 'SELECT COUNT(*) FROM product_server_groups WHERE server_id=?',
                    'groups' => 'SELECT COUNT(*) FROM ibsng_groups WHERE server_id=?',
                    'provisioning history' => 'SELECT COUNT(*) FROM provisioning_attempts WHERE server_id=?',
                ];
                foreach ($checks as $label => $sql) {
                    $check = $pdo->prepare($sql);
                    $check->execute([$id]);
                    if ((int)$check->fetchColumn() > 0) {
                        throw new RuntimeException('این سرور دارای ' . $label . ' است؛ برای جلوگیری از حذف داده‌های وابسته، ابتدا وابستگی‌ها را مدیریت کنید.');
                    }
                }
                $pdo->prepare('DELETE FROM ibsng_servers WHERE id=?')->execute([$id]);
                $message = 'سرور حذف شد.';
            } else {
                throw new RuntimeException('عملیات نامعتبر است.');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $action = (string)($_POST['action'] ?? '');
            if ($e instanceof InvalidArgumentException) {
                $error = $e->getMessage();
            } elseif ($ibsngRemoteFailure) {
                // Only a failure from the actual IBSng connection/authentication
                // check belongs in last_error. Local database failures do not.
                $error = 'ارتباط با IBSng برقرار نشد یا پاسخ معتبر دریافت نشد؛ تنظیمات اتصال را بررسی کنید.';
                if (in_array($action, ['update', 'test'], true) && $id > 0) {
                    try {
                        $pdo->prepare('UPDATE ibsng_servers SET last_error=? WHERE id=?')->execute([$error, $id]);
                    } catch (Throwable $ignored) {
                        // Do not replace the IBSng failure with a persistence failure.
                    }
                }
            } else {
                $error = $e instanceof RuntimeException && !in_array($action, ['add', 'update', 'test'], true)
                    ? $e->getMessage()
                    : 'عملیات انجام نشد. خطای محلی یا دیتابیس رخ داد؛ وضعیت ذخیره‌سازی را بررسی کنید.';
            }
        }
    }
}

$servers = $pdo->query('SELECT id,name,host,port,scheme,isp_name,username,is_active,is_default,last_test_at,last_error,created_at FROM ibsng_servers ORDER BY is_default DESC, id DESC')->fetchAll();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>سرورهای IBSng | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto}.admin-top{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:24px 0}.admin-top form{margin:0}.admin-top button{border:1px solid var(--border);background:var(--surface-2);color:var(--text);font:inherit;cursor:pointer}.layout{display:grid;grid-template-columns:minmax(0,1fr);gap:20px}.panel-card{padding:24px}.field{display:grid;gap:8px;margin:14px 0}.field label{color:var(--muted);font-size:.9rem}.field input,.field select{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:#0d1426;color:var(--text);font:inherit}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 16px}.notice{padding:13px 15px;border-radius:12px;margin:14px 0;line-height:1.8}.ok{background:#44d6a018;border:1px solid #44d6a044}.err{background:#ff718018;border:1px solid #ff718044}.server{padding:22px;margin:14px 0}.server-head{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}.server-head h3{margin:0}.meta{color:var(--muted);line-height:1.9;overflow-wrap:anywhere}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}.actions button{font:inherit;cursor:pointer;border:1px solid var(--border);background:var(--surface-2);color:var(--text)}.actions .danger{color:#ff8e9a}.actions .primary{background:linear-gradient(120deg,var(--blue),var(--purple));border:0;color:white}.empty{padding:26px;text-align:center;color:var(--muted)}@media(max-width:600px){.grid{grid-template-columns:1fr}.admin-top{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<main class="admin-shell">
<header class="admin-top">
<a class="brand" href="<?= e(app_url('admin/')) ?>">ATD IBSng Store · مدیریت</a>
<nav><a class="button" href="<?= e(app_url('admin/')) ?>">داشبورد</a> <a class="button" href="<?= e(app_url('admin/ibsng-groups.php')) ?>">نگاشت پلن/گروه</a><button class="button lang-toggle" id="langToggle" type="button" aria-label="Switch language">English</button></nav>
</header>
<section class="panel panel-card">
<div class="eyebrow">IBSng</div><h1 style="font-size:2rem">سرورهای IBSng</h1>
<p class="muted">در این مرحله، تست اتصال با ورود واقعی به Web Panel انجام می‌شود. رمز عبور در دیتابیس به‌صورت رمزنگاری‌شده نگهداری می‌شود.</p>
<?php if ($message !== ''): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
</section>
<section class="panel panel-card" style="margin-top:20px">
<h2>افزودن سرور</h2>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="add">
<div class="grid">
<div class="field"><label for="name">نام سرور</label><input id="name" name="name" required maxlength="120"></div>
<div class="field"><label for="host">Host یا IP (بدون http و مسیر)</label><input id="host" name="host" required placeholder="ibsng.example.com"></div>
<div class="field"><label for="port">پورت Web Panel</label><select id="port" name="port"><option value="80">80 — HTTP</option><option value="443">443 — HTTPS</option></select></div>
<div class="field"><label for="isp_name">ISP name</label><input id="isp_name" name="isp_name" value="Main" required></div>
<div class="field"><label for="username">نام کاربری مدیر IBSng</label><input id="username" name="username" required autocomplete="off"></div>
<div class="field"><label for="password">رمز عبور مدیر IBSng</label><input id="password" name="password" type="password" required autocomplete="new-password"></div>
</div>
<button class="button primary" type="submit">تست اتصال و ذخیره سرور</button>
</form>
</section>
<section class="panel panel-card" style="margin-top:20px">
<h2>سرورهای ثبت‌شده</h2>
<?php if (!$servers): ?><div class="empty">هنوز سروری اضافه نشده است.</div><?php endif; ?>
<?php foreach ($servers as $server): ?>
<article class="server panel">
<div class="server-head"><h3><?= e((string)$server['name']) ?> <?= (int)$server['is_default'] === 1 ? '· پیش‌فرض' : '' ?></h3><span class="status-chip"><?= (int)$server['is_active'] === 1 ? 'فعال' : 'غیرفعال' ?></span></div>
<div class="meta" dir="ltr"><?= e((string)$server['host']) ?>:<?= (int)$server['port'] ?></div>
<div class="meta">ISP name: <?= e((string)$server['isp_name']) ?> · نام کاربری: <?= e((string)$server['username']) ?></div>
<div class="meta">آخرین تست: <?= e((string)($server['last_test_at'] ?? 'تست نشده')) ?><?= !empty($server['last_error']) ? ' · خطا: ' . e((string)$server['last_error']) : '' ?></div>
<details style="margin-top:12px"><summary class="button">ویرایش تنظیمات</summary>
<form method="post" style="margin-top:14px">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$server['id'] ?>">
<div class="grid">
<div class="field"><label>نام سرور</label><input name="name" required value="<?= e((string)$server['name']) ?>"></div>
<div class="field"><label>Host</label><input name="host" required value="<?= e((string)$server['host']) ?>"></div>
<div class="field"><label>پورت</label><select name="port"><option value="80"<?= (int)$server['port'] === 80 ? ' selected' : '' ?>>80 — HTTP</option><option value="443"<?= (int)$server['port'] === 443 ? ' selected' : '' ?>>443 — HTTPS</option></select></div>
<div class="field"><label>ISP name</label><input name="isp_name" value="<?= e((string)$server['isp_name']) ?>"></div>
<div class="field"><label>نام کاربری جدید (خالی = حفظ قبلی)</label><input name="username" autocomplete="off"></div>
<div class="field"><label>رمز جدید (خالی = حفظ قبلی)</label><input name="password" type="password" autocomplete="new-password"></div>
</div><button class="button primary" type="submit">تست اتصال و ذخیره تغییرات</button>
</form></details>
<div class="actions">
<form method="post"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="test"><input type="hidden" name="id" value="<?= (int)$server['id'] ?>"><button class="button" type="submit">Test Connection</button></form>
<form method="post"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="default"><input type="hidden" name="id" value="<?= (int)$server['id'] ?>"><button class="button" type="submit">انتخاب به‌عنوان پیش‌فرض</button></form>
<form method="post"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$server['id'] ?>"><button class="button" type="submit"><?= (int)$server['is_active'] === 1 ? 'غیرفعال‌سازی' : 'فعال‌سازی' ?></button></form>
<form method="post" onsubmit="return confirm('سرور حذف شود؟ حذف فقط در صورت نداشتن داده وابسته مجاز است.');"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$server['id'] ?>"><button class="button danger" type="submit">حذف</button></form>
</div>
</article>
<?php endforeach; ?>
</section>
<footer><a href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a></footer>
</main>
<script>
(function(){
const pairs={"ATD IBSng Store · مدیریت":"ATD IBSng Store · Admin","داشبورد":"Dashboard","سرورهای IBSng":"IBSng Servers","نگاشت پلن/گروه":"Plan / Group Mapping","بازگشت به فروشگاه":"Back to Store","افزودن سرور IBSng":"Add IBSng Server","مدیریت سرورهای IBSng":"IBSng Server Management","نام سرور":"Server Name","Host":"Host","پورت":"Port","ISP name":"ISP Name","نام کاربری":"Username","رمز عبور":"Password","تست اتصال و ذخیره تغییرات":"Test Connection & Save Changes","Test Connection":"Test Connection","انتخاب به‌عنوان پیش‌فرض":"Set as Default","غیرفعال‌سازی":"Disable","فعال‌سازی":"Enable","حذف":"Delete","حذف سرور":"Delete Server","ویرایش تنظیمات":"Edit Settings","نام کاربری جدید (خالی = حفظ قبلی)":"New Username (blank = keep current)","رمز جدید (خالی = حفظ قبلی)":"New Password (blank = keep current)","درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.":"Invalid request. Please refresh the page.","نام، Host، نام کاربری و رمز عبور IBSng الزامی است.":"Server name, host, username and IBSng password are required.","پورت باید 80 یا 443 باشد.":"Port must be 80 or 443.","اتصال IBSng با موفقیت تأیید و سرور ذخیره شد.":"IBSng connection verified and server saved.","سرور موردنظر پیدا نشد.":"The selected server was not found.","تنظیمات سرور به‌روزرسانی شد و تست اتصال موفق بود.":"Server settings updated; connection test succeeded.","اتصال موفق است؛ ورود مدیر IBSng تأیید شد.":"Connection succeeded; IBSng admin authentication verified.","برای انتخاب سرور پیش‌فرض، ابتدا آن را فعال کنید.":"Activate the server before setting it as default.","نگاشت پلن و گروه IBSng":"IBSng Plan / Group Mapping","نگاشت پلن فروشگاه به گروه IBSng":"Store Plan to IBSng Group Mapping","نام گروه باید دقیقاً مطابق گروه موجود در IBSng وارد شود. در این بخش نگاشت به‌صورت دستی تعریف می‌شود؛ دریافت خودکار فهرست گروه‌ها هنوز اضافه نشده است.":"Enter the exact existing IBSng group name. Mapping is manual; automatic group-list fetching is not implemented.","نام پلن":"Plan Name","نام دقیق گروه IBSng":"Exact IBSng Group Name","نام دقیق گروه":"Exact Group Name","مثلاً: یک ماهه":"For example: One month","نام گروه در IBSng":"IBSng group name","ثبت نگاشت":"Save Mapping","نگاشت‌های ثبت‌شده":"Saved Mappings","ویرایش نگاشت":"Edit Mapping","ذخیره تغییرات":"Save Changes","این نگاشت حذف شود؟":"Delete this mapping?","نگاشت پلن فروشگاه به گروه IBSng اضافه شد.":"Store plan to IBSng group mapping added.","نگاشت پلن و گروه IBSng ذخیره شد.":"Plan and IBSng group mapping saved.","وضعیت نگاشت تغییر کرد.":"Mapping status changed.","نگاشت حذف شد.":"Mapping deleted.","برای این سرور هنوز نگاشتی ثبت نشده است.":"No mappings have been added for this server yet.","ابتدا از بخش سرورهای IBSng یک سرور اضافه کنید.":"Add an IBSng server first.","سرور، نام پلن و نام دقیق گروه IBSng الزامی هستند.":"Server, plan name and exact IBSng group name are required.","نام پلن و نام دقیق گروه IBSng الزامی هستند.":"Plan name and exact IBSng group name are required.","این نام پلن یا گروه برای همین سرور قبلاً ثبت شده یا ذخیره‌سازی با خطا مواجه شد.":"This plan or group name already exists for this server, or saving failed.","این گروه به محصول/پلن فروشگاه متصل است؛ ابتدا نگاشت محصول را تغییر دهید.":"This group is assigned to a Store product/plan. Change the product mapping first.","نگاشت موردنظر پیدا نشد.":"The selected mapping was not found.","عملیات نامعتبر است.":"Invalid operation.","عملیات انجام نشد.":"The operation could not be completed.","سرور IBSng":"IBSng Server","Plan Name (نام پلن فروشگاه)":"Plan Name","IBSng Group Name (نام دقیق گروه)":"IBSng Group Name"};
const reverse={"ATD IBSng Store · Admin":"ATD IBSng Store · مدیریت","Dashboard":"داشبورد","IBSng Servers":"سرورهای IBSng","Plan / Group Mapping":"نگاشت پلن/گروه","Back to Store":"بازگشت به فروشگاه","Add IBSng Server":"افزودن سرور IBSng","IBSng Server Management":"مدیریت سرورهای IBSng","Server Name":"نام سرور","Host":"Host","Port":"پورت","ISP Name":"ISP name","Username":"نام کاربری","Password":"رمز عبور","Test Connection & Save Changes":"تست اتصال و ذخیره تغییرات","Test Connection":"Test Connection","Set as Default":"انتخاب به‌عنوان پیش‌فرض","Disable":"غیرفعال‌سازی","Enable":"فعال‌سازی","Delete":"حذف","Delete Server":"حذف سرور","Edit Settings":"ویرایش تنظیمات","New Username (blank = keep current)":"نام کاربری جدید (خالی = حفظ قبلی)","New Password (blank = keep current)":"رمز جدید (خالی = حفظ قبلی)","Invalid request. Please refresh the page.":"درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.","Server name, host, username and IBSng password are required.":"نام، Host، نام کاربری و رمز عبور IBSng الزامی است.","Port must be 80 or 443.":"پورت باید 80 یا 443 باشد.","IBSng connection verified and server saved.":"اتصال IBSng با موفقیت تأیید و سرور ذخیره شد.","The selected server was not found.":"سرور موردنظر پیدا نشد.","Server settings updated; connection test succeeded.":"تنظیمات سرور به‌روزرسانی شد و تست اتصال موفق بود.","Connection succeeded; IBSng admin authentication verified.":"اتصال موفق است؛ ورود مدیر IBSng تأیید شد.","Activate the server before setting it as default.":"برای انتخاب سرور پیش‌فرض، ابتدا آن را فعال کنید.","IBSng Plan / Group Mapping":"نگاشت پلن و گروه IBSng","Store Plan to IBSng Group Mapping":"نگاشت پلن فروشگاه به گروه IBSng","Enter the exact existing IBSng group name. Mapping is manual; automatic group-list fetching is not implemented.":"نام گروه باید دقیقاً مطابق گروه موجود در IBSng وارد شود. در این بخش نگاشت به‌صورت دستی تعریف می‌شود؛ دریافت خودکار فهرست گروه‌ها هنوز اضافه نشده است.","Plan Name":"Plan Name (نام پلن فروشگاه)","Exact IBSng Group Name":"نام دقیق گروه IBSng","Exact Group Name":"نام دقیق گروه","For example: One month":"مثلاً: یک ماهه","IBSng group name":"نام گروه در IBSng","Save Mapping":"ثبت نگاشت","Saved Mappings":"نگاشت‌های ثبت‌شده","Edit Mapping":"ویرایش نگاشت","Save Changes":"ذخیره تغییرات","Delete this mapping?":"این نگاشت حذف شود؟","Store plan to IBSng group mapping added.":"نگاشت پلن فروشگاه به گروه IBSng اضافه شد.","Plan and IBSng group mapping saved.":"نگاشت پلن و گروه IBSng ذخیره شد.","Mapping status changed.":"وضعیت نگاشت تغییر کرد.","Mapping deleted.":"نگاشت حذف شد.","No mappings have been added for this server yet.":"برای این سرور هنوز نگاشتی ثبت نشده است.","Add an IBSng server first.":"ابتدا از بخش سرورهای IBSng یک سرور اضافه کنید.","Server, plan name and exact IBSng group name are required.":"سرور، نام پلن و نام دقیق گروه IBSng الزامی هستند.","Plan name and exact IBSng group name are required.":"نام پلن و نام دقیق گروه IBSng الزامی هستند.","This plan or group name already exists for this server, or saving failed.":"این نام پلن یا گروه برای همین سرور قبلاً ثبت شده یا ذخیره‌سازی با خطا مواجه شد.","This group is assigned to a Store product/plan. Change the product mapping first.":"این گروه به محصول/پلن فروشگاه متصل است؛ ابتدا نگاشت محصول را تغییر دهید.","The selected mapping was not found.":"نگاشت موردنظر پیدا نشد.","Invalid operation.":"عملیات نامعتبر است.","The operation could not be completed.":"عملیات انجام نشد.","IBSng Server":"سرور IBSng","IBSng Group Name":"IBSng Group Name (نام دقیق گروه)"};
const root=document.documentElement;
const button=document.getElementById('langToggle');
function walk(node,map){
 if(node.nodeType===Node.TEXT_NODE){const raw=node.nodeValue||'',t=raw.trim();if(map[t])node.nodeValue=raw.replace(t,map[t]);return;}
 if(node.nodeType!==Node.ELEMENT_NODE)return;
 ['placeholder','title','aria-label'].forEach(a=>{const v=node.getAttribute(a);if(v&&map[v])node.setAttribute(a,map[v]);});
 if((node.matches('input[type=submit],input[type=button]'))&&node.value&&map[node.value])node.value=map[node.value];
 node.childNodes.forEach(n=>walk(n,map));
}
function setLang(lang){walk(document.body,lang==='en'?pairs:reverse);root.lang=lang;root.dir=lang==='fa'?'rtl':'ltr';
 document.body.style.fontFamily=lang==='fa'?'Vazirmatn,Tahoma,sans-serif':'"Segoe UI",Arial,sans-serif';
 if(button)button.textContent=lang==='en'?'فارسی':'English';
 try{localStorage.setItem('atd-ibsng-language',lang)}catch(e){}
}
let lang='en';try{lang=localStorage.getItem('atd-ibsng-language')||'en'}catch(e){}
if(button)button.addEventListener('click',()=>setLang(root.lang==='fa'?'en':'fa'));
setLang(lang);
})();
</script>
</body>
</html>
