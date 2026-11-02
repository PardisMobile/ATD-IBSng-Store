<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymentGateway.php';

/**
 * IRCrypto Pay invoice adapter.
 *
 * The provider invoice is denominated in IRR/USD/EUR. Store USDT prices are
 * mapped to USD at a 1:1 nominal value; actual settlement asset/network is
 * selected by the payer on IRCrypto's hosted invoice page.
 */
final class IRCryptoGateway implements PaymentGateway
{
    private const API_BASE = 'https://api.ircryptopay.online';

    public function code(): string
    {
        return 'ircrypto';
    }

    public function callbackReference(array $callback): string
    {
        return trim((string)($callback['invoice_id'] ?? $callback['id'] ?? ''));
    }

    /** @return array<string,array{label:string,required?:bool,secret?:bool}> */
    public function configurationFields(): array
    {
        return [
            'api_key' => ['label' => 'IRCrypto API Key', 'required' => true, 'secret' => true],
            'webhook_secret' => ['label' => 'IRCrypto Webhook Secret', 'required' => true, 'secret' => true],
        ];
    }

    public function initiate(array $order, array $settings): array
    {
        $apiKey = trim((string)($settings['api_key'] ?? ''));
        $callbackUrl = trim((string)($settings['callback_url'] ?? ''));
        $amountMinor = filter_var($order['amount_minor'] ?? null, FILTER_VALIDATE_INT);
        $currency = strtoupper(trim((string)($order['currency'] ?? '')));
        if ($apiKey === '' || !filter_var($callbackUrl, FILTER_VALIDATE_URL)
            || !in_array($currency, ['IRR', 'IRT', 'USDT'], true)
            || $amountMinor === false || $amountMinor < 1) {
            throw new InvalidArgumentException('IRCrypto payment parameters are invalid.');
        }

        // IRCrypto invoice currency accepts IRR, USD, EUR. IRT is converted to
        // IRR exactly; USDT uses USD as the nominal invoice currency (1:1).
        if ($currency === 'IRT') {
            $amount = self::minorToDecimal($amountMinor, 0);
            $amount = self::multiplyDecimalByInteger($amount, 10);
            $invoiceCurrency = 'IRR';
        } elseif ($currency === 'USDT') {
            $amount = self::minorToDecimal($amountMinor, 8);
            $invoiceCurrency = 'USD';
        } else {
            $amount = self::minorToDecimal($amountMinor, 0);
            $invoiceCurrency = 'IRR';
        }

        $payload = [
            'amount_mode' => 'fixed',
            'amount' => $amount,
            'currency' => $invoiceCurrency,
            'external_ref' => (string)($order['public_id'] ?? ''),
            'description' => self::limitDescription((string)($order['description'] ?? 'Store order'), 500),
            'redirect_url' => $callbackUrl,
            'expires_in' => 86400,
        ];
        if ($payload['external_ref'] === '') {
            throw new InvalidArgumentException('IRCrypto requires a stable store order reference.');
        }

        $response = $this->request('POST', '/v1/invoices', $apiKey, $payload);
        $invoice = $response['invoice'] ?? [];
        $invoiceId = trim((string)($invoice['id'] ?? ''));
        $paymentUrl = trim((string)($invoice['payment_url'] ?? ''));
        if ($invoiceId === '' || !filter_var($paymentUrl, FILTER_VALIDATE_URL)
            || !str_starts_with(strtolower($paymentUrl), 'https://pay.ircryptopay.online/')) {
            throw new RuntimeException('IRCrypto returned an invalid invoice response.');
        }

        return [
            'redirect_url' => $paymentUrl,
            'provider_reference' => $invoiceId,
            'request_data' => $response,
        ];
    }

    public function verify(array $callback, array $transaction, array $settings): array
    {
        $apiKey = trim((string)($settings['api_key'] ?? ''));
        $invoiceId = trim((string)($transaction['provider_reference'] ?? ''));
        $amountMinor = filter_var($transaction['amount_minor'] ?? null, FILTER_VALIDATE_INT);
        $currency = strtoupper(trim((string)($transaction['currency'] ?? '')));
        if ($apiKey === '' || $invoiceId === '' || $amountMinor === false || $amountMinor < 1
            || !in_array($currency, ['IRR', 'IRT', 'USDT'], true)) {
            return ['verified' => false];
        }

        // Do not trust the browser return payload. Fetch the invoice state directly.
        $response = $this->request('GET', '/v1/invoices/' . rawurlencode($invoiceId), $apiKey);
        $invoice = $response['invoice'] ?? [];
        $expectedRef = (string)($transaction['order_public_id'] ?? '');
        $expectedCurrency = $currency === 'USDT' ? 'USD' : 'IRR';
        $expectedAmount = $currency === 'IRT'
            ? self::multiplyDecimalByInteger(self::minorToDecimal($amountMinor, 0), 10)
            : self::minorToDecimal($amountMinor, $currency === 'USDT' ? 8 : 0);
        $actualAmount = trim((string)($invoice['price_amount'] ?? ''));
        $actualCurrency = strtoupper(trim((string)($invoice['price_currency'] ?? '')));
        $referenceMatches = $expectedRef !== ''
            && hash_equals($expectedRef, (string)($invoice['external_ref'] ?? ''));
        $amountMatches = self::decimalEqual($actualAmount, $expectedAmount);

        return [
            'verified' => strtolower((string)($invoice['status'] ?? '')) === 'paid'
                && $referenceMatches && $amountMatches && $actualCurrency === $expectedCurrency,
            'provider_reference' => $invoiceId,
            'callback_id' => $invoiceId,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'result' => $response,
        ];
    }

    private function request(string $method, string $path, string $apiKey, ?array $payload = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The cURL extension is required for IRCrypto.');
        }
        $handle = curl_init(self::API_BASE . $path);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize IRCrypto request.');
        }
        $headers = ['Accept: application/json', 'Authorization: Bearer ' . $apiKey];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload === null
                ? null
                : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
        ]);
        $body = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($body) || $body === '' || $status < 200 || $status >= 300) {
            throw new RuntimeException('IRCrypto request failed (HTTP ' . $status . '): ' . $error);
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('IRCrypto returned invalid JSON.');
        }
        return $decoded;
    }

    private static function minorToDecimal(int $value, int $scale): string
    {
        if ($scale === 0) {
            return (string)$value;
        }
        $digits = str_pad((string)$value, $scale + 1, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -$scale);
        $fraction = rtrim(substr($digits, -$scale), '0');
        return $fraction === '' ? $whole : $whole . '.' . $fraction;
    }

    private static function multiplyDecimalByInteger(string $value, int $multiplier): string
    {
        if (!preg_match('/^(\d+)(?:\.(\d+))?$/D', $value, $m)) {
            throw new InvalidArgumentException('Invalid decimal amount.');
        }
        $scale = strlen($m[2] ?? '');
        $digits = ltrim($m[1] . ($m[2] ?? ''), '0');
        $digits = $digits === '' ? '0' : $digits;
        $product = (string)((int)$digits * $multiplier);
        if ($scale === 0) {
            return $product;
        }
        $product = str_pad($product, $scale + 1, '0', STR_PAD_LEFT);
        $whole = substr($product, 0, -$scale);
        $fraction = rtrim(substr($product, -$scale), '0');
        return $fraction === '' ? $whole : $whole . '.' . $fraction;
    }

    private static function decimalEqual(string $left, string $right): bool
    {
        if (!preg_match('/^(\d+)(?:\.(\d+))?$/D', $left, $a)
            || !preg_match('/^(\d+)(?:\.(\d+))?$/D', $right, $b)) {
            return false;
        }
        $scale = max(strlen($a[2] ?? ''), strlen($b[2] ?? ''));
        $aDigits = ltrim($a[1] . str_pad($a[2] ?? '', $scale, '0'), '0');
        $bDigits = ltrim($b[1] . str_pad($b[2] ?? '', $scale, '0'), '0');
        return ($aDigits === '' ? '0' : $aDigits) === ($bDigits === '' ? '0' : $bDigits);
    }

    private static function limitDescription(string $value, int $limit): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit, 'UTF-8');
        }
        if (preg_match('//u', $value) === 1) {
            $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
            return is_array($chars) ? implode('', array_slice($chars, 0, $limit)) : substr($value, 0, $limit);
        }
        return substr($value, 0, $limit);
    }
}
