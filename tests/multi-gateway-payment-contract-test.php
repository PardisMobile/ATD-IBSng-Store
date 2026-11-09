<?php
declare(strict_types=1);

function multi_gateway_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$settings = file_get_contents($root . '/admin/settings.php');
$checkout = file_get_contents($root . '/checkout.php');
$flow = file_get_contents($root . '/app/Payments/PaymentFlow.php');
$manager = file_get_contents($root . '/app/Payments/PaymentManager.php');
$catalog = file_get_contents($root . '/index.php');

multi_gateway_contract_assert(is_string($settings) && str_contains($settings, 'name="enabled_providers[]"'), 'Admin settings must allow multiple enabled gateway codes.');
multi_gateway_contract_assert(str_contains($settings, 'payment_enabled_providers'), 'Admin settings must persist the enabled gateway list.');
multi_gateway_contract_assert(str_contains($settings, 'gateway-grid'), 'Gateway configuration cards must use the shared settings card layout.');
multi_gateway_contract_assert(is_string($flow) && str_contains($flow, 'public static function availableProviders'), 'Payment flow must expose configured compatible payment choices.');
multi_gateway_contract_assert(str_contains($flow, 'payment_enabled_providers'), 'Payment flow must read the enabled gateway list.');
multi_gateway_contract_assert(str_contains($flow, 'payment_provider'), 'Payment flow must retain legacy single-provider settings fallback.');
multi_gateway_contract_assert(str_contains($flow, 'public static function initiate(PDO $pdo, int $orderId, string $selectedProvider, bool $allowConcurrentPending = false)'), 'Payment initiation must accept the selected provider and optional safe concurrent retry flag.');
multi_gateway_contract_assert(str_contains($flow, '$provider,') && str_contains($flow, 'PaymentTransactionService($pdo)'), 'The selected provider must be passed into durable payment transaction creation.');
multi_gateway_contract_assert(is_string($checkout) && str_contains($checkout, 'name="payment_provider"'), 'Checkout must present a customer-selectable payment provider.');
multi_gateway_contract_assert(str_contains($checkout, 'PaymentFlow::initiate($pdo, $orderId, $selectedPaymentProvider)'), 'Checkout must pass the selected provider to payment initiation.');
multi_gateway_contract_assert(is_string($manager) && str_contains($manager, 'glob('), 'Future trusted gateway adapters must remain discoverable without checkout-specific provider branches.');
multi_gateway_contract_assert(is_string($catalog) && str_contains($catalog, 'payment_enabled_providers') && str_contains($catalog, 'array_unique(array_filter($enabledProviders'), 'USDT catalog visibility must consider every enabled configured crypto gateway, not only the legacy single provider.');

echo "Multi-gateway payment contract tests passed.\\n";
