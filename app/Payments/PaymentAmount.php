<?php
declare(strict_types=1);

/**
 * Resolves a payment amount from trusted persisted order/product snapshots.
 *
 * Fiat and crypto both use immutable order snapshots. Crypto uses an independent
 * USDT price captured when the order is created (never an FX conversion).
 * Provider initiation and verification remain separate responsibilities.
 */
final class PaymentAmount
{
    /**
     * @param array<string,mixed> $order Persisted order row.
     * @return array{amount_minor:int,currency:string}
     */
    public static function resolve(string $method, array $order): array
    {
        $method = strtolower(trim($method));

        if ($method === 'fiat') {
            $amount = filter_var($order['price_minor_snapshot'] ?? null, FILTER_VALIDATE_INT);
            $currency = strtoupper(trim((string)($order['currency'] ?? '')));

            if ($amount === false || $amount < 1 || !in_array($currency, ['IRR', 'IRT'], true)) {
                throw new InvalidArgumentException('Persisted fiat order amount or currency is invalid.');
            }

            return ['amount_minor' => $amount, 'currency' => $currency];
        }

        if ($method === 'crypto') {
            $price = trim((string)($order['price_usdt_snapshot'] ?? ''));
            if ($price === '' || !preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,8})?$/D', $price)) {
                throw new InvalidArgumentException('Persisted USDT order snapshot is missing or invalid.');
            }

            [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '');
            $scaled = ltrim($whole . str_pad($fraction, 8, '0'), '0');
            if ($scaled === '' || strlen($scaled) > 18) {
                throw new InvalidArgumentException('Persisted USDT product price is outside the supported range.');
            }

            return ['amount_minor' => (int)$scaled, 'currency' => 'USDT'];
        }

        throw new InvalidArgumentException('Unsupported payment method.');
    }
}
