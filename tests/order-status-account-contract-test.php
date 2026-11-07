<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$status = file_get_contents($root . '/order-status.php');
$schema = file_get_contents($root . '/database/schema.sql');
$servers = file_get_contents($root . '/admin/ibsng-servers.php');

$checks = [
    'service name is optional in fresh schema' => is_string($schema) && str_contains($schema, 'service_name VARCHAR(150) NULL'),
    'admin form accepts service name on create and update' => is_string($servers) && substr_count($servers, 'name="service_name"') === 2,
    'server name and customer service label are distinct' => is_string($servers)
        && str_contains($servers, 'data-fa="نام سرور"')
        && str_contains($servers, 'data-en="Server Name"')
        && str_contains($servers, 'service_name'),
    'customer status loads service name' => is_string($status) && str_contains($status, 's.service_name') && str_contains($status, "['service_name']"),
    'tracking page includes purchase amount, payment status, and timestamp' => is_string($status) && str_contains($status, 'price_minor_snapshot') && str_contains($status, 'price_usdt_snapshot') && str_contains($status, 'payment_status') && str_contains($status, 'created_at'),
    'credential decrypt function is used' => is_string($status) && str_contains($status, 'app_decrypt_secret'),
    'credentials are shown only for succeeded attempts' => is_string($status) && str_contains($status, "['status'] === 'succeeded'") && str_contains($status, 'نمایش رمز عبور'),
    'verified account password is available regardless of tracking code format' => is_string($status)
        && str_contains($status, "\$successfulAttempts = array_values(array_filter(\$attempts")
        && str_contains($status, "\$attempt['status'] === 'succeeded'")
        && str_contains($status, "if (is_string(\$accountPassword))")
        && str_contains($status, 'رمز عبور فقط برای سرورهایی نمایش داده می‌شود که فعال‌سازی اکانت در آن‌ها با موفقیت تأیید شده است. کد رهگیری سفارش را محرمانه نگه دارید.')
        && !str_contains($status, "!\$isTrackingCode && \$attempt['status'] === 'succeeded'"),
    'empty service name is suppressed without rendering an empty label' => is_string($status)
        && str_contains($status, "if (trim((string)(\$attempt['service_name'] ?? '')) !== ''):")
        && str_contains($status, "<strong><?= e((string)\$attempt['service_name']) ?></strong>")
        && !str_contains($status, "? (string)\$attempt['service_name'] : ''"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}.\n");
        exit(1);
    }
}
echo "Customer order account display contract checks passed (" . count($checks) . " checks).\n";
