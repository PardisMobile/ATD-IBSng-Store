<?php
declare(strict_types=1);

/**
 * Store prices remain in their persisted currency/minor-unit representation.
 * This helper only converts the amount for presentation; it never changes an
 * order snapshot or a payment amount.
 */
function store_price_display_unit(PDO $pdo): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute(['price_display_unit']);
    $value = $stmt->fetchColumn();
    return in_array($value, ['IRR', 'IRT'], true) ? (string)$value : 'IRT';
}

/** @return array{amount:string,currency:string,label:string} */
function store_format_price(int $amount, string $currency, string $displayUnit): array
{
    $currency = strtoupper(trim($currency));
    $displayAmount = (string)$amount;
    if ($currency === 'IRR' && $displayUnit === 'IRT') {
        $displayAmount = number_format($amount / 10, 1, '.', ',');
        if (str_ends_with($displayAmount, '.0')) {
            $displayAmount = substr($displayAmount, 0, -2);
        }
        $currency = 'IRT';
    } elseif ($currency === 'IRT' && $displayUnit === 'IRR') {
        $displayAmount = number_format($amount * 10, 0, '.', ',');
        $currency = 'IRR';
    } else {
        $displayAmount = number_format($amount, 0, '.', ',');
    }
    $label = $currency === 'IRT' ? 'تومان' : ($currency === 'IRR' ? 'ریال' : $currency);
    return ['amount' => $displayAmount, 'currency' => $currency, 'label' => $label];
}
