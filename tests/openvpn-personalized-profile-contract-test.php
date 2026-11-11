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
        && str_contains($endpoint, "filename*=UTF-8")
        && str_contains($endpoint, "$profileName"),
    'Profile endpoint must return the OpenVPN MIME type and use the configured profile name as download filename.'
);

echo "OpenVPN personalized profile contract checks passed.\n";
