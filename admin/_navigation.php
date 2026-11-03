<?php
declare(strict_types=1);

$adminCurrentPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$adminNavEntries = [
    ['index.php', 'dashboard', 'داشبورد', 'Dashboard'],
    ['ibsng-servers.php', 'servers', 'سرورهای IBSng', 'IBSng Servers'],
    ['ibsng-groups.php', 'groups', 'نگاشت پلن/گروه', 'Plan / Group Mapping'],
    ['ibsng-users.php', 'users', 'مدیریت حساب‌های IBSng', 'IBSng Accounts'],
    ['products.php', 'products', 'مدیریت محصولات', 'Products'],
    ['provisioning.php', 'provisioning', 'صف فعال‌سازی', 'Provisioning Queue'],
    ['orders.php', 'orders', 'گزارش فروش', 'Sales Report'],
    ['email-settings.php', 'email-settings', 'تنظیمات ایمیل', 'Email Settings'],
    ['email-templates.php', 'email-templates', 'قالب‌های ایمیل', 'Email Templates'],
    ['settings.php', 'settings', 'تنظیمات پشتیبانی و فروشگاه', 'Store & Support Settings'],
    ['ibsng-renewal-migrate.php', 'renewal', 'ارتقای تمدید IBSng', 'IBSng Renewal Upgrade'],
];
?>
<header class="atd-admin-header">
  <div class="atd-admin-header-top">
    <a class="brand storefront-brand" href="<?= e(app_url('admin/')) ?>"><span class="storefront-brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 12h18l-1.5 14h-15L7 12Z"/><path d="M11 12V9a5 5 0 0 1 10 0v3"/><path d="M12.5 18.2a3.5 3.5 0 1 1 7 0"/><path d="M11.5 23h9"/></svg></span><span class="storefront-brand-title"><?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?> · <span data-fa="مدیریت" data-en="Administration">مدیریت</span></span></a>
    <div class="atd-admin-preferences-slot" aria-label="ظاهر و زبان"></div>
  </div>
  <nav class="atd-admin-nav" aria-label="منوی مدیریت">
    <?php foreach ($adminNavEntries as [$file, $key, $fa, $en]): ?>
      <a class="atd-admin-nav-link button" href="<?= e(app_url('admin/' . $file)) ?>" data-admin-nav="<?= e($key) ?>" data-fa="<?= e($fa) ?>" data-en="<?= e($en) ?>"<?= $adminCurrentPage === $file ? ' aria-current="page"' : '' ?>><?= e($fa) ?></a>
    <?php endforeach; ?>
    <form method="post" action="<?= e(app_url('admin/logout.php')) ?>" class="atd-admin-logout">
      <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
      <button class="atd-admin-nav-link button" type="submit" data-fa="خروج" data-en="Sign out">خروج</button>
    </form>
  </nav>
</header>