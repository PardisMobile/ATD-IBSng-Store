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
    $baseUrl = '';
    $configFile = __DIR__ . '/config.php';
    if (is_file($configFile)) {
        $config = require $configFile;
        if (is_array($config)) {
            $baseUrl = (string)($config['base_url'] ?? '');
        }
    }

    $path = store_normalize_route_path((string)($_SERVER['REQUEST_URI'] ?? '/'), $baseUrl);
    $routes = store_route_map(__DIR__);
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
