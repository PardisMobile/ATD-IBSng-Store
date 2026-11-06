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
    $entries = [
        ['home', 'home', 'خانه', 'Home', ''],
        ['account', 'account', 'حساب کاربری', 'Account', 'customer-account.php'],
        ['orders', 'orders', 'پیگیری و بازیابی', 'Track & recover', 'my-orders.php'],
        ['guide', 'guide', 'راهنما', 'Help', 'guide.php'],
    ];
    ?>
    <header class="store-header">
      <div class="store-header-top">
        <a class="brand storefront-brand" href="<?= e(app_url('/')) ?>" aria-label="<?= e($siteTitle) ?>">
          <?= storefront_brand_mark() ?>
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
    ?>
    <footer class="site-footer">
      <span>© <?= date('Y') ?> <?= e($siteTitle) ?></span>
      <span class="site-footer-dot" aria-hidden="true">·</span>
      <a class="admin-link" href="<?= e(app_url('admin/login.php')) ?>" data-ui-text="admin_login">ورود مدیریت</a>
    </footer>
    <?php
}
