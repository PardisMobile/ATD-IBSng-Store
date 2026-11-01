<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $isSecure,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

$locale = (string)($_POST['locale'] ?? $_GET['lang'] ?? $_SESSION['install_locale'] ?? 'fa');
$locale = in_array($locale, ['fa', 'en'], true) ? $locale : 'fa';
$_SESSION['install_locale'] = $locale;
$rtl = $locale === 'fa';

$copy = [
 'fa' => [
  'title'=>'راه‌اندازی ATD IBSng Store','tag'=>'راه‌اندازی ساده و امن','intro'=>'فروشگاهتان را در چند مرحله آماده کنید.',
  'lang'=>'English','site'=>'عنوان فروشگاه','db'=>'اتصال پایگاه داده','host'=>'میزبان','name'=>'نام دیتابیس','dbuser'=>'کاربر دیتابیس','dbpass'=>'رمز دیتابیس',
  'admin'=>'حساب مدیر','username'=>'نام کاربری','display'=>'نام نمایشی','email'=>'ایمیل بازیابی مدیر','password'=>'رمز عبور (حداقل ۱۲ کاراکتر)','confirm'=>'تکرار رمز عبور',
  'test'=>'آزمایش اتصال','install'=>'نصب فروشگاه','done'=>'نصب با موفقیت انجام شد.','locked'=>'این فروشگاه قبلاً نصب شده است؛ نصب‌کننده قفل است.',
  'testok'=>'اتصال دیتابیس موفق بود.','badcsrf'=>'درخواست معتبر نیست. صفحه را تازه‌سازی کنید.','dbbad'=>'اتصال برقرار نشد. اطلاعات دیتابیس و مجوزها را بررسی کنید.',
  'missing'=>'اطلاعات اتصال دیتابیس کامل نیست.','schema'=>'فایل ساختار دیتابیس خوانده نشد.','usernamebad'=>'نام کاربری باید ۳ تا ۱۰۰ حرف انگلیسی، عدد یا . _ - باشد.',
  'passwordbad'=>'رمز عبور باید حداقل ۱۲ کاراکتر باشد.','confirmbad'=>'تکرار رمز عبور یکسان نیست.','emailbad'=>'ایمیل مدیر معتبر نیست.',
  'installbad'=>'نصب کامل نشد. اطلاعات دیتابیس و مجوز نوشتن فایل تنظیمات را بررسی کنید.',
  'success'=>'آماده‌اید!','sign'=>'ورود به پنل مدیریت','store'=>'مشاهده فروشگاه','step'=>'نصب مرحله‌ای','note'=>'تنظیمات دیگر را پس از نصب از پنل مدیریت تغییر می‌دهید.',
 ],
 'en' => [
  'title'=>'ATD IBSng Store Setup','tag'=>'Simple & secure setup','intro'=>'Get your store ready in a few clear steps.',
  'lang'=>'فارسی','site'=>'Store title','db'=>'Database connection','host'=>'Database host','name'=>'Database name','dbuser'=>'Database user','dbpass'=>'Database password',
  'admin'=>'Administrator account','username'=>'Username','display'=>'Display name','email'=>'Administrator recovery email','password'=>'Password (at least 12 characters)','confirm'=>'Confirm password',
  'test'=>'Test connection','install'=>'Install store','done'=>'Installation completed successfully.','locked'=>'This store is already installed. The installer is locked.',
  'testok'=>'Database connection succeeded.','badcsrf'=>'Invalid request. Refresh the page and try again.','dbbad'=>'Could not connect. Check database details and permissions.',
  'missing'=>'Database connection details are incomplete.','schema'=>'Could not read the database schema file.','usernamebad'=>'Username must be 3–100 characters using letters, numbers, dot, underscore or hyphen.',
  'passwordbad'=>'Password must contain at least 12 characters.','confirmbad'=>'The passwords do not match.','emailbad'=>'Enter a valid administrator email address.',
  'installbad'=>'Installation failed. Check database details and permission to write the configuration file.',
  'success'=>'You are all set!','sign'=>'Open admin panel','store'=>'Visit store','step'=>'Guided installation','note'=>'Other settings can be changed later in the admin panel.',
 ],
];
function it(string $key): string { global $copy, $locale; return $copy[$locale][$key] ?? $key; }
function install_e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$errors = [];
$success = '';
$values = [
 'site_title'=>'ATD IBSng Store','db_host'=>'localhost','db_name'=>'','db_user'=>'','db_pass'=>'',
 'admin_username'=>'admin','admin_display_name'=>'','admin_email'=>'',
];
function install_db(array $v): PDO {
 $host=trim((string)$v['db_host']); $name=trim((string)$v['db_name']); $user=trim((string)$v['db_user']);
 if ($host==='' || $name==='' || $user==='') throw new InvalidArgumentException(it('missing'));
 $dsn=sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',$host,$name);
 return new PDO($dsn,$user,(string)$v['db_pass'],[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES=>false,
 ]);
}
function install_schema(PDO $pdo): void {
 $path=dirname(__DIR__).'/database/schema.sql'; $schema=file_get_contents($path);
 if ($schema===false || trim($schema)==='') throw new RuntimeException(it('schema'));
 $lines=preg_split('/\R/',$schema) ?: []; $clean=[];
 foreach($lines as $line) if(!preg_match('/^\s*--/',$line)) $clean[]=$line;
 $statements=preg_split('/;\s*(?:\R|$)/',implode("\n",$clean)) ?: [];
 foreach($statements as $statement) { $statement=trim($statement); if($statement!=='') $pdo->exec($statement); }
}
$configPath=dirname(__DIR__).'/config.php';
if(is_file($configPath)) $success=it('locked');

if($_SERVER['REQUEST_METHOD']==='POST' && !is_file($configPath)) {
 foreach($values as $key=>$default) $values[$key]=(string)($_POST[$key]??$default);
 $dbPass=(string)($_POST['db_pass']??'');
 $adminPassword=(string)($_POST['admin_password']??'');
 $confirm=(string)($_POST['admin_password_confirm']??'');
 $token=(string)($_POST['csrf']??'');
 if(!isset($_SESSION['install_csrf']) || !hash_equals($_SESSION['install_csrf'],$token)) {
  $errors[]=it('badcsrf');
 } elseif((string)($_POST['action']??'')==='test') {
  try { install_db(array_merge($values,['db_pass'=>$dbPass])); $success=it('testok'); }
  catch(Throwable $e) { $errors[]=it('dbbad'); }
 } else {
  $username=trim($values['admin_username']);
  if(!preg_match('/^[a-zA-Z0-9_.-]{3,100}$/',$username)) $errors[]=it('usernamebad');
  if(strlen($adminPassword)<12) $errors[]=it('passwordbad');
  if(!hash_equals($adminPassword,$confirm)) $errors[]=it('confirmbad');
  if(!filter_var(trim($values['admin_email']),FILTER_VALIDATE_EMAIL)) $errors[]=it('emailbad');
  if(!$errors) {
   try {
    $pdo=install_db(array_merge($values,['db_pass'=>$dbPass]));
    install_schema($pdo);
    $siteTitle=trim($values['site_title'])?:'ATD IBSng Store';
    $setting=$pdo->prepare('INSERT INTO app_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $setting->execute(['site_title',$siteTitle]);
    // Make the data writes retry-safe. DDL in install_schema() is idempotent,
    // while settings/admin writes below are protected by a transaction.
    $pdo->beginTransaction();
    $setting=$pdo->prepare('INSERT INTO app_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $setting->execute(['site_title',$siteTitle]);

    // Recover an interrupted earlier install without creating a duplicate admin.
    // Reuse the account only when the supplied password verifies against it.
    $existing=$pdo->prepare('SELECT password_hash FROM admin_users WHERE username=? LIMIT 1');
    $existing->execute([$username]);
    $existingHash=$existing->fetchColumn();
    if (is_string($existingHash)) {
     if (!password_verify($adminPassword,$existingHash)) {
      throw new RuntimeException('Existing administrator credentials do not match');
     }
    } else {
     $admin=$pdo->prepare('INSERT INTO admin_users (username,password_hash,display_name,email,is_active) VALUES (?,?,?,?,1)');
     $admin->execute([$username,password_hash($adminPassword,PASSWORD_DEFAULT),trim($values['admin_display_name'])?:'Admin',trim($values['admin_email'])]);
    }

    $baseUrl=rtrim(dirname(dirname((string)($_SERVER['SCRIPT_NAME']??'/install/index.php'))),'/');
    $config=[
     'site_title'=>$siteTitle,'db_host'=>trim($values['db_host']),'db_name'=>trim($values['db_name']),
     'db_user'=>trim($values['db_user']),'db_pass'=>$dbPass,'encryption_key'=>base64_encode(random_bytes(32)),
     'base_url'=>$baseUrl==='/'?'':$baseUrl,'installed'=>true,
    ];
    $php="<?php\nreturn ".var_export($config,true).";\n";
    // Write beside config.php and atomically rename, so a failed write cannot
    // leave a truncated configuration file that appears to be an installation.
    $temporary=$configPath.'.tmp.'.bin2hex(random_bytes(6));
    if(file_put_contents($temporary,$php,LOCK_EX)===false) throw new RuntimeException('temporary config write failed');
    @chmod($temporary,0640);
    if(!rename($temporary,$configPath)) {
     @unlink($temporary);
     throw new RuntimeException('config replace failed');
    }
    try {
     $pdo->commit();
    } catch(Throwable $commitError) {
     @unlink($configPath);
     throw $commitError;
    }
    session_regenerate_id(true);
    $success=it('done');
   } catch(Throwable $e) {
    if(isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    $errors[]=it('installbad');
   }
  }
 }
}
if(empty($_SESSION['install_csrf'])) $_SESSION['install_csrf']=bin2hex(random_bytes(32));
?>
<!doctype html>
<html lang="<?= $locale ?>" dir="<?= $rtl?'rtl':'ltr' ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= install_e(it('title')) ?></title>
<link rel="stylesheet" href="../assets/css/app.css">
<style>
:root{color-scheme:dark}
body{min-height:100vh;background:radial-gradient(ellipse at 12% 8%,#5a65dc2b,transparent 34%),radial-gradient(ellipse at 88% 90%,#20c9971c,transparent 30%),var(--bg)}
.setup{width:min(920px,calc(100% - 28px));margin:clamp(22px,6vh,64px) auto;padding:clamp(22px,5vw,48px);border:1px solid var(--border);border-radius:26px;background:linear-gradient(145deg,#171f35f5,#101827f5);box-shadow:0 28px 90px #0005}
.top{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap}.brand{display:flex;gap:12px;align-items:center}.mark{width:48px;height:48px;border-radius:15px;display:grid;place-items:center;background:linear-gradient(135deg,#6c7cff,#9a66f7);font-weight:800;color:white;font-size:1.15rem}
.pill{border:1px solid var(--border);border-radius:999px;padding:9px 14px;text-decoration:none;color:var(--text)}.eyebrow{color:#9baaff;font-size:.82rem;letter-spacing:.08em}.setup h1{font-size:clamp(1.65rem,4vw,2.4rem);margin:10px 0}.muted{color:var(--muted);line-height:1.8}
.progress{display:flex;gap:8px;margin:26px 0}.progress span{height:5px;flex:1;border-radius:9px;background:linear-gradient(90deg,#667aff,#8f63e8)}
fieldset{border:1px solid var(--border);border-radius:18px;padding:20px;margin:18px 0}legend{padding:0 9px;color:#a9b6ff;font-weight:700}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 16px}.field{display:grid;gap:8px;margin:12px 0}.field label{font-size:.9rem;color:var(--muted)}.field input{width:100%;box-sizing:border-box;padding:13px 14px;border:1px solid var(--border);border-radius:12px;background:#0b1221;color:var(--text);font:inherit;outline:none}.field input:focus{border-color:#788aff;box-shadow:0 0 0 3px #788aff20}
.notice{padding:14px;border-radius:13px;margin:12px 0;line-height:1.75}.ok{background:#44d6a018;border:1px solid #44d6a044}.err{background:#ff718018;border:1px solid #ff718044}.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px}.actions button{font:inherit;cursor:pointer}.actions .secondary{background:transparent;border:1px solid var(--border);color:var(--text)}
@media(max-width:620px){.grid{grid-template-columns:1fr}.setup{padding:22px}.actions>*{width:100%}}
</style>
</head>
<body>
<main class="setup panel">
 <div class="top"><div class="brand"><div class="mark">ATD</div><div><div class="eyebrow"><?= install_e(it('tag')) ?></div><strong>IBSng Store</strong></div></div>
 <a class="pill" href="?lang=<?= $locale==='fa'?'en':'fa' ?>"><?= install_e(it('lang')) ?></a></div>
 <div class="progress" aria-hidden="true"><span></span><span></span><span></span></div>
 <div class="eyebrow"><?= install_e(it('step')) ?></div><h1><?= install_e(it('title')) ?></h1><p class="muted"><?= install_e(it('intro')) ?></p>
 <?php foreach($errors as $error): ?><div class="notice err"><?= install_e($error) ?></div><?php endforeach; ?>
 <?php if($success!==''): ?><div class="notice ok"><?= install_e($success) ?></div><?php endif; ?>
 <?php if(!is_file($configPath)): ?>
 <form method="post" autocomplete="off">
  <input type="hidden" name="csrf" value="<?= install_e($_SESSION['install_csrf']) ?>"><input type="hidden" name="locale" value="<?= install_e($locale) ?>">
  <fieldset><legend><?= install_e(it('site')) ?></legend><div class="field"><label for="site_title"><?= install_e(it('site')) ?></label><input id="site_title" name="site_title" required value="<?= install_e($values['site_title']) ?>"></div><p class="muted"><?= install_e(it('note')) ?></p></fieldset>
  <fieldset><legend><?= install_e(it('db')) ?></legend><div class="grid">
   <div class="field"><label for="db_host"><?= install_e(it('host')) ?></label><input id="db_host" name="db_host" required value="<?= install_e($values['db_host']) ?>"></div>
   <div class="field"><label for="db_name"><?= install_e(it('name')) ?></label><input id="db_name" name="db_name" required value="<?= install_e($values['db_name']) ?>"></div>
   <div class="field"><label for="db_user"><?= install_e(it('dbuser')) ?></label><input id="db_user" name="db_user" required value="<?= install_e($values['db_user']) ?>"></div>
   <div class="field"><label for="db_pass"><?= install_e(it('dbpass')) ?></label><input id="db_pass" name="db_pass" type="password" value="<?= install_e($dbPass??'') ?>" autocomplete="new-password"></div>
  </div></fieldset>
  <fieldset><legend><?= install_e(it('admin')) ?></legend><div class="grid">
   <div class="field"><label for="admin_username"><?= install_e(it('username')) ?></label><input id="admin_username" name="admin_username" required autocomplete="username" value="<?= install_e($values['admin_username']) ?>"></div>
   <div class="field"><label for="admin_display_name"><?= install_e(it('display')) ?></label><input id="admin_display_name" name="admin_display_name" value="<?= install_e($values['admin_display_name']) ?>"></div>
   <div class="field"><label for="admin_email"><?= install_e(it('email')) ?></label><input id="admin_email" name="admin_email" type="email" required autocomplete="email" value="<?= install_e($values['admin_email']) ?>"></div>
   <div class="field"><label for="admin_password"><?= install_e(it('password')) ?></label><input id="admin_password" name="admin_password" type="password" required minlength="12" autocomplete="new-password"></div>
   <div class="field"><label for="admin_password_confirm"><?= install_e(it('confirm')) ?></label><input id="admin_password_confirm" name="admin_password_confirm" type="password" required minlength="12" autocomplete="new-password"></div>
  </div></fieldset>
  <div class="actions"><button class="button secondary" type="submit" name="action" value="test"><?= install_e(it('test')) ?></button><button class="button primary" type="submit" name="action" value="install"><?= install_e(it('install')) ?></button></div>
 </form>
 <?php else: ?><div class="actions"><a class="button primary" href="../admin/login.php"><?= install_e(it('sign')) ?></a><a class="button secondary" href="../"><?= install_e(it('store')) ?></a></div><?php endif; ?>
</main>
</body></html>
