<?php
declare(strict_types=1);

function zarinpal_sandbox_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);

require_once $root . '/app/Payments/PaymentGateway.php';
require_once $root . '/app/Payments/gateways/zarinpal.php';
$reachability = new ReflectionMethod(ZarinpalGateway::class, 'requestMayHaveReachedProvider');
$reachability->setAccessible(true);
$dnsTimeoutDiagnostics = [
    'namelookup_time' => 0,
    'connect_time' => 0,
    'appconnect_time' => null,
    'pretransfer_time' => 10.002004,
    'starttransfer_time' => 10.002008,
    'primary_ip' => '',
    'request_size' => 0,
    'size_upload' => 0,
];
zarinpal_sandbox_assert(
    $reachability->invoke(null, 0, $dnsTimeoutDiagnostics) === false,
    'A DNS-resolution timeout with no HTTP response, destination IP, connection, or sent request bytes must be safe to retry even if pretransfer_time is non-zero.'
);
$sentRequestDiagnostics = $dnsTimeoutDiagnostics;
$sentRequestDiagnostics['request_size'] = 180;
zarinpal_sandbox_assert(
    $reachability->invoke(null, 0, $sentRequestDiagnostics) === true,
    'A transport failure after request bytes were sent must remain ambiguous.'
);
$httpResponseDiagnostics = $dnsTimeoutDiagnostics;
zarinpal_sandbox_assert(
    $reachability->invoke(null, 502, $httpResponseDiagnostics) === true,
    'An HTTP response must remain ambiguous for automatic retry classification.'
);


$adapter = file_get_contents($root . '/app/Payments/gateways/zarinpal.php');
$flow = file_get_contents($root . '/app/Payments/PaymentFlow.php');
$transactions = file_get_contents($root . '/app/Payments/PaymentTransactionService.php');
$settings = file_get_contents($root . '/admin/settings.php');
$css = file_get_contents($root . '/assets/css/app.css');
$account = file_get_contents($root . '/customer-account.php');
$orders = file_get_contents($root . '/admin/orders.php');
$orderStatus = file_get_contents($root . '/order-status.php');

zarinpal_sandbox_assert(is_string($adapter) && str_contains($adapter, 'https://sandbox.zarinpal.com/pg/v4/payment'), 'Zarinpal adapter must use the v4 sandbox API host.');
zarinpal_sandbox_assert(str_contains($adapter, "https://payment.zarinpal.com/pg/v4/payment"), 'Zarinpal live API must use the official payment.zarinpal.com host.');
zarinpal_sandbox_assert(str_contains($adapter, "https://payment.zarinpal.com/pg/StartPay/"), 'Zarinpal live payment must redirect to the official hosted payment page.');
zarinpal_sandbox_assert(str_contains($adapter, 'Response: ') && str_contains($adapter, "'errors' => \$response['errors'] ?? []"), 'Zarinpal API rejection details must be retained for server-side diagnostics.');
zarinpal_sandbox_assert(str_contains($adapter, 'https://sandbox.zarinpal.com/pg/StartPay/'), 'Sandbox payment must redirect to the sandbox hosted payment page.');
zarinpal_sandbox_assert(str_contains($adapter, "sandbox_merchant_id"), 'Sandbox verification and request must use the separate sandbox merchant ID.');
zarinpal_sandbox_assert(str_contains($adapter, "raw_result_json") && str_contains($adapter, "gateway_environment"), 'Zarinpal verification must recover the environment snapshot from the transaction.');
zarinpal_sandbox_assert(is_string($adapter) && str_contains($adapter, 'function transactionMetadata') && str_contains($adapter, "'gateway_environment'") && str_contains($adapter, "sandbox_mode"), 'Zarinpal adapter must own and snapshot its Sandbox/Live environment.');
zarinpal_sandbox_assert(is_string($flow) && str_contains($flow, '$manager->transactionMetadata($providerSettingsForMetadata)'), 'Payment flow must request provider metadata through the generic gateway manager.');
$bootstrap = file_get_contents($root . '/app/bootstrap.php');
zarinpal_sandbox_assert(is_string($bootstrap) && str_contains($bootstrap, 'function app_absolute_url') && str_contains($bootstrap, 'FILTER_VALIDATE_DOMAIN'), 'Payment callbacks must use a validated absolute origin without changing installer base_url storage.');
zarinpal_sandbox_assert(str_contains($flow, "app_absolute_url('payment-callback.php?provider='") && !str_contains($flow, "app_url('payment-callback.php?provider='"), 'Payment initiation must provide an absolute callback URL.');
zarinpal_sandbox_assert(str_contains($flow, '$e instanceof InvalidArgumentException') && str_contains($flow, '$transactions->failInitiation($transactionId)'), 'Definitive local Zarinpal validation failures must release the initiation for retry.');

zarinpal_sandbox_assert(str_contains($adapter, 'Zarinpal did not issue a payment authority. Response:') && str_contains($adapter, 'PaymentGatewayInitiationException') && str_contains($adapter, 'true,'), 'Zarinpal must classify a definitive rejection without authority through the generic safe-retry exception.');
zarinpal_sandbox_assert(str_contains($flow, 'PaymentGatewayInitiationException') && str_contains($flow, '$transactions->failInitiation($transactionId)'), 'Payment flow must release only failures explicitly classified safe to retry by the generic gateway contract.');
zarinpal_sandbox_assert(is_string($orderStatus) && str_contains($orderStatus, 'retry_payment') && str_contains($orderStatus, "\$lastPayment['status'] === 'failed'") && str_contains($orderStatus, 'PaymentFlow::initiate'), 'Pending orders with a failed payment must offer retry through the same provider.');
zarinpal_sandbox_assert(str_contains($adapter, 'public function resume') && str_contains($adapter, "'gateway_environment'") && str_contains($adapter, 'sandbox.zarinpal.com'), 'Zarinpal adapter must resume its existing authority using the original Sandbox/Live environment snapshot.');

zarinpal_sandbox_assert(is_string($transactions) && str_contains($transactions, 'raw_result_json') && str_contains($transactions, '$metadata'), 'Payment transaction creation must persist environment metadata before provider initiation.');
zarinpal_sandbox_assert(is_string($settings) && str_contains($settings, "['type'] ?? '') === 'checkbox'") && str_contains($settings, 'sandbox_merchant_id'), 'Admin settings must expose sandbox mode and sandbox merchant credentials.');
zarinpal_sandbox_assert(is_string($settings) && str_contains($settings, 'support_uuid_v4') && str_contains($settings, 'support_is_uuid_v4') && str_contains($settings, 'readonly dir="ltr"'), 'Admin settings must automatically generate and retain a UUID v4 for Sandbox Merchant ID.');
zarinpal_sandbox_assert(is_string($orders) && str_contains($orders, 't.raw_result_json') && str_contains($orders, '$isSandboxPayment'), 'Admin sales report must read the stored transaction environment.');
zarinpal_sandbox_assert(str_contains($orders, 'data-fa="آزمایشی" data-en="Sandbox"') && str_contains($orders, 'if ($isSandboxPayment)'), 'Admin sales report must show only the Sandbox label for sandbox transactions.');
zarinpal_sandbox_assert(is_string($css) && !str_contains($css, 'fonts.googleapis.com') && str_contains($css, 'Vazirmatn-Variable.woff2'), 'Vazirmatn must load from the locally hosted assets/fonts directory, not Google Fonts.');
zarinpal_sandbox_assert(is_string($account) && str_contains($account, 'مشاهده مشخصات اکانت') && str_contains($account, 'font-size:clamp(1.65rem,3.2vw,2.25rem)'), 'Customer account title text and typography must follow the shared heading scale.');

echo "Zarinpal sandbox and local font contracts passed.\\n";
