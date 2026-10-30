<?php
declare(strict_types=1);

/**
 * Minimal, independently implemented IBSng A1.24 Web Panel authentication
 * check. A successful login redirect is the test; it intentionally does not
 * load slow admin pages or group lists.
 */
final class IBSngClient
{
    private string $cookieFile;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        private readonly int $timeout = 15
    ) {
        if ($this->host === '' || $this->username === '' || $this->password === '') {
            throw new InvalidArgumentException('اطلاعات اتصال IBSng کامل نیست.');
        }
        if (!in_array($this->port, [80, 443], true)) {
            throw new InvalidArgumentException('پورت Web Panel در این نسخه باید 80 یا 443 باشد.');
        }
        if (str_contains($this->host, '://') || str_contains($this->host, '/') || str_contains($this->host, '?') || str_contains($this->host, '#')) {
            throw new InvalidArgumentException('Host را فقط به‌صورت دامنه یا IP وارد کنید؛ scheme و مسیر لازم نیست.');
        }

        $hostForUrl = $this->host;
        if (filter_var($hostForUrl, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $hostForUrl = '[' . $hostForUrl . ']';
        } elseif (!filter_var($hostForUrl, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && !preg_match('/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)(?:\.(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?))*$/', $hostForUrl)) {
            throw new InvalidArgumentException('فرمت Host معتبر نیست.');
        }

        $this->baseUrl = ($this->port === 443 ? 'https' : 'http') . '://' . $hostForUrl . ':' . $this->port . '/IBSng';
        $cookie = tempnam(sys_get_temp_dir(), 'atd_ibsng_');
        if ($cookie === false) {
            throw new RuntimeException('ساخت فضای موقت نشست IBSng انجام نشد.');
        }
        $this->cookieFile = $cookie;
    }

    private string $baseUrl;

    public function __destruct()
    {
        if (isset($this->cookieFile) && is_file($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function testConnection(): void
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('افزونه PHP cURL روی هاست فعال نیست.');
        }

        $url = $this->baseUrl . '/admin/';
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('راه‌اندازی ارتباط HTTP با IBSng ناموفق بود.');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT => min(8, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_USERAGENT => 'ATD-IBSng-Store/1.0',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml',
                'Cache-Control: no-cache',
                'Accept-Encoding: identity',
                'Connection: close',
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'username' => $this->username,
                'password' => $this->password,
            ], '', '&'),
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml',
                'Cache-Control: no-cache',
                'Accept-Encoding: identity',
                'Connection: close',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0,
        ]);

        $response = curl_exec($curl);
        $errno = curl_errno($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $headerSize = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            throw new RuntimeException($errno === CURLE_OPERATION_TIMEDOUT
                ? 'اتصال به IBSng timeout شد.'
                : 'ارتباط با IBSng برقرار نشد. Host، پورت و دسترسی شبکه را بررسی کنید.');
        }

        $headers = substr($response, 0, max(0, $headerSize));
        $body = substr($response, max(0, $headerSize));

        // IBSng A1.24 sends a redirect after successful admin authentication.
        if ($status >= 300 && $status < 400 && preg_match('/^Location\s*:/im', $headers)) {
            return;
        }

        $hasLoginForm = stripos($body, 'name="username"') !== false
            && stripos($body, 'name="password"') !== false;
        if ($hasLoginForm || $status === 200) {
            throw new RuntimeException('IBSng نام کاربری یا رمز عبور مدیر را نپذیرفت؛ اطلاعات ورود را بررسی کنید.');
        }

        throw new RuntimeException('پاسخ غیرمنتظره از Web Panel دریافت شد (HTTP ' . $status . ').');
    }
}
