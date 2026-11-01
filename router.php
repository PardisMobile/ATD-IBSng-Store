<?php
declare(strict_types=1);

/**
 * Explicit allowlist front controller. It remains reachable before install.
 * The URI is only used as a lookup key, never as an include path.
 */
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Router.php';

function store_dispatch(): never
{
    $routes = store_route_map(__DIR__);
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $baseUrl = '';
    $configFile = __DIR__ . '/config.php';

    if (is_file($configFile)) {
        $config = require $configFile;
        if (is_array($config)) {
            $baseUrl = (string)($config['base_url'] ?? '');
        }
    } else {
        // Before installation, config.php cannot tell us the mount directory.
        // Infer it only from a known route suffix (e.g. /shop/install/).
        $baseUrl = store_detect_base_url(
            $requestUri,
            (string)($_SERVER['SCRIPT_NAME'] ?? ''),
            array_keys($routes)
        );
    }

    $path = store_normalize_route_path($requestUri, $baseUrl);

    // At the mount root, send pre-install visitors to the correct installer URL.
    if (!is_file($configFile) && ($path === '/' || $path === '/index.php')) {
        header('Location: ' . rtrim($baseUrl, '/') . '/install/', true, 302);
        exit;
    }

    $target = $routes[$path] ?? null;
    if ($target === null || !is_file($target)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo '404 Not Found';
        exit;
    }

    // Keep script path semantics consistent with direct PHP page access.
    $scriptPath = '/' . ltrim(substr($target, strlen(__DIR__)), '/');
    $_SERVER['SCRIPT_NAME'] = rtrim($baseUrl, '/') . $scriptPath;
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];

    require $target;
    exit;
}

store_dispatch();
