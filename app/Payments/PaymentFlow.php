<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymentAmount.php';
require_once __DIR__ . '/PaymentManager.php';
require_once __DIR__ . '/PaymentTransactionService.php';

/**
 * Coordinates the customer-facing payment boundary.
 *
 * Provider calls happen only after a durable transaction exists. Callback
 * identity is resolved from the persisted provider/reference pair, and only
 * the adapter's server-side verification result can confirm payment.
 */
final class PaymentFlow
{
    /** @return array<string,mixed> */
    private static function settings(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT setting_key, setting_value FROM app_settings');
        $settings = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings[(string)$row['setting_key']] = (string)$row['setting_value'];
        }
        return $settings;
    }

    /** @return array<string,string> */
    private static function providerSettings(array $all, string $provider): array
    {
        $prefix = 'payment_' . $provider . '_';
        $result = [];
        foreach ($all as $key => $value) {
            if (str_starts_with((string)$key, $prefix)) {
                $field = substr((string)$key, strlen($prefix));
                if ($field !== '' && $field !== 'type') {
                    $result[$field] = (string)$value;
                }
            }
        }
        return $result;
    }

    /**
     * Initiate checkout using the selected gateway's configured currency type.
     *
     * @return array{redirect_url:string,transaction_id:int}
     */
    public static function initiate(PDO $pdo, int $orderId): array
    {
        $all = self::settings($pdo);
        $provider = strtolower(trim((string)($all['payment_provider'] ?? '')));
        if ($provider === '') {
            throw new RuntimeException('No payment provider is configured.');
        }

        $stmt = $pdo->prepare(
            'SELECT id, public_id, buyer_name, buyer_phone, buyer_email,
                    product_id, product_name_snapshot, price_minor_snapshot, currency,
                    payment_status, order_status
             FROM orders WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order || $order['payment_status'] !== 'pending' || $order['order_status'] !== 'pending_payment') {
            throw new RuntimeException('Order is not awaiting payment.');
        }

        $paymentType = strtolower(trim((string)($all['payment_' . $provider . '_type'] ?? 'fiat')));
        if (!in_array($paymentType, ['fiat', 'crypto'], true)) {
            throw new RuntimeException('Configured payment gateway type is invalid.');
        }

        if ($paymentType === 'crypto') {
            $productQuery = $pdo->prepare('SELECT price_usdt FROM products WHERE id = ? LIMIT 1');
            $productQuery->execute([(int)($order['product_id'] ?? 0)]);
            $product = $productQuery->fetch(PDO::FETCH_ASSOC);
            $amount = PaymentAmount::resolve('crypto', [], $product ?: null);
        } else {
            $amount = PaymentAmount::resolve('fiat', [
                'price_minor_snapshot' => $order['price_minor_snapshot'],
                'currency' => $order['currency'],
            ]);
        }
        $transactions = new PaymentTransactionService($pdo);
        $transactionId = $transactions->create(
            $orderId,
            $provider,
            $amount['amount_minor'],
            $amount['currency']
        );

        try {
            $manager = new PaymentManager($provider);
            $callbackUrl = app_url('payment-callback.php?provider=' . rawurlencode($provider));
            $providerSettings = [$provider => self::providerSettings($all, $provider)];
            $providerSettings[$provider]['callback_url'] = $callbackUrl;
            $started = $manager->initiate([
                'amount_minor' => $amount['amount_minor'],
                'currency' => $amount['currency'],
                'description' => 'Order ' . (string)$order['public_id'] . ' - ' . (string)$order['product_name_snapshot'],
                'buyer_phone' => (string)$order['buyer_phone'],
                'buyer_email' => (string)$order['buyer_email'],
                'public_id' => (string)$order['public_id'],
            ], $providerSettings);

            $transactions->attachProviderReference($transactionId, (string)($started['provider_reference'] ?? ''));
            $redirect = (string)($started['redirect_url'] ?? '');
            if (!filter_var($redirect, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($redirect), 'https://')) {
                throw new RuntimeException('Payment provider returned an invalid redirect URL.');
            }
            return ['redirect_url' => $redirect, 'transaction_id' => $transactionId];
        } catch (Throwable $e) {
            // A provider reference may already have been issued. Never mark such
            // a transaction failed or automatically retry the external request.
            try {
                $transactions->failInitiation($transactionId);
            } catch (Throwable) {
                // Preserve the original error; the persisted transaction remains
                // available for operator reconciliation if issuance was ambiguous.
            }
            throw $e;
        }
    }

    /**
     * Resolve and verify a provider callback. Browser parameters never mark an
     * order paid by themselves.
     *
     * @return array{confirmed:bool,order_id:int,order_public_id:string}
     */
    public static function handleCallback(PDO $pdo, string $provider, array $callback): array
    {
        $provider = strtolower(trim($provider));
        // The callback's reference field is provider-specific. Resolve it via
        // the registered adapter instead of assuming Zarinpal's Authority key.
        $reference = (new PaymentManager($provider))->gateway()->callbackReference($callback);
        if ($provider === '' || $reference === '') {
            throw new InvalidArgumentException('Payment callback is missing its provider reference.');
        }

        $stmt = $pdo->prepare(
            'SELECT t.*, o.public_id AS order_public_id
             FROM payment_transactions t
             JOIN orders o ON o.id = t.order_id
             WHERE t.provider = ? AND t.provider_reference = ?
             LIMIT 1'
        );
        $stmt->execute([$provider, $reference]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$transaction) {
            throw new RuntimeException('Payment transaction was not found for this callback.');
        }

        $all = self::settings($pdo);
        $manager = new PaymentManager($provider);
        $verification = $manager->verify(
            $callback,
            $transaction,
            [$provider => self::providerSettings($all, $provider)]
        );
        $transactionService = new PaymentTransactionService($pdo);
        if (($verification['verified'] ?? false) !== true) {
            // A matching Authority with a non-OK provider callback represents
            // cancellation/failure, never payment proof. It may safely release
            // the active slot so the buyer can start a fresh attempt.
            if (strtoupper(trim((string)($callback['Status'] ?? ''))) !== 'OK') {
                $transactionService->markFailed(
                    (int)$transaction['id'],
                    $reference,
                    ['callback_status' => (string)($callback['Status'] ?? '')]
                );
            }
            return [
                'confirmed' => false,
                'paid' => false,
                'order_id' => (int)$transaction['order_id'],
                'order_public_id' => (string)$transaction['order_public_id'],
            ];
        }

        $confirmed = $transactionService->confirmVerified((int)$transaction['id'], $verification);

        return [
            'confirmed' => $confirmed,
            'paid' => true,
            'order_id' => (int)$transaction['order_id'],
            'order_public_id' => (string)$transaction['order_public_id'],
        ];
    }
}
