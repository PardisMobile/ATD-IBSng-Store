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
    ['amount' => '250,000', 'currency' => 'IRT', 'label' => 'تومان'],
    store_format_price(2500000, 'IRT'),
    'Canonical Rial price is converted to Toman for display'
);
assertStorePrice(
    ['amount' => '2,500,000', 'currency' => 'IRR', 'label' => 'ریال'],
    store_format_price(2500000, 'IRR'),
    'Canonical Rial price is shown in Rial'
);

if (store_price_to_storage(250000, 'IRT') !== 2500000
    || store_price_to_storage(2500000, 'IRR') !== 2500000
    || store_price_for_unit(2500000, 'IRT') !== 250000
    || store_price_for_unit(2500000, 'IRR') !== 2500000) {
    throw new RuntimeException('Global currency conversion failed.');
}

echo "Store pricing display tests passed.\n";
