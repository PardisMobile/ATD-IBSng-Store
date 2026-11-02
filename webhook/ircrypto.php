<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Payments/PaymentFlow.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$rawBody = file_get_contents('php://input');
$signatureHeader = trim((string)($_SERVER['HTTP_IR_SIGNATURE'] ?? ''));
$parts = [];
foreach (explode(',', $signatureHeader) as $part) {
    $pair = explode('=', trim($part), 2);
    if (count($pair) === 2) {
        $parts[trim($pair[0])] = trim($pair[1]);
    }
}
$timestamp = $parts['t'] ?? '';
$signature = strtolower($parts['v1'] ?? '');
$secret = '';
try {
    $stmt = Database::connection()->prepare(
        "SELECT setting_value FROM app_settings WHERE setting_key = 'payment_ircrypto_webhook_secret' LIMIT 1"
    );
    $stmt->execute();
    $value = $stmt->fetchColumn();
    $secret = is_string($value) ? trim($value) : '';
} catch (Throwable $e) {
    error_log('IRCrypto webhook configuration lookup failed: ' . $e->getMessage());
}

if ($rawBody === false || $secret === '' || !ctype_digit($timestamp)
    || !preg_match('/^[a-f0-9]{64}$/D', $signature)
    || abs(time() - (int)$timestamp) > 300) {
    http_response_code(400);
    exit;
}
$expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
if (!hash_equals($expected, $signature)) {
    http_response_code(400);
    exit;
}

$event = json_decode($rawBody, true);
if (!is_array($event) || strtolower(trim((string)($event['type'] ?? $event['event'] ?? $_SERVER['HTTP_IR_EVENT_TYPE'] ?? ''))) !== 'invoice.paid') {
    http_response_code(204);
    exit;
}
$data = $event['data'] ?? [];
$invoiceId = is_array($data)
    ? trim((string)($data['id'] ?? $data['resource_id'] ?? $event['invoice_id'] ?? ''))
    : '';
if ($invoiceId === '') {
    http_response_code(400);
    exit;
}

try {
    // Signature authenticates delivery; PaymentFlow then fetches invoice status
    // from IRCrypto's API and checks stored amount, currency and order reference.
    PaymentFlow::handleCallback(Database::connection(), 'ircrypto', ['invoice_id' => $invoiceId]);
    http_response_code(204);
} catch (Throwable $e) {
    error_log('IRCrypto webhook processing failed: ' . $e->getMessage());
    http_response_code(500);
}
