<?php
declare(strict_types=1);

function zarinpal_sandbox_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$adapter = file_get_contents($root . '/app/Payments/gateways/zarinpal.php');
$flow = file_get_contents($root . '/app/Payments/PaymentFlow.php');
$transactions = file_get_contents($root . '/app/Payments/PaymentTransactionService.php');
$settings = file_get_contents($root . '/admin/settings.php');
$css = file_get_contents($root . '/assets/css/app.css');
$account = file_get_contents($root . '/customer-account.php');

zarinpal_sandbox_assert(is_string($adapter) && str_contains($adapter, 'https://sandbox.zarinpal.com/pg/v4/payment'), 'Zarinpal adapter must use the v4 sandbox API host.');
zarinpal_sandbox_assert(str_contains($adapter, 'https://sandbox.zarinpal.com/pg/StartPay/'), 'Sandbox payment must redirect to the sandbox hosted payment page.');
zarinpal_sandbox_assert(str_contains($adapter, "sandbox_merchant_id"), 'Sandbox verification and request must use the separate sandbox merchant ID.');
zarinpal_sandbox_assert(str_contains($adapter, "raw_result_json") && str_contains($adapter, "gateway_environment"), 'Zarinpal verification must recover the environment snapshot from the transaction.');
zarinpal_sandbox_assert(is_string($flow) && str_contains($flow, "payment_zarinpal_sandbox_mode") && str_contains($flow, "'gateway_environment'"), 'Payment initiation must snapshot Zarinpal environment.');
zarinpal_sandbox_assert(is_string($transactions) && str_contains($transactions, 'raw_result_json') && str_contains($transactions, '$metadata'), 'Payment transaction creation must persist environment metadata before provider initiation.');
zarinpal_sandbox_assert(is_string($settings) && str_contains($settings, "'type' => 'checkbox'") && str_contains($settings, 'sandbox_merchant_id'), 'Admin settings must expose sandbox mode and sandbox merchant credentials.');
zarinpal_sandbox_assert(is_string($css) && !str_contains($css, 'fonts.googleapis.com') && str_contains($css, 'assets/fonts') === false && str_contains($css, 'Vazirmatn-Variable.woff2'), 'Vazirmatn must load from the locally hosted assets/fonts directory, not Google Fonts.');
zarinpal_sandbox_assert(is_string($account) && str_contains($account, 'مشاهده مشخصات اکانت') && str_contains($account, 'font-size:clamp(1.65rem,3.2vw,2.25rem)'), 'Customer account title text and typography must follow the shared heading scale.');

echo "Zarinpal sandbox and local font contracts passed.\\n";
