<?php
declare(strict_types=1);

session_start();

$errors = [];
$success = '';
$values = [
    'site_title' => 'ATD IBSng Store',
    'db_host' => 'localhost',
    'db_name' => '',
    'db_user' => '',
    'db_pass' => '',
    'admin_username' => 'admin',
    'admin_display_name' => 'مدیر سایت',
];

function install_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function install_db(array $v): PDO
{
    $host = trim((string)$v['db_host']);
    $name = trim((string)$v['db_name']);
    if ($host === '' || $name === '' || trim((string)$v['db_user']) === '') {
        throw new InvalidArgumentException('اطلاعات اتصال دیتابیس کامل نیست.');
    }
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);
    return new PDO($dsn, trim((string)$v['db_user']), (string)$v['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function install_schema(PDO $pdo): void
{
    $schemaPath = dirname(__DIR__) . '/database/schema.sql';
    $schema = file_get_contents($schemaPath);
    if ($schema === false || trim($schema) === '') {
        throw new RuntimeException('فایل schema دیتابیس خوانده نشد.');
    }

    // This schema contains ordinary SQL statements only (no stored procedures/delimiters).
    $lines = preg_split('/\R/', $schema) ?: [];
    $clean = [];
    foreach ($lines as $line) {
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }
        $clean[] = $line;
    }
    $statements = preg_split('/;\s*(?:\R|$)/', implode("\n", $clean)) ?: [];
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
}

$configPath = dirname(__DIR__) . '/config.php';
if (is_file($configPath)) {
    $success = 'این سامانه قبلاً نصب شده است. نصب‌کننده قفل شده است.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !is_file($configPath)) {
    foreach ($values as $key => $default) {
        $values[$key] = (string)($_POST[$key] ?? $default);
    }
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $adminPassword = (string)($_POST['admin_password'] ?? '');
    $adminPasswordConfirm = (string)($_POST['admin_password_confirm'] ?? '');
    $token = (string)($_POST['csrf'] ?? '');

    if (!isset($_SESSION['install_csrf']) || !hash_equals($_SESSION['install_csrf'], $token)) {
        $errors[] = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی و دوباره تلاش کنید.';
    } elseif ((string)($_POST['action'] ?? '') === 'test') {
        try {
            install_db(array_merge($values, ['db_pass' => $dbPass]));
            $success = 'اتصال به پایگاه داده با موفقیت برقرار شد.';
        } catch (Throwable $e) {
            $errors[] = 'اتصال برقرار نشد. اطلاعات دیتابیس و دسترسی کاربر را بررسی کنید.';
        }
    } else {
        $username = trim($values['admin_username']);
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,100}$/', $username)) {
            $errors[] = 'نام کاربری مدیر باید ۳ تا ۱۰۰ کاراکتر و شامل حروف انگلیسی، عدد یا . _ - باشد.';
        }
        if (strlen($adminPassword) < 12) {
            $errors[] = 'رمز مدیر باید حداقل ۱۲ کاراکتر باشد.';
        }
        if (!hash_equals($adminPassword, $adminPasswordConfirm)) {
            $errors[] = 'تکرار رمز عبور مدیر یکسان نیست.';
        }

        if (!$errors) {
            try {
                $pdo = install_db(array_merge($values, ['db_pass' => $dbPass]));
                install_schema($pdo);

                $siteTitle = trim($values['site_title']) ?: 'ATD IBSng Store';
                $setting = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
                $setting->execute(['site_title', $siteTitle]);

                $admin = $pdo->prepare('INSERT INTO admin_users (username, password_hash, display_name, is_active) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), display_name = VALUES(display_name), is_active = 1');
                $admin->execute([$username, password_hash($adminPassword, PASSWORD_DEFAULT), trim($values['admin_display_name']) ?: 'مدیر سایت']);

                $baseUrl = rtrim(dirname(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'))), '/');
                $config = [
                    'site_title' => $siteTitle,
                    'db_host' => trim($values['db_host']),
                    'db_name' => trim($values['db_name']),
                    'db_user' => trim($values['db_user']),
                    'db_pass' => $dbPass,
                    'encryption_key' => base64_encode(random_bytes(32)),
                    'base_url' => $baseUrl === '/' ? '' : $baseUrl,
                    'installed' => true,
                ];
                $php = "<?php\nreturn " . var_export($config, true) . ";\n";
                if (file_put_contents($configPath, $php, LOCK_EX) === false) {
                    throw new RuntimeException('فایل تنظیمات نوشته نشد؛ دسترسی نوشتن پوشه اصلی را بررسی کنید.');
                }
                @chmod($configPath, 0640);
                session_regenerate_id(true);
                $success = 'نصب انجام شد. ورود مدیر از مسیر /admin/login.php امکان‌پذیر است. نصب‌کننده اکنون قفل شده است.';
            } catch (Throwable $e) {
                $errors[] = 'نصب کامل نشد. دسترسی‌های دیتابیس و امکان نوشتن فایل config.php را بررسی کنید؛ اطلاعات فنی محرمانه نمایش داده نمی‌شوند.';
            }
        }
    }
}

if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>نصب ATD IBSng Store</title><link rel="stylesheet" href="../assets/css/app.css">
<style>
.installer{max-width:760px;margin:6vh auto;padding:clamp(22px,5vw,42px)}.installer h1{font-size:2rem}.field{display:grid;gap:8px;margin:16px 0}.field label{color:var(--muted);font-size:.9rem}.field input{width:100%;padding:13px 14px;border:1px solid var(--border);border-radius:12px;background:#0d1426;color:var(--text);font:inherit;outline:none}.field input:focus{border-color:var(--blue);box-shadow:0 0 0 3px #5795ff20}.grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}.notice{padding:14px;border-radius:12px;margin:12px 0;line-height:1.8}.ok{background:#44d6a018;border:1px solid #44d6a044}.err{background:#ff718018;border:1px solid #ff718044}.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px}@media(max-width:560px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<main class="installer panel">
<div class="eyebrow">راه‌اندازی گام‌به‌گام</div>
<h1>نصب فروشگاه ATD IBSng</h1>
<p class="muted">اطلاعات سایت، پایگاه داده و حساب مدیر اولیه را وارد کنید. ابتدا اتصال دیتابیس را آزمایش کنید.</p>
<?php foreach ($errors as $error): ?><div class="notice err"><?= install_e($error) ?></div><?php endforeach; ?>
<?php if ($success !== ''): ?><div class="notice ok"><?= install_e($success) ?></div><?php endif; ?>
<?php if (!is_file($configPath)): ?>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?= install_e($_SESSION['install_csrf']) ?>">
<div class="field"><label for="site_title">عنوان سایت</label><input id="site_title" name="site_title" required value="<?= install_e($values['site_title']) ?>"></div>
<div class="grid">
<div class="field"><label for="db_host">میزبان دیتابیس</label><input id="db_host" name="db_host" required value="<?= install_e($values['db_host']) ?>"></div>
<div class="field"><label for="db_name">نام دیتابیس</label><input id="db_name" name="db_name" required value="<?= install_e($values['db_name']) ?>"></div>
<div class="field"><label for="db_user">نام کاربری دیتابیس</label><input id="db_user" name="db_user" required value="<?= install_e($values['db_user']) ?>"></div>
<div class="field"><label for="db_pass">رمز عبور دیتابیس</label><input id="db_pass" name="db_pass" type="password" value="<?= install_e($dbPass ?? '') ?>"></div>
</div>
<h2>حساب مدیر اولیه</h2>
<div class="grid">
<div class="field"><label for="admin_username">نام کاربری مدیر</label><input id="admin_username" name="admin_username" required autocomplete="username" value="<?= install_e($values['admin_username']) ?>"></div>
<div class="field"><label for="admin_display_name">نام نمایشی</label><input id="admin_display_name" name="admin_display_name" value="<?= install_e($values['admin_display_name']) ?>"></div>
<div class="field"><label for="admin_password">رمز عبور مدیر (حداقل ۱۲ کاراکتر)</label><input id="admin_password" name="admin_password" type="password" required minlength="12" autocomplete="new-password"></div>
<div class="field"><label for="admin_password_confirm">تکرار رمز مدیر</label><input id="admin_password_confirm" name="admin_password_confirm" type="password" required minlength="12" autocomplete="new-password"></div>
</div>
<div class="actions">
<button class="button primary" type="submit" name="action" value="test">تست اتصال دیتابیس</button>
<button class="button primary" type="submit" name="action" value="install">نصب فروشگاه</button>
</div>
</form>
<?php else: ?><p><a class="button primary" href="../admin/login.php">ورود مدیر</a> <a class="button primary" href="../">رفتن به فروشگاه</a></p><?php endif; ?>
</main>
</body></html>
