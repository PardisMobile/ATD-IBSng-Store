<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function app_config(): array
{
    static $config;
    if ($config !== null) {
        return $config;
    }

    $file = dirname(__DIR__) . '/config.php';
    if (!is_file($file)) {
        http_response_code(503);
        echo 'سامانه هنوز نصب نشده است. ابتدا نصب‌کننده را اجرا کنید: /install/';
        exit;
    }

    $loaded = require $file;
    $config = is_array($loaded) ? $loaded : [];
    return $config;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_url(string $path = ''): string
{
    $base = rtrim((string)(app_config()['base_url'] ?? ''), '/');
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

/**
 * Build an absolute same-site URL for payment-provider callbacks.
 *
 * base_url remains the installer's path-only setting. The public origin is
 * derived from the current validated request host so existing cPanel installs
 * do not need a reinstall or a config/database migration.
 */
function app_absolute_url(string $path = ''): string
{
    $hostHeader = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($hostHeader === '' || preg_match('/[\\r\\n\\/\\\\@]/', $hostHeader)) {
        throw new RuntimeException('A valid public host is required to build an absolute callback URL.');
    }

    $parsedHost = parse_url('https://' . $hostHeader);
    $host = strtolower((string)($parsedHost['host'] ?? ''));
    $port = isset($parsedHost['port']) ? (int)$parsedHost['port'] : null;
    $validHost = $host !== '' && (
        filter_var($host, FILTER_VALIDATE_IP) !== false
        || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
    );
    if (!$validHost || ($port !== null && ($port < 1 || $port > 65535))) {
        throw new RuntimeException('The public host is invalid; unable to build an absolute callback URL.');
    }

    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    if (!$https) {
        $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        $cloudflareVisitor = json_decode((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''), true);
        $https = $forwardedProto === 'https'
            || (is_array($cloudflareVisitor) && strtolower((string)($cloudflareVisitor['scheme'] ?? '')) === 'https');
    }

    $origin = ($https ? 'https' : 'http') . '://' . $host;
    if ($port !== null && !(($https && $port === 443) || (!$https && $port === 80))) {
        $origin .= ':' . $port;
    }

    $sitePath = app_url($path);
    if ($sitePath !== '' && $sitePath[0] !== '/') {
        throw new RuntimeException('The configured site path is invalid for an absolute callback URL.');
    }
    return $origin . $sitePath;
}

/**
 * Return a versioned public asset URL so browsers and edge caches cannot mix
 * updated markup with stale CSS or JavaScript after a deployment.
 */
function app_asset_url(string $path): string
{
    $relativePath = ltrim($path, '/');
    $file = dirname(__DIR__) . '/' . $relativePath;
    $version = is_file($file) ? substr((string)hash_file('sha256', $file), 0, 12) : '';
    $url = app_url($relativePath);
    return $version !== '' ? $url . '?v=' . $version : $url;
}
