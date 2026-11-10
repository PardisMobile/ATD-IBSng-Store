<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/StorefrontView.php';
require_once __DIR__ . '/app/OpenVpnManager.php';

$config = app_config();

try {
    $manager = new OpenVpnManager(Database::connection());
    $result = $manager->resolveToken((string)($_GET['t'] ?? ''));
    $filename = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $result['name']) ?: 'openvpn-profile';

    // A valid token keeps the existing direct-download response.
    header('Content-Type: application/x-openvpn-profile');
    header('Content-Disposition: attachment; filename="' . $filename . '.ovpn"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('Content-Length: ' . strlen($result['content']));
    echo $result['content'];
    exit;
} catch (Throwable $e) {
    // Do not expose token, database, or decryption details to the visitor.
    http_response_code(404);
    header('Cache-Control: no-store');
}

$siteTitle = (string)($config['site_title'] ?? 'ATD IBSng Store');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="<?= e(app_asset_url('assets/icons/atd-app-icon.svg')) ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= e(app_asset_url('assets/icons/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(app_asset_url('site.webmanifest')) ?>">
<title data-fa="پیوند دانلود معتبر نیست" data-en="Invalid download link">پیوند دانلود معتبر نیست | <?= e($siteTitle) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.openvpn-download-shell{width:min(820px,calc(100% - 28px));margin:0 auto}
.openvpn-download-card{padding:clamp(20px,4vw,34px);margin:24px 0 36px;text-align:center;min-width:0}
.openvpn-download-card h1{max-width:none;font-size:clamp(1.4rem,4vw,2rem);line-height:1.5;margin:14px 0 10px}
.openvpn-download-card p{max-width:620px;margin:0 auto;color:var(--muted);line-height:1.95}
.openvpn-download-symbol{display:grid;place-items:center;width:66px;height:66px;margin:0 auto 12px;border:1px solid color-mix(in srgb,var(--danger,var(--amber)) 35%,var(--border));border-radius:20px;background:color-mix(in srgb,var(--danger,var(--amber)) 10%,var(--surface-2));color:var(--danger,var(--amber))}
.openvpn-download-symbol svg{width:30px;height:30px}
.openvpn-download-alert{margin:18px auto 0;padding:14px 16px;border:1px solid color-mix(in srgb,var(--danger,var(--amber)) 28%,var(--border));border-radius:var(--ui-radius-md,14px);background:color-mix(in srgb,var(--danger,var(--amber)) 7%,var(--surface-2));color:var(--text);line-height:1.9;overflow-wrap:anywhere}
.openvpn-download-actions{display:flex;justify-content:center;flex-wrap:wrap;gap:10px;margin-top:22px}
.openvpn-download-actions .button{min-width:160px}
@media(max-width:560px){.openvpn-download-shell{width:calc(100% - 20px)}.openvpn-download-card{padding:22px 17px}.openvpn-download-actions{display:grid}.openvpn-download-actions .button{width:100%}}
</style>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script>
<script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</head>
<body>
<?php storefront_header($siteTitle, 'guide'); ?>
<main class="openvpn-download-shell">
<section class="panel openvpn-download-card" aria-labelledby="download-error-title">
  <div class="openvpn-download-symbol" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="9"></circle><path d="M12 7v5"></path><path d="M12 16h.01"></path>
    </svg>
  </div>
  <div class="eyebrow" data-fa="دانلود پروفایل OpenVPN" data-en="OpenVPN profile download">دانلود پروفایل OpenVPN</div>
  <h1 id="download-error-title" data-fa="پیوند دانلود معتبر نیست" data-en="This download link is unavailable">پیوند دانلود معتبر نیست</h1>
  <p data-fa="این پیوند ممکن است اشتباه، منقضی یا توسط مدیر غیرفعال شده باشد. برای دریافت لینک معتبر، از راهنمای اتصال یا پشتیبانی سایت کمک بگیرید."
     data-en="This link may be incorrect, expired, or disabled by the site administrator. Please use the connection guide or contact support to obtain a valid link.">
     این پیوند ممکن است اشتباه، منقضی یا توسط مدیر غیرفعال شده باشد. برای دریافت لینک معتبر، از راهنمای اتصال یا پشتیبانی سایت کمک بگیرید.
  </p>
  <div class="openvpn-download-alert" role="alert" data-fa="فایل به دلایل امنیتی قابل ارائه نیست." data-en="The file cannot be provided for security reasons.">فایل به دلایل امنیتی قابل ارائه نیست.</div>
  <div class="openvpn-download-actions">
    <a class="button primary" href="<?= e(app_url('guide.php')) ?>" data-fa="مشاهده راهنمای اتصال" data-en="View connection guide">مشاهده راهنمای اتصال</a>
    <a class="button" href="<?= e(app_url('/')) ?>" data-fa="بازگشت به فروشگاه" data-en="Back to store">بازگشت به فروشگاه</a>
  </div>
</section>
<?php storefront_footer($siteTitle); ?>
</main>
</body>
</html>
