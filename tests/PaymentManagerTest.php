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
payment_manager_assert($manager->supportedCurrencies() === ['IRR', 'IRT'], 'The provider must expose its supported payment currencies.');
payment_manager_assert((new PaymentManager('ircrypto'))->supportedCurrencies() === ['IRR', 'IRT', 'USDT'], 'IRCrypto must expose crypto currency support.');

foreach (['', 'unknown-provider', '../zarinpal'] as $invalidProvider) {
    $rejected = false;
    try {
        new PaymentManager($invalidProvider);
    } catch (InvalidArgumentException) {
        $rejected = true;
    }
    payment_manager_assert($rejected, 'Unlisted provider names must fail closed.');
}

final class TestPaymentGateway implements PaymentGateway
{
    public function code(): string { return 'testgateway'; }
    public function supportedCurrencies(): array { return ['IRR', 'IRT']; }
    public function callbackReference(array $callback): string { return trim((string)($callback['transaction_id'] ?? '')); }
    public function retryStrategy(): string { return 'resume'; }
    public function transactionMetadata(array $settings): array { return []; }
    public function configurationComplete(array $settings): bool { return true; }
    public function initiate(array $order, array $settings): array
    {
        return ['redirect_url' => 'https://gateway.example/pay', 'provider_reference' => 'test-ref'];
    }
    public function resume(array $transaction, array $settings): string
    {
        return 'https://gateway.example/pay/test-ref';
    }
    public function verify(array $callback, array $transaction, array $settings): array
    {
        return ['verified' => false];
    }
}

PaymentManager::registerGateway('testgateway', TestPaymentGateway::class);
$registered = new PaymentManager('testgateway');
payment_manager_assert($registered->selectedCode() === 'testgateway', 'A registered adapter must be selectable through PaymentManager.');
payment_manager_assert(in_array('testgateway', PaymentManager::registeredCodes(), true), 'PaymentManager must expose its registered provider codes.');
payment_manager_assert($registered->gateway()->code() === 'testgateway', 'A registered class must resolve without a separate provider registry file.');
payment_manager_assert($registered->gateway()->callbackReference(['transaction_id' => 'custom-ref']) === 'custom-ref', 'A provider adapter must extract its own callback reference field.');
payment_manager_assert($registered->retryStrategy() === 'resume', 'PaymentManager must expose the adapter retry strategy.');
payment_manager_assert($registered->resume(['provider' => 'testgateway', 'provider_reference' => 'test-ref'], []) === 'https://gateway.example/pay/test-ref', 'PaymentManager must delegate session resumption to the selected adapter.');
payment_manager_assert((new PaymentManager('zarinpal'))->gateway()->callbackReference(['Authority' => 'zarinpal-ref']) === 'zarinpal-ref', 'Zarinpal callback reference extraction must retain Authority semantics.');
$ircrypto = new PaymentManager('ircrypto');
payment_manager_assert($ircrypto->gateway()->code() === 'ircrypto', 'IRCrypto must be selectable through the same provider manager.');
payment_manager_assert($ircrypto->gateway()->callbackReference(['invoice_id' => 'invoice-123']) === 'invoice-123', 'IRCrypto must extract its invoice reference.');
payment_manager_assert($ircrypto->retryStrategy() === 'resume', 'IRCrypto must resume an existing invoice instead of creating a duplicate on retry.');
$ircryptoFields = $ircrypto->configurationFields('ircrypto');
payment_manager_assert(($ircryptoFields['api_key']['required'] ?? false) === true, 'IRCrypto API key must be required in provider settings.');
payment_manager_assert(($ircryptoFields['webhook_secret']['secret'] ?? false) === true, 'IRCrypto webhook secret must be marked secret.');
$zarinpalFields = (new PaymentManager('zarinpal'))->configurationFields('zarinpal');
payment_manager_assert(array_key_exists('merchant_id', $zarinpalFields) && array_key_exists('sandbox_merchant_id', $zarinpalFields), 'Zarinpal must expose separate live and sandbox merchant credentials.');
payment_manager_assert(($zarinpalFields['sandbox_mode']['type'] ?? '') === 'checkbox', 'Zarinpal must expose a persistent Sandbox mode toggle.');

echo "PaymentManager tests passed.\\n";
