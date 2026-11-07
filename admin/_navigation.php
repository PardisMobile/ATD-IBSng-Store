<?php
declare(strict_types=1);

$adminCurrentPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$adminNavEntries = [
    ['index.php', 'dashboard', 'داشبورد', 'Dashboard'],
    ['ibsng-servers.php', 'servers', 'مدیریت سرورها', 'IBSng Servers'],
    ['ibsng-groups.php', 'groups', 'تعریف پلن و گروه', 'Plan & Group Setup'],
    ['ibsng-users.php', 'users', 'مدیریت اکانت ها', 'IBSng Accounts'],
    ['products.php', 'products', 'مدیریت محصولات', 'Products'],
    ['guide.php', 'guide', 'راهنمای کاربران', 'Account Guide'],
    ['provisioning.php', 'provisioning', 'صف فعال‌سازی', 'Activation Queue'],
    ['orders.php', 'orders', 'گزارش فروش', 'Sales Report'],
    ['email-settings.php', 'email-settings', 'تنظیمات ایمیل', 'Email Settings'],
    ['email-templates.php', 'email-templates', 'قالب‌های ایمیل', 'Email Templates'],
    ['settings.php', 'settings', 'تنظیمات سایت', 'Site Settings'],
    ['upgrade.php', 'upgrade', 'ارتقا', 'Upgrade'],
];
$adminNavIcons = [
    'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'servers' => '<rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01"/>',
    'groups' => '<circle cx="6" cy="6" r="3"/><circle cx="18" cy="6" r="3"/><circle cx="12" cy="18" r="3"/><path d="M8.5 7.5 11 15M15.5 7.5 13 15M9 6h6"/>',
    'users' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0M18 4.5h3M19.5 3v3"/>',
    'products' => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/>',
    'guide' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5v-16Z"/><path d="M4 17.5A2.5 2.5 0 0 1 6.5 15H20M9 7h7M9 10h5"/>',
    'provisioning' => '<path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9A7 7 0 0 1 17 7l3 5M4 12l3 5a7 7 0 0 0 11.4-2"/>',
    'orders' => '<path d="M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M8 8h8M8 12h5M8 16h3"/>',
    'email-settings' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
    'email-templates' => '<path d="M6 3h9l4 4v14H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5M8 13h7M8 17h5"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
    'upgrade' => '<path d="M12 3v12m0 0 5-5m-5 5-5-5"/><path d="M5 17v3h14v-3"/>',
];
?>
<header class="atd-admin-header">
  <div class="atd-admin-header-top">
    <a class="brand admin-brand" href="<?= e(app_url('admin/')) ?>">
      <span class="admin-brand-mark" aria-hidden="true">ATD</span>
      <span><?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?><small data-fa="مدیریت فروشگاه" data-en="Store administration">مدیریت فروشگاه</small></span>
    </a>
    <div class="atd-admin-header-actions">
      <a class="button admin-store-link" href="<?= e(app_url('/')) ?>" data-fa="مشاهده فروشگاه" data-en="View store">مشاهده فروشگاه</a>
      <div id="atd-preferences-host" class="atd-admin-preferences-slot" aria-label="ظاهر و زبان"></div>
    </div>
  </div>
  <nav class="atd-admin-nav" aria-label="منوی مدیریت">
    <?php foreach ($adminNavEntries as [$file, $key, $fa, $en]): ?>
      <a class="atd-admin-nav-link" href="<?= e(app_url('admin/' . $file)) ?>" data-admin-nav="<?= e($key) ?>" data-fa="<?= e($fa) ?>" data-en="<?= e($en) ?>"<?= $adminCurrentPage === $file ? ' aria-current="page"' : '' ?>>
        <svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?= $adminNavIcons[$key] ?? '' ?></svg>
        <span><?= e($fa) ?></span>
      </a>
    <?php endforeach; ?>
    <form method="post" action="<?= e(app_url('admin/logout.php')) ?>" class="atd-admin-logout">
      <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
      <button class="atd-admin-nav-link" type="submit" data-fa="خروج" data-en="Sign out">
        <svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5M14 8l4 4-4 4M9 12h9"/></svg>
        <span>خروج</span>
      </button>
    </form>
  </nav>
</header>
