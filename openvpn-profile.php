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
        $filename = 'openvpn-personal-profile';
    } else {
        $result = $manager->resolvePublicProfile((int)($_GET['p'] ?? 0));
        $content = $result['content'];
        $filename = 'openvpn-public-profile';
    }
    header('Content-Type: application/x-openvpn-profile');
    header('Content-Disposition: inline; filename="' . $filename . '.ovpn"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('Content-Length: ' . strlen($content));
    echo $content;
} catch (Throwable $e) {
    http_response_code(404);
    header('Cache-Control: no-store');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'OpenVPN profile unavailable.';
}
