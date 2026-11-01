<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/IBSngRenewalPolicy.php';

function assertRenewalPolicy(array $expected, array $actual, string $case): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($case . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

assertRenewalPolicy(
    ['relative_expiration' => 32, 'reset_first_login' => true],
    IBSngRenewalPolicy::calculate(true, 0, 30, 2),
    'expired account starts validity from zero and resets First Login'
);
assertRenewalPolicy(
    ['relative_expiration' => 37, 'reset_first_login' => false],
    IBSngRenewalPolicy::calculate(false, 5, 30, 2),
    'active account retains remaining days and does not reset First Login'
);
assertRenewalPolicy(
    ['relative_expiration' => 30, 'reset_first_login' => false],
    IBSngRenewalPolicy::calculate(false, 0, 30, 0),
    'zero gift is supported'
);

foreach ([
    [false, -1, 30, 0],
    [false, 0, 0, 0],
    [false, 0, 30, -1],
] as [$expired, $remaining, $validity, $gift]) {
    try {
        IBSngRenewalPolicy::calculate($expired, $remaining, $validity, $gift);
        throw new RuntimeException('Invalid duration values were accepted.');
    } catch (InvalidArgumentException) {
        // Expected contract.
    }
}

echo "IBSng renewal policy tests passed.\n";
