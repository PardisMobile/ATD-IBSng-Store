<?php
declare(strict_types=1);

/**
 * Pure policy helper for IBSng renewal.
 *
 * Kept independent of HTTP and persistence so expiration rules can be
 * regression-tested without contacting an IBSng server.
 */
final class IBSngRenewalPolicy
{
    /**
     * @return array{relative_expiration:int,reset_first_login:bool}
     */
    public static function calculate(
        bool $expired,
        int $remainingDays,
        int $validityDays,
        int $giftDays
    ): array {
        if ($remainingDays < 0 || $validityDays < 1 || $giftDays < 0) {
            throw new InvalidArgumentException('IBSng renewal duration values are invalid.');
        }

        return [
            'relative_expiration' => ($expired ? 0 : $remainingDays) + $validityDays + $giftDays,
            'reset_first_login' => $expired,
        ];
    }
}
