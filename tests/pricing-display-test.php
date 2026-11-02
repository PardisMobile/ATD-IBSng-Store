<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Pricing.php';

function assertStorePrice(array $expected, array $actual, string $case): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($case . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

assertStorePrice(
    ['amount' => '123.4', 'currency' => 'IRT', 'label' => 'تومان'],
    store_format_price(1234, 'IRR', 'IRT'),
    'Rial base is converted to Toman for display without losing tenths'
);
assertStorePrice(
    ['amount' => '12,340', 'currency' => 'IRR', 'label' => 'ریال'],
    store_format_price(1234, 'IRT', 'IRR'),
    'Toman base is converted to Rial for display'
);
assertStorePrice(
    ['amount' => '1,234', 'currency' => 'IRR', 'label' => 'ریال'],
    store_format_price(1234, 'IRR', 'IRR'),
    'Rial display leaves Rial base unchanged'
);
assertStorePrice(
    ['amount' => '1,234', 'currency' => 'USD', 'label' => 'USD'],
    store_format_price(1234, 'USD', 'IRT'),
    'Non-IRR currencies are not affected by the Toman/Rial display preference'
);

echo "Store pricing display tests passed.\n";
