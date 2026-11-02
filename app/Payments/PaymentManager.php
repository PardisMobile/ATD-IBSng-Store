<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymentGateway.php';

/**
 * Central registry and dispatcher for payment gateway modules.
 *
 * Trusted adapters are discovered from app/Payments/gateways/*.php. Adding a
 * provider requires one adapter file; checkout, callback, and this registry
 * remain unchanged. Provider selection is always constrained to discovered
 * adapters and persisted transaction provider codes.
 */
final class PaymentManager
{
    /** @var array<string, PaymentGateway> */
    private array $instances = [];

    /** @var array<string, class-string<PaymentGateway>> */
    private static array $gatewayClasses = [];

    private static bool $discovered = false;

    private readonly string $selectedProvider;

    /**
     * Register an adapter programmatically when a trusted bootstrap needs it.
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

    /** Discover trusted adapter modules once per PHP request. */
    private static function discoverGateways(): void
    {
        if (self::$discovered) {
            return;
        }
        self::$discovered = true;

        $directory = __DIR__ . '/gateways';
        $files = glob($directory . '/*.php');
        if ($files === false) {
            throw new RuntimeException('Unable to discover payment gateway modules.');
        }

        foreach ($files as $file) {
            require_once $file;
        }

        foreach (get_declared_classes() as $class) {
            if (!is_subclass_of($class, PaymentGateway::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $classFile = $reflection->getFileName();
            if (!is_string($classFile)
                || dirname(realpath($classFile) ?: $classFile) !== (realpath($directory) ?: $directory)) {
                continue;
            }

            $instance = new $class();
            self::registerGateway($instance->code(), $class);
        }
    }

    /** @return list<string> */
    public static function registeredCodes(): array
    {
        self::discoverGateways();
        return array_keys(self::$gatewayClasses);
    }

    public function __construct(string $selectedProvider)
    {
        self::discoverGateways();
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

    /** @return list<string> */
    public function supportedCurrencies(?string $provider = null): array
    {
        return $this->gateway($provider)->supportedCurrencies();
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
        self::discoverGateways();
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
