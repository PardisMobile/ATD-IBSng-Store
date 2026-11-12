<?php
declare(strict_types=1);
final class DiagnosticLogger
{
    private const MAX_BYTES = 1048576;
    public static function write(string $event, array $context = []): void
    {
        try {
            $directory = dirname(__DIR__) . '/storage/logs';
            if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) { error_log('ATD diagnostic logger: unable to create private log directory.'); return; }
            $safe = [];
            foreach ($context as $key => $value) {
                $name = strtolower((string)$key);
                if (preg_match('/password|cookie|secret|token|authorization|credential|username|email|phone|body|payload/i', $name)) continue;
                if (is_scalar($value) || $value === null) $safe[(string)$key] = is_string($value) ? self::clean($value) : $value;
            }
            $entry = ['time_utc' => gmdate('Y-m-d\\TH:i:s\\Z'), 'event' => self::clean($event), 'context' => $safe];
            $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if (!is_string($line)) return;
            $file = $directory . '/ibsng-debug.txt';
            if (is_file($file) && (int)@filesize($file) >= self::MAX_BYTES) @rename($file, $file . '.1');
            if (@file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) { error_log('ATD diagnostic logger: unable to append private diagnostic entry.'); return; }
            @chmod($file, 0600);
        } catch (Throwable $ignored) {}
    }
    private static function clean(string $value): string
    {
        return substr(preg_replace('/[\\r\\n\\x00-\\x1F\\x7F]+/', ' ', $value) ?? '', 0, 1200);
    }
}
