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
        '/admin/ibsng-renewal-migrate.php' => $root . '/admin/ibsng-renewal-migrate.php',
        '/admin/products.php' => $root . '/admin/products.php',
        '/admin/orders.php' => $root . '/admin/orders.php',
        '/install/' => $root . '/install/index.php',
        '/install/index.php' => $root . '/install/index.php',
    ];
}

/**
 * Infer a pre-install mount path by matching the URI against known routes.
 * Returns an empty string for root installs or unknown paths.
 */
function store_infer_base_url(string $requestUri, array $routePaths): string
{
    $path = parse_url($requestUri, PHP_URL_PATH);
    if (!is_string($path) || $path === '' || $path === '/') {
        return '';
    }

    usort($routePaths, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
    foreach ($routePaths as $route) {
        if ($route === '/') {
            continue;
        }
        if ($path === $route) {
            return '';
        }
        if (str_ends_with($path, $route)) {
            $prefix = substr($path, 0, -strlen($route));
            if ($prefix === '' || $prefix === '/') {
                return '';
            }
            return rtrim($prefix, '/');
        }
    }

    return '';
}


/**
 * Determine the application mount path before installation.
 * Prefer the executing front-controller path when the web server exposes it;
 * retain route-suffix inference for environments that rewrite SCRIPT_NAME.
 */
function store_detect_base_url(string $requestUri, string $scriptName, array $routePaths): string
{
    $scriptPath = parse_url($scriptName, PHP_URL_PATH);
    if (is_string($scriptPath) && $scriptPath !== '' && basename($scriptPath) === 'router.php') {
        $directory = dirname($scriptPath);
        if ($directory !== '/' && $directory !== '.') {
            return rtrim($directory, '/');
        }
        // A root-level SCRIPT_NAME does not prove that the app itself is
        // mounted at root; infer a subdirectory from the known route suffix.
    }

    return store_infer_base_url($requestUri, $routePaths);
}
