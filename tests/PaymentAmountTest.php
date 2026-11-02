<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Payments/PaymentAmount.php';

function payment_amount_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$fiat = PaymentAmount::resolve('fiat', [
    'price_minor_snapshot' => 125000,
    'currency' => 'IRT',
]);
payment_amount_assert($fiat === ['amount_minor' => 125000, 'currency' => 'IRT'], 'Fiat must use the exact order snapshot.');

$crypto = PaymentAmount::resolve('crypto', [], ['price_usdt' => '12.34000005']);
payment_amount_assert($crypto === ['amount_minor' => 1234000005, 'currency' => 'USDT'], 'Crypto must preserve all eight decimal places.');

$rejected = false;
try {
    PaymentAmount::resolve('crypto', [], ['price_usdt' => '12.340000059']);
} catch (InvalidArgumentException) {
    $rejected = true;
}
payment_amount_assert($rejected, 'Crypto values with more than eight decimals must fail closed.');

$rejected = false;
try {
    PaymentAmount::resolve('fiat', ['price_minor_snapshot' => 500, 'currency' => 'USD']);
} catch (InvalidArgumentException) {
    $rejected = true;
}
payment_amount_assert($rejected, 'Unsupported fiat currencies must fail closed.');

echo "PaymentAmount tests passed.\n";
