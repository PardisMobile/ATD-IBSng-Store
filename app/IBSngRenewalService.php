<?php
declare(strict_types=1);

require_once __DIR__ . '/IBSngRenewalPolicy.php';
require_once __DIR__ . '/IBSngRenewalCoordinator.php';
require_once __DIR__ . '/IBSngRenewalState.php';

/**
 * Safe, single-attempt IBSng renewal orchestration.
 *
 * Call only after the admin route has authenticated/authorized the operator,
 * validated CSRF, and resolved the manually configured server/group mapping.
 * No automatic retry is performed for a recorded or uncertain operation.
 */
final class IBSngRenewalService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly IBSngClient $client,
        private readonly IBSngRenewalCoordinator $ledger
    ) {}

    /**
     * @return array{status:string,renewal_id?:int,result?:array}
     */
    public function renew(
        int $serverId,
        string $username,
        string $groupName,
        int $validityDays,
        int $giftDays,
        string $idempotencyKey,
        ?int $productId = null,
        string $comment = ''
    ): array {
        $username = trim($username);
        $groupName = trim($groupName);
        if ($serverId < 1 || $username === '' || $groupName === ''
            || $validityDays < 1 || $giftDays < 0
            || $idempotencyKey === '' || strlen($idempotencyKey) > 190) {
            throw new InvalidArgumentException('Renewal request data is invalid.');
        }

        // Serialize operations for one IBSng user across simultaneous admin requests.
        $lockName = 'atd_ibsng_renew_' . substr(hash('sha256', $serverId . ':' . strtolower($username)), 0, 40);
        $lock = $this->pdo->prepare('SELECT GET_LOCK(:lock_name, 10)');
        $lock->execute(['lock_name' => $lockName]);
        if ((int)$lock->fetchColumn() !== 1) {
            throw new RuntimeException('A renewal for this account is already being processed.');
        }

        try {
            $prior = $this->ledger->resolveExisting($idempotencyKey);
            if ($prior !== null) {
                return $prior;
            }

            // Read and normalize remote state before reserving the mutation.
            $state = $this->client->getRenewalState($username);
            if (!isset($state['user_id'], $state['expired'], $state['remaining_days'])
                || !is_bool($state['expired'])
                || !is_int($state['remaining_days'])
                || $state['remaining_days'] < 0) {
                throw new RuntimeException('IBSng account expiry state is incomplete; renewal stopped safely.');
            }

            $this->ledger->assertNoUnresolvedForAccount($serverId, (string)$state['user_id']);

            $policy = IBSngRenewalPolicy::calculate(
                $state['expired'],
                $state['remaining_days'],
                $validityDays,
                $giftDays
            );
            $renewalId = $this->ledger->reserve(
                $idempotencyKey,
                $serverId,
                (string)$state['user_id'],
                $username,
                $validityDays,
                $giftDays,
                $state['expired'] ? 'expired' : 'active',
                $policy['relative_expiration'],
                $productId
            );

            try {
                $verified = $this->client->submitRenewalAttributes(
                    (string)$state['user_id'],
                    $username,
                    $policy['relative_expiration'],
                    $policy['reset_first_login'],
                    $comment,
                    $groupName
                );
                $result = [
                    'username' => $username,
                    'account_state_before' => $state['expired'] ? 'expired' : 'active',
                    'remaining_days_before' => $state['remaining_days'],
                    'validity_days' => $validityDays,
                    'gift_days' => $giftDays,
                    'relative_expiration_after' => $policy['relative_expiration'],
                    'first_login_reset' => $policy['reset_first_login'],
                    'remote_confirmation' => $verified,
                ];
                $this->ledger->markCompleted($renewalId, $result);
                return ['status' => 'completed', 'renewal_id' => $renewalId, 'result' => $result];
            } catch (Throwable $e) {
                // The remote request may have been accepted even if its response was lost.
                // Mark for manual reconciliation; never replay this idempotency key.
                try {
                    $this->ledger->markNeedsReview(
                        $renewalId,
                        'Remote renewal result is uncertain; automatic retry is prohibited. ' . $e->getMessage()
                    );
                } catch (Throwable $ledgerError) {
                    error_log('IBSng renewal ledger could not be marked for review: ' . $ledgerError->getMessage());
                }
                throw new RuntimeException(
                    'IBSng renewal could not be conclusively confirmed. Check the renewal ledger before any retry.',
                    0,
                    $e
                );
            }
        } finally {
            $release = $this->pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $release->execute(['lock_name' => $lockName]);
        }
    }
}
