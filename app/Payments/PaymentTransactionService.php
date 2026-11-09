<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymentAmount.php';

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
    public function create(int $orderId, string $provider, int $amountMinor, string $currency, array $metadata = [], bool $allowConcurrentPending = false): int
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
                'SELECT id, price_minor_snapshot, price_usdt_snapshot, currency, payment_status, order_status FROM orders WHERE id = ? FOR UPDATE'
            );
            $query->execute([$orderId]);
            $order = $query->fetch(PDO::FETCH_ASSOC);
            if (!$order || $order['payment_status'] !== 'pending'
                || $order['order_status'] !== 'pending_payment') {
                throw new RuntimeException('Order is not eligible for payment initiation.');
            }

            if ($currency === 'USDT') {
                $expected = PaymentAmount::resolve('crypto', $order);
            } else {
                $expected = PaymentAmount::resolve('fiat', [
                    'price_minor_snapshot' => $order['price_minor_snapshot'],
                    'currency' => $order['currency'],
                ]);
            }
            if ($expected['amount_minor'] !== $amountMinor || $expected['currency'] !== $currency) {
                throw new RuntimeException('Payment amount/currency does not match the persisted order price.');
            }

            if (!$allowConcurrentPending) {
                $active = $this->pdo->prepare(
                    "SELECT id FROM payment_transactions WHERE order_id = ? AND status IN ('initiated','pending') LIMIT 1 FOR UPDATE"
                );
                $active->execute([$orderId]);
                if ($active->fetch(PDO::FETCH_ASSOC)) {
                    throw new RuntimeException('Order already has an active payment transaction.');
                }
            }

            $rawMetadata = $metadata !== []
                ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                : null;
            $insert = $this->pdo->prepare(
                "INSERT INTO payment_transactions (order_id, provider, amount_minor, currency, status, raw_result_json)
                 VALUES (?, ?, ?, ?, 'initiated', ?)"
            );
            $insert->execute([$orderId, $provider, $amountMinor, $currency, $rawMetadata]);
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
     * Manually release an ambiguous initiation only after an administrator has
     * checked the provider and confirmed no payable invoice remains.
     */
    public function resolveAmbiguousInitiationAsFailed(int $transactionId, string $note): bool
    {
        $note = trim($note);
        $noteLength = preg_match_all('/./us', $note, $noteCharacters);
        if ($transactionId < 1 || $note === '' || $noteLength === false || $noteLength > 2000) {
            throw new InvalidArgumentException('A transaction ID and reconciliation note are required.');
        }

        $this->pdo->beginTransaction();
        try {
            $select = $this->pdo->prepare(
                "SELECT raw_result_json FROM payment_transactions
                 WHERE id = ? AND status = 'initiated' AND provider_reference IS NULL
                 FOR UPDATE"
            );
            $select->execute([$transactionId]);
            $current = $select->fetchColumn();
            if ($current === false) {
                $this->pdo->commit();
                return false;
            }

            $metadata = is_string($current) && trim($current) !== ''
                ? json_decode($current, true)
                : [];
            if (!is_array($metadata)) {
                $metadata = ['previous_raw_result' => is_string($current) ? $current : null];
            }
            $metadata['manual_resolution'] = [
                'result' => 'confirmed_no_invoice',
                'note' => $note,
                'resolved_at' => gmdate('c'),
            ];
            $raw = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);

            $stmt = $this->pdo->prepare(
                "UPDATE payment_transactions
                 SET status = 'failed', raw_result_json = ?
                 WHERE id = ? AND status = 'initiated' AND provider_reference IS NULL"
            );
            $stmt->execute([$raw, $transactionId]);
            $changed = $stmt->rowCount() === 1;
            $this->pdo->commit();
            return $changed;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Persist a non-sensitive diagnostic for an initiation that did not reach
     * provider-reference attachment. Preserve the environment snapshot and
     * any earlier metadata so an administrator can inspect the exact failure.
     *
     * @param array<string,mixed> $diagnostic
     */
    public function recordInitiationDiagnostic(int $transactionId, array $diagnostic): void
    {
        if ($transactionId < 1) {
            throw new InvalidArgumentException('Payment transaction ID is invalid.');
        }

        $select = $this->pdo->prepare(
            "SELECT raw_result_json FROM payment_transactions
             WHERE id = ? AND status = 'initiated' AND provider_reference IS NULL
             LIMIT 1"
        );
        $select->execute([$transactionId]);
        $current = $select->fetchColumn();
        if ($current === false) {
            return;
        }

        $metadata = is_string($current) && trim($current) !== ''
            ? json_decode($current, true)
            : [];
        if (!is_array($metadata)) {
            $metadata = ['previous_raw_result' => is_string($current) ? $current : null];
        }
        $metadata['initiation_diagnostic'] = $diagnostic;
        $raw = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);

        $update = $this->pdo->prepare(
            "UPDATE payment_transactions SET raw_result_json = ?
             WHERE id = ? AND status = 'initiated' AND provider_reference IS NULL"
        );
        $update->execute([$raw, $transactionId]);
    }

    /** Mark a provider request as failed only before a provider reference exists. */
    public function failInitiation(int $transactionId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE payment_transactions SET status = 'failed'
             WHERE id = ? AND status = 'initiated' AND provider_reference IS NULL"
        );
        $stmt->execute([$transactionId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Payment initiation can no longer be marked failed.');
        }
    }

    /**
     * Record a provider-declared cancellation/failure without ever treating it
     * as payment proof. The provider reference must match the stored transaction.
     */
    public function markFailed(int $transactionId, string $providerReference, ?array $result = null): bool
    {
        $providerReference = trim($providerReference);
        if ($transactionId < 1 || $providerReference === '') {
            throw new InvalidArgumentException('Failed payment reference is invalid.');
        }

        $raw = $result !== null
            ? json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            : null;
        $stmt = $this->pdo->prepare(
            "UPDATE payment_transactions
             SET status = 'failed', raw_result_json = ?
             WHERE id = ? AND provider_reference = ? AND status IN ('initiated','pending')"
        );
        $stmt->execute([$raw, $transactionId, $providerReference]);
        return $stmt->rowCount() === 1;
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
                'SELECT t.*, o.payment_status AS order_payment_status,
                        o.server_targets_snapshot AS server_targets_snapshot
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
                $samePayment = is_string($transaction['provider_reference'])
                    && hash_equals((string)$transaction['provider_reference'], $reference)
                    && (int)$transaction['amount_minor'] === $amount
                    && strtoupper((string)$transaction['currency']) === $currency;
                if (!$samePayment) {
                    throw new RuntimeException('Replay does not match the already verified payment.');
                }
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

            // Queue captured IBSng destinations exactly once in the payment
            // transaction. Remote IBSng mutations are handled separately.
            $targetsJson = $transaction['server_targets_snapshot'] ?? null;
            if (is_string($targetsJson) && trim($targetsJson) !== '') {
                $targets = json_decode($targetsJson, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($targets) || !array_is_list($targets) || $targets === []) {
                    throw new RuntimeException('Order has an invalid provisioning destination snapshot.');
                }
                $queue = $this->pdo->prepare(
                    "INSERT IGNORE INTO provisioning_attempts
                     (order_id, server_id, operation, status, attempt_number, idempotency_key)
                     VALUES (?, ?, ?, 'queued', 1, ?)"
                );
                $queued = 0;
                foreach ($targets as $target) {
                    if (!is_array($target) || (int)($target['server_id'] ?? 0) < 1
                        || trim((string)($target['group_name'] ?? '')) === '') {
                        throw new RuntimeException('Order provisioning destination snapshot is incomplete.');
                    }
                    $serverId = (int)$target['server_id'];
                    $operation = (string)($target['operation'] ?? 'create');
                    if (!in_array($operation, ['create', 'renew'], true)) {
                        throw new RuntimeException('Order operation is not supported for provisioning.');
                    }
                    $idempotencyKey = 'order:' . (int)$transaction['order_id'] . ':server:' . $serverId . ':' . $operation;
                    $queue->execute([(int)$transaction['order_id'], $serverId, $operation, $idempotencyKey]);
                    $queued += $queue->rowCount();
                }
                if ($queued > 0) {
                    $markProvisioning = $this->pdo->prepare(
                        "UPDATE orders SET order_status = 'provisioning'
                         WHERE id = ? AND payment_status = 'paid' AND order_status = 'paid'"
                    );
                    $markProvisioning->execute([(int)$transaction['order_id']]);
                }
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
