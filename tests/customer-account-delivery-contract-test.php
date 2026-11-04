<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$delivery = file_get_contents($root . '/app/CustomerAccountDelivery.php');
$dispatcher = file_get_contents($root . '/app/IBSngProvisioningDispatcher.php');
$admin = file_get_contents($root . '/admin/provisioning.php');
$schema = file_get_contents($root . '/database/schema.sql');
$migration = file_get_contents($root . '/database/migrations/2026-11-03-customer-email-deliveries.sql');
$template = file_get_contents($root . '/app/EmailTemplates.php');
$guide = file_get_contents($root . '/guide.php');

$checks = [
    'delivery checks paid status and recipient email' => is_string($delivery) && str_contains($delivery, "payment_status'] !== 'paid'") && str_contains($delivery, "buyer_email"),
    'delivery waits until no create attempt is queued or running' => is_string($delivery) && str_contains($delivery, "['queued','running']"),
    'only successful attempts are selected for email destination list' => is_string($delivery) && str_contains($delivery, "['status'] === 'succeeded'"),
    'durable order/event unique claim is in schema and migration' => is_string($schema) && is_string($migration) && str_contains($schema, 'uq_customer_email_delivery') && str_contains($migration, 'uq_customer_email_delivery'),
    'failed email can be explicitly retried but sent email cannot be claimed' => is_string($delivery) && str_contains($delivery, "AND status='failed'") && str_contains($delivery, "status='sending'"),
    'provisioning invokes delivery after finishing state commit' => is_string($dispatcher) && str_contains($dispatcher, 'CustomerAccountDelivery::afterProvisioning'),
    'template carries account details, guide, and order tracking' => is_string($template) && str_contains($template, '{{account_details_html}}') && str_contains($template, '{{guide_url}}') && str_contains($template, '{{order_url}}'),
    'admin has explicit failed-email retry action' => is_string($admin) && str_contains($admin, 'retry_delivery') && str_contains($admin, 'تلاش مجدد ارسال'),
    'account delivery order URL uses the order-status query parameter' => is_string($delivery) && str_contains($delivery, 'order-status.php?order=') && str_contains($delivery, "rawurlencode((string)\$order['public_id'])"),
    'guide page exists with order tracking link' => is_string($guide) && str_contains($guide, 'order-status.php'),
    'unified customer page offers tracking by order ID and recovery by email' => is_string(file_get_contents($root . '/my-orders.php')) && str_contains(file_get_contents($root . '/my-orders.php'), 'name="order"') && str_contains(file_get_contents($root . '/my-orders.php'), 'name="email"') && str_contains(file_get_contents($root . '/my-orders.php'), 'action="<?= e(app_url(\'order-status.php\')) ?>"'),
    'storefront links to unified order recovery and tracking' => is_string(file_get_contents($root . '/index.php')) && str_contains(file_get_contents($root . '/index.php'), 'storefront_header(') && is_string(file_get_contents($root . '/app/StorefrontView.php')) && str_contains(file_get_contents($root . '/app/StorefrontView.php'), 'my-orders.php'),
    'order detail page links back to unified order recovery' => is_string(file_get_contents($root . '/order-status.php')) && str_contains(file_get_contents($root . '/order-status.php'), 'storefront_header(') && is_string(file_get_contents($root . '/app/StorefrontView.php')) && str_contains(file_get_contents($root . '/app/StorefrontView.php'), 'my-orders.php'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}.\n");
        exit(1);
    }
}
echo "Account delivery contract checks passed (" . count($checks) . " checks).\n";
