<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/StorefrontView.php';
require_once __DIR__ . '/app/OpenVpnManager.php';

$config = app_config();
$valid = false;
$isPersonalized = false;
$personalUrl = $publicUrl = $profileName = $importUrl = $publicImportUrl = '';
try {
    $manager = new OpenVpnManager(Database::connection());
    $token = trim((string)($_GET['t'] ?? ''));
    $profileId = (int)($_GET['p'] ?? 0);
    if ($token !== '') {
        $result = $manager->resolveToken($token, false);
        if ($result['type'] === 'public') {
            header('Location: ' . app_absolute_url('openvpn-download.php?p=' . (int)$result['id']), true, 302);
            exit;
        }
        if ($result['type'] !== 'personalized') throw new RuntimeException('Personalized link required.');
        $isPersonalized = true;
        // Show the same clean endpoint-style URL used by the public card.
        // Keep the generated physical file URL private to the Import action.
        $personalUrl = app_absolute_url('openvpn-profile.php?t=' . rawurlencode($token));
        $personalFileUrl = $manager->generatedProfileUrl($token);
        $importUrl = 'openvpn://import-profile/' . $personalFileUrl;
        $profileId = (int)$result['id'];
        $profileName = (string)$result['name'];
    } elseif ($profileId > 0) {
        $result = $manager->resolvePublicProfile($profileId);
        $profileName = (string)$result['name'];
    } else {
        throw new RuntimeException('A valid public profile or personalized token is required.');
    }

    // Both landing-page variants show the public profile card. Resolve its
    // physical file URL after the profile ID is known, including token pages.
    $publicFileUrl = $manager->publicProfileFileUrl($profileId);
    $publicImportUrl = 'openvpn://import-profile/' . $publicFileUrl;
    $publicUrl = app_absolute_url('openvpn-profile.php?p=' . $profileId);
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
.openvpn-link-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:14px}
.openvpn-link-card{padding:clamp(15px,2.5vw,20px);display:flex;flex-direction:column;gap:12px;min-width:0;position:relative;overflow:hidden;border:1px solid var(--border);border-radius:var(--ui-radius-lg,18px);background:var(--surface-2);transition:transform var(--ui-transition,180ms ease),border-color var(--ui-transition,180ms ease),box-shadow var(--ui-transition,180ms ease)}\n.openvpn-link-card::before{content:\"\";position:absolute;inset-block-start:0;inset-inline:0;height:3px;background:var(--accent);opacity:.72}\n.openvpn-link-card:hover{transform:translateY(-2px);border-color:var(--border-strong,var(--accent));box-shadow:var(--ui-shadow,0 18px 48px rgba(0,0,0,.18))}
.openvpn-link-card h2{display:flex;align-items:center;gap:9px;margin:0;font-size:1.08rem;line-height:1.5}
.openvpn-link-card p{margin:0;color:var(--muted);line-height:1.85;font-size:.92rem;min-height:7.4em}
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
<p class="openvpn-guide-intro" data-fa="برای وارد کردن مستقیم پروفایل، دکمه Import مستقیم در OpenVPN را بزنید. اگر برنامه باز نشد، از راهنمای ایمپورت دستی در پایین صفحه استفاده کنید." data-en="Tap Import to OpenVPN to import the profile directly. If the app does not open, follow the manual import instructions below.">برای وارد کردن مستقیم پروفایل، دکمه Import مستقیم در OpenVPN را بزنید. اگر برنامه باز نشد، از راهنمای ایمپورت دستی در پایین صفحه استفاده کنید.</p>
<div class="openvpn-link-grid">
<?php if ($isPersonalized): ?>
<article class="openvpn-link-card">
<h2><?= storefront_icon_svg('account') ?><span data-fa="لینک اختصاصی" data-en="Personalized link">لینک اختصاصی</span></h2>
<p data-fa="لینک اختصاصی شما حاوی اطلاعات ورود اکانت همین سفارش است. این لینک فقط برای استفاده شخصی شماست و نباید در اختیار دیگران قرار بگیرد." data-en="Your personalized link contains the login credentials for the account in this order. It is for your personal use only and must not be shared with others.">لینک اختصاصی شما حاوی اطلاعات ورود اکانت همین سفارش است. این لینک فقط برای استفاده شخصی شماست و نباید در اختیار دیگران قرار بگیرد.</p>
<input class="openvpn-url" type="text" readonly value="<?= e($personalUrl) ?>" aria-label="Personalized OpenVPN URL">
<button class="button primary openvpn-copy" type="button" data-copy-value="<?= e($personalUrl) ?>"><?= storefront_icon_svg('copy') ?><span data-fa="کپی لینک اختصاصی" data-en="Copy personalized link">کپی لینک اختصاصی</span></button>
<a class="button primary" href="<?= e($importUrl) ?>" aria-label="Import to OpenVPN"><span data-fa="Import مستقیم در OpenVPN" data-en="Import to OpenVPN">Import to OpenVPN</span></a>
</article>
<?php endif; ?>
<article class="openvpn-link-card">
<h2><?= storefront_icon_svg('globe') ?><span data-fa="لینک عمومی پروفایل" data-en="Public profile link">لینک عمومی پروفایل</span></h2>
<p data-fa="این لینک فقط شامل تنظیمات اتصال به سرور است و اطلاعات ورود هیچ مشتری‌ای را در خود ندارد. برای استفاده از حساب خود باید اطلاعات ورود معتبر را وارد کنید." data-en="This link contains only the server connection settings and no customer login credentials. To use your account, enter valid login credentials.">این لینک بدون توکن اختصاصی است و اطلاعات ورود هیچ مشتری‌ای را در خود ندارد.</p>
<input class="openvpn-url" type="text" readonly value="<?= e($publicUrl) ?>" aria-label="Public OpenVPN URL">
<button class="button primary openvpn-copy" type="button" data-copy-value="<?= e($publicUrl) ?>"><?= storefront_icon_svg('copy') ?><span data-fa="کپی لینک عمومی" data-en="Copy public link">کپی لینک عمومی</span></button>
<a class="button primary" href="<?= e($publicImportUrl) ?>" aria-label="Import to OpenVPN"><span data-fa="Import مستقیم در OpenVPN" data-en="Import to OpenVPN">Import to OpenVPN</span></a>
</article>
</div>
<div class="openvpn-guide-manual">
<h2 data-fa="راهنمای ایمپورت دستی پروفایل:" data-en="Manual profile import instructions:">راهنمای ایمپورت دستی پروفایل:</h2>
<ol class="openvpn-guide-steps">
<li data-fa="لینک را در مرورگر گوشی (Safari یا Chrome) وارد کنید." data-en="Open the link in your phone's browser (Safari or Chrome).">لینک را در مرورگر گوشی (Safari یا Chrome) وارد کنید.</li>
<li data-fa="فایل دانلودشده را باز کنید و گزینه اشتراک‌گذاری (Share) را بزنید." data-en="Open the downloaded file and tap Share.">فایل دانلودشده را باز کنید و گزینه اشتراک‌گذاری (Share) را بزنید.</li>
<li data-fa="برنامه OpenVPN Connect را انتخاب کنید تا پروفایل به آن اضافه شود." data-en="Select OpenVPN Connect to add the profile to the app.">برنامه OpenVPN Connect را انتخاب کنید تا پروفایل به آن اضافه شود.</li>
</ol>
</div>
<?php if ($isPersonalized): ?><div class="openvpn-guide-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7h.01"/></svg><span data-fa="لینک اختصاصی شامل اطلاعات ورود شماست؛ آن را برای دیگران ارسال نکنید. لینک عمومی فاقد اطلاعات ورود است." data-en="Your personalized link contains your login credentials. Do not share it. The public link does not contain login credentials.">لینک اختصاصی شامل اطلاعات ورود شماست؛ آن را برای دیگران ارسال نکنید. لینک عمومی فاقد اطلاعات ورود است.</span></div><?php endif; ?>
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
