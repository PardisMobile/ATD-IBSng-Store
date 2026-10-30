<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Encrypt sensitive integration credentials with an application key stored
 * outside the database in the installer-created config.php.
 */
function app_encrypt_secret(string $plainText): string
{
    $config = app_config();
    $key = base64_decode((string)($config['encryption_key'] ?? ''), true);
    if (!function_exists('openssl_encrypt') || !is_string($key) || strlen($key) !== 32) {
        throw new RuntimeException('کلید رمزنگاری برنامه تنظیم نشده یا OpenSSL در دسترس نیست.');
    }

    $nonce = random_bytes(12);
    $tag = '';
    $cipherText = openssl_encrypt($plainText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
    if (!is_string($cipherText) || strlen($tag) !== 16) {
        throw new RuntimeException('رمزنگاری اطلاعات حساس ناموفق بود.');
    }

    return 'v1:' . base64_encode($nonce) . ':' . base64_encode($tag) . ':' . base64_encode($cipherText);
}

function app_decrypt_secret(string $encoded): string
{
    $parts = explode(':', $encoded, 4);
    if (count($parts) !== 4 || $parts[0] !== 'v1') {
        throw new RuntimeException('قالب اطلاعات رمزنگاری‌شده معتبر نیست.');
    }

    $config = app_config();
    $key = base64_decode((string)($config['encryption_key'] ?? ''), true);
    $nonce = base64_decode($parts[1], true);
    $tag = base64_decode($parts[2], true);
    $cipherText = base64_decode($parts[3], true);
    if (!function_exists('openssl_decrypt') || !is_string($key) || strlen($key) !== 32
        || !is_string($nonce) || strlen($nonce) !== 12
        || !is_string($tag) || strlen($tag) !== 16 || !is_string($cipherText)) {
        throw new RuntimeException('کلید یا داده رمزنگاری‌شده معتبر نیست.');
    }

    $plainText = openssl_decrypt($cipherText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
    if (!is_string($plainText)) {
        throw new RuntimeException('رمزگشایی اطلاعات حساس انجام نشد.');
    }
    return $plainText;
}
