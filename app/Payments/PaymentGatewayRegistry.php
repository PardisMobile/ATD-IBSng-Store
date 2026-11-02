<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymentGateway.php';

/**
 * Central inventory of payment adapter modules.
 *
 * Register a new gateway here and provide its adapter file in this directory.
 * PaymentGateway and PaymentManager remain unchanged as providers are added.
 *
 * @internal
 */
final class PaymentGatewayRegistry
{
    /** @var array<string, class-string<PaymentGateway>> */
    private const GATEWAYS = [
        'zarinpal' => ZarinpalGateway::class,
    ];

    public static function has(string $code): bool
    {
        return isset(self::GATEWAYS[strtolower(trim($code))]);
    }

    /** @return class-string<PaymentGateway> */
    public static function classFor(string $code): string
    {
        $normalized = strtolower(trim($code));
        if (!isset(self::GATEWAYS[$normalized])) {
            throw new InvalidArgumentException('Unknown payment provider.');
        }

        return self::GATEWAYS[$normalized];
    }
}
