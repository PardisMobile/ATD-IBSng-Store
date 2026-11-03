(() => {
  'use strict';
  const root = document.documentElement;
  const storage = { mode: 'atd-ui-mode', accent: 'atd-ui-accent', lang: 'atd-ui-language', legacyLang: 'atd-ibsng-language' };
  const accents = {
    blue: ['#2563eb','#7aa7ff'], violet: ['#7c3aed','#b69aff'],
    teal: ['#0d9488','#42d6b0'], rose: ['#e11d48','#fb8aa4'],
    amber: ['#b45309','#fbbf24']
  };
  const icons = {
    sun:'<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/>',
    moon:'<path d="M20.9 13A9 9 0 0 1 11 3.1 9 9 0 1 0 20.9 13Z"/>',
    palette:'<path d="M12 3a9 9 0 1 0 0 18h1.2a1.8 1.8 0 0 0 1.3-3.1 1.8 1.8 0 0 1 1.3-3.1H18a3 3 0 0 0 3-3 9 9 0 0 0-9-8Z"/><path d="M7.5 10h.01M10 6.5h.01M15 7.5h.01M17 10.5h.01"/>',
    globe:'<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/>'
  };
  const svg = (name) => '<svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'+(icons[name]||'')+'</svg>';
  const get = (k, fallback) => { try { return localStorage.getItem(k) || fallback; } catch (_) { return fallback; } };
  const set = (k,v) => { try { localStorage.setItem(k,v); } catch (_) {} };
  const schemeNames = { blue:{fa:'آبی',en:'Blue'}, violet:{fa:'بنفش',en:'Violet'}, teal:{fa:'فیروزه‌ای',en:'Teal'}, rose:{fa:'صورتی',en:'Rose'}, amber:{fa:'کهربایی',en:'Amber'} };
  const pageCopy = {
    checkout: {
      checkout_back_store: 'Back to store',
      checkout_heading: 'Place your order',
      checkout_success_title: 'Order request received',
      checkout_tracking_code: 'Tracking code:',
      checkout_track_order: 'Track order status',
      checkout_back: 'Back',
      checkout_duration: 'Duration',
      checkout_buyer_name: 'Buyer name',
      checkout_phone: 'Phone number',
      checkout_email_optional: 'Email (optional)',
      checkout_email_help: 'If you provide an email, your order details will be sent after successful payment. Without an email, recovery by email is unavailable; keep your order tracking code.',
      checkout_credentials_method: 'Account credential method',
      checkout_custom_credentials: 'Choose my username and password',
      checkout_auto_credentials: 'Generate username and password automatically',
      checkout_username: 'Username',
      checkout_password: 'Account password',
      checkout_auto_note: 'Your username and account password are generated securely and shown in the order details after successful payment.',
      checkout_password_storage: 'Password information is stored encrypted in the database.',
      checkout_submit: 'Place order'
    },
    orders: {
      orders_back_store: 'Back to store',
      orders_tracking_recovery: 'Track and recover purchases',
      orders_title: 'My orders',
      orders_intro: 'Enter the email verification code to view orders associated with your purchase email. You can also open any order using its tracking ID.',
      orders_verification_code: '6-digit code sent to your email',
      orders_verify_submit: 'Verify and view orders',
      orders_verified_email: 'Verified email:',
      orders_logout: 'Sign out of order history',
      orders_none_for_email: 'No orders were found for this email.',
      orders_tracking_id: 'Tracking ID:',
      orders_purchase_date: 'Purchase date:',
      orders_paid_amount: 'Amount paid',
      orders_status: 'Order status',
      orders_details: 'Order details',
      orders_lookup_heading: 'Track using an order ID',
      orders_lookup_help: 'Enter the tracking ID shown on your purchase receipt.',
      orders_order_id: 'Order ID',
      orders_track_submit: 'Track order',
      orders_email_recovery_heading: 'Or recover all purchases by email',
      orders_email_at_purchase: 'Email used at checkout',
      orders_send_code: 'Send verification code',
      orders_order_placeholder: 'ATD-XXXXXXX or order ID'
    },
    document_titles: {
      title_checkout: 'Checkout',
      title_orders: 'My orders',
      title_order_status: 'Order status',
      title_customer_account: 'Customer account',
      title_admin_dashboard: 'Admin dashboard',
      title_admin_products: 'Product management',
      title_admin_settings: 'Support settings',
      title_admin_orders: 'Sales report',
      title_admin_ibsng_groups: 'IBSng plan and group mapping',
      title_admin_ibsng_servers: 'IBSng servers'
    },
    admin_ibsng_servers: {
      server_add: 'Add IBSng server',
      server_title: 'IBSng servers',
      server_intro: 'Connection testing performs a real login to the Web Panel. The password is stored encrypted in the database.',
      server_add_heading: 'Add server',
      server_default_label: '· Default',
      server_management_title: 'IBSng server management',
      server_host_label: 'Host or IP (without http or path)',
      server_web_panel_port: 'Web Panel port',
      server_admin_username: 'IBSng administrator username',
      server_admin_password: 'IBSng administrator password',
      server_save_test: 'Test connection and save server',
      server_saved_heading: 'Saved servers',
      server_empty: 'No servers have been added yet.',
      server_username_label: '· Username:',
      server_last_test_label: 'Last test:',
      server_not_tested: 'Not tested',
      server_error_label: '· Error:'
    },
    admin_ibsng_groups: {
      groups_add_mapping: 'Add mapping'
    },
    admin_orders: {
      orders_dashboard: 'Dashboard',
      orders_reports_eyebrow: 'Reports',
      orders_report_title: 'Sales report',
      orders_report_intro: 'Sales are calculated from orders whose payment status is paid. Pending orders are excluded. Values come from order snapshots so later product name or price changes do not alter historical reports.',
      orders_total_paid: 'Total paid orders',
      orders_total_pending: 'Orders awaiting payment',
      orders_service: 'Service',
      orders_paid_count: 'Paid orders',
      orders_activation_success: 'Successful activations',
      orders_partial_result: 'Partial result',
      orders_gross_amount: 'Gross amount',
      orders_unit: 'Unit',
      orders_no_paid_sales: 'No paid sales have been recorded yet.',
      orders_ambiguous_title: 'Review ambiguous payments',
      orders_ambiguous_intro: 'This section shows rare payments whose gateway initiation did not return a definitive result. Before releasing an order for another attempt, check the gateway dashboard to ensure no payable invoice was created. This does not cancel the gateway invoice; it only changes the internal order state.',
      orders_transaction: 'Transaction',
      orders_order: 'Order',
      orders_gateway: 'Gateway',
      orders_amount: 'Amount',
      orders_buyer: 'Buyer',
      orders_created_at: 'Created at',
      orders_action: 'Action',
      orders_no_ambiguous: 'There are no ambiguous payments to review.',
      orders_review_note: 'Review note (required)',
      orders_review_placeholder: 'Describe the gateway review result',
      orders_mark_failed_retry: 'Mark failed and allow retry',
      orders_back_store: 'Back to store',
      orders_confirm_release: 'Did you check the gateway dashboard and confirm that no payable invoice exists for this order?'
    },
    admin_settings: {
      settings_admin: 'Store administration',
      settings_back_dashboard: 'Back to dashboard',
      settings_title: 'Store settings',
      settings_support_title: 'Support contacts',
      settings_support_intro: 'Each contact method is optional. Empty fields are not shown in the storefront.',
      settings_telegram_id: 'Telegram ID',
      settings_telegram_hint: 'Enter the ID only, without @ or a website address. The t.me link is generated automatically.',
      settings_whatsapp_target: 'WhatsApp ID or destination',
      settings_whatsapp_hint: 'Enter only the destination; a domain or URL is not required. This field is not limited to phone-number format.',
      settings_custom_link_label: 'Custom link title',
      settings_custom_link_url: 'Full custom link URL',
      settings_custom_link_hint: 'For another messenger or service; only a valid HTTPS link is accepted.',
      settings_price_unit_title: 'Price display unit',
      settings_price_unit_hint: 'The base price in the database and order snapshot does not change. Only the displayed amount is converted between toman and rial; 1 toman = 10 rial. Fresh installations default to toman.',
      settings_display_unit: 'Display unit',
      settings_toman: 'Toman',
      settings_rial: 'Rial',
      settings_payment_title: 'Payment gateway',
      settings_payment_intro: 'Gateways are loaded from registered modules; no provider is enabled by default. Changing the gateway does not affect previous transactions.',
      settings_active_gateway: 'Active gateway',
      settings_disabled: 'Disabled',
      settings_currency_type: 'Gateway currency type',
      settings_fiat_type: 'Fiat (IRR/IRT)',
      settings_crypto_type: 'Crypto (USDT)',
      settings_currency_type_hint: 'This option only determines the currency type and price sent to this gateway. The selected type must be supported by the provider.',
      settings_webhook_title: 'Webhook instructions:',
      settings_webhook_intro: 'Register this URL as the Webhook in your IRCrypto dashboard:',
      settings_webhook_validation: 'The URL is generated from the store domain and configured installation path. The endpoint verifies the request signature and validates invoice status, amount, and currency server-side.',
      settings_crypto_display_title: 'Crypto price display',
      settings_crypto_display_hint: 'This option permits displaying the independent USDT price in the store. The price is shown only when the active gateway is configured as Crypto (USDT) and supports USDT. This option alone does not enable crypto payments.',
      settings_show_usdt: 'Show USDT price (this does not enable crypto payments)',
      settings_credentials_title: 'Account credential creation policy',
      settings_credentials_hint: 'This policy applies globally to new orders. Previous orders and their stored information are unchanged.',
      settings_credential_method: 'Credential entry method',
      settings_customer_credentials: 'Customer enters username and password',
      settings_auto_credentials: 'Store generates credentials automatically',
      settings_choose_credentials: 'Customer chooses manual or automatic',
      settings_username_prefix: 'Username prefix',
      settings_username_prefix_hint: 'Latin letters, numbers, and . _ - only; for example, atd',
      settings_username_suffix: 'Random username suffix length',
      settings_password_length: 'Generated password length',
      settings_password_charset: 'Password character set',
      settings_charset_alnum: 'Latin letters and numbers',
      settings_charset_mixed: 'Latin letters, numbers, and safe symbols',
      settings_renewal_gift_title: 'IBSng renewal gift policy',
      settings_renewal_gift_hint: 'Gift days are configured separately for the first successful renewal and subsequent renewals. Reference defaults: 1 day for the first renewal and 5 days for later renewals.',
      settings_gift_first: 'First renewal gift (days)',
      settings_gift_repeat: 'Subsequent renewal gift (days)',
      settings_save: 'Save settings',
      settings_visit_store: 'View store'
    },
    admin_products: {
      products_nav_mapping: 'Plan/group mapping',
      products_nav_servers: 'IBSng servers',
      products_title: 'Manage products and plans',
      products_intro: 'Each product can be connected to one or more corresponding IBSng servers and groups. Duration is stored in seconds, price in the smallest currency unit, and quota in bytes.',
      products_prerequisite: 'Create an active IBSng server and then an active group mapping before defining a product.',
      products_name: 'Product name',
      products_duration: 'Validity duration (seconds)',
      products_renewal_days: 'IBSng renewal duration (days)',
      products_renewal_hint: 'This value is only for IBSng renewal and is not inferred from the general product duration.',
      products_base_price: 'Base price (smallest unit of selected currency)',
      products_usdt_price: 'Independent USDT price (optional)',
      products_usdt_hint: 'This price is stored separately and is not calculated from an exchange rate. It will not appear in the store until crypto payment is enabled.',
      products_currency: 'ISO currency code (e.g. IRR)',
      products_quota: 'Data limit (bytes; blank means unknown/unlimited)',
      products_sort: 'Display order',
      products_mappings: 'IBSng servers and groups for this product',
      products_server: 'IBSng server',
      products_select_server: 'Select server',
      products_group_plan: 'Active group / plan',
      products_select_mapping: 'Select mapping',
      products_remove_mapping: 'Remove',
      products_delete: 'Delete',
      products_add_mapping: 'Add server / group',
      products_mapping_hint: 'Select each server only once per product. The group must be a manual mapping for that server.',
      products_description: 'Description',
      products_show_store: 'Show in store',
      products_save: 'Save product',
      products_cancel_edit: 'Cancel editing',
      products_registered: 'Registered products',
      products_empty: 'No products have been added yet.',
      products_duration_label: 'Duration:',
      products_seconds: 'seconds · IBSng renewal:',
      products_days: 'days',
      products_not_configured: 'Not configured',
      products_price_label: '· Price:',
      products_mappings_label: 'Servers and groups:',
      products_edit: 'Edit',
      products_back_store: 'Back to store',
      products_active: 'Active',
      products_inactive: 'Inactive',
      products_duplicate_server: 'Select each server only once for a product.',
      products_delete_confirm: 'Delete this product?'
    },
    admin_dashboard: {
      dashboard_support: 'Support contacts',
      dashboard_nav_aria: 'Administration sections',
      dashboard_email_templates: 'Email templates',
      dashboard_email_settings: 'Email delivery settings',
      dashboard_ibsng_servers: 'IBSng servers',
      dashboard_group_mapping: 'Plan/group mapping',
      dashboard_ibsng_users: 'IBSng account management',
      dashboard_renewal_migration: 'IBSng renewal migration',
      dashboard_products: 'Product management',
      dashboard_provisioning: 'Provisioning queue',
      dashboard_sales: 'Sales report',
      dashboard_logout: 'Sign out',
      dashboard_eyebrow: 'Dashboard',
      dashboard_greeting: 'Welcome,',
      dashboard_intro: 'Administrator sign-in and basic statistics are available. Manage IBSng servers in their dedicated section; additional features will be added after reference review and testing.',
      dashboard_database_error: 'Could not read database statistics. Check the connection and schema installation.',
      dashboard_stats_aria: 'Statistics',
      dashboard_stat_servers: 'IBSng servers',
      dashboard_stat_plans: 'Plans',
      dashboard_stat_orders: 'Orders',
      dashboard_stat_admins: 'Administrators',
      dashboard_catalog_title: 'Store catalog management',
      dashboard_catalog_intro: 'Define sale plans in Products and connect them to active IBSng server/group mappings. Active products appear in the public store. Order registration, payment, and provisioning are being completed.',
      dashboard_back_store: 'Back to store'
    },
    customer_account_page: {
      customer_back_store: 'Back to store',
      customer_track_orders: 'Track purchases',
      customer_panel: 'Customer account',
      customer_view_account: 'View account details',
      customer_intro: 'Enter your username and password. The four-digit code is only used to prevent automated access.',
      customer_username: 'Username',
      customer_password: 'Password',
      customer_captcha: 'Four-digit code',
      customer_captcha_placeholder: 'Enter the code',
      customer_captcha_aria: 'Anti-bot code',
      customer_show_details: 'Show account details',
      customer_account_details: 'Account details',
      customer_service: 'Service',
      customer_plan: 'Plan',
      customer_validity: 'Validity status',
      customer_expiry: 'Expiration date',
      customer_expiry_unknown: 'No expiration date available',
      customer_remaining: 'Days remaining',
      customer_days: 'days',
      customer_password_label: 'Password',
      customer_show_password: 'Show password',
      customer_hide_password: 'Hide password'
    },
    order_status_page: {
      status_nav_recovery: 'Track and recover orders',
      status_nav_store: 'Back to store',
      status_page_heading: 'Order tracking',
      status_not_found: 'Order not found',
      status_not_found_help: 'The tracking ID is invalid or the order is unavailable. Open the tracking link from the payment result page.',
      status_tracking_id: 'Tracking ID',
      status_order_state: 'Order status',
      status_paid_amount: 'Amount paid',
      status_purchase_date: 'Purchase date',
      status_payment_state: 'Payment status',
      status_product: 'Product',
      status_payment_unconfirmed: 'Payment has not been confirmed yet. No IBSng account is created before payment confirmation.',
      status_waiting_provision: 'Payment was recorded, but activation results are not available yet. Please check again shortly.',
      status_account_heading: 'Account details',
      status_username: 'Username',
      status_plan: 'Plan',
      status_server: 'Server',
      status_validity: 'Validity',
      status_days: 'days',
      status_gigabytes: 'GB',
      status_quota: 'Data limit',
      status_unlimited: 'Unlimited',
      status_expiry: 'Expiration date',
      status_validity_state: 'Validity status',
      status_service_details: 'Service details',
      status_unavailable: 'Not available at this time',
      status_password: 'Password',
      status_show_password: 'Show password',
      status_password_hidden: 'Not available for display at this time',
      status_support_review: 'If a destination requires review, support will verify the result in the IBSng panel. Each server status is recorded independently.',
      status_keep_tracking_private: 'The password is shown only for destinations whose activation was confirmed successfully. Keep the tracking code confidential.'
    },
    status: {
      pending_payment: 'Awaiting payment',
      paid: 'Payment confirmed',
      provisioning: 'Activating',
      active: 'Active',
      inactive: 'Inactive',
      partial: 'Partially activated',
      failed: 'Failed',
      under_review: 'Under review',
      expired: 'Expired',
      succeeded: 'Succeeded',
      pending: 'Pending',
      cancelled: 'Cancelled'
    },
    catalog: {
      customer_account: 'Customer account',
      order_tracking: 'Track & recover orders',
      storefront_status: 'Account services',
      catalog_hero_title: 'Choose the service you need',
      catalog_hero_intro: 'Browse the available plans. Details are shown according to the store configuration.',
      catalog_view_services: 'Browse services',
      catalog_no_registration: 'No store registration required',
      catalog_section_eyebrow: 'Services',
      catalog_section_title: 'Available plans',
      catalog_load_error: 'Products are temporarily unavailable. Check the database connection and schema installation.',
      catalog_empty_title: 'No plans are available yet',
      catalog_empty_intro: 'The store administrator can add and publish products from the administration panel.',
      catalog_plan_eyebrow: 'Service plan',
      catalog_crypto_price: 'Crypto price',
      catalog_validity: 'Validity',
      catalog_quota: 'Data allowance',
      catalog_order: 'Place order',
      admin_login: 'Administration'
    }
  };
  function applyPageCopy() {
    const language = root.lang === 'en' ? 'en' : 'fa';
    document.querySelectorAll('[data-ui-text]').forEach((element) => {
      const key = element.dataset.uiText;
      const translated = Object.values(pageCopy).map((dictionary) => dictionary[key]).find(Boolean);
      if (element.dataset.uiOriginalText === undefined) element.dataset.uiOriginalText = element.textContent;
      element.textContent = language === 'en' && translated ? translated : element.dataset.uiOriginalText;
    });
    document.querySelectorAll('[data-ui-title-key]').forEach((element) => {
      const key = element.dataset.uiTitleKey;
      const translated = pageCopy.document_titles[key];
      if (element.dataset.uiOriginalTitle === undefined) element.dataset.uiOriginalTitle = element.textContent;
      const original = element.dataset.uiOriginalTitle;
      const separator = original.indexOf('|');
      const brand = separator >= 0 ? original.slice(separator + 1).trim() : '';
      element.textContent = language === 'en' && translated
        ? translated + (brand ? ' | ' + brand : '')
        : original;
    });
    document.querySelectorAll('[data-ui-placeholder]').forEach((element) => {
      const key = element.dataset.uiPlaceholder;
      const translated = Object.values(pageCopy).map((dictionary) => dictionary[key]).find(Boolean);
      if (element.dataset.uiOriginalPlaceholder === undefined) element.dataset.uiOriginalPlaceholder = element.getAttribute('placeholder') || '';
      element.setAttribute('placeholder', language === 'en' && translated ? translated : element.dataset.uiOriginalPlaceholder);
    });
    document.querySelectorAll('[data-ui-aria]').forEach((element) => {
      const key = element.dataset.uiAria;
      const translated = Object.values(pageCopy).map((dictionary) => dictionary[key]).find(Boolean);
      if (element.dataset.uiOriginalAria === undefined) element.dataset.uiOriginalAria = element.getAttribute('aria-label') || '';
      element.setAttribute('aria-label', language === 'en' && translated ? translated : element.dataset.uiOriginalAria);
    });
    document.querySelectorAll('[data-ui-password-toggle]').forEach((button) => {
      const value = button.previousElementSibling;
      const shown = value && value.textContent !== '••••••••';
      button.textContent = language === 'en'
        ? (shown ? pageCopy.customer_account_page.customer_hide_password : pageCopy.customer_account_page.customer_show_password)
        : (shown ? 'مخفی کردن' : 'نمایش رمز');
    });
    document.querySelectorAll('[data-ui-status]').forEach((element) => {
      const key = element.dataset.uiStatus;
      const translated = pageCopy.status[key];
      if (element.dataset.uiOriginalText === undefined) element.dataset.uiOriginalText = element.textContent;
      element.textContent = language === 'en' && translated ? translated : element.dataset.uiOriginalText;
    });
    document.querySelectorAll('[data-ui-duration]').forEach((element) => {
      if (language !== 'en') {
        element.textContent = element.dataset.uiDurationFa || element.dataset.uiOriginalText || element.textContent;
        return;
      }
      const seconds = Number(element.dataset.uiDuration);
      if (!Number.isFinite(seconds) || seconds < 0) return;
      const unit = seconds % 86400 === 0 ? [seconds / 86400, 'day']
        : seconds % 3600 === 0 ? [seconds / 3600, 'hour']
        : seconds % 60 === 0 ? [seconds / 60, 'minute']
        : [seconds, 'second'];
      element.textContent = new Intl.NumberFormat('en').format(unit[0]) + ' ' + unit[1] + (unit[0] === 1 ? '' : 's');
    });
  }
  function apply() {
    const mode = get(storage.mode, 'dark'), accent = get(storage.accent, 'blue');
    root.dataset.theme = mode === 'light' ? 'light' : 'dark';
    root.dataset.mode = root.dataset.theme;
    const language = get(storage.lang, get(storage.legacyLang, root.lang || 'fa'));
    root.lang = language === 'en' ? 'en' : 'fa';
    root.dir = language === 'en' ? 'ltr' : 'rtl';
    root.dataset.language = root.lang;
    const languageCookie = 'atd-ui-language=' + encodeURIComponent(root.lang) + '; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    document.cookie = languageCookie;
    root.dataset.accent = accents[accent] ? accent : 'blue';
    root.style.colorScheme = root.dataset.theme;
    const colors = accents[root.dataset.accent];
    root.style.setProperty('--accent-light', colors[0]);
    root.style.setProperty('--accent-dark', colors[1]);
    root.style.setProperty('--accent', mode === 'light' ? colors[0] : colors[1]);
    applyPageCopy();
  }
  function mount() {
    if (document.querySelector('.atd-preferences')) return;
    const controls = document.createElement('div');
    controls.className = 'atd-preferences';
    controls.setAttribute('aria-label',get(storage.lang,get(storage.legacyLang,root.lang||'fa'))==='fa'?'ظاهر و زبان':'Appearance and language');
    const initialLanguage=get(storage.lang,get(storage.legacyLang,root.lang||'fa'))==='fa'?'fa':'en';
    const initialMode=get(storage.mode,'dark')==='light'?'light':'dark';
    controls.innerHTML =
      '<button type="button" class="atd-pref-mode" aria-label="'+(initialLanguage==='fa'?(initialMode==='dark'?'روشن کردن حالت روشن':'روشن کردن حالت تاریک'):(initialMode==='dark'?'Switch to light mode':'Switch to dark mode'))+'" aria-pressed="'+(initialMode==='light')+'" title="'+(initialLanguage==='fa'?(initialMode==='dark'?'حالت تاریک':'حالت روشن'):(initialMode==='dark'?'Dark mode':'Light mode'))+'">'+svg(initialMode==='dark'?'sun':'moon')+'</button>'+
      '<button type="button" class="atd-pref-palette" aria-label="'+(initialLanguage==='fa'?'انتخاب طرح رنگ':'Choose color scheme')+'" title="'+(initialLanguage==='fa'?'طرح رنگ':'Color scheme')+'">'+svg('palette')+'</button>'+
      '<button type="button" class="atd-pref-language" aria-label="'+(initialLanguage==='fa'?'Switch language to English':'تغییر زبان به فارسی')+'" title="'+(initialLanguage==='fa'?'زبان':'Language')+'">'+svg('globe')+'<span>'+(initialLanguage==='fa'?'EN':'فا')+'</span></button>';
    const palette = document.createElement('div');
    palette.className='atd-palette-popover'; palette.hidden=true;
    palette.id='atd-accent-palette';
    palette.setAttribute('role','group'); palette.setAttribute('aria-label',initialLanguage==='fa'?'طرح رنگ':'Color scheme');
    Object.entries(accents).forEach(([name,colors])=>{
      const b=document.createElement('button'); b.type='button'; b.className='atd-swatch'; b.dataset.accent=name;
      b.style.setProperty('--swatch',colors[0]);
      const currentLanguage=initialLanguage;
      b.setAttribute('aria-label',(currentLanguage==='fa'?'طرح رنگ ':'')+schemeNames[name][currentLanguage]); b.title=schemeNames[name][currentLanguage];
      b.innerHTML='<span></span>'; b.addEventListener('click',()=>{set(storage.accent,name);apply();palette.querySelectorAll('button').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));});
      b.setAttribute('aria-pressed',String(get(storage.accent,'blue')===name)); palette.appendChild(b);
    });
    controls.appendChild(palette);
    controls.querySelector('.atd-pref-mode').addEventListener('click',()=>{
      const next=get(storage.mode,'dark')==='dark'?'light':'dark';set(storage.mode,next);apply();
      const modeButton=controls.querySelector('.atd-pref-mode');
      modeButton.innerHTML=svg(next==='dark'?'sun':'moon');
      modeButton.setAttribute('aria-pressed',String(next==='light'));
      const language=get(storage.lang,get(storage.legacyLang,root.lang||'fa'))==='fa'?'fa':'en';
      modeButton.setAttribute('aria-label',language==='fa'?(next==='dark'?'روشن کردن حالت روشن':'روشن کردن حالت تاریک'):(next==='dark'?'Switch to light mode':'Switch to dark mode'));
      modeButton.title=language==='fa'?(next==='dark'?'حالت تاریک':'حالت روشن'):(next==='dark'?'Dark mode':'Light mode');
    });
    const paletteButton=controls.querySelector('.atd-pref-palette');
    paletteButton.setAttribute('aria-controls',palette.id);
    paletteButton.setAttribute('aria-expanded','false');
    const closePalette=()=>{palette.hidden=true;paletteButton.setAttribute('aria-expanded','false');};
    paletteButton.addEventListener('click',()=>{palette.hidden=!palette.hidden;paletteButton.setAttribute('aria-expanded',String(!palette.hidden));});
    controls.querySelector('.atd-pref-language').addEventListener('click',()=>{
      const next=(get(storage.lang,get(storage.legacyLang,root.lang||'fa'))==='fa'?'en':'fa');set(storage.lang,next);set(storage.legacyLang,next);apply();
      controls.querySelector('.atd-pref-language span').textContent=next==='fa'?'EN':'فا';
      controls.setAttribute('aria-label',next==='fa'?'ظاهر و زبان':'Appearance and language');
      palette.setAttribute('aria-label',next==='fa'?'طرح رنگ':'Color scheme');
      controls.querySelector('.atd-pref-language').setAttribute('aria-label',next==='fa'?'Switch language to English':'تغییر زبان به فارسی');
      controls.querySelector('.atd-pref-language').title=next==='fa'?'زبان':'Language';
      const currentMode=get(storage.mode,'dark')==='light'?'light':'dark';
      const modeButton=controls.querySelector('.atd-pref-mode');
      modeButton.setAttribute('aria-label',next==='fa'?(currentMode==='dark'?'روشن کردن حالت روشن':'روشن کردن حالت تاریک'):(currentMode==='dark'?'Switch to light mode':'Switch to dark mode'));
      modeButton.title=next==='fa'?(currentMode==='dark'?'حالت تاریک':'حالت روشن'):(currentMode==='dark'?'Dark mode':'Light mode');
      modeButton.setAttribute('aria-pressed',String(currentMode==='light'));
      controls.querySelector('.atd-pref-palette').setAttribute('aria-label',next==='fa'?'انتخاب طرح رنگ':'Choose color scheme');
      controls.querySelector('.atd-pref-palette').title=next==='fa'?'طرح رنگ':'Color scheme';
      palette.querySelectorAll('.atd-swatch').forEach((swatch)=>{const key=swatch.dataset.accent;swatch.setAttribute('aria-label',(next==='fa'?'طرح رنگ ':'')+schemeNames[key][next]);swatch.title=schemeNames[key][next];});
      root.dispatchEvent(new CustomEvent('atd:language-change',{detail:{language:next,direction:root.dir}}));
      document.dispatchEvent(new CustomEvent('atd:language-change',{detail:{language:next,direction:root.dir}}));
    });
    document.addEventListener('atd:language-change', (event) => {
      const next = event.detail?.language === 'en' ? 'en' : event.detail?.language === 'fa' ? 'fa' : null;
      if (!next) return;
      set(storage.lang, next);
      set(storage.legacyLang, next);
      apply();
      const languageButton = controls.querySelector('.atd-pref-language');
      const modeButton = controls.querySelector('.atd-pref-mode');
      const paletteControl = controls.querySelector('.atd-pref-palette');
      const currentMode = get(storage.mode, 'dark') === 'light' ? 'light' : 'dark';
      controls.querySelector('.atd-pref-language span').textContent = next === 'fa' ? 'EN' : 'فا';
      controls.setAttribute('aria-label', next === 'fa' ? 'ظاهر و زبان' : 'Appearance and language');
      languageButton.setAttribute('aria-label', next === 'fa' ? 'Switch language to English' : 'تغییر زبان به فارسی');
      languageButton.title = next === 'fa' ? 'زبان' : 'Language';
      modeButton.setAttribute('aria-label', next === 'fa' ? (currentMode === 'dark' ? 'روشن کردن حالت روشن' : 'روشن کردن حالت تاریک') : (currentMode === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'));
      modeButton.title = next === 'fa' ? (currentMode === 'dark' ? 'حالت تاریک' : 'حالت روشن') : (currentMode === 'dark' ? 'Dark mode' : 'Light mode');
      modeButton.setAttribute('aria-pressed', String(currentMode === 'light'));
      paletteControl.setAttribute('aria-label', next === 'fa' ? 'انتخاب طرح رنگ' : 'Choose color scheme');
      paletteControl.title = next === 'fa' ? 'طرح رنگ' : 'Color scheme';
      palette.setAttribute('aria-label', next === 'fa' ? 'طرح رنگ' : 'Color scheme');
      palette.querySelectorAll('.atd-swatch').forEach((swatch) => {
        const key = swatch.dataset.accent;
        swatch.setAttribute('aria-label', (next === 'fa' ? 'طرح رنگ ' : '') + schemeNames[key][next]);
        swatch.title = schemeNames[key][next];
      });
    });
    const host=document.querySelector('.topbar,.admin-top,.store-header,.admin-header')||document.body;
    host.appendChild(controls);
    document.addEventListener('pointerdown',(event)=>{
      if(!controls.contains(event.target)) closePalette();
    });
    document.addEventListener('keydown',(event)=>{
      if(event.key==='Escape') closePalette();
    });
  }
  apply();
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount,{once:true});else mount();
})();