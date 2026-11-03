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
        $error = 'نام کاربری و رمز عبور خود را وارد کنید.';
    } else {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->query("SELECT id,name,service_name,host,port,username,password_ciphertext FROM ibsng_servers WHERE is_active=1 ORDER BY is_default DESC,id DESC");
            $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$servers) throw new RuntimeException('در حال حاضر امکان بررسی حساب وجود ندارد.');
            $matched = [];
            foreach ($servers as $server) {
                $client = new IBSngClient((string)$server['host'], (int)$server['port'], (string)$server['username'], app_decrypt_secret((string)$server['password_ciphertext']));
                try {
                    $data = $client->lookupUser($username);
                    $state = IBSngRenewalState::fromClient($client, $username);
                    $credentials = $client->getCredentials($username);
                    if (!hash_equals((string)($credentials['password'] ?? ''), $accountPassword)) {
                        // Account username exists, but supplied IBSng account password is invalid.
                        continue;
                    }
                    $groupName = trim((string)($data['group'] ?? ''));
                    $planStmt = $pdo->prepare('SELECT plan_name FROM ibsng_groups WHERE server_id=? AND group_name=? AND is_active=1 LIMIT 1');
                    $planStmt->execute([(int)$server['id'], $groupName]);
                    $planName = (string)($planStmt->fetchColumn() ?: '');

                    $matched[] = [
                        'server' => (string)($server['service_name'] ?: $server['name']),
                        'username' => $username,
                        // Customer-facing label is the Store plan name mapped to the exact IBSng group.
                        'plan' => $planName !== '' ? $planName : 'نامشخص',
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
            if (!$matched) throw new RuntimeException('نام کاربری یا رمز عبور صحیح نیست، یا حساب پیدا نشد.');
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
<title>پنل حساب | <?= e($title) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.account-shell{width:min(900px,calc(100% - 28px));margin:0 auto;padding:clamp(16px,3vw,28px) 0}
.account-card{padding:clamp(18px,4vw,30px);margin-top:20px;min-width:0}
.account-card h1,.account-card h2{line-height:1.5;overflow-wrap:anywhere}
.account-form{display:grid;grid-template-columns:minmax(0,1fr) minmax(150px,auto);gap:14px;align-items:end}
.field{display:grid;gap:8px;min-width:0}
.field label{color:var(--muted);font-size:.92rem}
.field input{box-sizing:border-box;width:100%;min-height:46px;padding:12px 14px;border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);background:var(--control-bg,var(--surface-2));color:var(--text);font:inherit;transition:border-color .18s ease,box-shadow .18s ease}
.field input::placeholder{color:var(--muted);opacity:.8}
.field input:focus-visible{outline:0;border-color:var(--accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 22%,transparent)}
.captcha-row{display:flex;align-items:stretch;gap:10px;min-width:0}
.captcha-row input{flex:1;min-width:0}
.captcha-code{direction:ltr;text-align:center;font-weight:800;letter-spacing:4px;padding:10px 14px;border-radius:var(--ui-radius-md,14px);background:var(--surface-2);border:1px dashed var(--border);font-size:1.15rem;font-variant-numeric:tabular-nums;display:flex;align-items:center;justify-content:center;min-width:92px}
.account-form>.button{min-height:46px;white-space:normal}
.account-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,210px),1fr));gap:12px;margin-top:18px}
.account-item{padding:clamp(13px,2.5vw,17px);border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);background:var(--surface-2);min-width:0;transition:border-color .18s ease,background-color .18s ease}
.account-item small{display:block;color:var(--muted);margin-bottom:8px;line-height:1.6}
.account-item strong{overflow-wrap:anywhere;line-height:1.7}
.account-item strong[dir="ltr"],.password-value[dir="ltr"]{direction:ltr;unicode-bidi:isolate;text-align:left;display:inline-block;max-width:100%;font-variant-numeric:tabular-nums}
.field input[dir="ltr"]{direction:ltr;text-align:left;unicode-bidi:isolate}
.account-error{padding:13px 15px;border:1px solid color-mix(in srgb,var(--danger,var(--red)) 35%,var(--border));background:color-mix(in srgb,var(--danger,var(--red)) 9%,var(--surface));color:var(--text);border-radius:var(--ui-radius-md,12px);margin:15px 0;line-height:1.8;overflow-wrap:anywhere}
.password-line{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.password-line button{font:inherit;cursor:pointer;min-height:40px}
.password-line button:focus-visible,.account-form>.button:focus-visible,.account-shell .topbar nav .button:focus-visible{outline:2px solid var(--accent);outline-offset:3px;box-shadow:0 0 0 4px var(--ui-focus-ring)}
.account-shell .topbar nav{display:flex;flex-wrap:wrap;align-items:center;gap:10px;min-width:0}
.account-shell .topbar nav .button{white-space:normal;text-align:center}
.account-item strong{font-variant-numeric:tabular-nums}
@media(max-width:560px){.account-shell .topbar nav{width:100%}.account-shell .topbar nav .button{flex:1 1 auto}.password-line{align-items:flex-start}.password-line button{flex:0 0 auto}}
.muted-note{color:var(--muted);line-height:1.85}
@media(max-width:560px){.account-form{grid-template-columns:minmax(0,1fr)}.captcha-row{gap:8px}.account-form>.button{width:100%}}
@media(prefers-reduced-motion:reduce){.field input,.account-item{transition:none}}
</style>
</head>
<body>
<main class="account-shell">
<header class="topbar"><a class="brand" href="<?= e(app_url()) ?>"><?= e($title) ?></a><nav><a class="button" href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a><a class="button" href="<?= e(app_url('my-orders.php')) ?>">پیگیری خریدها</a></nav></header>
<section class="panel account-card">
<div class="eyebrow">پنل مشتری</div><h1>مشاهده مشخصات حساب</h1>
<p class="muted-note">نام کاربری و رمز عبور خود را وارد کنید. کد چهاررقمی فقط برای جلوگیری از ورود ربات‌هاست.</p>
<?php if ($error !== ''): ?><div class="account-error" role="alert" aria-live="assertive"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="account-form">
<div style="display:grid;gap:12px">
<div class="field"><label for="username">نام کاربری</label><input id="username" name="username" dir="ltr" value="<?= e($username) ?>" required autocomplete="username"></div>
<div class="field"><label for="account_password">رمز عبور</label><input id="account_password" name="account_password" type="password" dir="ltr" required autocomplete="current-password"></div>
<div class="field"><label for="captcha">کد چهاررقمی</label><div class="captcha-row"><span class="captcha-code" dir="ltr" aria-label="کد ضدربات"><?= e($captcha) ?></span><input id="captcha" name="captcha" dir="ltr" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="کد را وارد کنید" required></div></div>
</div><button class="button primary" type="submit">نمایش مشخصات</button>
</form>
</section>
<?php if (is_array($account)): ?>
<?php foreach ($account as $item): ?>
<section class="panel account-card">
<h2>مشخصات حساب</h2>
<div class="account-grid">
<div class="account-item"><small>نام کاربری</small><strong dir="ltr"><?= e($item['username']) ?></strong></div>
<div class="account-item"><small>سرویس</small><strong><?= e($item['server']) ?></strong></div>
<div class="account-item"><small>پلن</small><strong><?= e($item['plan']) ?></strong></div>
<div class="account-item"><small>وضعیت اعتبار</small><strong><?= $item['expired'] ? 'منقضی شده' : 'فعال' ?></strong></div>
<div class="account-item"><small>تاریخ انقضا</small><strong><?= e($item['expiry'] !== '' ? $item['expiry'] : 'بدون تاریخ انقضای قابل نمایش') ?></strong></div>
<div class="account-item"><small>روزهای باقی‌مانده</small><strong><?= $item['expired'] ? '۰' : e((string)$item['remaining']) ?></strong></div>
<div class="account-item"><small>رمز عبور</small><div class="password-line"><strong dir="ltr" class="password-value" data-password="<?= e($item['password']) ?>">••••••••</strong><button type="button" class="button" onclick="const n=this.previousElementSibling;const show=n.textContent==='••••••••';n.textContent=show?n.dataset.password:'••••••••';this.textContent=show?'مخفی کردن':'نمایش رمز';">نمایش رمز</button></div></div>
</div>
</section>
<?php endforeach; ?>
<?php endif; ?>
<footer><a href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a></footer>
</main>
<script src="<?= e(app_url('assets/js/ui-preferences.js')) ?>" defer></script>
</body></html>
