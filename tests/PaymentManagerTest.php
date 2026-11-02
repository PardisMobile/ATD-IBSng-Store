<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Payments/PaymentManager.php';

function payment_manager_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$manager = new PaymentManager('zarinpal');
payment_manager_assert($manager->selectedCode() === 'zarinpal', 'The selected provider code must be normalized and retained.');

foreach (['', 'unknown-provider', '../zarinpal'] as $invalidProvider) {
    $rejected = false;
    try {
        new PaymentManager($invalidProvider);
    } catch (InvalidArgumentException) {
        $rejected = true;
    }
    payment_manager_assert($rejected, 'Unlisted provider names must fail closed.');
}

echo "PaymentManager tests passed.\n";
