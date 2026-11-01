<?php
declare(strict_types=1);

/**
 * Coordinates one IBSng renewal attempt against the durable ledger.
 * It intentionally has no automatic retry path.
 */
final class IBSngRenewalCoordinator
{
    public function __construct(private readonly PDO $pdo) {}

    public function findExisting(string $idempotencyKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ibsng_renewals WHERE idempotency_key = :key LIMIT 1'
        );
        $stmt->execute(['key' => $idempotencyKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function completedRenewalCount(int $serverId, string $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM ibsng_renewals
             WHERE server_id = :server AND ibsng_user_id = :user_id
               AND status = 'completed'"
        );
        $stmt->execute(['server' => $serverId, 'user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Stop new grants while any earlier operation for this server/account
     * remains pending, failed, or awaiting manual reconciliation.
     */
    public function assertNoUnresolvedForAccount(int $serverId, string $userId): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT id,status FROM ibsng_renewals
             WHERE server_id = :server AND ibsng_user_id = :user_id
               AND status IN ('pending', 'failed', 'needs_review')
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['server' => $serverId, 'user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            throw new RuntimeException(
                'This IBSng account has an unresolved renewal operation; reconcile it before starting another renewal.'
            );
        }
    }

    /**
     * Return completed prior operations only. Any other recorded state is
     * intentionally a stop condition requiring reconciliation.
     */
    public function resolveExisting(
        string $idempotencyKey,
        ?int $serverId = null,
        ?string $username = null,
        ?int $productId = null
    ): ?array {
        $row = $this->findExisting($idempotencyKey);
        if ($row === null) {
            return null;
        }
        if (($serverId !== null && (int)$row['server_id'] !== $serverId)
            || ($username !== null && strtolower((string)$row['username']) !== strtolower(trim($username)))
            || ($productId !== null && (int)($row['product_id'] ?? 0) !== $productId)) {
            throw new RuntimeException('This renewal request key belongs to a different account or plan.');
        }
        if ($row['status'] !== 'completed') {
            throw new RuntimeException(
                'A renewal operation already exists and requires ledger reconciliation.'
            );
        }
        $decoded = json_decode((string)($row['result_json'] ?? 'null'), true);
        return [
            'status' => 'completed',
            'reused_existing' => true,
            'renewal_id' => (int)$row['id'],
            'result' => is_array($decoded) ? $decoded : [],
        ];
    }

    /**
     * Reserve a durable operation before a remote change. A unique-key
     * conflict is surfaced and must never be followed by another mutation.
     */
    public function reserve(
        string $idempotencyKey,
        int $serverId,
        string $userId,
        string $username,
        int $validityDays,
        int $giftDays,
        string $accountState,
        int $relativeExpiration,
        ?int $productId = null
    ): int {
        if ($idempotencyKey === '' || $serverId < 1 || $userId === ''
            || $username === '' || $validityDays < 1 || $giftDays < 0
            || !in_array($accountState, ['active', 'expired'], true)
            || $relativeExpiration < 1) {
            throw new InvalidArgumentException('Renewal reservation data is invalid.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO ibsng_renewals
            (idempotency_key, server_id, ibsng_user_id, username, product_id,
             account_state, validity_days, gift_days, relative_expiration_after,
             status, started_at)
            VALUES
            (:key, :server, :user_id, :username, :product, :state, :validity,
             :gift, :relative_exp, \'pending\', UTC_TIMESTAMP())'
        );
        $stmt->execute([
            'key' => $idempotencyKey,
            'server' => $serverId,
            'user_id' => $userId,
            'username' => $username,
            'product' => $productId,
            'state' => $accountState,
            'validity' => $validityDays,
            'gift' => $giftDays,
            'relative_exp' => $relativeExpiration,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function markCompleted(int $renewalId, array $verifiedResult): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ibsng_renewals
             SET status = \'completed\', result_json = :result,
                 error_message = NULL, completed_at = UTC_TIMESTAMP()
             WHERE id = :id AND status = \'pending\''
        );
        $stmt->execute([
            'result' => json_encode($verifiedResult, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'id' => $renewalId,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Renewal ledger completion was not confirmed.');
        }
    }

    public function markNeedsReview(int $renewalId, string $safeReason): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ibsng_renewals
             SET status = \'needs_review\', error_message = :reason,
                 completed_at = UTC_TIMESTAMP()
             WHERE id = :id AND status = \'pending\''
        );
        $reason = function_exists('mb_substr')
            ? mb_substr($safeReason, 0, 4000, 'UTF-8')
            : substr($safeReason, 0, 4000);
        $stmt->execute([
            'reason' => $reason,
            'id' => $renewalId,
        ]);
    }
}
