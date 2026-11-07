<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$templates = file_get_contents($root . '/app/EmailTemplates.php');
$adminTemplates = file_get_contents($root . '/admin/email-templates.php');
$checkout = file_get_contents($root . '/checkout.php');
$delivery = file_get_contents($root . '/app/CustomerAccountDelivery.php');
$recovery = file_get_contents($root . '/my-orders.php');
$schemaSync = file_get_contents($root . '/app/DatabaseSchemaSync.php');
$schema = file_get_contents($root . '/database/schema.sql');
$preferences = file_get_contents($root . '/assets/js/ui-preferences.js');

$checks = [
    'independent English defaults exist for every email event' => is_string($templates) && str_contains($templates, 'DEFAULTS_EN') && substr_count($templates, "'subject' =>") >= 10,
    'template lookup selects locale-specific setting keys' => is_string($templates) && str_contains($templates, "'email_template_' . $event . '_en_' . $field") && str_contains($templates, 'applyDocumentDirection'),
    'Persian and English templates receive matching document direction' => is_string($templates) && str_contains($templates, "'rtl'") && str_contains($templates, "'ltr'") && str_contains($templates, 'text-align:'),
    'admin can edit and preview each locale independently' => is_string($adminTemplates) && str_contains($adminTemplates, 'template-language') && str_contains($adminTemplates, "($language === 'en' ? '_en' : '')"),
    'checkout captures selected storefront language with the order' => is_string($checkout) && str_contains($checkout, 'site_language') && str_contains($checkout, 'buyer_email,language,product_id') && str_contains($checkout, '$orderLanguage'),
    'legacy installations receive an additive language column' => is_string($schemaSync) && str_contains($schemaSync, "ADD COLUMN language CHAR(2) NOT NULL DEFAULT 'fa'") && str_contains($schemaSync, "!$this->columnExists('orders', 'language')"),
    'fresh installs include order language defaulting to Persian' => is_string($schema) && str_contains($schema, "language CHAR(2) NOT NULL DEFAULT 'fa'"),
    'account delivery selects template and generated account details by saved order language' => is_string($delivery) && str_contains($delivery, "EmailTemplates::get($pdo, 'purchase_success', $language)") && str_contains($delivery, 'Account details'),
    'order recovery email uses the language selected on the recovery form' => is_string($recovery) && str_contains($recovery, "EmailTemplates::get($pdo, 'order_recovery', $language)") && str_contains($recovery, 'data-ui-language-field'),
    'English UI has display-only Persian digit normalization' => is_string($preferences) && str_contains($preferences, 'formatEnglishDigits') && str_contains($preferences, '__atdOriginalDigitText'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}.\n");
        exit(1);
    }
}
echo 'Email language and presentation contract checks passed (' . count($checks) . " checks).\n";
