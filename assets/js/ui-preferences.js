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
      checkout_duration: 'Validity:',
      checkout_buyer_name: 'Buyer name',
      checkout_phone: 'Phone number',
      checkout_email_optional: 'Email (optional)',
      checkout_contact_section: 'Buyer information',
      checkout_credentials_section: 'Account login details',
      checkout_email_help: 'If you provide an email, your order details will be sent after successful payment. If you do not provide an email, you will not be able to recover the order by email, so be sure to keep your tracking code.',
      checkout_credentials_method: 'Account credential method',
      checkout_custom_credentials: 'Choose my username and password',
      checkout_auto_credentials: 'Generate username and password automatically',
      checkout_username: 'Username',
      checkout_password: 'Account password',
      checkout_auto_note: 'Your username and password are generated automatically and will be displayed on the order details page after payment is confirmed.',
      checkout_renew_notice_before: 'This order is being placed to renew account',
      checkout_renew_notice_after: 'No account password is required to submit the renewal request.',
      checkout_password_storage: 'Password information is stored encrypted in the database.',
      checkout_payment_method: 'Payment method',
      checkout_choose_payment_method: 'Choose one of the available payment gateways.',
      checkout_no_payment_methods: 'No configured payment gateway is currently available for this order. Please contact support.',
      checkout_submit: 'Place order and pay'
    },
    orders: {
      orders_back_store: 'Back to store',
      orders_tracking_recovery: 'Track orders',
      orders_title: 'My orders',
      orders_intro: 'To view a single order, enter its tracking code or order ID. To view all orders placed with your email address, use email verification.',
      orders_verification_sent_to: 'A verification code was sent to this email:',
      orders_change_email: 'Use another email address or request a new code',
      orders_resend_code: 'Resend code / change email',
      orders_verification_code: '6-digit code sent to your email',
      orders_verify_submit: 'Verify and view orders',
      orders_verified_email: 'Verified email:',
      orders_logout: 'Sign out of order history',
      orders_none_for_email: 'No orders were found for this email.',
      orders_tracking_id: 'Tracking code:',
      orders_purchase_date: 'Purchase date:',
      orders_paid_amount: 'Amount paid',
      orders_status: 'Order status',
      orders_details: 'Order details',
      orders_lookup_heading: 'Track with a tracking code or order ID',
      orders_lookup_help: 'Enter your tracking code or order ID.',
      orders_order_id: 'Tracking code or order ID',
      orders_track_submit: 'Track order',
      orders_email_recovery_heading: 'View orders by email',
      orders_email_at_purchase: 'Email address used at checkout',
      orders_send_code: 'Send verification code',
      orders_order_placeholder: 'Tracking code ATD-XXXXXXX or order ID'
    },
    document_titles: {
      title_guide: 'User guide',
      title_payment_result: 'Payment result',
      title_checkout: 'Checkout',
      title_orders: 'My orders',
      title_order_status: 'Order status',
      title_customer_account: 'Customer account',
      title_admin_dashboard: 'Admin dashboard',
      title_admin_guide: 'Account guide',
      title_admin_products: 'Product management',
      title_admin_settings: 'Support settings',
      title_admin_orders: 'Sales report',
      title_admin_ibsng_groups: 'IBSng plan and group setup',
      title_admin_ibsng_servers: 'IBSng servers',
      title_admin_ibsng_users: 'IBSng account management',
      title_admin_email_templates: 'Email templates',
      title_admin_email_settings: 'Email settings',
      title_admin_login: 'Administrator sign in',
      title_admin_forgot_password: 'Forgot administrator password',
      title_admin_reset_password: 'Set a new administrator password',
      title_admin_provisioning: 'Provisioning queue',
      title_admin_upgrade: 'Database upgrade'
    },
    admin_ibsng_servers: {
      server_add: 'Add IBSng server',
      server_title: 'IBSng servers',
      server_intro: 'Connection testing performs a real login to the Web Panel over HTTP. The password is stored encrypted in the database.',
      server_add_heading: 'Add server',
      server_default_label: '· Default',
      server_management_title: 'IBSng server management',
      server_host_label: 'Server address (domain or IP, without http or path)',
      server_web_panel_port: 'Web Panel port',
      server_admin_username: 'IBSng administrator username',
      server_admin_password: 'IBSng administrator password',
      server_save_test: 'Test connection and save server',
      server_saved_heading: 'Saved servers',
      server_empty: 'No servers have been added yet.',
      server_username_label: '· Username:',
      server_last_test_label: 'Last test:',
      server_not_tested: 'Not tested',
      server_error_label: '· Error:',
      server_service_placeholder: 'For example: Multi-location service',
      server_name_placeholder: 'e.g. Iran server, Germany server, Hetzner server'
    },
    admin_ibsng_groups: {
      groups_add_plan: 'Add plan',
      groups_plan_placeholder: 'For example: One month',
      groups_group_placeholder: 'IBSng group name',
      groups_duration_placeholder: 'Group Duration Days'
    },
    admin_email_templates: {
      email_templates_admin: 'Store administration',
      email_templates_back: 'Back to dashboard',
      email_templates_customer: 'Customer communication',
      email_templates_title: 'Manage email templates',
      email_templates_variables: 'Available template variables: {{site_name}}, {{customer_name}}, {{order_id}}, {{order_url}}, {{verification_code}}, {{expires_in}}, {{verification_url}}, {{expiration_date}}, and {{service_url}}. Variables unrelated to an event will be empty for that event.',
      email_templates_event: 'Email event',
      email_templates_subject: 'Email subject',
      email_templates_html: 'HTML template',
      email_templates_html_hint: 'Edit the email HTML. A sample-data preview is available on this page.',
      email_templates_text: 'Fallback text version',
      email_templates_save: 'Save template',
      email_templates_preview_save: 'Save and preview',
      email_templates_preview: 'Preview',
      email_templates_preview_title: 'Email template preview',
      email_event_purchase_success: 'Successful purchase',
      email_event_renewal_success: 'Successful renewal',
      email_event_order_recovery: 'Order recovery / purchase details',
      email_event_email_verification: 'Email verification',
      email_event_expiration_reminder: 'Expiration reminder'
    },
    admin_renewal_migration: {
      upgrade_admin: 'Store administration',
      upgrade_back_products: 'Back to products',
      upgrade_title: 'Database upgrade',
      upgrade_intro: 'This page checks and updates the database structure required for IBSng account renewals. It prepares group validity settings, the renewal history table, and default renewal gift values. The current database structure is checked first, and the upgrade is applied only when needed. The operation is designed to be safely repeatable.',
      upgrade_ready: 'The database structure required for IBSng renewals is up to date.',
      upgrade_products: 'Open plans and groups',
      upgrade_not_ready: 'The required database changes have not yet been applied to this installation.',
      upgrade_confirm: 'Apply the database upgrade required for IBSng account renewals?',
      upgrade_apply: 'Run database structure upgrade'
    },
    admin_provisioning: {
      provisioning_admin: 'Store administration',
      provisioning_dashboard: 'Dashboard',
      provisioning_ibsng_ops: 'IBSng operations',
      provisioning_title: 'Service activation queue',
      provisioning_intro: 'After payment confirmation, the queue receives an initial best-effort run. This page retries remaining queued items and resolves ambiguous outcomes. Errors or ambiguous responses remain in Needs review; items left running are not retried automatically. For needs_review or running, an administrator can record a manual result after checking IBSng directly; this sends no new remote request.',
      provisioning_id: 'ID',
      provisioning_order: 'Order',
      provisioning_username: 'Username',
      provisioning_service: 'Service',
      provisioning_server: 'Server',
      provisioning_status: 'Status',
      provisioning_result_error: 'Result / error',
      provisioning_action: 'Action',
      provisioning_empty: 'No items are in the queue.',
      provisioning_email_empty: 'No account-delivery email records have been added yet.',
      provisioning_run_once: 'Run once',
      provisioning_review_result: 'IBSng review result',
      provisioning_account_confirmed: 'Account confirmed',
      provisioning_account_failed: 'Account not created / definitive failure',
      provisioning_remote_user_id: 'IBSng numeric user ID (optional)',
      provisioning_review_note: 'Review note required',
      provisioning_review_placeholder: 'Describe what was checked and confirmed in IBSng',
      provisioning_resolve: 'Record resolution',
      provisioning_email_delivery: 'Account delivery email status',
      provisioning_email_intro: 'Failed delivery can be retried. This action sends the email again; do not retry after status sent.',
      provisioning_order_id: 'Order ID',
      provisioning_email: 'Email',
      provisioning_attempts: 'Attempts',
      provisioning_last_error: 'Last error',
      provisioning_updated: 'Updated',
      provisioning_retry_email: 'Retry delivery'
    },
    admin_forgot_password: {
      forgot_panel: 'Administrator panel',
      forgot_title: 'Forgot password',
      forgot_intro: 'Enter the email address registered for the administrator.',
      forgot_email: 'Administrator email',
      forgot_submit: 'Send password recovery link',
      forgot_back_login: 'Back to sign in'
    },
    admin_reset_password: {
      reset_panel: 'Administrator panel',
      reset_title: 'Set a new password',
      reset_success: 'Password changed successfully.',
      reset_login: 'Sign in to admin panel',
      reset_request_new: 'Request a new link',
      reset_password: 'New password (at least 12 characters)',
      reset_confirm: 'Confirm new password',
      reset_submit: 'Save new password',
      reset_invalid_link: 'The recovery link is invalid or expired.'
    },
    admin_login: {
      login_admin_panel: 'Administrator panel',
      login_title: 'Administrator sign in',
      login_intro: 'Sign in to manage the store.',
      login_username: 'Username',
      login_password: 'Password',
      login_submit: 'Sign in',
      login_forgot: 'Have you forgotten your password?',
      login_back_store: 'Back to store'
    },
    admin_email_settings: {
      email_settings_admin: 'Store administration',
      email_settings_back: 'Back to dashboard',
      email_settings_customer: 'Customer communication',
      email_settings_title: 'Email delivery settings',
      email_settings_intro: 'Email is sent through the hosting provider’s mail service; separate SMTP configuration is not required. For better delivery, the domain and sender must be authorized by the host and have appropriate email DNS records. This page sends a test email but cannot guarantee final delivery.',
      email_settings_from_address: 'Sender email',
      email_settings_from_name: 'Sender display name',
      email_settings_save: 'Save settings',
      email_settings_test_title: 'Send test email',
      email_settings_test_to: 'Send to this email',
      email_settings_send_test: 'Save and send test',
      email_settings_manage_templates: 'Manage templates'
    },
    admin_ibsng_users: {
      users_select_plan: 'Select plan',
      users_days_unit: 'days',
      users_change_group_title: 'Change an existing user group',
      users_change_group_intro: 'The user must already exist in IBSng. The destination group must be an active manual mapping for this server; use the Edit User form to change it.',
      users_change_group_empty: 'No active manual group mapping is available for this server.',
      users_existing_username: 'Existing user username',
      users_destination_group: 'Destination group',
      users_change_group_submit: 'Change group in IBSng'
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
      settings_telegram_id: 'Telegram phone number or username',
      settings_telegram_placeholder: 'Telegram username or number',
      settings_telegram_hint: 'Enter the ID only, without @ or a website address. The t.me link is generated automatically.',
      settings_whatsapp_target: 'WhatsApp phone number or username',
      settings_whatsapp_placeholder: 'WhatsApp username or number',
      settings_whatsapp_hint: 'Enter only the destination; a domain or URL is not required. This field is not limited to phone-number format.',
      settings_custom_link_label: 'Custom link title',
      settings_custom_label_placeholder: 'e.g. Online support',
      settings_custom_link_url: 'Full custom link URL',
      settings_custom_link_hint: 'For another messenger or service; only a valid HTTPS link is accepted.',
      settings_price_unit_title: 'Price display unit',
      settings_price_unit_hint: 'The base price in the database and order snapshot does not change. Only the displayed amount is converted between toman and rial; 1 toman = 10 rial. Fresh installations default to toman.',
      settings_display_unit: 'Display unit',
      settings_toman: 'Toman',
      settings_rial: 'Rial',
      settings_payment_title: 'Payment gateway',
      settings_payment_intro: 'Gateways are loaded from registered modules. Each provider has its own settings card and can be enabled independently; previous transactions keep their original provider.',
      settings_multi_gateway_intro: 'Enable any number of fully configured gateways. Customers choose one of the enabled gateways during checkout.',
      settings_gateway_enable: 'Enable this gateway',
      settings_zarinpal_sandbox_mode: 'Enable Zarinpal Sandbox mode',
      settings_zarinpal_sandbox_hint: 'Test payments are requested and verified through Zarinpal Sandbox without charging real money. If the Sandbox Merchant ID is left empty, it is generated and saved automatically when you save the settings. After a successful test payment, account provisioning will still run on the configured IBSng servers.',
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
      settings_crypto_display_hint: 'This option permits displaying the independent USDT price in the store. The price is shown only when at least one enabled and configured gateway supports USDT and is set to Crypto (USDT). This option alone does not enable crypto payments.',
      settings_show_usdt: 'Show USDT price (this does not enable crypto payments)',
      settings_credentials_title: 'Account credential creation policy',
      settings_credentials_hint: 'This policy applies globally to new orders. Previous orders and their stored information are unchanged.',
      settings_credential_method: 'Username and password setup method',
      settings_customer_credentials: 'Customer enters username and password',
      settings_auto_credentials: 'Store generates credentials automatically',
      settings_choose_credentials: 'Customer chooses manual or automatic',
      settings_username_prefix: 'Username prefix',
      settings_username_prefix_hint: 'Latin letters, numbers, and . _ - only; for example, atd',
      settings_username_suffix: 'Random username suffix length',
      settings_password_length: 'Generated password length (4–11 characters)',
      settings_password_charset: 'Password character set',
      settings_charset_digits: 'Numbers only',
      settings_charset_letters: 'Letters only',
      settings_charset_alnum: 'Letters and numbers',
      settings_renewal_gift_title: 'Account Renewal Gift Settings',
      settings_renewal_gift_hint: 'Gift days are configured separately for the first successful renewal and subsequent renewals. Reference defaults: 1 day for the first renewal and 5 days for later renewals.',
      settings_gift_first: 'First renewal gift (days)',
      settings_gift_repeat: 'Subsequent renewal gift (days)',
      settings_save: 'Save settings',
      settings_visit_store: 'View store'
    },
    admin_products: {
      products_section_basic: 'Product basics',
      products_section_validity: 'Validity and renewal',
      products_section_pricing: 'Pricing and currency',
      products_section_capacity: 'Data allowance and order',
      products_section_mappings: 'IBSng server and group selection',
      products_section_publication: 'Storefront publication',
      products_nav_mapping: 'Plan & group setup',
      products_nav_servers: 'IBSng servers',
      products_title: 'Manage products and plans',
      products_intro: 'Each product can be connected to one or more IBSng servers, with a corresponding group selected for each server. Product validity and renewal duration are specified in days. Enter the service volume in MB, GB, or TB; leave it blank or set it to zero for unlimited service. Enter the product price using the currency unit selected in the store settings.',
      products_prerequisite: 'To create a product, first add at least one active server in the “IBSng Servers” section and an active group for that server in the “Plans & Groups” section.',
      products_name: 'Product name',
      products_validity_days: 'Validity duration (days)',
      products_base_price: 'Base price (smallest unit of selected currency)',
      products_usdt_price: 'Independent USDT price (optional)',
      products_quota_placeholder: 'e.g. 100 or 5',
      products_usdt_hint: 'This price is stored separately and is not calculated from an exchange rate. It will not appear in the store until crypto payment is enabled.',
      products_currency_unit_hint: 'The toman/rial currency unit is determined by Site Settings.',
      products_quota_unit_hint: 'MB, GB and TB are converted using a base of 1024. Existing stored quota values remain unchanged when editing a product.',
      products_currency: 'ISO currency code (e.g. IRR)',
      products_quota: 'Data limit (bytes; blank means unknown/unlimited)',
      products_sort: 'Display order',
      products_mappings: 'IBSng servers and groups for this product',
      products_server: 'IBSng server',
      products_select_server: 'Select server',
      products_group_plan: 'Active group',
      products_select_group: 'Select group',
      products_remove_mapping: 'Remove',
      products_delete: 'Delete',
      products_add_mapping: 'Add server / group',
      products_mapping_hint: 'Select each server only once per product. Choose an active group on that server.',
      products_description: 'Description',
      products_show_store: 'Show in store',
      products_save: 'Save product',
      products_cancel_edit: 'Cancel editing',
      products_registered: 'Registered products',
      products_empty: 'No products have been added yet.',
      products_duration_label: 'Validity:',
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
      dashboard_group_mapping: 'Plan & group setup',
      dashboard_ibsng_users: 'IBSng account management',
      dashboard_renewal_migration: 'Database upgrade',
      dashboard_products: 'Product management',
      dashboard_provisioning: 'Provisioning queue',
      dashboard_sales: 'Sales report',
      dashboard_logout: 'Sign out',
      dashboard_eyebrow: 'Dashboard',
      dashboard_greeting: 'Welcome,',
      dashboard_intro: 'Manage servers, products, orders and store settings from this panel.',
      dashboard_database_error: 'Could not read database statistics. Check the connection and schema installation.',
      dashboard_stats_aria: 'Statistics',
      dashboard_stat_servers: 'IBSng servers',
      dashboard_stat_plans: 'Plans',
      dashboard_stat_orders: 'Orders',
      dashboard_stat_admins: 'Administrators',
      dashboard_catalog_title: 'Store catalog management',
      dashboard_catalog_intro: 'Define sale plans in Products and connect them to active IBSng server/group mappings. Active products appear in the public store.',
      dashboard_back_store: 'Back to store'
    },
    customer_account_page: {
      customer_back_store: 'Back to store',
      customer_track_orders: 'Track purchases',
      customer_panel: 'View account',
      customer_view_account: 'View account details',
      customer_intro: 'Enter your username and password. The four-digit code is only used to prevent automated access.',
      customer_username: 'Username',
      customer_password: 'Password',
      customer_captcha: 'Four-digit security code',
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
      status_not_found_help: 'The tracking code or order ID is invalid, or no matching order was found. Please check the information you entered.',
      status_tracking_id: 'Tracking code',
      status_order_state: 'Order status',
      status_paid_amount: 'Amount paid',
      status_purchase_date: 'Purchase date',
      status_payment_state: 'Payment status',
      status_product: 'Product',
      status_payment_unconfirmed: 'Payment for this order has not been confirmed yet. The purchased service will not be created until payment is confirmed.',
      status_waiting_provision: 'Payment has been recorded, but the activation result is not ready yet. Please check the order status again in a few minutes.',
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
      status_unavailable: 'This information is currently unavailable',
      status_password: 'Password',
      status_show_password: 'Show password',
      status_password_hidden: 'Not available for display at this time',
      status_support_review: 'If activation on one of the servers requires review, the support team will check its status on the server. The result for each server is displayed separately.',
      status_keep_tracking_private: 'The password is shown only for servers where account activation has been confirmed successfully. Keep your order tracking code confidential.'
    },
    guide_page: {
      guide_title: 'Account user guide',
      guide_intro: 'The login details for a purchased account are included in the delivery email and on the order tracking page. The connection steps depend on the service and application supported by your provider.',
      guide_intro_placeholder: 'Enter a short introduction for customers.',
      guide_step_account_title: '1. Find your account details',
      guide_step_account_body: 'Open the account delivery email or use the order tracking link. Enter the username and password exactly as provided. Check letter case and remove any extra spaces.',
      guide_step_app_title: '2. Choose the right app or connection method',
      guide_step_app_body: 'The application, operating system and connection method must match the service you purchased. Use the provider-specific setup guide; this page does not guess server or protocol details.',
      guide_step_troubleshoot_title: '3. Connection and initial troubleshooting',
      guide_troubleshoot_credentials: 'Check the username and password again.',
      guide_troubleshoot_expiry: 'Make sure the service is still active and has not expired.',
      guide_troubleshoot_destination: 'If the delivery email specifies a destination or connection method, use that exact option.',
      guide_troubleshoot_support: 'If the problem is not resolved, provide your tracking code, order ID, or username when contacting support. Do not share your password in public messages.',
      guide_tracking_title: 'Order tracking',
      guide_tracking_body: 'Use the order tracking page to view payment status, service activation and account details.',
      guide_tracking_link: 'Open order tracking'
    },
    payment_result_page: {
      payment_success_title: 'Payment confirmed',
      payment_success_body: 'The gateway confirmed your payment.',
      payment_order_id: 'Order ID:',
      payment_provisioning_note: 'Service activation is recorded and tracked separately.',
      payment_track_activation: 'Track activation status',
      payment_result_title: 'Payment result',
      payment_back_store: 'Back to store'
    },
    status: {
      pending_payment: 'Awaiting payment',
      paid: 'Payment confirmed',
      provisioning: 'Activating service',
      active: 'Active',
      inactive: 'Inactive',
      partial: 'Partially activated',
      failed: 'Failed',
      under_review: 'Under review',
      queued: 'Queued',
      needs_review: 'Needs review',
      running: 'Running',
      expired: 'Expired',
      pending: 'Pending',
      cancelled: 'Cancelled'
    },
    catalog: {
      nav_home: 'Home',
      nav_orders: 'Track order',
      nav_guide: 'Help',
      store_nav_aria: 'Store navigation',
      customer_account: 'Customer account',
      order_tracking: 'Track & recover orders',
      storefront_status: 'Account services',
      catalog_hero_title: 'Fast, easy, and reliable purchases and renewals',
      catalog_hero_intro: 'Browse the available services and review each service’s details before purchasing.',
      catalog_view_services: 'Browse services',
      catalog_no_registration: 'Purchase without creating a user account',
      catalog_section_eyebrow: 'Services',
      catalog_section_title: 'Available plans',
      catalog_load_error: 'The store is being updated. Please try again in a few moments.',
      catalog_empty_title: 'No services are currently available',
      catalog_empty_intro: 'The website is being updated. No services are currently available to display.',
      catalog_plan_eyebrow: 'Service details',
      catalog_crypto_price: 'Price in Tether',
      catalog_validity: 'Validity',
      catalog_quota: 'Service data allowance',
      catalog_order: 'Place order',
      admin_login: 'Administration'
    },
    common: {
      dialog_cancel: 'Cancel',
      dialog_confirm: 'Continue',
      dialog_title: 'Please confirm',
      dialog_close: 'Close',
      password_show: 'Show password',
      password_hide: 'Hide password'
    }
  };
  const noticeCopy = {
    'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.': 'Invalid request. Refresh the page and try again.',
    'شناسه تراکنش معتبر نیست.': 'The transaction ID is invalid.',
    'یادداشت بررسی درگاه الزامی است.': 'A gateway review note is required.',
    'تراکنش پس از ثبت یادداشت بررسی، ناموفق تعیین شد. امکان تلاش مجدد برای سفارش آزاد شد.': 'The transaction was marked failed after review. A new attempt is now available for the order.',
    'شناسه سفارش معتبر نیست.': 'The order ID is invalid.',
    'شناسه عملیات معتبر نیست.': 'The operation ID is invalid.',
    'تعیین تکلیف دستی ثبت شد؛ هیچ درخواست جدیدی به IBSng ارسال نشد.': 'The manual resolution was saved; no new request was sent to IBSng.',
    'درخواست بررسی و ارسال مجدد ایمیل تحویل ثبت شد؛ وضعیت نتیجه را در جدول ایمیل‌ها ببینید.': 'The delivery retry was requested. Check the email table for the result.',
    'تنظیمات راه‌های پشتیبانی، قیمت و هدیهٔ تمدید IBSng ذخیره شد.': 'Support, pricing and IBSng renewal gift settings were saved.',
    'واحد نمایش قیمت معتبر نیست.': 'The price display unit is invalid.',
    'نوع ارز درگاه پرداخت معتبر نیست.': 'The payment gateway currency type is invalid.',
    'درگاه پرداخت انتخاب‌شده معتبر نیست.': 'The selected payment gateway is invalid.',
    'تنظیمات تولید اطلاعات اکانت معتبر نیست.': 'The account credential generation settings are invalid.',
    'روزهای هدیه باید عدد صحیح بین صفر تا ۳۶۵۰ باشند.': 'Gift days must be a whole number between 0 and 3650.',
    'تنظیمات ایمیل ذخیره شد.': 'Email settings were saved.',
    'درخواست ارسال ایمیل تست به mail service هاست تحویل داده شد. تحویل نهایی به صندوق مقصد تضمین‌شده نیست.': 'The test email was submitted to the host mail service. Final inbox delivery is not guaranteed.',
    'mail() هاست ارسال را نپذیرفت. تنظیمات سرویس ایمیل هاست را بررسی کنید.': 'The host mail service rejected the message. Check its email configuration.',
    'قالب ایمیل ذخیره شد.': 'The email template was saved.',
    'ارتقای ساختار تمدید IBSng با موفقیت انجام شد.': 'The IBSng renewal schema upgrade completed successfully.',
    'ارتقای دیتابیس انجام نشد. دسترسی ALTER و INSERT دیتابیس را بررسی کنید.': 'The database upgrade failed. Check ALTER and INSERT permissions.',
    'سرور فعال IBSng ثبت نشده است. ابتدا از صفحه سرورها یک سرور فعال اضافه کنید.': 'No active IBSng server is configured. Add and activate a server first.',
    'ساختار دیتابیس برای تمدید IBSng آماده نیست.': 'The database schema is not ready for IBSng renewal.',
    'تمدید از پاسخ Web Panel IBSng تأیید شد.': 'Renewal was confirmed by the IBSng Web Panel.',
    'عملیات IBSng انجام نشد؛ وضعیت اتصال و پاسخ Web Panel را بررسی کنید.': 'The IBSng operation failed. Check connectivity and the Web Panel response.',
    'برای حذف اکانت، تأیید حذف الزامی است.': 'Deletion confirmation is required.',
    'این نام کاربری در IBSng وجود ندارد.': 'This username does not exist in IBSng.',
    'نام کاربری برای جست‌وجو الزامی است.': 'A username is required for lookup.',
    'نام کاربری برای دریافت اطلاعات اکانت الزامی است.': 'A username is required to retrieve account details.',
    'گروه، نام کاربری و رمز عبور الزامی هستند.': 'Group, username and password are required.',
    'Credit باید یک مقدار عددی معتبر باشد.': 'Credit must be a valid number.',
    'درخواست ایجاد حساب در IBSng با پاسخ موفق تأیید شد.': 'IBSng confirmed the account creation request.',
    'نتیجهٔ IBSng قطعی نیست یا تعارض وجود دارد؛ عملیات در وضعیت «نیازمند بررسی» قرار گرفت و retry خودکار انجام نشد.': 'The IBSng result is uncertain or conflicting. The operation needs review and was not retried automatically.',
    'ساخت حساب در IBSng تأیید شد.': 'Account creation was confirmed by IBSng.',
    'نام کاربری یا رمز عبور صحیح نیست.': 'The username or password is incorrect.',
    'تلاش‌های ورود بیش از حد بوده است. ۱۵ دقیقه دیگر دوباره تلاش کنید.': 'Too many login attempts. Please try again in 15 minutes.',
    'ورود در حال حاضر ممکن نیست. وضعیت نصب و دیتابیس را بررسی کنید.': 'Login is currently unavailable. Check installation and database status.',
    'کد چهاررقمی صحیح نیست؛ دوباره تلاش کنید.': 'The four-digit code is incorrect. Please try again.',
    'نام کاربری و رمز عبور خود را وارد کنید.': 'Enter your username and password.',
    'در حال حاضر امکان بررسی اکانت وجود ندارد.': 'Account lookup is currently unavailable.',
    'نام کاربری یا رمز عبور صحیح نیست، یا اکانت پیدا نشد.': 'The username or password is incorrect, or the account was not found.',
    'اگر این ایمیل سفارش ثبت‌شده‌ای داشته باشد، کد تأیید برای آن ارسال می‌شود.': 'If this email has an order, a verification code will be sent.',
    'کد معتبر نیست یا منقضی شده است.': 'The code is invalid or has expired.',
    'ایمیل با موفقیت تأیید شد.': 'Email verified successfully.',
    'از بخش سفارش‌های من خارج شدید.': 'You have signed out of My Orders.',
    'دریافت فهرست سفارش‌ها موقتاً ممکن نیست.': 'The order list is temporarily unavailable.',
    'این پلن دیگر برای فروش فعال نیست.': 'This plan is no longer available for sale.',
    'این نام کاربری قبلاً در سامانه وجود دارد. نام دیگری انتخاب کنید.': 'This username already exists. Please choose another one.',
    'در حال حاضر امکان بررسی نام کاربری وجود ندارد. چند لحظه بعد دوباره تلاش کنید.': 'The username cannot be checked right now. Please try again shortly.',
    'ارتباط با سرور سرویس برقرار نشد. لطفاً بعداً دوباره تلاش کنید.': 'The service server could not be reached. Please try again later.'
  };
  const originalNotices = new WeakMap();
  function applyNoticeCopy(language) {
    document.querySelectorAll('.notice, .account-error, .catalog-notice').forEach((element) => {
      if (element.children.length > 0 || element.hasAttribute('data-ui-text')) return;
      if (!originalNotices.has(element)) originalNotices.set(element, element.textContent);
      const original = originalNotices.get(element);
      const translated = language === 'en'
        ? noticeCopy[original]
        : Object.keys(noticeCopy).find((key) => noticeCopy[key] === original);
      element.textContent = translated || original;
    });
  }
  function applyPageCopy() {
    const language = root.lang === 'en' ? 'en' : 'fa';
    document.querySelectorAll('[data-ui-text]').forEach((element) => {
      const key = element.dataset.uiText;
      const translated = Object.values(pageCopy).map((dictionary) => dictionary[key]).find(Boolean);
      const target = element.querySelector(':scope > .ui-icon + span') || element;
      if (element.dataset.uiOriginalText === undefined) element.dataset.uiOriginalText = target.textContent;
      target.textContent = language === 'en' && translated ? translated : element.dataset.uiOriginalText;
    });
    document.querySelectorAll('[data-fa][data-en]').forEach((element) => {
      const label = element.querySelector(':scope > span');
      const next = language === 'en' ? element.dataset.en : element.dataset.fa;
      if (label) label.textContent = next;
      else element.textContent = next;
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
      const label = language === 'en'
        ? (shown ? pageCopy.customer_account_page.customer_hide_password : pageCopy.customer_account_page.customer_show_password)
        : (shown ? 'مخفی کردن رمز' : 'نمایش رمز');
      // Keep the SVG eye icon intact; language changes update accessible text only.
      button.setAttribute('aria-label', label);
      button.title = label;
    });
    document.querySelectorAll('[data-ui-event]').forEach((element) => {
      const key = 'email_event_' + element.dataset.uiEvent;
      const translated = pageCopy.admin_email_templates[key];
      if (element.dataset.uiOriginalText === undefined) element.dataset.uiOriginalText = element.textContent;
      element.textContent = language === 'en' && translated ? translated : element.dataset.uiOriginalText;
    });
    document.querySelectorAll('[data-ui-status]').forEach((element) => {
      const key = element.dataset.uiStatus;
      const translated = pageCopy.status[key];
      if (element.dataset.uiOriginalText === undefined) element.dataset.uiOriginalText = element.textContent;
      element.textContent = language === 'en' && translated ? translated : element.dataset.uiOriginalText;
    });
    document.querySelectorAll('[data-ui-validity-days]').forEach((element) => {
      const days = Number(element.dataset.uiValidityDays);
      if (!Number.isFinite(days) || days < 1) return;
      element.textContent = new Intl.NumberFormat(language === 'en' ? 'en' : 'fa').format(days) + ' ' + (language === 'en' ? (days === 1 ? 'day' : 'days') : 'روز');
    });
    applyNoticeCopy(language);
  }
  function formatEnglishDigits(language) {
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    let node;
    const persianDigits = /[۰-۹٠-٩]/;
    const toLatin = (value) => value.replace(/[۰-۹٠-٩]/g, (digit) => {
      const code = digit.charCodeAt(0);
      return String(code >= 0x06f0 && code <= 0x06f9 ? code - 0x06f0 : code - 0x0660);
    });
    while ((node = walker.nextNode())) {
      const parent = node.parentElement;
      if (!parent || parent.closest('script,style,textarea,input,code,pre,[contenteditable="true"],[data-preserve-digits]')) continue;
      if (language === 'en') {
        if (node.__atdOriginalDigitText === undefined && persianDigits.test(node.nodeValue)) {
          Object.defineProperty(node, '__atdOriginalDigitText', {value: node.nodeValue, writable: true, configurable: true});
        }
        if (node.__atdOriginalDigitText !== undefined) node.nodeValue = toLatin(node.__atdOriginalDigitText);
      } else if (node.__atdOriginalDigitText !== undefined) {
        node.nodeValue = node.__atdOriginalDigitText;
      }
    }
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
    formatEnglishDigits(root.lang);
  }
  function mount() {
    if (document.querySelector('.atd-preferences')) return;
    const controls = document.createElement('div');
    controls.className = 'atd-preferences';
    controls.setAttribute('aria-label',get(storage.lang,get(storage.legacyLang,root.lang||'fa'))==='fa'?'ظاهر و زبان':'Appearance and language');
    const initialLanguage=get(storage.lang,get(storage.legacyLang,root.lang||'fa'))==='fa'?'fa':'en';
    const initialMode=get(storage.mode,'dark')==='light'?'light':'dark';
    controls.innerHTML =
      '<button type="button" class="atd-pref-mode" aria-label="'+(initialLanguage==='fa'?(initialMode==='dark'?'روشن کردن حالت روشن':'روشن کردن حالت تاریک'):(initialMode==='dark'?'Switch to light mode':'Switch to dark mode'))+'" aria-pressed="'+(initialMode==='light')+'" title="'+(initialLanguage==='fa'?(initialMode==='dark'?'روشن کردن حالت روشن':'روشن کردن حالت تاریک'):(initialMode==='dark'?'Switch to light mode':'Switch to dark mode'))+'">'+svg(initialMode==='dark'?'sun':'moon')+'</button>'+
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
      b.innerHTML='<span></span>'; b.addEventListener('click',()=>{set(storage.accent,name);apply();palette.querySelectorAll('button').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));closePalette();});
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
      modeButton.title=language==='fa'?(next==='dark'?'روشن کردن حالت روشن':'روشن کردن حالت تاریک'):(next==='dark'?'Switch to light mode':'Switch to dark mode');
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
      modeButton.title=next==='fa'?(currentMode==='dark'?'روشن کردن حالت روشن':'روشن کردن حالت تاریک'):(currentMode==='dark'?'Switch to light mode':'Switch to dark mode');
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
      modeButton.title = next === 'fa' ? (currentMode === 'dark' ? 'روشن کردن حالت روشن' : 'روشن کردن حالت تاریک') : (currentMode === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
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
    // Shared administration navigation is server-rendered; keep its labels in sync with the global language preference.
    const adminNav = document.querySelector('.atd-admin-nav');
    if (adminNav) {
      const updateAdminNav = (language) => {
        adminNav.setAttribute('aria-label', language === 'fa' ? 'منوی مدیریت' : 'Administration navigation');
        adminNav.querySelectorAll('[data-fa][data-en]').forEach((item) => {
          const label = item.querySelector('span');
          if (label) label.textContent = language === 'fa' ? item.dataset.fa : item.dataset.en;
          else item.textContent = language === 'fa' ? item.dataset.fa : item.dataset.en;
        });
      };
      updateAdminNav(root.lang === 'en' ? 'en' : 'fa');
      document.addEventListener('atd:language-change', (event) => {
        updateAdminNav(event.detail?.language === 'en' ? 'en' : 'fa');
      });
    }
    document.querySelectorAll('.atd-store-nav').forEach((nav) => {
      nav.setAttribute('aria-label', root.lang === 'fa' ? 'منوی فروشگاه' : 'Store navigation');
      nav.querySelectorAll('[data-fa][data-en]').forEach((link) => {
        const label = link.querySelector('span');
        if (label) label.textContent = root.lang === 'fa' ? link.dataset.fa : link.dataset.en;
      });
      document.addEventListener('atd:language-change', (event) => {
        const language = event.detail?.language === 'en' ? 'en' : 'fa';
        nav.setAttribute('aria-label', language === 'fa' ? 'منوی فروشگاه' : 'Store navigation');
        nav.querySelectorAll('[data-fa][data-en]').forEach((link) => {
          const label = link.querySelector('span');
          if (label) label.textContent = language === 'fa' ? link.dataset.fa : link.dataset.en;
        });
      });
    });
    const host=document.getElementById('atd-preferences-host')||document.querySelector('.atd-preferences-slot,.atd-admin-preferences-slot')||document.querySelector('.topbar,.admin-top,.store-header,.admin-header,.top');
    if (!host) return;
    host.appendChild(controls);
    document.addEventListener('pointerdown',(event)=>{
      if(!controls.contains(event.target)) closePalette();
    });
    document.addEventListener('keydown',(event)=>{
      if(event.key==='Escape') closePalette();
    });
  }
  // Convert standalone Gregorian date strings for display only. Keep the original
  // text on each node so language changes never convert an already converted date.
  const formatStandaloneDates = (requestedLanguage) => {
    const language = requestedLanguage === 'en' ? 'en' : requestedLanguage === 'fa' ? 'fa' : root.lang === 'en' ? 'en' : 'fa';
    const locale = language === 'en' ? 'en-GB' : 'fa-IR-u-ca-persian';
    const datePattern = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/;
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    let node;
    while ((node = walker.nextNode())) {
      const parent = node.parentElement;
      if (!parent || parent.closest('script,style,textarea,input,select,option,code,.report-date,[data-no-date-format]')) continue;
      if (!Object.prototype.hasOwnProperty.call(node, '__atdOriginalDateText')) {
        const candidate = node.nodeValue.trim();
        if (!datePattern.test(candidate)) continue;
        Object.defineProperty(node, '__atdOriginalDateText', {value: node.nodeValue, writable: true, configurable: true});
      }
      const original = node.__atdOriginalDateText;
      const value = original.trim();
      const match = value.match(datePattern);
      if (!match) continue;
      const [, year, month, day, hour, minute, second] = match;
      const parts = [Number(year), Number(month), Number(day)];
      const date = new Date(parts[0], parts[1] - 1, parts[2], Number(hour || 0), Number(minute || 0), Number(second || 0));
      if (date.getFullYear() !== parts[0] || date.getMonth() !== parts[1] - 1 || date.getDate() !== parts[2]
          || (hour !== undefined && (date.getHours() !== Number(hour) || date.getMinutes() !== Number(minute) || date.getSeconds() !== Number(second || 0)))) {
        node.nodeValue = original;
        continue;
      }
      const options = {year: 'numeric', month: '2-digit', day: '2-digit'};
      if (hour !== undefined) {
        options.hour = '2-digit';
        options.minute = '2-digit';
        if (second !== undefined) options.second = '2-digit';
        options.hourCycle = 'h23';
      }
      node.nodeValue = new Intl.DateTimeFormat(locale, options).format(date);
    }
  };
  document.addEventListener('atd:language-change', (event) => formatStandaloneDates(event.detail?.language));

  apply();
  formatStandaloneDates();
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount,{once:true});else mount();
})();
