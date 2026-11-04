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
    $serviceName = ibsng_server_input('service_name');
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
    return compact('name', 'serviceName', 'host', 'port', 'isp', 'username', 'password');
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

                $stmt = $pdo->prepare('INSERT INTO ibsng_servers (name, service_name, host, port, scheme, isp_name, username, password_ciphertext, is_active, is_default, last_test_at, last_error) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 0, NOW(), NULL)');
                $stmt->execute([
                    $fields['name'], ($fields['serviceName'] !== '' ? $fields['serviceName'] : null), $fields['host'], $fields['port'], $fields['port'] === 443 ? 'https' : 'http',
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
                $serviceName = ibsng_server_input('service_name');
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

                $stmt = $pdo->prepare('UPDATE ibsng_servers SET name=?, service_name=?, host=?, port=?, scheme=?, isp_name=?, username=?, password_ciphertext=?, last_test_at=NOW(), last_error=NULL WHERE id=?');
                $stmt->execute([
                    $name, ($serviceName !== '' ? $serviceName : null), $host, $port, $port === 443 ? 'https' : 'http', $isp, $username,
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

$servers = $pdo->query('SELECT id,name,service_name,host,port,scheme,isp_name,username,is_active,is_default,last_test_at,last_error,created_at FROM ibsng_servers ORDER BY is_default DESC, id DESC')->fetchAll();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title data-ui-title-key="title_admin_ibsng_servers">سرورهای IBSng | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto}.admin-top{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:24px 0;flex-wrap:wrap}.admin-top form{margin:0}.admin-top button{border:1px solid var(--border);background:var(--surface-2);color:var(--text);font:inherit;cursor:pointer}
.layout{display:grid;grid-template-columns:minmax(0,1fr);gap:20px}.panel-card{padding:clamp(18px,3vw,26px);min-width:0}
.field{display:grid;gap:8px;margin:14px 0;min-width:0}.field label{color:var(--muted);font-size:.9rem}.field input,.field select{width:100%;min-width:0;min-height:var(--ui-control-height,46px);padding:12px 14px;border:1px solid var(--border);border-radius:var(--ui-radius-md,12px);background:var(--control-bg,var(--surface-2));color:var(--text);font:inherit}.field input:focus-visible,.field select:focus-visible{outline:2px solid var(--accent);outline-offset:2px;box-shadow:0 0 0 3px var(--ui-focus-ring)}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 16px}
.notice{padding:13px 15px;border-radius:var(--ui-radius-md,12px);margin:14px 0;line-height:1.8}.ok{background:color-mix(in srgb,var(--success,var(--green)) 10%,transparent);border:1px solid color-mix(in srgb,var(--success,var(--green)) 30%,transparent)}.err{background:color-mix(in srgb,var(--danger,#ff7180) 10%,transparent);border:1px solid color-mix(in srgb,var(--danger,#ff7180) 30%,transparent)}
.server{padding:clamp(16px,3vw,22px);margin:14px 0;min-width:0;border:1px solid var(--border);border-radius:var(--ui-radius-lg,16px);transition:border-color var(--ui-motion-fast,160ms) ease,background var(--ui-motion-fast,160ms) ease}.server:hover{border-color:color-mix(in srgb,var(--accent) 42%,var(--border));background:color-mix(in srgb,var(--surface-2) 35%,transparent)}.server-head{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center}.server-head h3{margin:0;overflow-wrap:anywhere}.meta{color:var(--muted);line-height:1.9;overflow-wrap:anywhere}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}.actions form{display:flex;min-width:0}.actions button{font:inherit;cursor:pointer;border:1px solid var(--border);background:var(--surface-2);color:var(--text);min-height:var(--ui-control-height,44px);padding:10px 14px;border-radius:var(--ui-radius-md,12px);white-space:normal}.actions button:focus-visible,.admin-top button:focus-visible{outline:2px solid var(--accent);outline-offset:2px;box-shadow:0 0 0 3px var(--ui-focus-ring)}.actions .danger{color:var(--danger,#ff8e9a)}.actions .primary{background:linear-gradient(120deg,var(--accent-light),var(--accent-dark));border:0;color:white}.empty{padding:26px;text-align:center;color:var(--muted)}
@media(max-width:600px){.grid{grid-template-columns:1fr}.admin-shell{width:calc(100% - 24px)}.admin-top{align-items:flex-start;flex-direction:column}.actions>*{flex:1 1 auto}}
</style>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</head>
<body>
<main class="admin-shell">
<?php require __DIR__ . '/_navigation.php'; ?>
<section class="panel panel-card">
<div class="eyebrow">IBSng</div><h1 data-ui-text="server_title">سرورهای IBSng</h1>
<p class="muted" data-ui-text="server_intro">در این مرحله، تست اتصال با ورود واقعی به Web Panel انجام می‌شود. رمز عبور در دیتابیس به‌صورت رمزنگاری‌شده نگهداری می‌شود.</p>
<?php if ($message !== ''): ?><div class="notice ok" role="status" aria-live="polite"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err" role="alert" aria-live="assertive"><?= e($error) ?></div><?php endif; ?>
</section>
<section class="panel panel-card">
<h2 data-ui-text="server_add_heading">افزودن سرور</h2>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="add">
<div class="grid">
<div class="field"><label for="name" data-fa="نام سرور (داخلی)" data-en="Internal server name">نام سرور (داخلی)</label><input id="name" name="name" required maxlength="120"></div>
<div class="field"><label for="service_name" data-fa="نام سرویس (اختیاری؛ برای نمایش به مشتری)" data-en="Service name (optional; customer-facing)">نام سرویس (اختیاری؛ برای نمایش به مشتری)</label><input id="service_name" name="service_name" maxlength="150" placeholder="مثلاً: سرویس مولتی لوکیشن" data-ui-placeholder="server_service_placeholder"></div>
<div class="field"><label for="host" data-ui-text="server_host_label">Host یا IP (بدون http و مسیر)</label><input id="host" name="host" required placeholder="ibsng.example.com"></div>
<div class="field"><label for="port" data-ui-text="server_web_panel_port">پورت Web Panel</label><select id="port" name="port"><option value="80">80 — HTTP</option><option value="443">443 — HTTPS</option></select></div>
<div class="field"><label for="isp_name">ISP name</label><input id="isp_name" name="isp_name" value="Main" required></div>
<div class="field"><label for="username" data-ui-text="server_admin_username">نام کاربری مدیر IBSng</label><input id="username" name="username" required autocomplete="off"></div>
<div class="field"><label for="password" data-ui-text="server_admin_password">رمز عبور مدیر IBSng</label><input id="password" name="password" type="password" required autocomplete="new-password"></div>
</div>
<button class="button primary" type="submit" data-ui-text="server_save_test">تست اتصال و ذخیره سرور</button>
</form>
</section>
<section class="panel panel-card">
<h2 data-ui-text="server_saved_heading">سرورهای ثبت‌شده</h2>
<?php if (!$servers): ?><div class="empty" data-ui-text="server_empty">هنوز سروری اضافه نشده است.</div><?php endif; ?>
<?php foreach ($servers as $server): ?>
<article class="server panel">
<div class="server-head"><h3><?= e((string)$server['name']) ?> <?= (int)$server['is_default'] === 1 ? '<span data-ui-text="server_default_label">· پیش‌فرض</span>' : '' ?></h3><span class="status-chip" data-ui-status="<?= (int)$server['is_active'] === 1 ? 'active' : 'inactive' ?>"><?= (int)$server['is_active'] === 1 ? 'فعال' : 'غیرفعال' ?></span></div>
<div class="meta" dir="ltr"><?= e((string)$server['host']) ?>:<?= (int)$server['port'] ?></div>
<div class="meta">ISP name: <?= e((string)$server['isp_name']) ?> <span data-ui-text="server_username_label">· نام کاربری:</span> <?= e((string)$server['username']) ?></div>
<div class="meta"><span data-ui-text="server_last_test_label">آخرین تست:</span> <?php if (($server['last_test_at'] ?? null) === null): ?><span data-ui-text="server_not_tested">تست نشده</span><?php else: ?><?= e((string)$server['last_test_at']) ?><?php endif; ?><?php if (!empty($server['last_error'])): ?> <span data-ui-text="server_error_label">· خطا:</span> <?= e((string)$server['last_error']) ?><?php endif; ?></div>
<details><summary class="button" data-fa="ویرایش تنظیمات" data-en="Edit settings">ویرایش تنظیمات</summary>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$server['id'] ?>">
<div class="grid">
<div class="field"><label data-fa="نام سرور (داخلی)" data-en="Internal server name">نام سرور (داخلی)</label><input name="name" required value="<?= e((string)$server['name']) ?>"></div>
<div class="field"><label data-fa="نام سرویس (اختیاری؛ برای نمایش به مشتری)" data-en="Service name (optional; customer-facing)">نام سرویس (اختیاری؛ برای نمایش به مشتری)</label><input name="service_name" maxlength="150" value="<?= e((string)($server['service_name'] ?? '')) ?>" placeholder="مثلاً: سرویس مولتی لوکیشن" data-ui-placeholder="server_service_placeholder"></div>
<div class="field"><label>Host</label><input name="host" required value="<?= e((string)$server['host']) ?>"></div>
<div class="field"><label data-fa="پورت" data-en="Port">پورت</label><select name="port"><option value="80"<?= (int)$server['port'] === 80 ? ' selected' : '' ?>>80 — HTTP</option><option value="443"<?= (int)$server['port'] === 443 ? ' selected' : '' ?>>443 — HTTPS</option></select></div>
<div class="field"><label>ISP name</label><input name="isp_name" value="<?= e((string)$server['isp_name']) ?>"></div>
<div class="field"><label data-fa="نام کاربری جدید (خالی = حفظ قبلی)" data-en="New username (blank = keep current)">نام کاربری جدید (خالی = حفظ قبلی)</label><input name="username" autocomplete="off"></div>
<div class="field"><label data-fa="رمز جدید (خالی = حفظ قبلی)" data-en="New password (blank = keep current)">رمز جدید (خالی = حفظ قبلی)</label><input name="password" type="password" autocomplete="new-password"></div>
</div><button class="button primary" type="submit" data-fa="تست اتصال و ذخیره تغییرات" data-en="Test connection and save changes">تست اتصال و ذخیره تغییرات</button>
</form></details>
<div class="actions">
<form method="post"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="test"><input type="hidden" name="id" value="<?= (int)$server['id'] ?>"><button class="button" type="submit" data-fa="تست اتصال" data-en="Test connection">تست اتصال</button></form>
<form method="post"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="default"><input type="hidden" name="id" value="<?= (int)$server['id'] ?>"><button class="button" type="submit" data-fa="انتخاب به‌عنوان پیش‌فرض" data-en="Set as default">انتخاب به‌عنوان پیش‌فرض</button></form>
<form method="post"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$server['id'] ?>"><button class="button" type="submit" data-fa="<?= (int)$server['is_active'] === 1 ? 'غیرفعال‌سازی' : 'فعال‌سازی' ?>" data-en="<?= (int)$server['is_active'] === 1 ? 'Disable' : 'Enable' ?>"><?= (int)$server['is_active'] === 1 ? 'غیرفعال‌سازی' : 'فعال‌سازی' ?></button></form>
<form method="post" data-confirm-fa="سرور حذف شود؟ حذف فقط در صورت نداشتن داده وابسته مجاز است." data-confirm-en="Delete this server? Deletion is allowed only when no dependent data exists."><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$server['id'] ?>"><button class="button danger" type="submit" data-fa="حذف" data-en="Delete">حذف</button></form>
</div>
</article>
<?php endforeach; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
</main>
</body>
</html>
