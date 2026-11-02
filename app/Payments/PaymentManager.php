<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymentGateway.php';
require_once __DIR__ . '/zarinpal.php';
require_once __DIR__ . '/ircrypto.php';

/**
 * Central registry and dispatcher for payment gateway modules.
 *
 * New adapters implement PaymentGateway and are registered through this class
 * during trusted application bootstrap. Checkout and callbacks never select
 * adapter classes or load provider files themselves.
 */
final class PaymentManager
{
    /** @var array<string, PaymentGateway> */
    private array $instances = [];

    /** @var array<string, class-string<PaymentGateway>> */
    private static array $gatewayClasses = [
        'zarinpal' => ZarinpalGateway::class,
        'ircrypto' => IRCryptoGateway::class,
    ];

    private readonly string $selectedProvider;

    /**
     * Register an adapter in the single payment-provider registry.
     *
     * @param class-string<PaymentGateway> $gatewayClass
     */
    public static function registerGateway(string $code, string $gatewayClass): void
    {
        $code = strtolower(trim($code));
        if (!preg_match('/^[a-z][a-z0-9_-]{1,79}$/D', $code)
            || !class_exists($gatewayClass)
            || !is_subclass_of($gatewayClass, PaymentGateway::class)) {
            throw new InvalidArgumentException('Payment gateway registration is invalid.');
        }

        $instance = new $gatewayClass();
        if ($instance->code() !== $code) {
            throw new InvalidArgumentException('Payment gateway code does not match its registration key.');
        }

        self::$gatewayClasses[$code] = $gatewayClass;
    }

    /** @return list<string> */
    public static function registeredCodes(): array
    {
        return array_keys(self::$gatewayClasses);
    }

    public function __construct(string $selectedProvider)
    {
        $provider = strtolower(trim($selectedProvider));
        if ($provider === '' || !isset(self::$gatewayClasses[$provider])) {
            throw new InvalidArgumentException('The configured payment provider is unavailable.');
        }
        $this->selectedProvider = $provider;
    }

    public function selectedCode(): string
    {
        return $this->selectedProvider;
    }

    /** @return array<string,array{label:string,required?:bool,secret?:bool}> */
    public function configurationFields(string $provider): array
    {
        $gateway = $this->gateway($provider);
        return method_exists($gateway, 'configurationFields') ? $gateway->configurationFields() : [];
    }

    /** Resolve the selected or transaction-specific registered adapter. */
    public function gateway(?string $provider = null): PaymentGateway
    {
        $code = strtolower(trim($provider ?? $this->selectedProvider));
        if (!isset(self::$gatewayClasses[$code])) {
            throw new InvalidArgumentException('Unknown payment provider.');
        }

        if (!isset($this->instances[$code])) {
            $class = self::$gatewayClasses[$code];
            if (!class_exists($class)) {
                throw new RuntimeException('Payment provider adapter class is missing.');
            }

            $instance = new $class();
            if (!$instance instanceof PaymentGateway || $instance->code() !== $code) {
                throw new RuntimeException('Payment provider adapter does not satisfy its contract.');
            }
            $this->instances[$code] = $instance;
        }

        return $this->instances[$code];
    }

    /**
     * @param array<string,mixed> $allSettings Provider-keyed trusted settings.
     * @return array<string,mixed>
     */
    public function initiate(array $order, array $allSettings): array
    {
        return $this->gateway()->initiate(
            $order,
            $this->settingsFor($this->selectedProvider, $allSettings)
        );
    }

    /**
     * Verify against the provider persisted on the transaction, not the current
     * global selection, so changing the selected gateway cannot affect old payments.
     *
     * @param array<string,mixed> $callback Untrusted callback parameters.
     * @param array<string,mixed> $transaction Persisted payment transaction.
     * @param array<string,mixed> $allSettings Trusted server-side provider settings.
     * @return array<string,mixed>
     */
    public function verify(array $callback, array $transaction, array $allSettings): array
    {
        $provider = strtolower(trim((string)($transaction['provider'] ?? '')));
        if ($provider === '') {
            throw new InvalidArgumentException('Payment transaction has no provider.');
        }

        return $this->gateway($provider)->verify(
            $callback,
            $transaction,
            $this->settingsFor($provider, $allSettings)
        );
    }

    /** @return array<string,mixed> */
    private function settingsFor(string $provider, array $allSettings): array
    {
        $settings = $allSettings[$provider] ?? [];
        if (!is_array($settings)) {
            throw new InvalidArgumentException('Payment provider settings are malformed.');
        }
        return $settings;
    }
}
