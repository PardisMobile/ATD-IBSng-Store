<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymentAmount.php';
require_once __DIR__ . '/PaymentManager.php';
require_once __DIR__ . '/PaymentTransactionService.php';
require_once __DIR__ . '/../IBSngProvisioningDispatcher.php';
require_once __DIR__ . '/../EmailSender.php';
require_once __DIR__ . '/../EmailTemplates.php';

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
                    product_id, product_name_snapshot, price_minor_snapshot, price_usdt_snapshot, currency,
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

        $manager = new PaymentManager($provider);
        $requestedCurrency = $paymentType === 'crypto' ? 'USDT' : strtoupper(trim((string)$order['currency']));
        if (!in_array($requestedCurrency, $manager->supportedCurrencies(), true)) {
            throw new RuntimeException('The selected payment gateway does not support the configured payment currency.');
        }

        if ($paymentType === 'crypto') {
            $amount = PaymentAmount::resolve('crypto', $order);
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
            // Once the outbound initiation call has started, its outcome may be
            // ambiguous (for example, a provider created an invoice but its
            // response timed out). Keep the transaction active and let an
            // administrator reconcile it; do not release the order for retry.
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
            // Browser callback parameters are untrusted. In particular, a
            // caller must not be able to cancel an active transaction by
            // submitting a matching reference with a forged non-OK status.
            // Keep it active until provider-side verification or the existing
            // administrative reconciliation establishes its final state.
            return [
                'confirmed' => false,
                'paid' => false,
                'order_id' => (int)$transaction['order_id'],
                'order_public_id' => (string)$transaction['order_public_id'],
            ];
        }

        $confirmed = $transactionService->confirmVerified((int)$transaction['id'], $verification);

        // Send a non-sensitive receipt notification only on the first verified
        // transition. Credentials remain exclusively on the private order page.
        if ($confirmed) {
            self::sendPurchaseNotice($pdo, (int)$transaction['order_id'], (string)$transaction['order_public_id']);
        }

        // Payment confirmation and remote IBSng provisioning are separate
        // durable stages. Kick queued creates immediately after the payment
        // transaction commits, but never let a provisioning issue turn a
        // verified payment into a failed callback response.
        self::dispatchQueuedProvisioning($pdo, (int)$transaction['order_id']);

        return [
            'confirmed' => $confirmed,
            'paid' => true,
            'order_id' => (int)$transaction['order_id'],
            'order_public_id' => (string)$transaction['order_public_id'],
        ];
    }

    /**
     * Send the purchase notice once per first payment confirmation. This is
     * best-effort: mail transport failure is logged and cannot undo payment.
     * No credentials are ever included in customer email.
     */
    private static function sendPurchaseNotice(PDO $pdo, int $orderId, string $publicId): void
    {
        try {
            $stmt = $pdo->prepare('SELECT buyer_email, buyer_name FROM orders WHERE id=? LIMIT 1');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            $email = trim((string)($order['buyer_email'] ?? ''));
            if ($email === '') {
                return;
            }
            $config = app_config();
            $template = [
                'subject' => 'پرداخت سفارش شما تأیید شد - {{site_name}}',
                'html' => '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><body><main><h2>{{site_name}}</h2><p>سلام {{customer_name}}،</p><p>پرداخت سفارش شما تأیید شده است. فعال‌سازی اکانت در حال انجام است. پس از تأیید موفق فعال‌سازی، اطلاعات ورود به ایمیل شما ارسال می‌شود.</p><p>شناسه سفارش: <strong>{{order_id}}</strong></p><p><a href="{{order_url}}">پیگیری سفارش</a></p></main></body></html>',
                'text' => "{{site_name}}\nسلام {{customer_name}}،\nپرداخت سفارش تأیید شد. فعال‌سازی در حال انجام است و اطلاعات ورود پس از موفقیت ارسال می‌شود.\nشناسه سفارش: {{order_id}}\nپیگیری سفارش: {{order_url}}",
            ];
            $variables = [
                'site_name' => (string)($config['site_title'] ?? 'ATD IBSng Store'),
                'customer_name' => (string)($order['buyer_name'] ?? ''),
                'order_id' => $publicId,
                'order_url' => app_url('order-status.php?order=' . rawurlencode($publicId)),
            ];
            $sent = EmailSender::send(
                $pdo,
                $email,
                EmailTemplates::render($template['subject'], $variables),
                EmailTemplates::render($template['html'], $variables),
                EmailTemplates::render($template['text'], $variables)
            );
            if (!$sent) {
                error_log('Purchase email was not accepted by host mail() for order ' . $orderId);
            }
        } catch (Throwable $e) {
            error_log('Purchase email notification failed for order ' . $orderId . ': ' . $e->getMessage());
        }
    }

    /**
     * Best-effort synchronous kick of the durable queue after verified payment.
     * Each dispatcher claim is guarded in the database; repeated callbacks
     * cannot execute the same attempt twice. Queued leftovers remain available
     * in the admin recovery screen if this request is interrupted.
     */
    private static function dispatchQueuedProvisioning(PDO $pdo, int $orderId): void
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT id FROM provisioning_attempts
                 WHERE order_id = ? AND operation = 'create' AND status = 'queued'
                 ORDER BY id ASC"
            );
            $stmt->execute([$orderId]);
            $attemptIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!is_array($attemptIds) || $attemptIds === []) {
                return;
            }

            $dispatcher = new IBSngProvisioningDispatcher($pdo);
            foreach ($attemptIds as $attemptId) {
                try {
                    $dispatcher->runQueued((int)$attemptId);
                } catch (Throwable $e) {
                    // Payment is already verified. Leave a recoverable queue
                    // state and log diagnostics for the administrator.
                    error_log(
                        'Automatic IBSng provisioning dispatch failed for attempt '
                        . (int)$attemptId . ': ' . $e->getMessage()
                    );
                }
            }
        } catch (Throwable $e) {
            error_log(
                'Unable to load queued IBSng provisioning attempts for order '
                . $orderId . ': ' . $e->getMessage()
            );
        }
    }
}
