<?php
declare(strict_types=1);

$adminCurrentPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$adminNavEntries = [
    ['index.php', 'dashboard', 'داشبورد', 'Dashboard', '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5M9 21v-7h6v7"/>'],
    ['ibsng-servers.php', 'servers', 'سرورهای IBSng', 'IBSng Servers', '<rect x="4" y="3" width="16" height="8" rx="2"/><rect x="4" y="13" width="16" height="8" rx="2"/><path d="M8 7h.01M8 17h.01M12 7h4M12 17h4"/>'],
    ['ibsng-groups.php', 'groups', 'نگاشت پلن/گروه', 'Plan / Group Mapping', '<rect x="3" y="4" width="7" height="6" rx="1.5"/><rect x="14" y="14" width="7" height="6" rx="1.5"/><path d="M10 7h3a2 2 0 0 1 2 2v5M14 17h-3a2 2 0 0 1-2-2v-5"/>'],
    ['ibsng-users.php', 'users', 'مدیریت حساب‌های IBSng', 'IBSng Accounts', '<circle cx="9" cy="8" r="3.5"/><path d="M3 20a6 6 0 0 1 12 0M17 8h4M19 6v4"/>'],
    ['products.php', 'products', 'مدیریت محصولات', 'Products', '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="m4.5 7.8 7.5 4.3 7.5-4.3M12 12v9M8 5.3l8 4.5"/>'],
    ['provisioning.php', 'provisioning', 'صف فعال‌سازی', 'Provisioning Queue', '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>'],
    ['orders.php', 'orders', 'گزارش فروش', 'Sales Report', '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>'],
    ['email-settings.php', 'email-settings', 'تنظیمات ایمیل', 'Email Settings', '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>'],
    ['email-templates.php', 'email-templates', 'قالب‌های ایمیل', 'Email Templates', '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h7M9 17h7"/>'],
    ['settings.php', 'settings', 'تنظیمات پشتیبانی و فروشگاه', 'Store & Support Settings', '<circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.6a8 8 0 0 1-1.7 1l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.7-1l-1.7.6-1.4-2.4 1.4-1.1a7 7 0 0 1 0-2l-1.4-1.1 1.4-2.4 1.7.6a8 8 0 0 1 1.7-1l.3-1.8h2.8l.3 1.8a8 8 0 0 1 1.7 1l1.7-.6 1.4 2.4-1.4 1.1a7 7 0 0 1 0 2Z"/>'],
    ['ibsng-renewal-migrate.php', 'renewal', 'ارتقای تمدید IBSng', 'IBSng Renewal Upgrade', '<path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.5 9A7 7 0 0 1 18 6l2 1M4 17l2-1a7 7 0 0 0 12.5-3"/>'],
];
?>
<header class="atd-admin-header">
  <div class="atd-admin-header-top">
    <a class="brand storefront-brand" href="<?= e(app_url('admin/')) ?>"><span class="storefront-brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 12h18l-1.5 14h-15L7 12Z"/><path d="M11 12V9a5 5 0 0 1 10 0v3"/><path d="M12.5 18.2a3.5 3.5 0 1 1 7 0"/><path d="M11.5 23h9"/></svg></span><span class="storefront-brand-title"><?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?> · <span data-fa="مدیریت" data-en="Administration">مدیریت</span></span></a>
    <div class="atd-admin-preferences-slot" aria-label="ظاهر و زبان"></div>
  </div>
  <nav class="atd-admin-nav" aria-label="منوی مدیریت">
    <?php foreach ($adminNavEntries as [$file, $key, $fa, $en, $icon]): ?>
      <a class="atd-admin-nav-link button" href="<?= e(app_url('admin/' . $file)) ?>" data-admin-nav="<?= e($key) ?>" data-fa="<?= e($fa) ?>" data-en="<?= e($en) ?>"<?= $adminCurrentPage === $file ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= $icon ?></svg><span><?= e($fa) ?></span></a>
    <?php endforeach; ?>
    <form method="post" action="<?= e(app_url('admin/logout.php')) ?>" class="atd-admin-logout">
      <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
      <button class="atd-admin-nav-link button" type="submit" data-fa="خروج" data-en="Sign out"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg><span>خروج</span></button>
    </form>
  </nav>
</header>