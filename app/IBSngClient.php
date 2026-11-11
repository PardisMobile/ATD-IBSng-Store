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
        private readonly int $timeout = 30
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

        $portSuffix = $this->port === 80 ? '' : ':' . $this->port;
        $this->baseUrl = 'http://' . $hostForUrl . $portSuffix . '/IBSng';
        $sessionKey = hash('sha256', strtolower($hostForUrl) . ':' . $this->port . '|' . $this->username . '|' . hash('sha256', $this->password));
        $this->cookieFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'atd_ibsng_' . $sessionKey . '.cookie';
        if (!is_file($this->cookieFile)) { if (@file_put_contents($this->cookieFile, '') === false) throw new RuntimeException('ساخت فضای موقت نشست IBSng انجام نشد.'); @chmod($this->cookieFile, 0600); }
        if (is_file($this->cookieFile) && filesize($this->cookieFile) > 0 && (time() - (int)filemtime($this->cookieFile)) > 900) { @file_put_contents($this->cookieFile, ''); @chmod($this->cookieFile, 0600); }
    }

    public function __destruct() { /* Retain isolated short-lived cookie jar for later PHP requests. */ }

    private function hasStoredSession(): bool
    {
        if (!is_file($this->cookieFile) || !is_readable($this->cookieFile)) return false;
        return preg_match('/(?:^|\\s)IBS_SESSID\\s+\\S+/m', (string)@file_get_contents($this->cookieFile)) === 1;
    }

    private function resetStoredSession(): void
    {
        @file_put_contents($this->cookieFile, ''); @chmod($this->cookieFile, 0600); $this->loggedIn = false;
    }

    public function testConnection(): void
    {
        // Match RouteBox's proven A1.24 handshake: POST /admin/ without
        // following the success redirect. The 3xx Location response is the
        // authentication signal; the redirected page can hang on legacy Apache.
        $this->login();
    }

    private function login(): void
    {
        if ($this->loggedIn) return;
        if ($this->hasStoredSession()) { $this->loggedIn = true; return; }
        [$body, $status, $headers] = $this->requestRaw('/admin/', [
            'username' => $this->username,
            'password' => $this->password,
        ], false);

        if ($status >= 300 && $status < 400 && preg_match('/^Location\\s*:/im', $headers)) {
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
    private function requestRaw(string $path, array $data = [], bool $followLocation = true): array
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
            CURLOPT_FOLLOWLOCATION => $followLocation,
            CURLOPT_MAXREDIRS => 5,
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
                if (preg_match('/^HTTP\/\d(?:\.\d)?\s+\d{3}/i', $line)) {
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
        $lock = @fopen($this->cookieFile . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) { if (is_resource($lock)) fclose($lock); curl_close($curl); throw new RuntimeException('قفل نشست IBSng در دسترس نیست.'); }
        $completed = curl_exec($curl); $errno = curl_errno($curl); $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
        flock($lock, LOCK_UN); fclose($lock); @chmod($this->cookieFile, 0600);

        if ($completed === false) {
            /*
             * Match the proven RouteBox A1.24 transport behavior:
             * the legacy Apache server may deliver the HTTP response and then
             * leave the connection open until cURL times out. In that case,
             * retain a received 2xx/3xx response body; a redirect with Location
             * is also a valid login response even when it has no body.
             */
            $hasRedirectLocation = $status >= 300 && $status < 400
                && preg_match('/^Location\\s*:/im', $headers) === 1;
            $hasHttpResponseBody = $body !== '';
            $completeTimeoutResponse = $errno === CURLE_OPERATION_TIMEDOUT
                && $status >= 200 && $status < 400
                && ($hasHttpResponseBody || $hasRedirectLocation);
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
        if ($status >= 400) throw new RuntimeException('Web Panel درخواست را نپذیرفت (HTTP ' . $status . ').');
        $loginForm = stripos($body, 'name="username"') !== false && stripos($body, 'name="password"') !== false;
        if ($data === [] && $loginForm) {
            $this->resetStoredSession(); $this->login(); [$body, $status] = $this->requestRaw($path, [], true);
            if ($status >= 400 || (stripos($body, 'name="username"') !== false && stripos($body, 'name="password"') !== false)) throw new RuntimeException('نشست مدیر IBSng پس از ورود مجدد معتبر نشد.');
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

    /** Update an existing IBSng account password through the native Edit User form. */
    public function setUserPassword(string $username, string $newPassword): void
    {
        $username = trim($username);
        if ($username === '' || $newPassword === '' || strlen($newPassword) > 128) {
            throw new InvalidArgumentException('نام کاربری یا رمز عبور جدید معتبر نیست.');
        }
        $user = $this->lookupUser($username);
        $uid = (string)$user['user_id'];
        $editPath = '/admin/plugins/edit.php?edit_user=1&user_id=' . rawurlencode($uid)
            . '&submit_form=1&add=1&count=1&owner_name=' . rawurlencode($this->username)
            . '&x=35&y=1&edit__normal_username=normal_username';
        $this->request($editPath);
        $saved = $this->request($editPath, [
            'target' => 'user',
            'target_id' => $uid,
            'update' => 1,
            'edit_tpl_cs' => 'normal_username',
            'attr_update_method_0' => 'normalAttrs',
            'has_normal_username' => 't',
            'current_normal_username' => $username,
            'normal_username' => $username,
            'password' => $newPassword,
            'normal_save_user_add' => 't',
        ]);
        // IBSng may save the password successfully while returning a
        // different redirect/body than the user-info marker. Verify the
        // persisted credential instead of treating that response as failure.
        $verified = $this->getCredentials($username);
        if (!hash_equals((string)($verified['password'] ?? ''), $newPassword)) {
            throw new RuntimeException('IBSng تغییر رمز را ذخیره نکرد؛ رمز قبلی همچنان فعال است.');
        }
    }

    public function createUser(string $groupName, string $username, string $password, string $credit = '1'): void
    {
        $groupName = trim($groupName);
        $username = trim($username);
        if ($groupName === '' || $username === '' || $password === '') {
            throw new InvalidArgumentException('نام دقیق گروه، نام کاربری و رمز عبور الزامی هستند.');
        }
        if ($this->findUserId($username) !== null) {
            throw new RuntimeException('این نام کاربری از قبل در IBSng وجود دارد.');
        }

        /*
         * Follow the verified RouteBox IBSng A1.24 creation flow:
         * create one account without following the 302 redirect, because
         * IBSng returns the new user ID in the Location response header.
         */
        $this->login();
        [$createBody, $createStatus, $createHeaders] = $this->requestRaw(
            '/admin/user/add_new_users.php',
            [
                'count' => 1,
                'credit' => $credit,
                'owner_name' => $this->username,
                'group_name' => $groupName,
                'credit_comment' => '',
            ],
            false
        );

        $uid = null;
        if (preg_match('/^Location\\s*:\\s*(.+)$/im', $createHeaders, $locationMatch)) {
            $location = trim($locationMatch[1]);
            if (preg_match('/[?&]user_id=([0-9]+)/i', $location, $idMatch)) {
                $uid = $idMatch[1];
            }
        }
        if ($uid === null && preg_match('/[?&]user_id=([0-9]+)/i', $createBody, $idMatch)) {
            $uid = $idMatch[1];
        }
        if ($uid === null) {
            throw new RuntimeException(
                'IBSng شناسه کاربر جدید را برنگرداند. HTTP ' . $createStatus
                . '؛ نتیجه ساخت باید در پنل IBSng بررسی شود.'
            );
        }

        /*
         * IBSng A1.24 requires opening the edit page before saving the
         * customer-selected normal username and password.
         */
        [, $editPageStatus] = $this->requestRaw(
            '/admin/plugins/edit.php?edit_user=1&user_id=' . rawurlencode($uid),
            [],
            false
        );
        if ($editPageStatus >= 400) {
            throw new RuntimeException('صفحه ویرایش کاربر جدید در IBSng باز نشد (HTTP ' . $editPageStatus . ').');
        }

        [$saved, $saveStatus] = $this->requestRaw(
            '/admin/plugins/edit.php',
            [
                'update' => '1',
                'edit_tpl_cs' => 'normal_username',
                'target' => 'user',
                'target_id' => $uid,
                'user_id' => $uid,
                'has_normal_username' => 't',
                'current_normal_username' => '',
                'normal_username' => $username,
                'password' => $password,
                'normal_save_user_add' => 't',
                'attr_update_method_0' => 'normalAttrs',
            ],
            false
        );

        if ($saveStatus >= 400) {
            throw new RuntimeException('IBSng ذخیره نام کاربری و رمز عبور را نپذیرفت (HTTP ' . $saveStatus . ').');
        }

        $responseText = trim(preg_replace(
            '/\\s+/u',
            ' ',
            html_entity_decode(strip_tags($saved), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        ) ?? '');
        if (
            stripos($responseText, 'normal username') !== false
            && stripos($responseText, 'error') !== false
        ) {
            throw new RuntimeException('IBSng تنظیم نام کاربری یا رمز عبور را نپذیرفت.');
        }
    }

    /** Change an existing account's group through the native IBSng A1.24 Edit User form. */
    public function changeUserGroup(string $username, string $groupName): void
    {
        $username = trim($username);
        $groupName = trim($groupName);
        if ($username === '' || $groupName === '') {
            throw new InvalidArgumentException('نام کاربری و گروه مقصد الزامی هستند.');
        }

        // Read the account first; IBSng's Edit User form is selected by user ID.
        $user = $this->lookupUser($username);
        $uid = (string)$user['user_id'];

        // IBSng first renders only the requested attribute editor when its
        // checkbox is posted. The live A1.24 form uses group_name here.
        $groupForm = $this->request('/admin/plugins/edit.php', [
            'user_id' => $uid,
            'edit_user' => '1',
            'attr_edit_checkbox_0' => 'group_name',
        ]);

        // Do not submit a group name that the server did not offer in its
        // actual group selector. This also catches login/error pages.
        if (!preg_match("/<select\\b[^>]*name=[\"']group_name[\"'][^>]*>(.*?)<\\/select>/is", $groupForm, $selectMatch)
            && !preg_match('/<select\\b[^>]*>(.*?)<\\/select>/is', $groupForm, $selectMatch)) {
            throw new RuntimeException('فرم انتخاب گروه از IBSng دریافت نشد.');
        }
        $optionsHtml = $selectMatch[1];
        $groupFound = false;
        if (preg_match_all('/<option\\b([^>]*)>(.*?)<\\/option>/is', $optionsHtml, $options, PREG_SET_ORDER)) {
            foreach ($options as $option) {
                $optionValue = null;
                if (preg_match("/\\bvalue=[\"']([^\"']*)[\"']/i", $option[1], $valueMatch)) {
                    $optionValue = html_entity_decode($valueMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                $optionLabel = trim(html_entity_decode(strip_tags($option[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($optionValue === $groupName || $optionLabel === $groupName) {
                    $groupFound = true;
                    break;
                }
            }
        }
        if (!$groupFound) {
            throw new RuntimeException('گروه مقصد در فهرست گروه‌های قابل انتخاب IBSng وجود ندارد: ' . $groupName);
        }

        // These field names and values are taken from the live A1.24 form:
        // target=user, update=1, edit_tpl_cs=group_name,
        // attr_update_method_0=groupName, and select name=group_name.
        $this->request('/admin/plugins/edit.php', [
            'target' => 'user',
            'target_id' => $uid,
            'update' => '1',
            'edit_tpl_cs' => 'group_name',
            'attr_update_method_0' => 'groupName',
            'group_name' => $groupName,
        ]);

        // Confirm the persisted group by reading User Information again.
        $verified = $this->lookupUser($username);
        if ((string)($verified['group'] ?? '') !== $groupName) {
            throw new RuntimeException('IBSng درخواست تغییر گروه را پذیرفت، اما گروه نهایی کاربر با گروه مقصد مطابقت ندارد.');
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

    /** Read the account's expiry state using the reference-aligned parser. */
    public function getRenewalState(string $username, ?string $utcOffset = null): array
    {
        require_once __DIR__ . '/IBSngRenewalState.php';
        return IBSngRenewalState::fromClient($this, $username, $utcOffset);
    }

    /**
     * Submit only the Relative Expiration and optional First Login reset
     * through the native A1.24 Edit User form. Absolute Expiration is never
     * included. This low-level operation must be called behind a durable ledger.
     */
    public function submitRenewalAttributes(
        string $userId,
        string $username,
        int $relativeExpiration,
        bool $resetFirstLogin,
        string $comment,
        string $groupName
    ): array {
        if (!ctype_digit($userId) || $username === '' || $relativeExpiration < 1 || trim($groupName) === '') {
            throw new InvalidArgumentException('اطلاعات تمدید IBSng معتبر نیست.');
        }
        $groupHtml = $this->request('/admin/group/group_info.php?group_name=' . rawurlencode(trim($groupName)));
        $groupId = null;
        if (preg_match("/name=[\\\"']group_id[\\\"'][^>]*value=[\\\"']([^\\\"']+)[\\\"']/i", $groupHtml, $m)
            || preg_match("/value=[\\\"']([^\\\"']+)[\\\"'][^>]*name=[\\\"']group_id[\\\"']/i", $groupHtml, $m)) {
            $groupId = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if ($groupId === null || $groupId === '' || !ctype_digit($groupId)) {
            throw new RuntimeException('شناسه گروه IBSng از Web Panel قابل تأیید نبود؛ تمدید متوقف شد.');
        }

        $this->request('/admin/plugins/edit.php?edit_user=1&user_id=' . rawurlencode($userId));
        $post = [
            'update' => '1',
            'target' => 'user',
            'target_id' => $userId,
            'user_id' => $userId,
            'group_id' => $groupId,
            'group_name' => trim($groupName),
            'owner_name' => $this->username,
            'comment' => $comment,
            'edit_tpl_cs' => $resetFirstLogin ? 'rel_exp,first_login' : 'rel_exp',
            'attr_update_method_0' => 'relExp',
            'has_rel_exp' => 't',
            'rel_exp' => (string)$relativeExpiration,
        ];
        if ($resetFirstLogin) {
            $post['attr_update_method_1'] = 'firstLogin';
            $post['reset_first_login'] = 't';
        }

        [$body, $status, $headers] = $this->requestRaw('/admin/plugins/edit.php', $post, false);
        $location = null;
        if ($status >= 300 && $status < 400
            && preg_match('/^Location\\s*:\\s*(.+)$/im', $headers, $locationMatch)) {
            $location = trim($locationMatch[1]);
        }
        $query = [];
        if ($location !== null) {
            parse_str((string)parse_url($location, PHP_URL_QUERY), $query);
        }
        if ($status < 300 || $status >= 400 || $location === null
            || stripos($location, 'user_info.php') === false
            || (string)($query['user_id'] ?? '') !== $userId) {
            throw new RuntimeException('IBSng تمدید را با پاسخ قابل‌تأیید تأیید نکرد؛ وضعیت عملیات باید بررسی شود.');
        }
        return [
            'updated' => true,
            'user_id' => $userId,
            'username' => $username,
            'relative_expiration' => $relativeExpiration,
            'first_login_reset' => $resetFirstLogin,
            'http_status' => $status,
        ];
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
