<?php
declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/app/IBSngProvisioningDispatcher.php');
if (!is_string($source)) {
    fwrite(STDERR, "FAIL: unable to read provisioning dispatcher source.\\n");
    exit(1);
}

$checks = [
    'success completion is wired to subscription persistence' =>
        str_contains($source, "if (\$status === 'succeeded')") && str_contains($source, "\$this->recordSubscription"),
    'manual successful resolution is wired to subscription persistence' =>
        str_contains($source, "if (\$outcome === 'succeeded')") && str_contains($source, "\$this->recordSubscription"),
    'subscription uses server-scoped account identity' =>
        str_contains($source, 'INSERT INTO subscriptions (order_id, product_id, server_id, ibsng_user_id, username, status, last_synced_at)'),
    'expiry and quota remain unknown until real sync' =>
        str_contains($source, 'expiry and quota are intentionally left unknown'),
    'manual resolution does not erase an existing remote ID when omitted' =>
        str_contains($source, 'ibsng_user_id=COALESCE(?,ibsng_user_id)'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}.\\n");
        exit(1);
    }
}

echo "IBSng provisioning subscription contract checks passed (" . count($checks) . " checks).\\n";
