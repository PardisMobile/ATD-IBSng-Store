<?php
declare(strict_types=1);

/**
 * Standardized failure raised by a gateway when it cannot complete initiation.
 *
 * safeToRetry must be true only when the adapter can prove that no payable
 * provider session was created. Ambiguous outcomes must remain false.
 */
final class PaymentGatewayInitiationException extends RuntimeException
{
    /** @param array<string,mixed> $diagnostics */
    public function __construct(
        string $message,
        int $code = 0,
        private readonly bool $safeToRetry = false,
        private readonly array $diagnostics = []
    ) {
        parent::__construct($message, $code);
    }

    public function isSafeToRetry(): bool
    {
        return $this->safeToRetry;
    }

    /** @return array<string,mixed> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }
}

/**
 * Contract for independently replaceable payment-provider modules.
 *
 * Implementations must never mark an order paid based only on a browser return.
 * Provider callbacks must be verified against the server-side transaction amount,
 * currency, and reference before the application records payment success.
 */
interface PaymentGateway
{
    public function code(): string;

    /** Currency codes accepted by this adapter, before any provider-side denomination mapping. @return list<string> */
    public function supportedCurrencies(): array;

    /** Extract the provider's transaction reference from untrusted callback data. */
    public function callbackReference(array $callback): string;

    /** Return 'new' when retrying requires a fresh provider session, or 'resume' to reuse the existing one. */
    public function retryStrategy(): string;

    /** Return provider-specific metadata that must be snapshotted before initiation. */
    public function transactionMetadata(array $settings): array;

    /** Validate whether this adapter has all credentials/settings needed to initiate payment. */
    public function configurationComplete(array $settings): bool;

    /**
     * Begin checkout for an already-persisted pending order.
     *
     * @param array<string, mixed> $order Immutable order snapshot.
     * @param array<string, mixed> $settings Provider-specific settings.
     * @return array{redirect_url:string, provider_reference:string, request_data?:array<string,mixed>}
     */
    public function initiate(array $order, array $settings): array;

    /**
     * Resume the existing provider payment session for a persisted transaction.
     * This must not create a new invoice or authority.
     *
     * @param array<string,mixed> $transaction Persisted transaction including provider reference and metadata.
     * @param array<string,mixed> $settings Provider-specific settings.
     */
    public function resume(array $transaction, array $settings): string;

    /**
     * Verify a provider return/callback using server-side transaction data.
     *
     * @param array<string, mixed> $callback Untrusted provider callback parameters.
     * @param array<string, mixed> $transaction Persisted payment transaction.
     * @param array<string, mixed> $settings Provider-specific settings.
     * @return array{verified:bool, provider_reference?:string, callback_id?:string, amount_minor?:int, currency?:string, result?:array<string,mixed>}
     */
    public function verify(array $callback, array $transaction, array $settings): array;
}
