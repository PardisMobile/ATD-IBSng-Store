<?php
$storeHeaderConfig = isset($config) && is_array($config) ? $config : app_config();
$storeHeaderTitle = (string)($storeHeaderConfig['site_title'] ?? 'ATD IBSng Store');
?>
<header class="topbar atd-shared-header">
  <div class="atd-shared-header-top">
    <a class="brand storefront-brand" href="<?= e(app_url()) ?>" aria-label="<?= e($storeHeaderTitle) ?>">
      <span class="storefront-brand-mark" aria-hidden="true">
        <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false">
          <path d="M7 12h18l-1.5 14h-15L7 12Z"/><path d="M11 12V9a5 5 0 0 1 10 0v3"/><path d="M12.5 18.2a3.5 3.5 0 1 1 7 0"/><path d="M11.5 23h9"/>
        </svg>
      </span>
      <span class="storefront-brand-title"><?= e($storeHeaderTitle) ?></span>
    </a>
    <div class="atd-shared-preferences-slot" aria-label="ظاهر و زبان"></div>
  </div>
</header>
