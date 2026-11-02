<?php
declare(strict_types=1);

require_once __DIR__ . '/Crypto.php';
require_once __DIR__ . '/IBSngClient.php';

/**
 * Executes one durable create-account queue item.
 *
 * A queued attempt is claimed before any remote call. Once IBSng mutation may
 * have started, transport/parse failures become needs_review and are never
 * automatically retried. This deliberately favors duplicate prevention.
 */
final class IBSngProvisioningDispatcher
{
    public function __construct(private readonly PDO $pdo) {}

    public function runQueued(int $attemptId): string
    {
        if ($attemptId < 1) {
            throw new InvalidArgumentException('Provisioning attempt ID is invalid.');
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT a.*, o.username, o.password_ciphertext, o.server_targets_snapshot,
                        s.host, s.port, s.username AS admin_username, s.password_ciphertext AS server_password
                 FROM provisioning_attempts a
                 JOIN orders o ON o.id = a.order_id
                 JOIN ibsng_servers s ON s.id = a.server_id
                 WHERE a.id = ? FOR UPDATE"
            );
            $stmt->execute([$attemptId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('Provisioning attempt was not found.');
            }
            if ($row['status'] !== 'queued' || $row['operation'] !== 'create') {
                throw new RuntimeException('Only queued create attempts can be dispatched; no retry was made.');
            }

            $targets = json_decode((string)$row['server_targets_snapshot'], true, 512, JSON_THROW_ON_ERROR);
            $target = null;
            foreach (is_array($targets) ? $targets : [] as $candidate) {
                if (is_array($candidate) && (int)($candidate['server_id'] ?? 0) === (int)$row['server_id']) {
                    $target = $candidate;
                    break;
                }
            }
            $groupName = trim((string)($target['group_name'] ?? ''));
            if ($groupName === '' || trim((string)$row['username']) === '' || empty($row['password_ciphertext'])) {
                throw new RuntimeException('Immutable order provisioning snapshot is incomplete.');
            }

            $claim = $this->pdo->prepare(
                "UPDATE provisioning_attempts SET status='running', started_at=UTC_TIMESTAMP(),
                 error_code=NULL, error_message=NULL WHERE id=? AND status='queued'"
            );
            $claim->execute([$attemptId]);
            if ($claim->rowCount() !== 1) {
                throw new RuntimeException('Provisioning attempt was claimed by another request.');
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        $username = (string)$row['username'];
        try {
            $client = new IBSngClient(
                (string)$row['host'],
                (int)$row['port'],
                (string)$row['admin_username'],
                app_decrypt_secret((string)$row['server_password'])
            );
            // Reconcile before attempting mutation. A found username in the
            // expected group is the durable result of an earlier ambiguous run.
            $existingId = $client->findUserId($username);
            if ($existingId !== null) {
                $user = $client->lookupUser($username);
                if (trim((string)($user['group'] ?? '')) === $groupName) {
                    $this->finish($attemptId, 'succeeded', $existingId, null, null);
                    return 'succeeded';
                }
                $this->finish($attemptId, 'needs_review', $existingId, 'username_conflict',
                    'IBSng already contains this username, but its group does not match the order snapshot.');
                return 'needs_review';
            }

            // A create may now be sent only once. Any thrown result is treated
            // as ambiguous because IBSng may have committed before response loss.
            $client->createUser($groupName, $username, app_decrypt_secret((string)$row['password_ciphertext']));
            $remoteId = $client->findUserId($username);
            if ($remoteId === null) {
                $this->finish($attemptId, 'needs_review', null, 'create_unconfirmed',
                    'IBSng create returned but a follow-up lookup could not confirm the account.');
                return 'needs_review';
            }
            $user = $client->lookupUser($username);
            if (trim((string)($user['group'] ?? '')) !== $groupName) {
                $this->finish($attemptId, 'needs_review', $remoteId, 'group_mismatch',
                    'The created IBSng account group does not match the order snapshot.');
                return 'needs_review';
            }
            $this->finish($attemptId, 'succeeded', $remoteId, null, null);
            return 'succeeded';
        } catch (Throwable $e) {
            $this->finish($attemptId, 'needs_review', null, 'remote_outcome_ambiguous',
                'IBSng operation result requires reconciliation: ' . substr($e->getMessage(), 0, 1500));
            return 'needs_review';
        }
    }

    private function finish(int $attemptId, string $status, ?string $remoteId, ?string $errorCode, ?string $errorMessage): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE provisioning_attempts SET status=?, remote_user_id=?, error_code=?, error_message=?,
             finished_at=UTC_TIMESTAMP() WHERE id=? AND status="running"'
        );
        $stmt->execute([$status, $remoteId, $errorCode, $errorMessage, $attemptId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Provisioning attempt state changed unexpectedly.');
        }

        $orderIdStmt = $this->pdo->prepare('SELECT order_id FROM provisioning_attempts WHERE id=?');
        $orderIdStmt->execute([$attemptId]);
        $orderId = (int)$orderIdStmt->fetchColumn();
        $counts = $this->pdo->prepare(
            "SELECT SUM(status='succeeded') AS succeeded, SUM(status IN ('queued','running')) AS pending,
                    SUM(status IN ('failed','needs_review')) AS issues
             FROM provisioning_attempts WHERE order_id=?"
        );
        $counts->execute([$orderId]);
        $state = $counts->fetch(PDO::FETCH_ASSOC) ?: [];
        $orderStatus = ((int)($state['pending'] ?? 0) > 0) ? 'provisioning'
            : (((int)($state['issues'] ?? 0) > 0)
                ? (((int)($state['succeeded'] ?? 0) > 0) ? 'partial' : 'failed')
                : 'active');
        $update = $this->pdo->prepare("UPDATE orders SET order_status=? WHERE id=? AND payment_status='paid'");
        $update->execute([$orderStatus, $orderId]);
    }
}
