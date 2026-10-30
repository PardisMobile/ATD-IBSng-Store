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
        try {
            if ($action === 'add') {
                $fields = ibsng_validate_server_fields();
                (new IBSngClient($fields['host'], $fields['port'], $fields['username'], $fields['password']))->testConnection();

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
                (new IBSngClient($host, $port, $username, $password))->testConnection();

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
                (new IBSngClient(
                    (string)$server['host'], (int)$server['port'], (string)$server['username'],
                    app_decrypt_secret((string)$server['password_ciphertext'])
                ))->testConnection();
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
                $pdo->prepare('UPDATE ibsng_servers SET is_active = IF(is_active=1,0,1) WHERE id=?')->execute([$id]);
                $message = 'وضعیت فعال‌بودن سرور تغییر کرد.';
            } elseif ($action === 'delete') {
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
            $error = $e instanceof InvalidArgumentException || $e instanceof RuntimeException
                ? $e->getMessage()
                : 'عملیات انجام نشد. وضعیت دیتابیس و تنظیمات را بررسی کنید.';
            if (($_POST['action'] ?? '') === 'test' && $id > 0) {
                try {
                    $pdo->prepare('UPDATE ibsng_servers SET last_error=? WHERE id=?')->execute([$error, $id]);
                } catch (Throwable $ignored) {
                    // Keep the original, user-safe test error as the page result.
                }
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
<nav><a class="button" href="<?= e(app_url('admin/')) ?>">داشبورد</a></nav>
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
</body>
</html>
