<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/StorefrontView.php';
require_once __DIR__ . '/app/OpenVpnManager.php';

$config = app_config();
$valid = false;
$personalUrl = $publicUrl = $profileName = '';
try {
    $manager = new OpenVpnManager(Database::connection());
    $result = $manager->resolveToken((string)($_GET['t'] ?? ''), false);
    if ($result['type'] !== 'personalized') throw new RuntimeException('Personalized link required.');
    $personalUrl = app_absolute_url('openvpn-profile.php?t=' . rawurlencode((string)($_GET['t'] ?? '')));
    $publicUrl = app_absolute_url('openvpn-profile.php?p=' . (int)$result['id']);
    $profileName = (string)$result['name'];
    $valid = true;
} catch (Throwable $e) {
    http_response_code(404);
    header('Cache-Control: no-store');
}
$siteTitle = (string)($config['site_title'] ?? 'ATD IBSng Store');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/svg+xml" href="<?= e(app_asset_url('assets/icons/atd-app-icon.svg')) ?>">
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<title data-fa="راهنمای اتصال OpenVPN" data-en="OpenVPN connection guide">راهنمای اتصال OpenVPN | <?= e($siteTitle) ?></title>
<style>
.openvpn-guide-shell{width:min(820px,calc(100% - 28px));margin:24px auto 38px}
.openvpn-guide-main{padding:clamp(18px,3vw,28px);display:flex;flex-direction:column;gap:16px;min-width:0;position:relative;overflow:hidden;transition:transform var(--ui-transition,180ms ease),border-color var(--ui-transition,180ms ease),box-shadow var(--ui-transition,180ms ease)}\n.openvpn-guide-main:hover{border-color:var(--border-strong,var(--accent));box-shadow:var(--ui-shadow,0 18px 48px rgba(0,0,0,.18))}
.openvpn-guide-main::before{content:"";display:block;height:3px;margin:calc(-1 * clamp(18px,3vw,28px)) calc(-1 * clamp(18px,3vw,28px)) 4px;background:var(--accent);opacity:.75}
.openvpn-guide-main h1{margin:0;font-size:clamp(1.35rem,3.5vw,1.8rem);line-height:1.5}
.openvpn-guide-intro{margin:0;color:var(--muted);line-height:1.9}
.openvpn-link-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.openvpn-link-card{padding:clamp(15px,2.5vw,20px);display:flex;flex-direction:column;gap:12px;min-width:0;position:relative;overflow:hidden;border:1px solid var(--border);border-radius:var(--ui-radius-lg,18px);background:var(--surface-2);transition:transform var(--ui-transition,180ms ease),border-color var(--ui-transition,180ms ease),box-shadow var(--ui-transition,180ms ease)}\n.openvpn-link-card::before{content:\"\";position:absolute;inset-block-start:0;inset-inline:0;height:3px;background:var(--accent);opacity:.72}\n.openvpn-link-card:hover{transform:translateY(-2px);border-color:var(--border-strong,var(--accent));box-shadow:var(--ui-shadow,0 18px 48px rgba(0,0,0,.18))}
.openvpn-link-card h2{display:flex;align-items:center;gap:9px;margin:0;font-size:1.08rem;line-height:1.5}
.openvpn-link-card p{margin:0;color:var(--muted);line-height:1.85;font-size:.92rem;min-height:5.1em}
.openvpn-link-icon{width:22px;height:22px;flex:0 0 22px;color:var(--accent)}\n.openvpn-link-card h2 .ui-icon{width:22px;height:22px;flex:0 0 22px;color:var(--accent)}
.openvpn-url{direction:ltr;text-align:left;unicode-bidi:plaintext;display:block;width:100%;min-width:0;box-sizing:border-box;padding:12px;border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);background:var(--control-bg,var(--surface));color:var(--text);font:inherit;font-size:.82rem;overflow-wrap:anywhere}
.openvpn-copy{width:100%;justify-content:center}
.openvpn-guide-note{display:flex;align-items:flex-start;gap:9px;padding:13px 15px;border:1px solid color-mix(in srgb,var(--warning,#d97706) 30%,var(--border));border-radius:var(--ui-radius-md,14px);background:color-mix(in srgb,var(--warning,#d97706) 7%,var(--surface-2));color:var(--text);line-height:1.8}
.openvpn-guide-note svg{width:20px;height:20px;flex:0 0 20px;color:var(--warning,#d97706);margin-top:2px}
.openvpn-guide-steps{margin:0;padding-inline-start:22px;color:var(--muted);line-height:1.9}
.openvpn-guide-steps li+li{margin-top:5px}
@media(prefers-reduced-motion:reduce){.openvpn-guide-main,.openvpn-link-card{transition:none}.openvpn-link-card:hover{transform:none}}\n@media(max-width:640px){.openvpn-link-grid{grid-template-columns:1fr}.openvpn-link-card p{min-height:0}.openvpn-guide-shell{width:calc(100% - 22px)}}
</style>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script>
<script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</head>
<body>
<?php storefront_header($siteTitle, 'guide'); ?>
<main class="openvpn-guide-shell">
<?php if (!$valid): ?>
<section class="panel plan-card openvpn-guide-main">
<h1 data-fa="پیوند اختصاصی معتبر نیست" data-en="Personalized link unavailable">پیوند اختصاصی معتبر نیست</h1>
<p class="openvpn-guide-intro" data-fa="این پیوند اشتباه، منقضی یا غیرفعال شده است. برای دریافت لینک جدید با پشتیبانی تماس بگیرید." data-en="This link is invalid, expired, or disabled. Contact support to receive a new link.">این پیوند اشتباه، منقضی یا غیرفعال شده است. برای دریافت لینک جدید با پشتیبانی تماس بگیرید.</p>
<a class="button primary" href="<?= e(app_url('guide.php')) ?>" data-fa="مشاهده راهنمای عمومی" data-en="View general guide">مشاهده راهنمای عمومی</a>
</section>
<?php else: ?>
<section class="panel plan-card openvpn-guide-main">
<div class="eyebrow" data-fa="پروفایل اتصال: <?= e($profileName) ?>" data-en="Connection profile: <?= e($profileName) ?>">پروفایل اتصال: <?= e($profileName) ?></div>
<h1 data-fa="راهنمای اتصال OpenVPN" data-en="OpenVPN connection guide">راهنمای اتصال OpenVPN</h1>
<p class="openvpn-guide-intro" data-fa="برای افزودن پروفایل به OpenVPN Connect، آدرس موردنظر را کپی کنید و در بخش Import from URL وارد نمایید." data-en="To add this profile to OpenVPN Connect, copy the required URL and enter it under Import from URL.">برای افزودن پروفایل به OpenVPN Connect، آدرس موردنظر را کپی کنید و در بخش Import from URL وارد نمایید.</p>
<div class="openvpn-link-grid">
<article class="openvpn-link-card">
<h2><?= storefront_icon_svg('account') ?><span data-fa="لینک اختصاصی" data-en="Personalized link">لینک اختصاصی</span></h2>
<p data-fa="این لینک مخصوص حساب شماست و پروفایل آن با نام کاربری و رمز عبور IBSng همین سفارش آماده شده است. آن را محرمانه نگه دارید." data-en="This link is unique to your account. Its profile is prepared with the IBSng username and password for this order. Keep it private.">این لینک مخصوص حساب شماست و پروفایل آن با نام کاربری و رمز عبور IBSng همین سفارش آماده شده است. آن را محرمانه نگه دارید.</p>
<input class="openvpn-url" type="text" readonly value="<?= e($personalUrl) ?>" aria-label="Personalized OpenVPN URL">
<button class="button primary openvpn-copy" type="button" data-copy-value="<?= e($personalUrl) ?>"><?= storefront_icon_svg('copy') ?><span data-fa="کپی لینک اختصاصی" data-en="Copy personalized link">کپی لینک اختصاصی</span></button>
</article>
<article class="openvpn-link-card">
<h2><?= storefront_icon_svg('globe') ?><span data-fa="لینک عمومی پروفایل" data-en="Public profile link">لینک عمومی پروفایل</span></h2>
<p data-fa="این لینک بدون توکن اختصاصی است و اطلاعات ورود هیچ مشتری‌ای را در خود ندارد. می‌توانید آن را در متن راهنمای عمومی سایت قرار دهید." data-en="This URL has no personalized token and contains no customer's login credentials. You can place it in the site's general connection guide.">این لینک بدون توکن اختصاصی است و اطلاعات ورود هیچ مشتری‌ای را در خود ندارد. می‌توانید آن را در متن راهنمای عمومی سایت قرار دهید.</p>
<input class="openvpn-url" type="text" readonly value="<?= e($publicUrl) ?>" aria-label="Public OpenVPN URL">
<button class="button openvpn-copy" type="button" data-copy-value="<?= e($publicUrl) ?>"><?= storefront_icon_svg('copy') ?><span data-fa="کپی لینک عمومی" data-en="Copy public link">کپی لینک عمومی</span></button>
</article>
</div>
<ol class="openvpn-guide-steps">
<li data-fa="برنامه OpenVPN Connect را باز کنید." data-en="Open the OpenVPN Connect app.">برنامه OpenVPN Connect را باز کنید.</li>
<li data-fa="از بخش Add Profile، گزینه Import from URL را انتخاب کنید." data-en="From Add Profile, choose Import from URL.">از بخش Add Profile، گزینه Import from URL را انتخاب کنید.</li>
<li data-fa="لینک اختصاصی را وارد کنید تا پروفایل حساب شما دریافت شود." data-en="Enter your personalized URL to retrieve your account profile.">لینک اختصاصی را وارد کنید تا پروفایل حساب شما دریافت شود.</li>
</ol>
<div class="openvpn-guide-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7h.01"/></svg><span data-fa="لینک اختصاصی شامل اطلاعات ورود شماست؛ آن را برای دیگران ارسال نکنید. لینک عمومی فاقد اطلاعات ورود است." data-en="Your personalized link contains your login credentials. Do not share it. The public link does not contain login credentials.">لینک اختصاصی شامل اطلاعات ورود شماست؛ آن را برای دیگران ارسال نکنید. لینک عمومی فاقد اطلاعات ورود است.</span></div>
</section>
<?php endif; ?>
</main>
<?php storefront_footer($siteTitle); ?>
<script>
document.addEventListener('click',async function(event){
 const button=event.target.closest('[data-copy-value]');if(!button)return;
 const value=button.getAttribute('data-copy-value')||'';
 try{if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(value);}else{const field=document.createElement('textarea');field.value=value;field.style.position='fixed';field.style.opacity='0';document.body.appendChild(field);field.select();document.execCommand('copy');field.remove();}
 const label=button.querySelector('span');if(label){const old=label.textContent;label.textContent=document.documentElement.lang==='en'?'Copied':'کپی شد';setTimeout(()=>label.textContent=old,1800);}
 }catch(_){const input=button.previousElementSibling;if(input){input.focus();input.select();}}
});
</script>
</body>
</html>
