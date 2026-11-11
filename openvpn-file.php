<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/OpenVpnManager.php';
try {
    $filename = (string)($_GET['f'] ?? '');
    $manager = new OpenVpnManager(Database::connection());
    $profile = $manager->resolveGeneratedFile($filename);
    $nameBase = trim((string)$profile['name']);
    if (function_exists('iconv')) {
        $asciiName = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nameBase);
        if (is_string($asciiName) && trim($asciiName) !== '') $nameBase = $asciiName;
    }
    $downloadFilename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $nameBase) ?? '';
    $downloadFilename = trim($downloadFilename, '.-_');
    if ($downloadFilename === '') $downloadFilename = 'openvpn-profile';
    $utf8Filename = rawurlencode(trim((string)$profile['name']) . '.ovpn');

    header('Content-Type: application/x-openvpn-profile');
    header('Content-Disposition: inline; filename="' . $downloadFilename . '.ovpn"; filename*=UTF-8\\'\\'' . $utf8Filename);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('Content-Length: ' . strlen($profile['content']));
    echo $profile['content'];
} catch (Throwable $e) {
    error_log('OpenVPN generated profile response failed: ' . $e->getMessage());
    http_response_code(404);
    header('Cache-Control: no-store');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'OpenVPN profile not found.';
}
