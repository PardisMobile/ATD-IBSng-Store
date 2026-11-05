<?php
declare(strict_types=1);

require_once __DIR__ . '/Crypto.php';
require_once __DIR__ . '/IBSngClient.php';
require_once __DIR__ . '/CustomerAccountDelivery.php';
require_once __DIR__ . '/IBSngRenewalService.php';
require_once __DIR__ . '/IBSngRenewalCoordinator.php';

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
                        o.operation AS order_operation, o.product_id, p.ibsng_validity_days,
                        s.host, s.port, s.username AS admin_username, s.password_ciphertext AS server_password
                 FROM provisioning_attempts a
                 JOIN orders o ON o.id = a.order_id
                 LEFT JOIN products p ON p.id = o.product_id
                 JOIN ibsng_servers s ON s.id = a.server_id
                 WHERE a.id = ? FOR UPDATE"
            );
            $stmt->execute([$attemptId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('Provisioning attempt was not found.');
            }
            if ($row['status'] !== 'queued' || !in_array($row['operation'], ['create', 'renew'], true)
                || $row['operation'] !== $row['order_operation']) {
                throw new RuntimeException('Queued operation does not match the immutable order operation; no remote action was sent.');
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
            if ($groupName === '' || trim((string)$row['username']) === ''
                || ($row['operation'] === 'create' && empty($row['password_ciphertext']))
                || ($row['operation'] === 'renew' && (int)($row['ibsng_validity_days'] ?? 0) < 1)) {
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
            if ($row['operation'] === 'renew') {
                $existingId = $client->findUserId($username);
                if ($existingId === null) {
                    $this->finish($attemptId, 'needs_review', null, 'renew_user_not_found', 'The existing IBSng account could not be found; renewal was not submitted.');
                    return 'needs_review';
                }
                $settingsStmt = $this->pdo->prepare('SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN (?, ?)');
                $settingsStmt->execute(['ibsng_gift_days_first', 'ibsng_gift_days_repeat']);
                $giftSettings = [];
                foreach ($settingsStmt->fetchAll(PDO::FETCH_ASSOC) as $setting) $giftSettings[(string)$setting['setting_key']] = (int)$setting['setting_value'];
                $renewal = new IBSngRenewalService($this->pdo, $client, new IBSngRenewalCoordinator($this->pdo));
                $result = $renewal->renew(
                    (int)$row['server_id'], $username, $groupName, (int)$row['ibsng_validity_days'],
                    max(0, $giftSettings['ibsng_gift_days_first'] ?? 1),
                    max(0, $giftSettings['ibsng_gift_days_repeat'] ?? 5),
                    (string)$row['idempotency_key'],
                    (int)$row['product_id'],
                    'ATD Store renewal order'
                );
                if (($result['status'] ?? '') !== 'completed') {
                    $this->finish($attemptId, 'needs_review', $existingId, 'renewal_not_confirmed', 'IBSng renewal did not return a completed result.');
                    return 'needs_review';
                }
                $this->finish($attemptId, 'succeeded', $existingId, null, null);
                return 'succeeded';
            }

            // Reconcile before attempting mutation. A found username in the
            // expected group is the durable result of an earlier ambiguous run.
            $existingId = $client->findUserId($username);
            if ($existingId !== null) {
                $user = $client->lookupUser($username);
                $expectedPassword = app_decrypt_secret((string)$row['password_ciphertext']);
                $credentials = $client->getCredentials($username);
                $matchesSnapshot = trim((string)($user['group'] ?? '')) === $groupName
                    && hash_equals($expectedPassword, (string)($credentials['password'] ?? ''));
                if ($matchesSnapshot) {
                    $this->finish($attemptId, 'succeeded', $existingId, null, null);
                    return 'succeeded';
                }
                $this->finish($attemptId, 'needs_review', $existingId, 'username_conflict',
                    'IBSng username exists, but its group or credentials do not match the immutable order snapshot.');
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
            $expectedPassword = app_decrypt_secret((string)$row['password_ciphertext']);
            $credentials = $client->getCredentials($username);
            if (trim((string)($user['group'] ?? '')) !== $groupName
                || !hash_equals($expectedPassword, (string)($credentials['password'] ?? ''))) {
                $this->finish($attemptId, 'needs_review', $remoteId, 'account_snapshot_mismatch',
                    'The IBSng account group or credentials do not match the immutable order snapshot.');
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

    /**
     * Resolve an ambiguous/stale attempt after an operator has independently
     * inspected IBSng. This records a decision only; it never sends a remote call.
     */
    public function resolveManually(int $attemptId, string $outcome, ?string $remoteUserId, string $note): void
    {
        if ($attemptId < 1 || !in_array($outcome, ['succeeded', 'failed'], true)
            || trim($note) === '' || strlen($note) > 1500
            || ($remoteUserId !== null && strlen($remoteUserId) > 150)) {
            throw new InvalidArgumentException('Manual reconciliation details are invalid.');
        }
        $this->pdo->beginTransaction();
        try {
            $lock = $this->pdo->prepare('SELECT order_id, server_id, status FROM provisioning_attempts WHERE id=? FOR UPDATE');
            $lock->execute([$attemptId]);
            $row = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$row || !in_array($row['status'], ['needs_review', 'running'], true)) {
                throw new RuntimeException('Only needs-review or stale-running attempts can be reconciled manually.');
            }
            $update = $this->pdo->prepare(
                "UPDATE provisioning_attempts SET status=?, remote_user_id=?, error_code='manual_reconciliation',
                 error_message=?, finished_at=UTC_TIMESTAMP() WHERE id=?"
            );
            $update->execute([$outcome, $remoteUserId, trim($note), $attemptId]);
            if ($outcome === 'succeeded') {
                $this->recordSubscription((int)$row['order_id'], (int)$row['server_id'], $remoteUserId);
            }

            $counts = $this->pdo->prepare(
                "SELECT SUM(status='succeeded') AS succeeded, SUM(status IN ('queued','running')) AS pending,
                        SUM(status IN ('failed','needs_review')) AS issues
                 FROM provisioning_attempts WHERE order_id=?"
            );
            $counts->execute([(int)$row['order_id']]);
            $state = $counts->fetch(PDO::FETCH_ASSOC) ?: [];
            $orderStatus = ((int)($state['pending'] ?? 0) > 0) ? 'provisioning'
                : (((int)($state['issues'] ?? 0) > 0)
                    ? (((int)($state['succeeded'] ?? 0) > 0) ? 'partial' : 'failed')
                    : 'active');
            $orderUpdate = $this->pdo->prepare("UPDATE orders SET order_status=? WHERE id=? AND payment_status='paid'");
            $orderUpdate->execute([$orderStatus, (int)$row['order_id']]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function finish(int $attemptId, string $status, ?string $remoteId, ?string $errorCode, ?string $errorMessage): void
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE provisioning_attempts SET status=?, remote_user_id=?, error_code=?, error_message=?,
                 finished_at=UTC_TIMESTAMP() WHERE id=? AND status="running"'
            );
            $stmt->execute([$status, $remoteId, $errorCode, $errorMessage, $attemptId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Provisioning attempt state changed unexpectedly.');
            }

            $attempt = $this->pdo->prepare('SELECT order_id, server_id FROM provisioning_attempts WHERE id=?');
            $attempt->execute([$attemptId]);
            $attemptRow = $attempt->fetch(PDO::FETCH_ASSOC);
            if (!$attemptRow) throw new RuntimeException('Provisioning attempt disappeared during completion.');
            $orderId = (int)$attemptRow['order_id'];
            if ($status === 'succeeded') {
                $this->recordSubscription($orderId, (int)$attemptRow['server_id'], $remoteId);
            }

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
            if ($ownsTransaction) $this->pdo->commit();
            if ($ownsTransaction) {
                try {
                    $operationStmt = $this->pdo->prepare('SELECT operation FROM orders WHERE id=?');
                    $operationStmt->execute([$orderId]);
                    if ((string)$operationStmt->fetchColumn() === 'create') CustomerAccountDelivery::afterProvisioning($this->pdo, $orderId);
                }
                catch (Throwable $emailError) { error_log('Account delivery hook failed for order ' . $orderId . ': ' . $emailError->getMessage()); }
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Persist a confirmed destination in the local subscription ledger. Account
     * expiry and quota are intentionally left unknown until a real IBSng sync
     * supplies those values; no product/order estimate is copied into them.
     */
    private function recordSubscription(int $orderId, int $serverId, ?string $remoteId): void
    {
        if ($orderId < 1 || $serverId < 1) {
            throw new InvalidArgumentException('Subscription identity is invalid.');
        }
        $remoteId = $remoteId !== null ? trim($remoteId) : null;
        if ($remoteId === '') $remoteId = null;
        if ($remoteId !== null && strlen($remoteId) > 150) {
            throw new InvalidArgumentException('IBSng remote user ID is too long.');
        }

        $order = $this->pdo->prepare('SELECT product_id, username FROM orders WHERE id=? FOR UPDATE');
        $order->execute([$orderId]);
        $orderRow = $order->fetch(PDO::FETCH_ASSOC);
        if (!$orderRow || trim((string)$orderRow['username']) === '') {
            throw new RuntimeException('Order account identity is unavailable for subscription persistence.');
        }
        $username = (string)$orderRow['username'];

        $existing = $this->pdo->prepare(
            'SELECT id FROM subscriptions WHERE server_id=? AND username=? FOR UPDATE'
        );
        $existing->execute([$serverId, $username]);
        $subscriptionId = $existing->fetchColumn();
        if ($subscriptionId !== false) {
            $update = $this->pdo->prepare(
                "UPDATE subscriptions SET order_id=?, product_id=?, ibsng_user_id=COALESCE(?,ibsng_user_id), status='active',
                 last_synced_at=UTC_TIMESTAMP() WHERE id=?"
            );
            $update->execute([$orderId, $orderRow['product_id'], $remoteId, (int)$subscriptionId]);
            return;
        }

        if ($remoteId !== null) {
            $remoteCollision = $this->pdo->prepare(
                'SELECT username FROM subscriptions WHERE server_id=? AND ibsng_user_id=? FOR UPDATE'
            );
            $remoteCollision->execute([$serverId, $remoteId]);
            $otherUsername = $remoteCollision->fetchColumn();
            if ($otherUsername !== false && (string)$otherUsername !== $username) {
                throw new RuntimeException('IBSng remote user ID is already linked to another subscription on this server.');
            }
        }

        $insert = $this->pdo->prepare(
            "INSERT INTO subscriptions (order_id, product_id, server_id, ibsng_user_id, username, status, last_synced_at)
             VALUES (?, ?, ?, ?, ?, 'active', UTC_TIMESTAMP())"
        );
        $insert->execute([$orderId, $orderRow['product_id'], $serverId, $remoteId, $username]);
    }
}
