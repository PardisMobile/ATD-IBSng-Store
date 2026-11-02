<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymentGateway.php';

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
    private const REQUEST_URL = 'https://api.zarinpal.com/pg/v4/payment/request.json';
    private const VERIFY_URL = 'https://api.zarinpal.com/pg/v4/payment/verify.json';

    public function code(): string
    {
        return 'zarinpal';
    }

    public function initiate(array $order, array $settings): array
    {
        $merchantId = trim((string)($settings['merchant_id'] ?? ''));
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
            'callback_url' => $callbackUrl,
            'description' => mb_substr((string)($order['description'] ?? 'Store order'), 0, 500),
            'metadata' => array_filter([
                'mobile' => (string)($order['buyer_phone'] ?? ''),
                'email' => (string)($order['buyer_email'] ?? ''),
                'order_id' => (string)($order['public_id'] ?? ''),
            ], static fn(string $value): bool => $value !== ''),
        ];

        $response = $this->postJson(self::REQUEST_URL, $payload);
        $data = $response['data'] ?? [];
        $authority = trim((string)($data['authority'] ?? ''));
        if ((int)($data['code'] ?? 0) !== 100 || $authority === '') {
            throw new RuntimeException('Zarinpal did not issue a payment authority.');
        }

        return [
            'redirect_url' => 'https://www.zarinpal.com/pg/StartPay/' . rawurlencode($authority),
            'provider_reference' => $authority,
            'request_data' => $response,
        ];
    }

    public function verify(array $callback, array $transaction, array $settings): array
    {
        $merchantId = trim((string)($settings['merchant_id'] ?? ''));
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

        $response = $this->postJson(self::VERIFY_URL, [
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
        curl_close($handle);

        if (!is_string($body) || $body === '' || $httpStatus < 200 || $httpStatus >= 300) {
            throw new RuntimeException('Zarinpal request failed (HTTP ' . $httpStatus . '): ' . $curlError);
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Zarinpal returned invalid JSON.');
        }
        return $decoded;
    }
}
