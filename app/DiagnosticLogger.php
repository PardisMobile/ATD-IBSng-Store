<?php
declare(strict_types=1);

/**
 * Private, best-effort diagnostics for IBSng and payment gateway operations.
 * Log contents are retained for at most seven days and are never web-readable.
 */
final class DiagnosticLogger
{
    private const MAX_BYTES = 1048576;
    private const RETENTION_SECONDS = 604800;
    private const SESSION_REUSE_INTERVAL = 3600;

    public static function write(string $event, array $context = []): void
    {
        self::append('ibsng-debug.txt', $event, $context);
    }

    public static function writeGateway(string $event, array $context = []): void
    {
        self::append('gateway-debug.txt', $event, $context);
    }


    /** Log routine session reuse at most once per hour for each IBSng server. */
    public static function writeSessionReuse(string $serverKey, array $context = []): void
    {
        try {
            $directory = dirname(__DIR__) . '/logs';
            if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) return;
            $stateFile = $directory . '/.session-reuse-throttle.json';
            $lock = @fopen($stateFile . '.lock', 'c');
            if ($lock === false) return;
            try {
                if (!flock($lock, LOCK_EX)) return;
                $state = [];
                if (is_file($stateFile)) {
                    $decoded = json_decode((string)@file_get_contents($stateFile), true);
                    if (is_array($decoded)) $state = $decoded;
                }
                $key = hash('sha256', $serverKey);
                $now = time();
                $last = isset($state[$key]) ? (int)$state[$key] : 0;
                if ($last > 0 && ($now - $last) < self::SESSION_REUSE_INTERVAL) return;
                $state[$key] = $now;
                foreach ($state as $id => $timestamp) {
                    if (!is_string($id) || !is_numeric($timestamp) || ($now - (int)$timestamp) >= self::RETENTION_SECONDS) unset($state[$id]);
                }
                @file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_SLASHES), LOCK_EX);
                @chmod($stateFile, 0600);
                self::append('ibsng-debug.txt', 'ibsng_session_cookie_reused', array_merge($context, ['operation' => 'ibsng.session.reuse']));
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        } catch (Throwable $ignored) {}
    }

    private static function append(string $filename, string $event, array $context): void
    {
        try {
            $directory = dirname(__DIR__) . '/logs';
            if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
                error_log('ATD diagnostic logger: unable to create private log directory.');
                return;
            }
            self::weeklyCleanup($directory);

            $safe = self::sanitize($context);
            $timestamp = date('Y-m-d H:i:s P');
            $operation = isset($safe['operation']) ? (string)$safe['operation'] : self::clean($event);
            unset($safe['operation']);
            $line = '[' . $timestamp . '] [' . self::clean($event) . '] operation=' . self::clean($operation);
            if ($safe !== []) {
                $details = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                if (is_string($details)) $line .= ' details=' . $details;
            }

            $file = $directory . '/' . $filename;
            if (is_file($file) && (int)@filesize($file) >= self::MAX_BYTES) {
                @rename($file . '.1', $file . '.2');
                @rename($file, $file . '.1');
            }
            if (@file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
                error_log('ATD diagnostic logger: unable to append private diagnostic entry.');
                return;
            }
            @chmod($file, 0600);
        } catch (Throwable $ignored) {
            // Diagnostics must never interrupt checkout, payment, or IBSng work.
        }
    }

    /** @return array<string,mixed> */
    private static function sanitize(array $context, int $depth = 0): array
    {
        if ($depth > 4) return [];
        $safe = [];
        foreach ($context as $key => $value) {
            $name = strtolower((string)$key);
            if (preg_match('/password|cookie|secret|token|authorization|credential|username|email|phone|body|payload|api.?key|merchant.?id|webhook/i', $name)) continue;
            if (is_array($value)) {
                $nested = self::sanitize($value, $depth + 1);
                if ($nested !== []) $safe[(string)$key] = $nested;
            } elseif (is_scalar($value) || $value === null) {
                $safe[(string)$key] = is_string($value) ? self::clean($value) : $value;
            }
        }
        return $safe;
    }

    /**
     * Once per seven days, clear current and rotated diagnostic files.
     * A locked marker avoids concurrent requests repeatedly performing cleanup.
     */
    private static function weeklyCleanup(string $directory): void
    {
        $marker = $directory . '/.diagnostic-cleanup';
        $handle = @fopen($marker, 'c+');
        if ($handle === false) return;
        try {
            if (!flock($handle, LOCK_EX)) return;
            rewind($handle);
            $last = (int)trim((string)stream_get_contents($handle));
            $now = time();
            if ($last <= 0 || ($now - $last) >= self::RETENTION_SECONDS) {
                if ($last > 0) {
                    foreach (['ibsng-debug.txt', 'ibsng-debug.txt.1', 'ibsng-debug.txt.2', 'gateway-debug.txt', 'gateway-debug.txt.1', 'gateway-debug.txt.2', '.session-reuse-throttle.json'] as $name) {
                        $path = $directory . '/' . $name;
                        if (is_file($path)) @file_put_contents($path, '');
                    }
                }
                rewind($handle);
                ftruncate($handle, 0);
                fwrite($handle, (string)$now);
                fflush($handle);
                @chmod($marker, 0600);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function clean(string $value): string
    {
        return substr(preg_replace('/[\\r\\n\\x00-\\x1F\\x7F]+/', ' ', $value) ?? '', 0, 1200);
    }
}
