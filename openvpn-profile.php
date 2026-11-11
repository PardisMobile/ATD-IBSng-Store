<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/OpenVpnManager.php';

try {
    $manager = new OpenVpnManager(Database::connection());
    $token = (string)($_GET['t'] ?? '');
    if ($token !== '') {
        $result = $manager->resolveToken($token);
        if ($result['type'] !== 'personalized') throw new RuntimeException('Personalized token required.');
        $content = OpenVpnManager::profileWithCredentials($result['content'], $result['username'], $result['password']);
        $profileName = (string)$result['name'];
    } else {
        $result = $manager->resolvePublicProfile((int)($_GET['p'] ?? 0));
        $content = $result['content'];
        $profileName = (string)$result['name'];
    }
    // OpenVPN Connect derives its imported display label partly from the
    // downloaded filename. Keep the configured profile name in that filename.
    $filenameBase = trim($profileName);
    if (function_exists('iconv')) {
        $asciiName = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filenameBase);
        if (is_string($asciiName) && trim($asciiName) !== '') $filenameBase = $asciiName;
    }
    $filenameBase = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filenameBase) ?? '';
    $filenameBase = trim($filenameBase, '.-_');
    if ($filenameBase === '') $filenameBase = 'openvpn-profile';
    $filename = $filenameBase . '.ovpn';
    $utf8Filename = rawurlencode(trim($profileName) . '.ovpn');

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: inline; filename="' . $filename . '"; filename*=UTF-8\'\'' . $utf8Filename);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('Content-Length: ' . strlen($content));
    echo $content;
} catch (Throwable $e) {
    // A missing/expired token is an application error, not a missing route.
    // Return 500 for profile-generation failures so clients and server logs
    // distinguish them from a genuine web-server/router 404.
    error_log('OpenVPN profile response failed: ' . $e->getMessage());
    http_response_code(500);
    header('Cache-Control: no-store');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'OpenVPN profile generation failed. Please contact support.';
}
