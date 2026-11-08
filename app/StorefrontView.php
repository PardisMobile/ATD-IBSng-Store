<?php
declare(strict_types=1);

/**
 * Shared presentation components for every customer-facing Store page.
 *
 * These helpers deliberately render markup only. They do not inspect or
 * change orders, payments, accounts, IISng data or application state.
 */

function storefront_icon_svg(string $name): string
{
    $icons = [
        'home' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1Z"/>',
        'account' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'orders' => '<path d="M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M8 8h8M8 12h5M8 16h3"/>',
        'guide' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 4 2c-1.2.8-1.5 1.2-1.5 2.5M12 17h.01"/>',
        'bag' => '<path d="M6 8h12l-1 12H7L6 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
    ];

    $path = $icons[$name] ?? $icons['home'];
    return '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true" focusable="false">' . $path . '</svg>';
}

function storefront_validity_label(int $days): string
{
    return number_format($days) . ' روز';
}

function storefront_bytes_label(?string $bytes): string
{
    if ($bytes === null || trim($bytes) === '' || (float)$bytes <= 0) return 'نامحدود';
    $value = (float)$bytes;
    if ($value >= 1099511627776) return number_format($value / 1099511627776, 2) . ' TiB';
    if ($value >= 1073741824) return number_format($value / 1073741824, 2) . ' GiB';
    if ($value >= 1048576) return number_format($value / 1048576, 2) . ' MiB';
    if ($value >= 1024) return number_format($value / 1024, 2) . ' KiB';
    return number_format($value) . ' B';
}

function storefront_brand_mark(): string
{
    return '<span class="storefront-brand-mark" aria-hidden="true">'
        . '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.8" '
        . 'stroke-linecap="round" stroke-linejoin="round" focusable="false">'
        . '<path d="M7 12h18l-1.5 14h-15L7 12Z"/>'
        . '<path d="M11 12V9a5 5 0 0 1 10 0v3"/>'
        . '<path d="M12.5 18.2a3.5 3.5 0 1 1 7 0"/>'
        . '<path d="M11.5 23h9"/>'
        . '</svg></span>';
}

function storefront_header(string $siteTitle, string $active = 'home'): void
{
$storefrontLogo = '';
    try {
        $logoQuery = Database::connection()->prepare('SELECT setting_key,setting_value FROM app_settings WHERE setting_key IN (?,?,?,?,?)');
        $logoQuery->execute(['storefront_logo','storefront_language','storefront_theme','storefront_accent','storefront_title']);
        $storePrefs = $logoQuery->fetchAll(PDO::FETCH_KEY_PAIR);
        $storefrontLogo = trim((string)($storePrefs['storefront_logo'] ?? ''));
        $siteTitle = trim((string)($storePrefs['storefront_title'] ?? '')) ?: $siteTitle;
        echo '<script>document.documentElement.dataset.defaultLanguage=' . json_encode(($storePrefs['storefront_language'] ?? 'fa')) . ';document.documentElement.dataset.defaultMode=' . json_encode(($storePrefs['storefront_theme'] ?? 'dark')) . ';document.documentElement.dataset.defaultAccent=' . json_encode(($storePrefs['storefront_accent'] ?? 'blue')) . ';</script>';
    } catch (Throwable) {}
    $entries = [
        ['home', 'home', 'خانه', 'Home', ''],
        ['account', 'account', 'حساب کاربری', 'Account', 'customer-account.php'],
        ['orders', 'orders', 'پیگیری سفارش', 'Track order', 'my-orders.php'],
        ['guide', 'guide', 'راهنما', 'Help', 'guide.php'],
    ];
    ?>
    <header class="store-header">
      <div class="store-header-top">
        <a class="brand storefront-brand" href="<?= e(app_url('/')) ?>" aria-label="<?= e($siteTitle) ?>">
          <?= $storefrontLogo !== '' ? '<img class="storefront-logo" src="' . e(app_url($storefrontLogo)) . '" alt="">' : storefront_brand_mark() ?>
          <span class="storefront-brand-title"><?= e($siteTitle) ?></span>
        </a>
        <div class="store-header-actions">
          <span class="status-chip" data-ui-text="storefront_status">خدمات حساب</span>
          <div id="atd-preferences-host" class="atd-preferences-slot" aria-label="ظاهر و زبان"></div>
        </div>
      </div>
      <nav class="atd-store-nav" aria-label="منوی فروشگاه" data-ui-aria="store_nav_aria">
        <?php foreach ($entries as [$key, $icon, $fa, $en, $path]): ?>
          <a class="atd-store-nav-link" href="<?= e($key === 'home' ? app_url('/') : app_url($path)) ?>"
             data-fa="<?= e($fa) ?>" data-en="<?= e($en) ?>"
             <?= $active === $key ? ' aria-current="page"' : '' ?>>
            <?= storefront_icon_svg($icon) ?><span><?= e($fa) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
    </header>
    <?php
}

function storefront_footer(string $siteTitle): void
{
    try { $titleQuery = Database::connection()->prepare('SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1'); $titleQuery->execute(['storefront_title']); $siteTitle = trim((string)$titleQuery->fetchColumn()) ?: $siteTitle; } catch (Throwable) {}
    ?>
    <footer class="site-footer">
      <span>© <?= date('Y') ?> <?= e($siteTitle) ?></span>
      <span class="site-footer-dot" aria-hidden="true">·</span>
      <a class="admin-link" href="<?= e(app_url('admin/login.php')) ?>" data-ui-text="admin_login">ورود مدیریت</a>
    </footer>
    <?php
}


function storefront_support_card(PDO $pdo): void
{
    $stmt = $pdo->prepare('SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN (?, ?, ?, ?)');
    $stmt->execute(['support_telegram_id', 'support_whatsapp_target', 'support_custom_label', 'support_custom_url']);
    $settings = [];
    foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $key => $value) {
        $settings[$key] = trim((string)$value);
    }

    $links = [];
    $telegram = $settings['support_telegram_id'] ?? '';
    if ($telegram !== '') {
        $links[] = ['label' => 'پشتیبانی تلگرام', 'label_en' => 'Telegram support', 'url' => 'https://t.me/' . rawurlencode(ltrim($telegram, '@')), 'icon' => '<path d="m21 3-7.2 18-4-7.1L3 10l18-7Z"/><path d="M9.8 13.9 21 3"/>'];
    }
    $whatsapp = $settings['support_whatsapp_target'] ?? '';
    if ($whatsapp !== '') {
        $target = preg_replace('/[^A-Za-z0-9._+-]/', '', $whatsapp);
        $links[] = ['label' => 'پشتیبانی واتساپ', 'label_en' => 'WhatsApp support', 'url' => 'https://wa.me/' . rawurlencode($target), 'icon' => '<path d="M20.5 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20l1.2-4.7A8.5 8.5 0 1 1 20.5 11.5Z"/><path d="M8.5 8.5c.5 2.5 2 4 4.5 5"/>'];
    }
    $customLabel = $settings['support_custom_label'] ?? '';
    $customUrl = $settings['support_custom_url'] ?? '';
    if ($customLabel !== '' && filter_var($customUrl, FILTER_VALIDATE_URL) && strtolower((string)parse_url($customUrl, PHP_URL_SCHEME)) === 'https') {
        $links[] = ['label' => $customLabel, 'url' => $customUrl, 'icon' => '<circle cx="12" cy="12" r="9"/><path d="M10 14 14 10M9 10h5v5"/>'];
    }
    if ($links === []) return;
    ?>
    <section class="panel customer-support-card" aria-label="پشتیبانی مشتریان" data-ui-aria="store_support_aria">
      <h2 data-fa="پشتیبانی مشتریان" data-en="Customer support">پشتیبانی مشتریان</h2>
      <p data-fa="اگر درباره سفارش یا سرویس خود سؤالی دارید، از راه‌های زیر با ما در ارتباط باشید." data-en="If you have a question about your order or service, contact us using one of the options below.">اگر درباره سفارش یا سرویس خود سؤالی دارید، از راه‌های زیر با ما در ارتباط باشید.</p>
      <div class="customer-support-links">
        <?php foreach ($links as $link): ?>
          <a class="customer-support-link" href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $link['icon'] ?></svg>
            <span<?= isset($link['label_en']) ? ' data-fa="' . e($link['label']) . '" data-en="' . e($link['label_en']) . '"' : '' ?>><?= e($link['label']) ?></span>
            <span class="support-external" aria-hidden="true">↗</span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
    <style>
    .customer-support-card{margin:18px 0;padding:clamp(18px,3vw,26px);border:1px solid var(--border);border-radius:var(--ui-radius-lg,18px);background:var(--surface)}
    .customer-support-card h2{margin:0 0 6px;font-size:1.15rem;color:var(--text)}
    .customer-support-card>p{margin:0 0 16px;color:var(--muted);line-height:1.8}
    .customer-support-links{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,210px),1fr));gap:10px}
    .customer-support-link{display:flex;align-items:center;gap:11px;min-width:0;padding:13px 15px;border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);background:var(--surface-2);color:var(--text);text-decoration:none;font-weight:700;transition:border-color .18s ease,background .18s ease}
    .customer-support-link:hover{border-color:var(--accent);background:color-mix(in srgb,var(--accent) 7%,var(--surface-2))}
    .customer-support-link svg{width:23px;height:23px;flex:0 0 23px;color:var(--accent)}
    .customer-support-link .support-external{margin-inline-start:auto;color:var(--accent)}
    </style>
    <?php
}
