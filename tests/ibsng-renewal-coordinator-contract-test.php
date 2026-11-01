<?php
declare(strict_types=1);

// Static contract checks: these assertions prevent the renewal coordinator
// from being wired to a mutating path until the durable ledger exists.
$client = file_get_contents(dirname(__DIR__) . '/app/IBSngClient.php');
$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
$migration = file_get_contents(dirname(__DIR__) . '/database/migrations/001_ibsng_renewal_ledger.sql');
$policyMigration = file_get_contents(dirname(__DIR__) . '/database/migrations/002_ibsng_renewal_policy.sql');
$coordinator = file_get_contents(dirname(__DIR__) . '/app/IBSngRenewalCoordinator.php');
$service = file_get_contents(dirname(__DIR__) . '/app/IBSngRenewalService.php');
$migrationAdmin = file_get_contents(dirname(__DIR__) . '/admin/ibsng-renewal-migrate.php');
$renewalAdmin = file_get_contents(dirname(__DIR__) . '/admin/ibsng-users.php');
foreach ([$client, $schema, $migration, $policyMigration, $coordinator, $service, $migrationAdmin, $renewalAdmin] as $source) {
    if (!is_string($source) || $source === '') {
        throw new RuntimeException('Required renewal source could not be read.');
    }
}
if (!str_contains($client, 'submitRenewalAttributes(')) {
    throw new RuntimeException('The native IBSng attribute submission adapter is missing.');
}
if (!str_contains($schema, 'idempotency_key') || !str_contains($migration, 'idempotency_key')) {
    throw new RuntimeException('The renewal ledger must retain its unique idempotency key in fresh and upgraded installs.');
}
if (!str_contains($schema, 'needs_review') || !str_contains($migration, 'needs_review')) {
    throw new RuntimeException('The ledger must represent uncertain remote outcomes.');
}
if (!str_contains($coordinator, 'assertNoUnresolvedForAccount')) {
    throw new RuntimeException('The coordinator must block new grants while a prior operation is unresolved.');
}
if (!str_contains($service, 'GET_LOCK') || !str_contains($service, 'RELEASE_LOCK')) {
    throw new RuntimeException('The renewal service must serialize per-account operations.');
}
if (strpos($service, 'reserve(') === false || strpos($service, 'submitRenewalAttributes(') === false
    || strpos($service, 'reserve(') > strpos($service, 'submitRenewalAttributes(')) {
    throw new RuntimeException('The durable ledger reservation must precede remote mutation.');
}
if (!str_contains($service, 'markNeedsReview')) {
    throw new RuntimeException('Uncertain remote outcomes must be marked for reconciliation.');
}
if (!str_contains($schema, 'ibsng_validity_days') || !str_contains($policyMigration, 'ibsng_validity_days')) {
    throw new RuntimeException('IBSng renewal validity must be stored explicitly, not inferred from generic product duration.');
}
if (!str_contains($policyMigration, 'ibsng_gift_days_first') || !str_contains($policyMigration, 'ibsng_gift_days_repeat')) {
    throw new RuntimeException('The upgrade migration must preserve configurable first/repeat gift policy.');
}
if (!str_contains($migrationAdmin, 'CREATE TABLE IF NOT EXISTS ibsng_renewals')
    || !str_contains($migrationAdmin, 'atd_ibsng_renewal_schema_ready')) {
    throw new RuntimeException('The one-click upgrade must create and verify the renewal ledger table.');
}
if (!str_contains($renewalAdmin, '$renewalColumnReady && $renewalLedgerReady')) {
    throw new RuntimeException('The renewal admin flow must not enable mutation unless both schema pieces exist.');
}
if (!str_contains($service, 'completedRenewalCount') || !str_contains($service, '$giftDaysFirst') || !str_contains($service, '$giftDaysRepeat')) {
    throw new RuntimeException('Gift selection must be based on completed renewals inside the central service.');
}

// Replaying a completed idempotency key must be explicitly identified as a
// ledger recovery, so the admin UI does not claim a fresh IBSng confirmation.
if (!str_contains($coordinator, "'reused_existing' => true")) {
    throw new RuntimeException('Completed ledger replay must be marked as reused_existing.');
}
if (!str_contains($renewalAdmin, "['reused_existing']")) {
    throw new RuntimeException('The admin renewal message must distinguish ledger replay from a fresh IBSng confirmation.');
}
echo "IBSng renewal ledger contract checks passed.\n";
