<?php
declare(strict_types=1);
$client = file_get_contents(dirname(__DIR__) . '/app/IBSngClient.php');
$checkout = file_get_contents(dirname(__DIR__) . '/checkout.php');
$logger = file_get_contents(dirname(__DIR__) . '/app/DiagnosticLogger.php');
$guard = file_get_contents(dirname(__DIR__) . '/storage/.htaccess');
$checks = [
'client loads logger' => str_contains($client, "require_once __DIR__ . '/DiagnosticLogger.php'"),
'login form detector exists' => str_contains($client, 'isLoginFormResponse'),
'stale-session recovery outcomes are logged' => str_contains($client, 'ibsng_stale_session_detected') && str_contains($client, 'ibsng_session_recovery_succeeded') && str_contains($client, 'ibsng_session_recovery_failed'),
'transport details are logged' => str_contains($client, 'curl_errno') && str_contains($client, 'curl_error'),
'checkout logs swallowed generation exceptions' => str_contains($checkout, 'checkout_username_generation_failed') && str_contains($checkout, '$generationError->getMessage()'),
'customer message remains generic' => str_contains($checkout, "app_message('username_available_check_failed')"),
'text log path exists in logger' => str_contains($logger, 'ibsng-debug.txt'),
'append lock and restrictive permissions' => str_contains($logger, 'FILE_APPEND | LOCK_EX') && str_contains($logger, 'chmod($file, 0600)'),
'sensitive context keys are filtered' => str_contains($logger, 'password|cookie|secret|token|authorization|credential|username|email|phone|body|payload'),
'logger is best effort' => str_contains($logger, 'catch (Throwable $ignored)'),
'storage is denied by Apache' => str_contains($guard, 'Require all denied'),
];
foreach ($checks as $label => $passed) { if (!$passed) { fwrite(STDERR, "FAIL: {$label}\n"); exit(1); } }
echo "IBSng session diagnostics contract passed (" . count($checks) . " checks).\n";
