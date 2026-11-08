<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/StorefrontView.php';
$pdo = Database::connection();
$stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1');
$stmt->execute(['storefront_rules']);
$rules = trim((string)$stmt->fetchColumn());
if ($rules === '') { http_response_code(404); $pageTitle = 'قوانین خرید و تمدید'; } else { $pageTitle = 'قوانین خرید و تمدید'; }
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($pageTitle) ?></title><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>"></head><body>
<?php storefront_header($pageTitle, ''); ?>
<main class="store-shell"><section class="panel" style="padding:clamp(20px,4vw,32px);margin-block:24px"><h1><?= e($pageTitle) ?></h1><?php if ($rules === ''): ?><p>قوانین خرید و تمدید هنوز ثبت نشده است.</p><?php else: ?><div style="white-space:normal;line-height:2"><?= nl2br(e($rules)) ?></div><?php endif; ?></section><?php storefront_footer($pageTitle); ?></main><script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script></body></html>
