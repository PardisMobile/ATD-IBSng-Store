<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
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
