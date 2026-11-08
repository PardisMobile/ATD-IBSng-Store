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
    'customer status maps the plan to the primary IBSng group and keeps server display name separate' => is_string($status)
        && str_contains($status, "SELECT plan_name FROM ibsng_groups WHERE server_id=? AND group_name=? AND is_active=1")
        && str_contains($status, "\$data['group']")
        && str_contains($status, "['service_name']")
        && str_contains($status, "['server_name']"),
    'tracking page includes purchase amount, payment status, and timestamp' => is_string($status) && str_contains($status, 'price_minor_snapshot') && str_contains($status, 'price_usdt_snapshot') && str_contains($status, 'payment_status') && str_contains($status, 'created_at'),
    'credential decrypt function is used' => is_string($status) && str_contains($status, 'app_decrypt_secret'),
    'credentials are shown only for succeeded attempts' => is_string($status) && str_contains($status, "['status'] === 'succeeded'") && str_contains($status, 'نمایش رمز عبور'),
    'verified account password is available regardless of tracking code format' => is_string($status)
        && str_contains($status, "\$successfulAttempts = array_values(array_filter(\$attempts")
        && str_contains($status, "\$attempt['status'] === 'succeeded'")
        && str_contains($status, "if (is_string(\$accountPassword))")
        && str_contains($status, 'رمز عبور فقط برای سرورهایی نمایش داده می‌شود که فعال‌سازی اکانت در آن‌ها با موفقیت تأیید شده است. کد رهگیری سفارش را محرمانه نگه دارید.')
        && !str_contains($status, "!\$isTrackingCode && \$attempt['status'] === 'succeeded'"),
    'account card displays each optional service detail only when its value is available' => is_string($status)
        && str_contains($status, 'data-ui-password-toggle')
        && str_contains($status, 'data-fa="سرویس" data-en="Service"')
        && str_contains($status, "app_url('userguide.php')")
        && str_contains($status, "['service_name']")
        && str_contains($status, "['server_name']")
        && str_contains($status, "status_validity")
        && str_contains($status, "status_quota")
        && str_contains($status, "status_expiry")
        && str_contains($status, "if (is_array(\$details) && trim((string)(\$details['plan'] ?? '')) !== '')")
        && str_contains($status, "if (is_array(\$details) && trim((string)(\$details['expiry'] ?? '')) !== '')"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}.\n");
        exit(1);
    }
}
echo "Customer order account display contract checks passed (" . count($checks) . " checks).\n";
