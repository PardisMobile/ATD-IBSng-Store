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
];

function install_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function install_db(array $v): PDO
{
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', trim($v['db_host']), trim($v['db_name']));
    return new PDO($dsn, trim($v['db_user']), $v['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

$configPath = dirname(__DIR__) . '/config.php';
if (is_file($configPath)) {
    $success = 'این سامانه قبلاً نصب شده است. برای امنیت، نصب‌کننده را حذف یا دسترسی آن را مسدود کنید.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !is_file($configPath)) {
    foreach ($values as $key => $default) {
        $values[$key] = (string)($_POST[$key] ?? $default);
    }
    $token = (string)($_POST['csrf'] ?? '');
    if (!isset($_SESSION['install_csrf']) || !hash_equals($_SESSION['install_csrf'], $token)) {
        $errors[] = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی و دوباره تلاش کنید.';
    } else {
        $action = (string)($_POST['action'] ?? 'install');
        try {
            $pdo = install_db($values);
            if ($action === 'test') {
                $success = 'اتصال به پایگاه داده با موفقیت برقرار شد.';
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
                    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
                    setting_value TEXT NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                $stmt = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
                $stmt->execute(['site_title', trim($values['site_title']) ?: 'ATD IBSng Store']);

                $baseUrl = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php')), '/');
                $config = [
                    'site_title' => trim($values['site_title']) ?: 'ATD IBSng Store',
                    'db_host' => trim($values['db_host']),
                    'db_name' => trim($values['db_name']),
                    'db_user' => trim($values['db_user']),
                    'db_pass' => $values['db_pass'],
                    'base_url' => $baseUrl === '/' ? '' : $baseUrl,
                    'installed' => true,
                ];
                $php = "<?php\nreturn " . var_export($config, true) . ";\n";
                if (file_put_contents($configPath, $php, LOCK_EX) === false) {
                    throw new RuntimeException('نوشتن فایل تنظیمات ناموفق بود. دسترسی نوشتن پوشه اصلی را بررسی کنید.');
                }
                @chmod($configPath, 0640);
                $success = 'نصب اولیه با موفقیت انجام شد. برای امنیت، پوشه install را حذف یا مسدود کنید.';
            }
        } catch (Throwable $e) {
            $errors[] = 'اتصال یا نصب انجام نشد: ' . $e->getMessage();
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
.installer{max-width:720px;margin:6vh auto;padding:clamp(22px,5vw,42px)}.installer h1{font-size:2rem}.field{display:grid;gap:8px;margin:16px 0}.field label{color:var(--muted);font-size:.9rem}.field input{width:100%;padding:13px 14px;border:1px solid var(--border);border-radius:12px;background:#0d1426;color:var(--text);font:inherit;outline:none}.field input:focus{border-color:var(--blue);box-shadow:0 0 0 3px #5795ff20}.grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}.notice{padding:14px;border-radius:12px;margin:12px 0;line-height:1.8}.ok{background:#44d6a018;border:1px solid #44d6a044}.err{background:#ff718018;border:1px solid #ff718044}.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px}@media(max-width:560px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<main class="installer panel">
<div class="eyebrow">راه‌اندازی گام‌به‌گام</div>
<h1>نصب فروشگاه ATD IBSng</h1>
<p class="muted">اطلاعات سایت و پایگاه داده را وارد کنید. پیش از نصب می‌توانید اتصال دیتابیس را آزمایش کنید.</p>
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
<div class="field"><label for="db_pass">رمز عبور دیتابیس</label><input id="db_pass" name="db_pass" type="password" value="<?= install_e($values['db_pass']) ?>"></div>
</div>
<div class="actions">
<button class="button primary" type="submit" name="action" value="test">تست اتصال دیتابیس</button>
<button class="button primary" type="submit" name="action" value="install">نصب فروشگاه</button>
</div>
</form>
<?php else: ?><p><a class="button primary" href="../">رفتن به فروشگاه</a></p><?php endif; ?>
</main>
</body></html>
