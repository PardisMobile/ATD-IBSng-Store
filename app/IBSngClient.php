<?php
declare(strict_types=1);

/**
 * IBSng Web Panel client. It uses the same login/session and real panel forms
 * as the referenced integrations. No group-list API is assumed.
 */
final class IBSngClient
{
    private string $cookieFile;
    private string $baseUrl;
    private bool $loggedIn = false;

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
        curl_close($curl);

        if ($response === false) {
            throw new RuntimeException($errno === CURLE_OPERATION_TIMEDOUT
                ? 'اتصال به IBSng timeout شد.'
                : 'ارتباط با IBSng برقرار نشد. Host، پورت و دسترسی شبکه را بررسی کنید.');
        }

        $headers = substr($response, 0, max(0, $headerSize));
        $body = substr($response, max(0, $headerSize));

        // Preserve the known-working Routebox authentication check.
        if ($status >= 300 && $status < 400 && preg_match('/^Location\\s*:/im', $headers)) {
            return;
        }

        $hasLoginForm = stripos($body, 'name="username"') !== false
            && stripos($body, 'name="password"') !== false;
        if ($hasLoginForm) {
            throw new RuntimeException('IBSng نام کاربری یا رمز عبور مدیر را نپذیرفت؛ اطلاعات ورود را بررسی کنید.');
        }

        // The reference RouteBox client treats a redirect after POST /admin/
        // as the successful A1.24 login signal. An arbitrary HTTP 200 is not
        // proof of bad credentials; it is an unexpected panel response.
        throw new RuntimeException('پاسخ غیرمنتظره از Web Panel دریافت شد (HTTP ' . $status . ').');
    }

    private function login(): void
    {
        if ($this->loggedIn) {
            return;
        }
        [$body, $status, $headers] = $this->requestRaw('/admin/', [
            'username' => $this->username,
            'password' => $this->password,
        ], true);

        if ($status >= 300 && $status < 400 && preg_match('/^Location\s*:/im', $headers)) {
            $this->loggedIn = true;
            return;
        }
        if (stripos($body, 'admin_index') !== false && stripos($body, 'name="password"') === false) {
            $this->loggedIn = true;
            return;
        }
        if (stripos($body, 'name="username"') !== false && stripos($body, 'name="password"') !== false) {
            throw new RuntimeException('IBSng نام کاربری یا رمز عبور مدیر را نپذیرفت؛ اطلاعات ورود را بررسی کنید.');
        }
        throw new RuntimeException('پاسخ غیرمنتظره از Web Panel دریافت شد (HTTP ' . $status . ').');
    }

    /**
     * @return array{0:string,1:int,2:string} body, HTTP status, response headers
     */
    private function requestRaw(string $path, array $data = [], bool $withHeaders = false): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('افزونه PHP cURL روی هاست فعال نیست.');
        }
        $url = $this->baseUrl . $path;
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('راه‌اندازی ارتباط HTTP با IBSng ناموفق بود.');
        }
        $headers = '';
        $body = '';
        $options = [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
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
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
                if (preg_match('/^HTTP\\/\\d(?:\\.\\d)?\\s+\\d{3}/i', $line)) {
                    $headers = $line;
                } else {
                    $headers .= $line;
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                $body .= $chunk;
                return strlen($chunk);
            },
        ];
        if ($data !== []) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($data, '', '&');
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
        }
        curl_setopt_array($curl, $options);
        $completed = curl_exec($curl);
        $errno = curl_errno($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($completed === false) {
            $contentLength = null;
            if (preg_match('/^Content-Length:\\s*(\\d+)\\s*$/im', $headers, $match)) {
                $contentLength = (int)$match[1];
            }
            $completeTimeoutResponse = $errno === CURLE_OPERATION_TIMEDOUT
                && $status >= 200 && $status < 400
                && $contentLength !== null
                && strlen($body) === $contentLength;
            if (!$completeTimeoutResponse) {
                throw new RuntimeException($errno === CURLE_OPERATION_TIMEDOUT
                    ? 'اتصال به IBSng timeout شد.'
                    : 'ارتباط با IBSng برقرار نشد. Host، پورت و دسترسی شبکه را بررسی کنید.');
            }
        }
        return [$body, $status, $headers];
    }

    private function request(string $path, array $data = []): string
    {
        $this->login();
        [$body, $status] = $this->requestRaw($path, $data);
        if ($status >= 400) {
            throw new RuntimeException('Web Panel درخواست را نپذیرفت (HTTP ' . $status . ').');
        }
        return $body;
    }

    /**
     * Check the customer-selected username using the proven A1.24 user-info
     * form. Returns true only when that page definitively reports no user;
     * malformed responses, login problems, and transport failures throw.
     */
    /**
     * Request the canonical A1.24 User Information page. All lookups use
     * normal_username so existence checks and
     * account reads share the exact same Web Panel response contract.
     */
    private function userInfoPage(string $username): array
    {
        $username = trim($username);
        if ($username === '') {
            throw new InvalidArgumentException('نام کاربری الزامی است.');
        }

        $html = $this->request('/admin/user/user_info.php?normal_username=' . rawurlencode($username));
        $loginForm = stripos($html, 'name="username"') !== false
            && stripos($html, 'name="password"') !== false;
        if ($loginForm) {
            throw new RuntimeException('نشست مدیر IBSng معتبر نیست.');
        }

        $userId = null;
        if (preg_match('/name="user_id"[^>]*value="([^"]+)"/i', $html, $match)
            || preg_match('/value="([^"]+)"[^>]*name="user_id"/i', $html, $match)) {
            $candidate = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($candidate !== '' && ctype_digit($candidate)) {
                $userId = $candidate;
            }
        }

        $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        $knownMissingUser = preg_match(
            '/(?:user\s+not\s+found|no\s+such\s+user|user\s+does\s+not\s+exist|does\s+not\s+exists|USER_NOT_FOUND)/i',
            $plain
        ) === 1;
        $hasUserInfoPage = stripos($plain, 'User Information') !== false
            || stripos($plain, 'User Informations') !== false;

        if ($userId !== null) {
            return ['exists' => true, 'user_id' => $userId, 'html' => $html, 'summary' => $plain];
        }
        if ($knownMissingUser || $hasUserInfoPage) {
            return ['exists' => false, 'user_id' => null, 'html' => $html, 'summary' => $plain];
        }

        throw new RuntimeException('پاسخ اطلاعات کاربر از IBSng قابل تشخیص نبود.');
    }

    /**
     * True only for a definitive user-not-found response. Transport,
     * authentication and parsing failures always throw.
     */
    public function isUsernameAvailable(string $username): bool
    {
        return !$this->userInfoPage($username)['exists'];
    }

    public function findUserId(string $username): ?string
    {
        $result = $this->userInfoPage($username);
        return $result['exists'] ? (string)$result['user_id'] : null;
    }

    public function lookupUser(string $username): array
    {
        $result = $this->userInfoPage($username);
        if (!$result['exists']) {
            throw new RuntimeException('این نام کاربری در IBSng وجود ندارد.');
        }

        $html = (string)$result['html'];
        $group = null;
        if (preg_match('/group_info\.php\?group_name=([^"&]+)/', $html, $match)) {
            $group = html_entity_decode(urldecode($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $text = (string)$result['summary'];
        return [
            'username' => trim($username),
            'user_id' => (string)$result['user_id'],
            'group' => $group,
            'html' => $html,
            'summary' => function_exists('mb_substr') ? mb_substr($text, 0, 1800, 'UTF-8') : substr($text, 0, 1800),
        ];
    }

    public function getCredentials(string $username): array
    {
        $user = $this->lookupUser($username);
        $html = $this->request('/admin/plugins/edit.php', [
            'user_id' => $user['user_id'],
            'edit_user' => 1,
            'attr_edit_checkbox_2' => 'normal_username',
        ]);
        if (!preg_match('/<input\b[^>]*name=["\']password["\'][^>]*value=["\']([^"\']*)["\']/i', $html, $match)
            && !preg_match('/<input\b[^>]*value=["\']([^"\']*)["\'][^>]*name=["\']password["\']/i', $html, $match)) {
            throw new RuntimeException('رمز این حساب از فرم IBSng قابل خواندن نبود.');
        }
        $user['password'] = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $user;
    }

    public function createUser(string $groupName, string $username, string $password, string $credit = '0'): void
    {
        $groupName = trim($groupName);
        $username = trim($username);
        if ($groupName === '' || $username === '' || $password === '') {
            throw new InvalidArgumentException('نام دقیق گروه، نام کاربری و رمز عبور الزامی هستند.');
        }
        if ($this->findUserId($username) !== null) {
            throw new RuntimeException('این نام کاربری از قبل در IBSng وجود دارد.');
        }

        $result = $this->request('/admin/user/add_new_users.php', [
            'submit_form' => 1,
            'add' => 1,
            'count' => 1,
            'credit' => $credit,
            'owner_name' => $this->username,
            'group_name' => $groupName,
            'edit__normal_username' => 1,
        ]);
        if (!preg_match('/user_id=(\d+)&su/', $result, $match)) {
            throw new RuntimeException('IBSng شناسه کاربر جدید را در پاسخ ایجاد حساب برنگرداند.');
        }
        $uid = $match[1];
        $editPath = '/admin/plugins/edit.php?edit_user=1&user_id=' . rawurlencode($uid)
            . '&submit_form=1&add=1&count=1&credit=1&owner_name=' . rawurlencode($this->username)
            . '&group_name=' . rawurlencode($groupName) . '&x=35&y=1&edit__normal_username=normal_username';
        $saved = $this->request($editPath, [
            'target' => 'user',
            'target_id' => $uid,
            'update' => 1,
            'edit_tpl_cs' => 'normal_username',
            'attr_update_method_0' => 'normalAttrs',
            'has_normal_username' => 't',
            'current_normal_username' => '',
            'normal_username' => $username,
            'password' => $password,
            'normal_save_user_add' => 't',
            'credit' => $credit,
        ]);
        if (stripos($saved, 'exist') !== false) {
            throw new RuntimeException('IBSng اعلام کرد نام کاربری تکراری است.');
        }
        if (stripos($saved, 'user_info.php?user_id_multi') === false) {
            throw new RuntimeException('پاسخ ذخیره حساب جدید از IBSng تأیید قابل تشخیص نداشت.');
        }
    }

    /** Change an existing account's group through the Mirza-style Edit User form. */
    public function changeUserGroup(string $username, string $groupName): void
    {
        $username = trim($username);
        $groupName = trim($groupName);
        if ($username === '' || $groupName === '') {
            throw new InvalidArgumentException('نام کاربری و گروه مقصد الزامی هستند.');
        }
        $user = $this->lookupUser($username);
        $uid = (string)$user['user_id'];
        $editPath = '/admin/plugins/edit.php?edit_user=1&user_id=' . rawurlencode($uid)
            . '&submit_form=1&add=1&count=1&credit=1&owner_name=' . rawurlencode($this->username)
            . '&group_name=' . rawurlencode($groupName) . '&x=35&y=1&edit__normal_username=normal_username';
        $saved = $this->request($editPath, [
            'target' => 'user', 'target_id' => $uid, 'update' => 1,
            'edit_tpl_cs' => 'normal_username', 'attr_update_method_0' => 'normalAttrs',
            'has_normal_username' => 't', 'current_normal_username' => $username,
            'normal_username' => $username, 'normal_save_user_add' => 't',
        ]);
        if (stripos($saved, 'user_info.php?user_id_multi') === false) {
            throw new RuntimeException('پاسخ ذخیره تغییر گروه از IBSng قابل تأیید نبود.');
        }
    }

    public function deleteUser(string $username, bool $deleteConnectionLogs = true, bool $deleteAuditLogs = true): void
    {
        $uid = $this->findUserId($username);
        if ($uid === null) {
            throw new RuntimeException('این نام کاربری در IBSng وجود ندارد.');
        }
        $data = [
            'user_id' => $uid,
            'delete' => 1,
            'delete_comment' => '',
        ];
        if ($deleteConnectionLogs) {
            $data['delete_connection_logs'] = 'on';
        }
        if ($deleteAuditLogs) {
            $data['delete_audit_logs'] = 'on';
        }
        $result = $this->request('/admin/user/del_user.php', $data);
        if (stripos($result, 'Successfully') === false) {
            throw new RuntimeException('IBSng حذف حساب را در پاسخ تأیید نکرد.');
        }
    }

    public function getGroupInfo(string $groupName): string
    {
        if (trim($groupName) === '') {
            throw new InvalidArgumentException('نام گروه الزامی است.');
        }
        $html = $this->request('/admin/group/group_info.php?group_name=' . rawurlencode(trim($groupName)));
        return trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
    }
}
