<?php
declare(strict_types=1);

// Static contract checks: these assertions prevent the renewal coordinator
// from being wired to a mutating path until the durable ledger exists.
$client = file_get_contents(dirname(__DIR__) . '/app/IBSngClient.php');
$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
$migration = file_get_contents(dirname(__DIR__) . '/database/migrations/001_ibsng_renewal_ledger.sql');
$coordinator = file_get_contents(dirname(__DIR__) . '/app/IBSngRenewalCoordinator.php');
$service = file_get_contents(dirname(__DIR__) . '/app/IBSngRenewalService.php');
foreach ([$client, $schema, $migration, $coordinator, $service] as $source) {
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
echo "IBSng renewal ledger contract checks passed.\n";
