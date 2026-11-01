<?php
declare(strict_types=1);

/**
 * Explicit allowlist front controller.
 *
 * It must work before installation too: config.php does not exist yet then,
 * while /install/ must remain reachable. Existing .php URLs and query strings
 * are supported. Request data is never used as an include path.
 */
require_once __DIR__ . '/app/bootstrap.php';

function store_route_path(): string
{
    $requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $requestPath = is_string($requestPath) ? $requestPath : '/';

    // Read the configured base path without calling app_config(), whose
    // uninstalled-state guard intentionally exits before the installer.
    $basePath = '';
    $configFile = __DIR__ . '/config.php';
    if (is_file($configFile)) {
        $config = require $configFile;
        if (is_array($config)) {
            $configuredPath = parse_url((string)($config['base_url'] ?? ''), PHP_URL_PATH);
            $basePath = is_string($configuredPath) ? rtrim($configuredPath, '/') : '';
        }
    }

    if ($basePath !== '' && ($requestPath === $basePath || str_starts_with($requestPath, $basePath . '/'))) {
        $requestPath = substr($requestPath, strlen($basePath));
    }

    $requestPath = '/' . ltrim($requestPath, '/');
    if ($requestPath === '/admin') {
        return '/admin/';
    }
    if ($requestPath === '/install') {
        return '/install/';
    }

    return $requestPath;
}

function store_dispatch(): never
{
    $routes = [
        '/' => __DIR__ . '/index.php',
        '/index.php' => __DIR__ . '/index.php',
        '/checkout.php' => __DIR__ . '/checkout.php',
        '/admin/' => __DIR__ . '/admin/index.php',
        '/admin/index.php' => __DIR__ . '/admin/index.php',
        '/admin/login.php' => __DIR__ . '/admin/login.php',
        '/admin/logout.php' => __DIR__ . '/admin/logout.php',
        '/admin/settings.php' => __DIR__ . '/admin/settings.php',
        '/admin/ibsng-servers.php' => __DIR__ . '/admin/ibsng-servers.php',
        '/admin/ibsng-groups.php' => __DIR__ . '/admin/ibsng-groups.php',
        '/admin/ibsng-users.php' => __DIR__ . '/admin/ibsng-users.php',
        '/admin/products.php' => __DIR__ . '/admin/products.php',
        '/admin/orders.php' => __DIR__ . '/admin/orders.php',
        '/install/' => __DIR__ . '/install/index.php',
        '/install/index.php' => __DIR__ . '/install/index.php',
    ];

    $path = store_route_path();
    $target = $routes[$path] ?? null;
    if ($target === null || !is_file($target)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo '404 Not Found';
        exit;
    }

    require $target;
    exit;
}

store_dispatch();
