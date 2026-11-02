<?php
declare(strict_types=1);

/**
 * Resolves a payment amount from trusted persisted order/product snapshots.
 *
 * Fiat uses the immutable order snapshot. Crypto uses the independent product
 * USDT price and an eight-decimal integer scale (never an FX conversion).
 * Provider initiation and verification remain separate responsibilities.
 */
final class PaymentAmount
{
    /**
     * @param array<string,mixed> $order Persisted order row.
     * @param array<string,mixed>|null $product Persisted product row, when needed.
     * @return array{amount_minor:int,currency:string}
     */
    public static function resolve(string $method, array $order, ?array $product = null): array
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
            if ($product === null) {
                throw new InvalidArgumentException('A persisted product price is required for crypto settlement.');
            }

            $price = trim((string)($product['price_usdt'] ?? ''));
            if ($price === '' || !preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,8})?$/D', $price)) {
                throw new InvalidArgumentException('Persisted USDT product price is missing or invalid.');
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
