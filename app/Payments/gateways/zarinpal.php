<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/PaymentGateway.php';

/**
 * Zarinpal API v4 transport adapter.
 *
 * Amounts are passed in the smallest unit represented by the persisted order
 * snapshot. The caller must persist the same amount and currency in the
 * payment_transactions row and configure the matching Zarinpal currency mode.
 * This class deliberately does not convert Toman/Rial/USDT.
 */
final class ZarinpalGateway implements PaymentGateway
{
    private const LIVE_BASE_URL = 'https://payment.zarinpal.com/pg/v4/payment';
    private const SANDBOX_BASE_URL = 'https://sandbox.zarinpal.com/pg/v4/payment';

    public function code(): string
    {
        return 'zarinpal';
    }

    public function supportedCurrencies(): array
    {
        return ['IRR', 'IRT'];
    }

    public function callbackReference(array $callback): string
    {
        return trim((string)($callback['Authority'] ?? ''));
    }

    /** Zarinpal issues a new Authority for each new payment attempt. */
    public function retryStrategy(): string
    {
        return 'new';
    }

    public function configurationComplete(array $settings): bool
    {
        $sandbox = ($settings['sandbox_mode'] ?? '0') === '1';
        $merchantKey = $sandbox ? 'sandbox_merchant_id' : 'merchant_id';
        return trim((string)($settings[$merchantKey] ?? '')) !== '';
    }

    public function transactionMetadata(array $settings): array
    {
        return ['gateway_environment' => [
            'sandbox' => (($settings['sandbox_mode'] ?? '0') === '1'),
        ]];
    }

    /** @return array<string,array{label:string,required?:bool,secret?:bool}> */
    public function configurationFields(): array
    {
        return [
            'merchant_id' => ['label' => 'Merchant ID (Live)', 'required' => false, 'secret' => true],
            'sandbox_merchant_id' => ['label' => 'Sandbox Merchant ID', 'required' => false, 'secret' => false],
            'sandbox_mode' => ['label' => 'حالت آزمایشی Sandbox', 'required' => false, 'type' => 'checkbox'],
        ];
    }

    public function initiate(array $order, array $settings): array
    {
        $sandbox = ($settings['sandbox_mode'] ?? '0') === '1';
        $merchantKey = $sandbox ? 'sandbox_merchant_id' : 'merchant_id';
        $merchantId = trim((string)($settings[$merchantKey] ?? ''));
        $callbackUrl = trim((string)($settings['callback_url'] ?? ''));
        $amount = filter_var($order['amount_minor'] ?? null, FILTER_VALIDATE_INT);
        $currency = strtoupper(trim((string)($order['currency'] ?? '')));

        if ($merchantId === '' || !filter_var($callbackUrl, FILTER_VALIDATE_URL)
            || !in_array($currency, ['IRR', 'IRT'], true)
            || $amount === false || $amount < 1) {
            throw new InvalidArgumentException('Zarinpal payment parameters are incomplete or invalid.');
        }

        $payload = [
            'merchant_id' => $merchantId,
            'amount' => $amount,
            // Explicitly tell Zarinpal whether the stored amount is Rial or Toman.
            'currency' => $currency,
            'callback_url' => $callbackUrl,
            'description' => self::limitDescription((string)($order['description'] ?? 'Store order'), 500),
            'metadata' => array_filter([
                'mobile' => (string)($order['buyer_phone'] ?? ''),
                'email' => (string)($order['buyer_email'] ?? ''),
                'order_id' => (string)($order['public_id'] ?? ''),
            ], static fn(string $value): bool => $value !== ''),
        ];

        $baseUrl = $sandbox ? self::SANDBOX_BASE_URL : self::LIVE_BASE_URL;
        $response = $this->postJson($baseUrl . '/request.json', $payload);
        $data = $response['data'] ?? [];
        $authority = trim((string)($data['authority'] ?? ''));
        if ((int)($data['code'] ?? 0) !== 100 || $authority === '') {
            $diagnostic = json_encode([
                'data' => $data,
                'errors' => $response['errors'] ?? [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            throw new PaymentGatewayInitiationException(
                'Zarinpal did not issue a payment authority. Response: ' . (is_string($diagnostic) ? substr($diagnostic, 0, 2000) : 'unavailable'),
                0,
                true,
                ['provider_response' => $diagnostic]
            );
        }

        return [
            'redirect_url' => ($sandbox ? 'https://sandbox.zarinpal.com/pg/StartPay/' : 'https://payment.zarinpal.com/pg/StartPay/') . rawurlencode($authority),
            'provider_reference' => $authority,
            'request_data' => $response,
        ];
    }

    /** Resume the already-issued Authority without creating another invoice. */
    public function resume(array $transaction, array $settings): string
    {
        $reference = trim((string)($transaction['provider_reference'] ?? ''));
        $metadata = json_decode((string)($transaction['raw_result_json'] ?? ''), true);
        $environment = is_array($metadata['gateway_environment'] ?? null) ? $metadata['gateway_environment'] : [];
        $sandbox = ($environment['sandbox'] ?? false) === true;
        if ($reference === '' || strlen($reference) > 190) {
            throw new InvalidArgumentException('Zarinpal transaction has no resumable authority.');
        }
        $host = $sandbox ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
        return $host . '/pg/StartPay/' . rawurlencode($reference);
    }

    public function verify(array $callback, array $transaction, array $settings): array
    {
        $metadata = json_decode((string)($transaction['raw_result_json'] ?? ''), true);
        $environment = is_array($metadata['gateway_environment'] ?? null) ? $metadata['gateway_environment'] : [];
        // Old transactions have no environment snapshot and remain on live verification.
        $sandbox = ($environment['sandbox'] ?? false) === true;
        $merchantKey = $sandbox ? 'sandbox_merchant_id' : 'merchant_id';
        $merchantId = trim((string)($settings[$merchantKey] ?? ''));
        $authority = trim((string)($callback['Authority'] ?? ''));
        $expectedAuthority = trim((string)($transaction['provider_reference'] ?? ''));
        $amount = filter_var($transaction['amount_minor'] ?? null, FILTER_VALIDATE_INT);
        $currency = strtoupper(trim((string)($transaction['currency'] ?? '')));
        $status = strtoupper(trim((string)($callback['Status'] ?? '')));

        if ($merchantId === '' || $authority === '' || $expectedAuthority === ''
            || !hash_equals($expectedAuthority, $authority) || $status !== 'OK'
            || !in_array($currency, ['IRR', 'IRT'], true) || $amount === false || $amount < 1) {
            return ['verified' => false];
        }

        $baseUrl = $sandbox ? self::SANDBOX_BASE_URL : self::LIVE_BASE_URL;
        $response = $this->postJson($baseUrl . '/verify.json', [
            'merchant_id' => $merchantId,
            'amount' => $amount,
            'authority' => $authority,
        ]);
        $data = $response['data'] ?? [];
        $code = (int)($data['code'] ?? 0);
        $verified = in_array($code, [100, 101], true);

        return [
            'verified' => $verified,
            'provider_reference' => $authority,
            'callback_id' => $authority,
            'amount_minor' => $amount,
            'currency' => $currency,
            'result' => $response,
        ];
    }

    private static function limitDescription(string $value, int $limit): string
    {
        // Keep this adapter usable on typical cPanel PHP installations where
        // mbstring may not be enabled. UTF-8 aware truncation is preferred.
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit, 'UTF-8');
        }
        if (preg_match('//u', $value) === 1) {
            $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
            return is_array($characters) ? implode('', array_slice($characters, 0, $limit)) : substr($value, 0, $limit);
        }
        return substr($value, 0, $limit);
    }

    /** @return array<string,mixed> */
    private function postJson(string $url, array $payload): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The cURL extension is required for Zarinpal.');
        }
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize Zarinpal request.');
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
        ]);
        $body = curl_exec($handle);
        $httpStatus = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $curlError = curl_error($handle);
        $curlErrno = curl_errno($handle);
        $transferInfo = curl_getinfo($handle);
        // pretransfer_time is populated only after DNS, connection and TLS setup
        // have completed and cURL is ready to send the HTTP request.
        $requestMayHaveReachedProvider = $httpStatus > 0
            || (float)($transferInfo['pretransfer_time'] ?? 0) > 0
            || (float)($transferInfo['starttransfer_time'] ?? 0) > 0;
        curl_close($handle);

        if (!is_string($body) || $body === '' || $httpStatus < 200 || $httpStatus >= 300) {
            $responseBody = is_string($body) ? substr($body, 0, 4000) : '[no response body]';
            $timing = [
                'namelookup_time' => $transferInfo['namelookup_time'] ?? null,
                'connect_time' => $transferInfo['connect_time'] ?? null,
                'appconnect_time' => $transferInfo['appconnect_time'] ?? null,
                'pretransfer_time' => $transferInfo['pretransfer_time'] ?? null,
                'starttransfer_time' => $transferInfo['starttransfer_time'] ?? null,
                'primary_ip' => $transferInfo['primary_ip'] ?? null,
                'request_may_have_reached_provider' => $requestMayHaveReachedProvider,
            ];
            throw new PaymentGatewayInitiationException(
                'Zarinpal request failed (HTTP ' . $httpStatus . '): ' . $curlError
                . ' | Transfer diagnostics: ' . json_encode($timing, JSON_UNESCAPED_SLASHES)
                . ' | Response body: ' . $responseBody,
                $curlErrno,
                !$requestMayHaveReachedProvider,
                [
                    'http_status' => $httpStatus,
                    'curl_errno' => $curlErrno,
                    'curl_error' => $curlError,
                    'transfer' => $timing,
                    'response_body' => $responseBody,
                ]
            );
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Zarinpal returned invalid JSON. Response body: ' . substr($body, 0, 4000));
        }
        return $decoded;
    }
}
