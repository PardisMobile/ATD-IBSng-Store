<?php
declare(strict_types=1);

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
