<?php
declare(strict_types=1);

/**
 * Normalize a request path against the configured installation base path.
 * Query strings are intentionally ignored; routing never includes user input.
 */
function store_normalize_route_path(string $requestUri, string $baseUrl = ''): string
{
    $path = parse_url($requestUri, PHP_URL_PATH);
    $path = is_string($path) && $path !== '' ? $path : '/';

    $basePath = parse_url($baseUrl, PHP_URL_PATH);
    $basePath = is_string($basePath) ? rtrim($basePath, '/') : '';
    if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
        $path = substr($path, strlen($basePath));
    }

    $path = '/' . ltrim($path, '/');
    if ($path === '/admin') {
        return '/admin/';
    }
    if ($path === '/install') {
        return '/install/';
    }
    return $path;
}

/** Return the complete explicit route allowlist for the application root. */
function store_route_map(string $root): array
{
    return [
        '/' => $root . '/index.php',
        '/index.php' => $root . '/index.php',
        '/checkout.php' => $root . '/checkout.php',
        '/admin/' => $root . '/admin/index.php',
        '/admin/index.php' => $root . '/admin/index.php',
        '/admin/login.php' => $root . '/admin/login.php',
        '/admin/logout.php' => $root . '/admin/logout.php',
        '/admin/settings.php' => $root . '/admin/settings.php',
        '/admin/ibsng-servers.php' => $root . '/admin/ibsng-servers.php',
        '/admin/ibsng-groups.php' => $root . '/admin/ibsng-groups.php',
        '/admin/ibsng-users.php' => $root . '/admin/ibsng-users.php',
        '/admin/products.php' => $root . '/admin/products.php',
        '/admin/orders.php' => $root . '/admin/orders.php',
        '/install/' => $root . '/install/index.php',
        '/install/index.php' => $root . '/install/index.php',
    ];
}
