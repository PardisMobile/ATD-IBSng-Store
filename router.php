<?php
declare(strict_types=1);

/**
 * Small allowlist-based front controller.
 *
 * Existing .php URLs remain valid; the dispatcher never derives an include
 * path from user input. Query strings are preserved by Apache.
 */
require_once __DIR__ . '/app/bootstrap.php';

function store_route_path(): string
{
    $requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $requestPath = is_string($requestPath) ? $requestPath : '/';

    $basePath = parse_url((string)(app_config()['base_url'] ?? ''), PHP_URL_PATH);
    $basePath = is_string($basePath) ? rtrim($basePath, '/') : '';

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
