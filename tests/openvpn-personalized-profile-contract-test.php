<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/OpenVpnManager.php';

function openvpn_profile_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$original = "client\r\n"
    . "dev tun\r\n"
    . "proto tcp-client\r\n"
    . "remote op1.vpnkish.com\r\n"
    . "port 8894\r\n"
    . "auth-user-pass\r\n"
    . "<ca>\r\nCA-CONTENT\r\n</ca>\r\n"
    . "<cert>\r\nCERT-CONTENT\r\n</cert>\r\n"
    . "<key>\r\nPRIVATE-KEY-CONTENT\r\n</key>\r\n";

openvpn_profile_assert(
    OpenVpnManager::validateProfile($original) === $original,
    'Profile validation must not normalize or rewrite the uploaded bytes.'
);

$expected = str_replace(
    "auth-user-pass\r\n",
    "<auth-user-pass>\r\ncustomer01\r\nP@ss-word-01\r\n</auth-user-pass>\r\n",
    $original
);
$personalized = OpenVpnManager::profileWithCredentials($original, 'customer01', 'P@ss-word-01');

openvpn_profile_assert(
    $personalized === $expected,
    'Personalization must replace only the standalone auth-user-pass line and preserve all other bytes.'
);
openvpn_profile_assert(
    str_contains($personalized, "remote op1.vpnkish.com\r\nport 8894\r\n"),
    'Remote hostname and port must remain unchanged.'
);
openvpn_profile_assert(
    str_contains($personalized, "<key>\r\nPRIVATE-KEY-CONTENT\r\n</key>\r\n"),
    'Embedded private-key block must remain byte-for-byte unchanged.'
);

$duplicateAuth = $original . "auth-user-pass\r\n";
try {
    OpenVpnManager::profileWithCredentials($duplicateAuth, 'customer01', 'P@ss-word-01');
    throw new RuntimeException('Multiple auth-user-pass directives must be rejected.');
} catch (RuntimeException $e) {
    openvpn_profile_assert(
        str_contains($e->getMessage(), 'دستور مستقل auth-user-pass'),
        'Duplicate auth-user-pass rejection must be explicit.'
    );
}

$endpoint = file_get_contents(dirname(__DIR__) . '/openvpn-profile.php');
openvpn_profile_assert(
    is_string($endpoint)
        && str_contains($endpoint, "Content-Type: application/x-openvpn-profile")
        && str_contains($endpoint, "Content-Disposition: inline")
        && str_contains($endpoint, "filename*=UTF-8")
        && str_contains($endpoint, '$profileName')
        && str_contains($endpoint, 'http_response_code(500)')
        && str_contains($endpoint, 'OpenVPN profile generation failed.'),
    'Profile endpoint must return the OpenVPN MIME type and use the configured profile name as download filename.'
);


$managerSource = file_get_contents(dirname(__DIR__) . '/app/OpenVpnManager.php');
$downloadSource = file_get_contents(dirname(__DIR__) . '/openvpn-download.php');
$fileEndpointSource = file_get_contents(dirname(__DIR__) . '/openvpn-file.php');
$rootHtaccess = file_get_contents(dirname(__DIR__) . '/.htaccess');
$schemaSource = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
$schemaSyncSource = file_get_contents(dirname(__DIR__) . '/app/DatabaseSchemaSync.php');
openvpn_profile_assert(is_string($managerSource) && str_contains($managerSource, "dirname(__DIR__) . '/ovpn'") && str_contains($managerSource, 'generated_filename') && str_contains($managerSource, 'writeGeneratedFile') && str_contains($managerSource, 'resolveGeneratedFile') && str_contains($managerSource, 'removeGeneratedFile'), 'Personalized profiles must be materialized in ovpn and linked to revocable database records.');
openvpn_profile_assert(
    is_string($managerSource)
        && str_contains($managerSource, "in_array(\$row['link_type'], ['public', 'personalized'], true)")
        && str_contains($managerSource, "if (\$row['link_type'] === 'personalized')")
        && str_contains($managerSource, "SELECT id FROM orders WHERE id=? AND payment_status='paid' LIMIT 1"),
    'Generated public profiles must be served without an order check, while personalized profiles must retain paid-order validation.'
);
openvpn_profile_assert(is_string($downloadSource) && str_contains($downloadSource, "openvpn://import-profile/") && str_contains($downloadSource, 'Import to OpenVPN') && str_contains($downloadSource, 'generatedProfileUrl'), 'The customer landing page must expose a direct OpenVPN Connect import action using the generated file URL.');
openvpn_profile_assert(
    is_string($downloadSource)
        && str_contains($downloadSource, "$" . "personalUrl = app_absolute_url('openvpn-profile.php?t=' . rawurlencode($token));")
        && str_contains($downloadSource, "$" . "personalFileUrl = $" . "manager->generatedProfileUrl($token);")
        && str_contains($downloadSource, "$" . "importUrl = 'openvpn://import-profile/' . $" . "personalFileUrl;"),
    'The personalized card must display/copy the clean profile endpoint URL while the Import deep link continues using the generated physical file URL.'
);
openvpn_profile_assert(
    is_string($downloadSource)
        && str_contains($downloadSource, '$publicFileUrl = $manager->publicProfileFileUrl($profileId);')
        && strpos($downloadSource, '$publicFileUrl = $manager->publicProfileFileUrl($profileId);') > strpos($downloadSource, "if (\$token !== '')")
        && strpos($downloadSource, '$publicImportUrl = \'openvpn://import-profile/\' . $publicFileUrl;') > strpos($downloadSource, '$publicFileUrl = $manager->publicProfileFileUrl($profileId);'),
    'The public Import deep link must be generated for both public and personalized landing pages after the profile ID is resolved.'
);
openvpn_profile_assert(is_string($fileEndpointSource) && str_contains($fileEndpointSource, 'resolveGeneratedFile') && str_contains($fileEndpointSource, 'application/x-openvpn-profile') && str_contains($fileEndpointSource, 'filename*=UTF-8'), 'Generated profile URLs must be served by the validating OpenVPN endpoint and preserve the configured profile display filename.');
openvpn_profile_assert(is_string($rootHtaccess) && str_contains($rootHtaccess, 'openvpn-file.php') && str_contains($rootHtaccess, 'ovpn/'), 'The generated profile URL must route through the protected file endpoint.');
openvpn_profile_assert(is_string($schemaSource) && str_contains($schemaSource, 'generated_filename') && is_string($schemaSyncSource) && str_contains($schemaSyncSource, 'generated_filename'), 'Fresh installs and existing databases must both support generated OpenVPN filenames.');

echo "OpenVPN personalized profile contract checks passed.\\n";
