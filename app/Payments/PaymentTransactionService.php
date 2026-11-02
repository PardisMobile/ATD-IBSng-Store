<?php
declare(strict_types=1);

/**
 * Durable payment-transaction state transitions.
 *
 * Provider verification must happen before confirmVerified() is called.
 * This class only commits a payment once; provisioning is intentionally a
 * separate downstream action and must use its own order-bound idempotency key.
 */
final class PaymentTransactionService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Persist the immutable amount/currency and selected provider before making
     * the outbound provider request.
     *
     * @return int Transaction ID
     */
    public function create(int $orderId, string $provider, int $amountMinor, string $currency): int
    {
        $provider = strtolower(trim($provider));
        $currency = strtoupper(trim($currency));
        if ($orderId < 1 || $provider === '' || strlen($provider) > 80
            || $amountMinor < 1 || !in_array($currency, ['IRR', 'IRT', 'USDT'], true)) {
            throw new InvalidArgumentException('Payment transaction parameters are invalid.');
        }

        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare(
                'SELECT id, payment_status, order_status FROM orders WHERE id = ? FOR UPDATE'
            );
            $query->execute([$orderId]);
            $order = $query->fetch(PDO::FETCH_ASSOC);
            if (!$order || $order['payment_status'] !== 'pending'
                || $order['order_status'] !== 'pending_payment') {
                throw new RuntimeException('Order is not eligible for payment initiation.');
            }

            $insert = $this->pdo->prepare(
                "INSERT INTO payment_transactions (order_id, provider, amount_minor, currency, status)
                 VALUES (?, ?, ?, ?, 'initiated')"
            );
            $insert->execute([$orderId, $provider, $amountMinor, $currency]);
            $id = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Attach the provider-issued reference after initiation succeeds.
     * The unique provider/reference constraint prevents collisions.
     */
    public function attachProviderReference(int $transactionId, string $reference): void
    {
        $reference = trim($reference);
        if ($transactionId < 1 || $reference === '' || strlen($reference) > 190) {
            throw new InvalidArgumentException('Provider reference is invalid.');
        }

        $stmt = $this->pdo->prepare(
            "UPDATE payment_transactions
             SET provider_reference = ?, status = 'pending'
             WHERE id = ? AND status = 'initiated' AND provider_reference IS NULL"
        );
        $stmt->execute([$reference, $transactionId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Payment transaction cannot accept a provider reference.');
        }
    }

    /**
     * Atomically record a provider-verified payment exactly once.
     *
     * The caller must have verified with the gateway using this persisted
     * transaction. A repeated confirmation returns false and performs no
     * additional state transition.
     *
     * @param array<string,mixed> $verification Gateway verification response
     */
    public function confirmVerified(int $transactionId, array $verification): bool
    {
        if (($verification['verified'] ?? false) !== true) {
            throw new InvalidArgumentException('Gateway verification is not successful.');
        }

        $reference = trim((string)($verification['provider_reference'] ?? ''));
        $callbackId = trim((string)($verification['callback_id'] ?? ''));
        $amount = filter_var($verification['amount_minor'] ?? null, FILTER_VALIDATE_INT);
        $currency = strtoupper(trim((string)($verification['currency'] ?? '')));
        if ($reference === '' || $amount === false || $amount < 1
            || !in_array($currency, ['IRR', 'IRT', 'USDT'], true)) {
            throw new InvalidArgumentException('Gateway verification result is incomplete.');
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT t.*, o.payment_status AS order_payment_status
                 FROM payment_transactions t
                 JOIN orders o ON o.id = t.order_id
                 WHERE t.id = ? FOR UPDATE'
            );
            $stmt->execute([$transactionId]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$transaction) {
                throw new RuntimeException('Payment transaction was not found.');
            }

            if ($transaction['status'] === 'verified') {
                $this->pdo->commit();
                return false;
            }

            if (!in_array($transaction['status'], ['initiated', 'pending'], true)
                || !is_string($transaction['provider_reference'])
                || !hash_equals((string)$transaction['provider_reference'], $reference)
                || (int)$transaction['amount_minor'] !== $amount
                || strtoupper((string)$transaction['currency']) !== $currency
                || $transaction['order_payment_status'] !== 'pending') {
                throw new RuntimeException('Verified payment does not match the stored transaction.');
            }

            $raw = isset($verification['result']) && is_array($verification['result'])
                ? json_encode($verification['result'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                : null;
            $update = $this->pdo->prepare(
                "UPDATE payment_transactions
                 SET status = 'verified', callback_id = ?, verified_at = UTC_TIMESTAMP(), raw_result_json = ?
                 WHERE id = ? AND status IN ('initiated','pending')"
            );
            $update->execute([$callbackId !== '' ? $callbackId : null, $raw, $transactionId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Payment transaction state changed during confirmation.');
            }

            $orderUpdate = $this->pdo->prepare(
                "UPDATE orders SET payment_status = 'paid', order_status = 'paid'
                 WHERE id = ? AND payment_status = 'pending' AND order_status = 'pending_payment'"
            );
            $orderUpdate->execute([(int)$transaction['order_id']]);
            if ($orderUpdate->rowCount() !== 1) {
                throw new RuntimeException('Order state changed during payment confirmation.');
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
