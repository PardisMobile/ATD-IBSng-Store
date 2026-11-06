<?php
declare(strict_types=1);

/**
 * Product fiat prices are stored canonically in IRR. The global store setting
 * controls the unit accepted in the admin form, shown to customers, and sent
 * to fiat payment providers. USDT remains an independent product price.
 */
function store_price_display_unit(PDO $pdo): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute(['price_display_unit']);
    $value = $stmt->fetchColumn();
    return in_array($value, ['IRR', 'IRT'], true) ? (string)$value : 'IRT';
}

/** Convert an amount entered in the configured store unit to canonical IRR storage. */
function store_price_to_storage(int $amount, string $unit): int
{
    if ($amount < 0 || !in_array($unit, ['IRR', 'IRT'], true)) {
        throw new InvalidArgumentException('Invalid store price or display unit.');
    }
    if ($unit === 'IRT') {
        if ($amount > intdiv(PHP_INT_MAX, 10)) {
            throw new InvalidArgumentException('Store price is outside the supported range.');
        }
        return $amount * 10;
    }
    return $amount;
}

/** Convert canonical IRR storage to the configured unit used for checkout/payment. */
function store_price_for_unit(int $storedIrr, string $unit): int
{
    if ($storedIrr < 0 || !in_array($unit, ['IRR', 'IRT'], true)) {
        throw new InvalidArgumentException('Invalid stored price or display unit.');
    }
    if ($unit === 'IRT') {
        if ($storedIrr % 10 !== 0) {
            throw new RuntimeException('Stored IRR price cannot be represented as a whole Toman amount.');
        }
        return intdiv($storedIrr, 10);
    }
    return $storedIrr;
}

/** @return array{amount:string,currency:string,label:string} */
function store_format_price(int $storedIrr, string $displayUnit): array
{
    if ($storedIrr < 0 || !in_array($displayUnit, ['IRR', 'IRT'], true)) {
        throw new InvalidArgumentException('Invalid stored price or display unit.');
    }

    if ($displayUnit === 'IRT') {
        $whole = intdiv($storedIrr, 10);
        $remainder = $storedIrr % 10;
        $displayAmount = number_format($whole, 0, '.', ',') . ($remainder > 0 ? '.' . $remainder : '');
        $label = 'تومان';
    } else {
        $displayAmount = number_format($storedIrr, 0, '.', ',');
        $label = 'ریال';
    }

    return ['amount' => $displayAmount, 'currency' => $displayUnit, 'label' => $label];
}
