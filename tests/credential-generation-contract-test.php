<?php
declare(strict_types=1);
$settings = file_get_contents(dirname(__DIR__) . '/admin/settings.php');
$checkout = file_get_contents(dirname(__DIR__) . '/checkout.php');
if (!is_string($settings) || !is_string($checkout)) { fwrite(STDERR, "FAIL: unable to read credential sources.\n"); exit(1); }
$checks = [
'username mode is validated and persisted' => str_contains($settings, "'username_suffix_charset'") && str_contains($settings, "['digits', 'letters', 'alphanumeric']"),
    'username suffix length is limited to 4–11 characters' => str_contains($settings, "'username_suffix_length'] < 4") && str_contains($settings, "'username_suffix_length'] > 11") && str_contains($settings, 'min="4" max="11"') && str_contains($checkout, 'min(11, (int)($credentialSettings[\'username_suffix_length\'] ?? 5))'),
'username mode selector is beside prefix settings' => str_contains($settings, 'name="username_suffix_charset"') && strpos($settings, 'name="username_prefix"') < strpos($settings, 'name="username_suffix_charset"'),
'checkout loads selected username mode' => str_contains($checkout, "'username_suffix_charset'"),
'digits-only suffix' => str_contains($checkout, "'digits' => '23456789'"),
'letters-only suffix remains available' => str_contains($checkout, "'letters' => 'abcdefghijklmnopqrstuvwxyz'"),
'default username mode is digits in settings and checkout' => str_contains($settings, "? support_setting(\$pdo, 'username_suffix_charset') : 'digits'") && str_contains($checkout, "'username_suffix_charset'] ?? 'digits'"),
'alphanumeric suffix' => str_contains($checkout, "'alphanumeric' => 'abcdefghijklmnopqrstuvwxyz0123456789'"),
'prefix is retained before generated suffix' => str_contains($checkout, '$candidate = $credentialPrefix . checkout_random_string($generatedUsernameAlphabet, $credentialSuffixLength);'),
];
foreach ($checks as $label => $passed) { if (!$passed) { fwrite(STDERR, "FAIL: {$label}.\n"); exit(1); } }
echo "Credential generation contract checks passed (" . count($checks) . " checks).\n";
