<?php
declare(strict_types=1);

/**
 * Customer-facing, easy-to-type order reference.
 *
 * This identifier is for lookup/display only; it is not an authentication
 * credential and must never authorize disclosure of account secrets.
 */
final class OrderTrackingCode
{
    private const PREFIX = 'ATD';
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const RANDOM_LENGTH = 7;
    private const MAX_ATTEMPTS = 8;

    public static function generate(PDO $pdo): string
    {
        $check = $pdo->prepare('SELECT 1 FROM orders WHERE tracking_code = ? LIMIT 1');

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $suffix = '';
            $max = strlen(self::ALPHABET) - 1;
            for ($i = 0; $i < self::RANDOM_LENGTH; $i++) {
                $suffix .= self::ALPHABET[random_int(0, $max)];
            }

            $candidate = self::PREFIX . '-' . $suffix;
            $check->execute([$candidate]);
            if (!$check->fetchColumn()) {
                return $candidate;
            }
        }

        throw new RuntimeException('Could not allocate a unique order tracking code.');
    }

    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[\\s-]+/', '', trim($code)) ?? '');
    }
}
