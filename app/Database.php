<?php
declare(strict_types=1);

/**
 * PDO connection factory for the store.
 * Credentials are loaded from the installer-generated config.php and never echoed.
 */
final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = app_config();
        $host = trim((string)($config['db_host'] ?? 'localhost'));
        $name = trim((string)($config['db_name'] ?? ''));
        $user = (string)($config['db_user'] ?? '');
        $pass = (string)($config['db_pass'] ?? '');

        if ($name === '' || $user === '') {
            throw new RuntimeException('تنظیمات پایگاه داده کامل نیست.');
        }

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);
        self::$connection = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);

        return self::$connection;
    }

    private function __construct()
    {
    }
}
