<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Crypto.php';
require_once __DIR__ . '/app/IBSngClient.php';
require_once __DIR__ . '/app/IBSngRenewalState.php';

if (empty($_SESSION['account_lookup_captcha']) || (int)($_SESSION['account_lookup_captcha_exp'] ?? 0) < time()) {
    $_SESSION['account_lookup_captcha'] = (string)random_int(1000, 9999);
    $_SESSION['account_lookup_captcha_exp'] = time() + 600;
}
$captcha = (string)$_SESSION['account_lookup_captcha'];
$error = '';
$account = null;
$serverLabel = '';
$username = trim((string)($_POST['username'] ?? ''));
$accountPassword = (string)($_POST['account_password'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provided = trim((string)($_POST['captcha'] ?? ''));
    if (!hash_equals($captcha, $provided)) {
        $error = 'کد چهاررقمی صحیح نیست؛ دوباره تلاش کنید.';
    } elseif ($username === '' || strlen($username) > 128 || $accountPassword === '') {
        $error = 'نام کاربری و رمز عبور IBSng را وارد کنید.';
    } else {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->query("SELECT id,name,service_name,host,port,username,password_ciphertext FROM ibsng_servers WHERE is_active=1 ORDER BY is_default DESC,id DESC");
            $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$servers) throw new RuntimeException('در حال حاضر سرور IBSng فعالی برای استعلام وجود ندارد.');
            $matched = [];
            foreach ($servers as $server) {
                $client = new IBSngClient((string)$server['host'], (int)$server['port'], (string)$server['username'], app_decrypt_secret((string)$server['password_ciphertext']));
                try {
                    $data = $client->lookupUser($username);
                    $state = $client->getRenewalState($username);
                    $credentials = $client->getCredentials($username);
                    if (!hash_equals((string)($credentials['password'] ?? ''), $accountPassword)) {
                        // Account username exists, but supplied IBSng account password is invalid.
                        continue;
                    }
                    $matched[] = [
                        'server' => (string)($server['service_name'] ?: $server['name']),
                        'username' => $username,
                        'group' => (string)($data['group'] ?? ''),
                        'expiry' => (string)($state['nearest_exp_date'] ?? ''),
                        'remaining' => (int)($state['remaining_days'] ?? 0),
                        'expired' => (bool)($state['expired'] ?? false),
                        'password' => (string)($credentials['password'] ?? ''),
                        'summary' => (string)($data['summary'] ?? ''),
                    ];
                } catch (RuntimeException $e) {
                    // A username may not exist on this server; continue searching.
                    if (str_contains($e->getMessage(), 'وجود ندارد')) continue;
                    throw $e;
                }
            }
            if (!$matched) throw new RuntimeException('نام کاربری یا رمز عبور IBSng صحیح نیست، یا حساب روی سرورهای فعال پیدا نشد.');
            $account = $matched;
            unset($_SESSION['account_lookup_captcha'], $_SESSION['account_lookup_captcha_exp']);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
    $_SESSION['account_lookup_captcha'] = (string)random_int(1000, 9999);
    $_SESSION['account_lookup_captcha_exp'] = time() + 600;
    $captcha = (string)$_SESSION['account_lookup_captcha'];
}
$config = app_config();
$title = (string)($config['site_title'] ?? 'ATD IBSng Store');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>پنل حساب IBSng | <?= e($title) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.account-shell{width:min(900px,calc(100% - 30px));margin:0 auto;padding:24px 0}.account-card{padding:clamp(18px,4vw,30px);margin-top:20px}.account-form{display:grid;grid-template-columns:1fr auto;gap:12px;align-items:end}.field{display:grid;gap:8px}.field label{color:var(--muted)}.field input{width:100%;padding:13px;border:1px solid var(--border);border-radius:12px;background:var(--surface-2);color:var(--text);font:inherit}.captcha-row{display:flex;align-items:center;gap:10px}.captcha-code{font-weight:800;letter-spacing:4px;padding:10px 14px;border-radius:10px;background:var(--surface-2);border:1px dashed var(--border);font-size:1.15rem}.account-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;margin-top:18px}.account-item{padding:15px;border:1px solid var(--border);border-radius:14px;background:var(--surface-2)}.account-item small{display:block;color:var(--muted);margin-bottom:8px}.account-item strong{overflow-wrap:anywhere}.account-error{padding:13px;border:1px solid #ff718044;background:#ff718018;border-radius:12px;margin:15px 0}.password-line{display:flex;align-items:center;gap:10px}.password-line button{font:inherit;cursor:pointer}.muted-note{color:var(--muted);line-height:1.8}@media(max-width:560px){.account-form{grid-template-columns:1fr}}
</style>
</head>
<body>
<main class="account-shell">
<header class="topbar"><a class="brand" href="<?= e(app_url()) ?>"><?= e($title) ?></a><nav><a class="button" href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a><a class="button" href="<?= e(app_url('my-orders.php')) ?>">پیگیری خریدها</a></nav></header>
<section class="panel account-card">
<div class="eyebrow">IBSng Customer Portal</div><h1>مشاهده مشخصات حساب</h1>
<p class="muted-note">با نام کاربری و رمز عبور خودِ حساب IBSng وارد شوید. کد چهاررقمی فقط ضدربات است.</p>
<?php if ($error !== ''): ?><div class="account-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="account-form">
<div style="display:grid;gap:12px">
<div class="field"><label for="username">نام کاربری IBSng</label><input id="username" name="username" value="<?= e($username) ?>" required autocomplete="username"></div>
<div class="field"><label for="account_password">رمز عبور حساب IBSng</label><input id="account_password" name="account_password" type="password" required autocomplete="current-password"></div>
<div class="field"><label for="captcha">کد چهاررقمی</label><div class="captcha-row"><span class="captcha-code" aria-label="کد ضدربات"><?= e($captcha) ?></span><input id="captcha" name="captcha" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="کد را وارد کنید" required></div></div>
</div><button class="button primary" type="submit">نمایش مشخصات</button>
</form>
</section>
<?php if (is_array($account)): ?>
<?php foreach ($account as $item): ?>
<section class="panel account-card">
<h2>مشخصات حساب</h2>
<div class="account-grid">
<div class="account-item"><small>نام کاربری</small><strong><?= e($item['username']) ?></strong></div>
<div class="account-item"><small>سرویس</small><strong><?= e($item['server']) ?></strong></div>
<div class="account-item"><small>گروه IBSng</small><strong><?= e($item['group'] !== '' ? $item['group'] : '—') ?></strong></div>
<div class="account-item"><small>وضعیت اعتبار</small><strong><?= $item['expired'] ? 'منقضی شده' : 'فعال' ?></strong></div>
<div class="account-item"><small>تاریخ انقضا</small><strong><?= e($item['expiry'] !== '' ? $item['expiry'] : 'بدون تاریخ انقضای قابل نمایش') ?></strong></div>
<div class="account-item"><small>روزهای باقی‌مانده</small><strong><?= $item['expired'] ? '۰' : e((string)$item['remaining']) ?></strong></div>
<div class="account-item"><small>رمز عبور</small><div class="password-line"><strong class="password-value" data-password="<?= e($item['password']) ?>">••••••••</strong><button type="button" class="button" onclick="const n=this.previousElementSibling;const show=n.textContent==='••••••••';n.textContent=show?n.dataset.password:'••••••••';this.textContent=show?'مخفی کردن':'نمایش رمز';">نمایش رمز</button></div></div>
</div>
</section>
<?php endforeach; ?>
<?php endif; ?>
<footer><a href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a></footer>
</main>
</body></html>
