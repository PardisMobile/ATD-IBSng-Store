<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$callback = file_get_contents($root . '/payment-callback.php');
$flow = file_get_contents($root . '/app/Payments/PaymentFlow.php');
$messages = file_get_contents($root . '/app/messages.php');
$icons = file_get_contents($root . '/app/StorefrontInfoIcons.php');

$checks = [
    'verified callback exposes the provider reference from the verified transaction' => is_string($flow)
        && str_contains($flow, "'provider_reference' => (string)(\$transaction['provider_reference'] ?? '')"),
    'success page loads the shared storefront SVG icon helper' => is_string($callback)
        && str_contains($callback, "require_once __DIR__ . '/app/StorefrontInfoIcons.php';"),
    'success page loads the order tracking code from the order record' => is_string($callback)
        && str_contains($callback, 'o.tracking_code')
        && str_contains($callback, "\$orderTrackingCode = (string)(\$paidOrder['tracking_code'] ?? '');"),
    'payment detail cards use the shared tracking and payment SVG icons' => is_string($callback)
        && str_contains($callback, 'storefront_info_icon("tracking")')
        && str_contains($callback, 'storefront_info_icon("payment")'),
    'account detail cards use the shared SVG information icons' => is_string($callback)
        && str_contains($callback, 'storefront_info_icon("account")')
        && str_contains($callback, 'storefront_info_icon("password")')
        && str_contains($callback, 'storefront_info_icon("plan")')
        && str_contains($callback, 'storefront_info_icon("duration")')
        && str_contains($callback, 'storefront_info_icon("quota")')
        && str_contains($callback, 'storefront_info_icon("date")'),
    'payment tracking reminder is displayed under the payment heading with compact styling' => is_string($callback)
        && str_contains($callback, 'payment_tracking_hint')
        && str_contains($callback, '.payment-details-hint'),
    'callback main card has the storefront top accent strip' => is_string($callback)
        && str_contains($callback, '.result-card::before')
        && str_contains($callback, 'height:4px'),
    'payment detail headings and labels have Persian and English translations' => is_string($messages)
        && str_contains($messages, "'payment_details_heading' => 'مشخصات پرداخت'")
        && str_contains($messages, "'payment_tracking_code' => 'کد رهگیری سفارش'")
        && str_contains($messages, "'payment_gateway_reference' => 'شناسه پرداخت درگاه'")
        && str_contains($messages, "'payment_details_heading' => 'Payment details'")
        && str_contains($messages, "'payment_tracking_code' => 'Order tracking code'")
        && str_contains($messages, "'payment_gateway_reference' => 'Gateway payment reference'")
        && str_contains($messages, "'payment_tracking_hint' => 'لطفاً کد رهگیری را برای پیگیری‌های بعدی نزد خود نگه دارید.'")
        && str_contains($messages, "'payment_tracking_hint' => 'Please keep your tracking code for future reference.'"),
    'payment SVG names are registered in the shared icon catalog' => is_string($icons)
        && str_contains($icons, "'tracking' =>")
        && str_contains($icons, "'payment' =>"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}.\n");
        exit(1);
    }
}
echo "Payment success presentation contract checks passed (" . count($checks) . " checks).\n";
