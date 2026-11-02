<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymentGateway.php';

/**
 * Resolves payment adapters using a small explicit allowlist.
 * Provider settings must come from trusted server-side storage.
 */
final class PaymentManager
{
    /** @var array<string, PaymentGateway> */
    private array $instances = [];

    /** @var array<string, class-string<PaymentGateway>> */
    private const GATEWAYS = [
        'zarinpal' => ZarinpalGateway::class,
    ];

    private readonly string $selectedProvider;

    public function __construct(string $selectedProvider)
    {
        $provider = strtolower(trim($selectedProvider));
        if ($provider === '' || !isset(self::GATEWAYS[$provider])) {
            throw new InvalidArgumentException('The configured payment provider is unavailable.');
        }
        $this->selectedProvider = $provider;
    }

    public function selectedCode(): string
    {
        return $this->selectedProvider;
    }

    /** Resolve and lazily load the selected or transaction-specific adapter. */
    public function gateway(?string $provider = null): PaymentGateway
    {
        $code = strtolower(trim($provider ?? $this->selectedProvider));
        if (!isset(self::GATEWAYS[$code])) {
            throw new InvalidArgumentException('Unknown payment provider.');
        }

        if (!isset($this->instances[$code])) {
            $file = __DIR__ . '/' . $code . '.php';
            if (!is_file($file)) {
                throw new RuntimeException('Payment provider adapter file is missing.');
            }
            require_once $file;
            $class = self::GATEWAYS[$code];
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
     * Verify with the provider persisted on the transaction, not the current
     * global selection, so an admin setting change does not affect old payments.
     *
     * @param array<string,mixed> $callback Untrusted callback parameters.
     * @param array<string,mixed> $transaction Persisted transaction.
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
