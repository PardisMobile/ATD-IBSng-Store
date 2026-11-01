<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/IBSngRenewalState.php';

function expectRenewalState(array $expected, array $actual, string $case): void {
    if ($expected !== $actual) throw new RuntimeException($case . ': mismatch: ' . var_export($actual, true));
}
expectRenewalState(
    ['user_id'=>'12','expired'=>false,'remaining_days'=>30,'first_login'=>null,'nearest_exp_date'=>null],
    IBSngRenewalState::normalize('12', null, '30 days', '---', 1700000000),
    'never logged in account'
);
expectRenewalState(
    ['user_id'=>'13','expired'=>false,'remaining_days'=>2,'first_login'=>'2025-01-01','nearest_exp_date'=>'2026-11-03 00:00:00'],
    IBSngRenewalState::normalize('13', '2025-01-01', '30', '2026-11-03 00:00:00', strtotime('2026-11-01 00:00:00')),
    'active account remaining time'
);
expectRenewalState(
    ['user_id'=>'14','expired'=>true,'remaining_days'=>0,'first_login'=>'2025-01-01','nearest_exp_date'=>'2026-10-30 00:00:00'],
    IBSngRenewalState::normalize('14', '2025-01-01', '30', '2026-10-30 00:00:00', strtotime('2026-11-01 00:00:00')),
    'expired account'
);
foreach ([['', null, '30', null], ['1', 'x', '30', null], ['1', 'x', '30', 'bad date']] as [$id, $first, $rel, $nearest]) {
    try { IBSngRenewalState::normalize($id, $first, $rel, $nearest, 1700000000); throw new RuntimeException('invalid state accepted'); }
    catch (RuntimeException $e) { if ($e->getMessage() === 'invalid state accepted') throw $e; }
}
echo "IBSng renewal state tests passed.\n";
