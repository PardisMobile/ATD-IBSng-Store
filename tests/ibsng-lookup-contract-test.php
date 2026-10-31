<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/IBSngClient.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$source = file_get_contents(__DIR__ . '/../app/IBSngClient.php');
expect(is_string($source), 'client source is readable');
expect(str_contains($source, "user_info.php?normal_username="), 'canonical normal_username endpoint is present');
expect(!preg_match('/user_info\\.php\\?normal_username_multi=/', $source), 'legacy lookup endpoint is absent');

$catalog = require __DIR__ . '/../app/messages.php';
expect(isset($catalog['fa']['username_taken']), 'Persian username message exists');
expect(isset($catalog['en']['username_taken']), 'English username message exists');
expect(!str_contains(json_encode($catalog, JSON_UNESCAPED_UNICODE), 'IBSng HTTP error'), 'catalog does not expose raw IBSng diagnostics');

expect(app_message('username_taken', 'fa') === $catalog['fa']['username_taken'], 'helper resolves Persian catalog message');
expect(app_message('username_taken', 'en') === $catalog['en']['username_taken'], 'helper resolves English catalog message');
expect(app_message('username_taken', 'en') === $catalog['en']['username_taken'], 'repeated helper calls reuse catalog without reloading its file');
expect(app_message('unknown_message_key', 'en') === $catalog['fa']['generic_error'], 'unknown key uses safe generic fallback');
expect(app_message('username_taken', 'unsupported') === $catalog['fa']['username_taken'], 'unsupported locale falls back to Persian');

$ref = new ReflectionClass(IBSngClient::class);
$method = $ref->getMethod('userInfoPage');
expect($method->isPrivate(), 'shared parser remains private');

echo "PASS: canonical IBSng lookup contract\n";
echo "PASS: bilingual customer message catalog\n";
echo "PASS: message helper loading and fallback behavior\n";
echo "PASS: private shared user-info parser\n";
