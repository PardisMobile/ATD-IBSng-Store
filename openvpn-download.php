<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/OpenVpnManager.php';

try {
    $manager = new OpenVpnManager(Database::connection());
    $result = $manager->resolveToken((string)($_GET['t'] ?? ''));
    $filename = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $result['name']) ?: 'openvpn-profile';
    header('Content-Type: application/x-openvpn-profile');
    header('Content-Disposition: attachment; filename="' . $filename . '.ovpn"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('Content-Length: ' . strlen($result['content']));
    echo $result['content'];
} catch (Throwable $e) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'پیوند دانلود معتبر نیست، منقضی شده یا غیرفعال است.';
}
