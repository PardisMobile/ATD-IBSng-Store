<?php
declare(strict_types=1);

require_once __DIR__ . '/Crypto.php';
require_once __DIR__ . '/EmailTemplates.php';
require_once __DIR__ . '/EmailSender.php';

final class OpenVpnManager
{
    private PDO $pdo;
    private const MAX_PROFILE_BYTES = 1048576;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public static function validateProfile(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", trim($content)) . "\n";
        if ($content === "\n" || strlen($content) > self::MAX_PROFILE_BYTES) {
            throw new InvalidArgumentException('فایل پروفایل خالی است یا حجم آن بیش از ۱ مگابایت است.');
        }
        if (preg_match('/[^\x09\x0A\x0D\x20-\x7E\x{0600}-\x{06FF}]/u', $content)) {
            throw new InvalidArgumentException('فایل پروفایل دارای نویسه‌های نامعتبر است.');
        }
        if (!preg_match('/^\s*client\s*$/mi', $content)
            || !preg_match('/^\s*(?:remote|<connection>)\b/im', $content)
            || !preg_match('/^\s*dev\s+\S+/mi', $content)) {
            throw new InvalidArgumentException('ساختار فایل معتبر OpenVPN نیست؛ client، remote و dev باید وجود داشته باشند.');
        }
        if (preg_match('/^\s*(?:script-security|up|down|plugin|config)\b/im', $content)) {
            throw new InvalidArgumentException('پروفایل شامل دستور اجرایی یا ارجاع config خارجی است و پذیرفته نمی‌شود.');
        }
        return $content;
    }

    public static function fetchProfileUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['fragment'])) {
            throw new InvalidArgumentException('نشانی پروفایل باید HTTPS و بدون اطلاعات ورود یا fragment باشد.');
        }

        $host = strtolower(rtrim((string)$parts['host'], '.'));
        $port = isset($parts['port']) ? (int)$parts['port'] : 443;
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('پورت نشانی پروفایل معتبر نیست.');
        }

        // Resolve and reject private/reserved destinations before opening the URL.
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            foreach (is_array($records) ? $records : [] as $record) {
                if (!empty($record['ip'])) $ips[] = $record['ip'];
                if (!empty($record['ipv6'])) $ips[] = $record['ipv6'];
            }
        }
        $ips = array_values(array_unique($ips));
        if (!$ips) throw new RuntimeException('نشانی سرور پروفایل قابل resolve نیست.');
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('دریافت پروفایل از نشانی‌های داخلی یا رزروشده مجاز نیست.');
            }
        }

        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('قابلیت دریافت URL در تنظیمات PHP هاست فعال نیست (allow_url_fopen).');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
                'protocol_version' => 1.1,
                'header' => "User-Agent: ATD-IBSng-Store OpenVPN Profile Fetcher\r\nAccept: text/plain, application/octet-stream;q=0.9, */*;q=0.1\r\nConnection: close\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
                'SNI_enabled' => true,
                'allow_self_signed' => false,
            ],
        ]);

        $stream = @fopen($url, 'rb', false, $context);
        if ($stream === false) {
            $last = error_get_last();
            $detail = is_array($last) && !empty($last['message']) ? $last['message'] : 'اتصال برقرار نشد.';
            throw new RuntimeException('دریافت فایل پروفایل ناموفق بود: ' . $detail);
        }

        try {
            $meta = stream_get_meta_data($stream);
            $headers = is_array($meta['wrapper_data'] ?? null) ? $meta['wrapper_data'] : [];
            $status = 0;
            foreach ($headers as $header) {
                if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', (string)$header, $match)) {
                    $status = (int)$match[1];
                }
            }
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException('دریافت فایل پروفایل ناموفق بود: HTTP ' . ($status ?: 'نامشخص'));
            }

            $body = stream_get_contents($stream, self::MAX_PROFILE_BYTES + 1);
            if (!is_string($body)) {
                throw new RuntimeException('خواندن فایل پروفایل از نشانی واردشده ناموفق بود.');
            }
            if (strlen($body) > self::MAX_PROFILE_BYTES) {
                throw new RuntimeException('حجم فایل پروفایل بیش از ۱ مگابایت است.');
            }
        } finally {
            fclose($stream);
        }

        return self::validateProfile($body);
    }

    public function profiles(bool $activeOnly = false): array
    {
        $sql = 'SELECT id, name, is_active, created_at, updated_at FROM openvpn_profiles' . ($activeOnly ? ' WHERE is_active=1' : '') . ' ORDER BY id DESC';
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveProfile(?int $id, string $name, string $content): int
    {
        $name = trim($name);
        if ($name === '' || (preg_match_all('/./us', $name, $nameChars) === false || count($nameChars[0]) > 120)) throw new InvalidArgumentException('نام پروفایل الزامی و حداکثر ۱۲۰ نویسه است.');
        if ($id && trim($content) === '') {
            $stmt = $this->pdo->prepare('UPDATE openvpn_profiles SET name=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$name, $id]);
            return $id;
        }
        $content = self::validateProfile($content);
        $cipher = app_encrypt_secret($content);
        if ($id) {
            $stmt = $this->pdo->prepare('UPDATE openvpn_profiles SET name=?, profile_ciphertext=?, profile_sha256=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$name, $cipher, hash('sha256', $content), $id]);
            if (!$stmt->rowCount()) throw new RuntimeException('پروفایل موردنظر پیدا نشد.');
            return $id;
        }
        $stmt = $this->pdo->prepare('INSERT INTO openvpn_profiles (name, profile_ciphertext, profile_sha256, is_active) VALUES (?,?,?,1)');
        $stmt->execute([$name, $cipher, hash('sha256', $content)]);
        return (int)$this->pdo->lastInsertId();
    }

    public function setProfileActive(int $id, bool $active): void
    {
        $stmt=$this->pdo->prepare('UPDATE openvpn_profiles SET is_active=? WHERE id=?');
        $stmt->execute([$active ? 1 : 0, $id]);
    }

    public function links(): array
    {
        return $this->pdo->query('SELECT l.id,l.link_type,l.order_id,l.recipient_email,l.is_active,l.expires_at,l.download_count,l.created_at,p.name AS profile_name FROM openvpn_links l JOIN openvpn_profiles p ON p.id=l.profile_id ORDER BY l.id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setLinkActive(int $id, bool $active): void
    {
        $stmt=$this->pdo->prepare('UPDATE openvpn_links SET is_active=? WHERE id=?');
        $stmt->execute([$active ? 1 : 0, $id]);
    }

    public function deleteProfile(int $id): void
    {
        $stmt=$this->pdo->prepare('DELETE FROM openvpn_profiles WHERE id=?');
        $stmt->execute([$id]);
    }

    public function profileContent(int $id): string
    {
        $stmt=$this->pdo->prepare('SELECT profile_ciphertext FROM openvpn_profiles WHERE id=? AND is_active=1');
        $stmt->execute([$id]); $value=$stmt->fetchColumn();
        if (!is_string($value)) throw new RuntimeException('پروفایل فعال پیدا نشد.');
        return self::validateProfile(app_decrypt_secret($value));
    }

    public function createLink(int $profileId, string $kind, ?int $orderId = null, string $email = ''): array
    {
        if (!in_array($kind, ['public','personalized'], true)) throw new InvalidArgumentException('نوع لینک معتبر نیست.');
        if ($kind === 'personalized') {
            if (!$orderId || filter_var($email, FILTER_VALIDATE_EMAIL) === false) throw new InvalidArgumentException('برای لینک اختصاصی، سفارش و ایمیل معتبر لازم است.');
            $check=$this->pdo->prepare("SELECT o.id,o.password_ciphertext FROM orders o WHERE o.id=? AND o.payment_status='paid' AND EXISTS (SELECT 1 FROM provisioning_attempts p WHERE p.order_id=o.id AND p.status='succeeded')");
            $check->execute([$orderId]);
            $eligible=$check->fetch(PDO::FETCH_ASSOC); if (!$eligible || !is_string($eligible['password_ciphertext']) || $eligible['password_ciphertext']==='') throw new RuntimeException('لینک اختصاصی فقط برای سفارش پرداخت‌شده، فعال‌سازی موفق و دارای رمز عبور ذخیره‌شده ساخته می‌شود.');
        }
        $profile=$this->pdo->prepare('SELECT id FROM openvpn_profiles WHERE id=? AND is_active=1');
        $profile->execute([$profileId]);
        if (!$profile->fetchColumn()) throw new RuntimeException('پروفایل انتخاب‌شده فعال نیست.');
        if ($kind === 'public') $orderId = null;
        $token=bin2hex(random_bytes(32));
        $stmt=$this->pdo->prepare('INSERT INTO openvpn_links (profile_id, order_id, link_type, recipient_email, token_hash, is_active, expires_at) VALUES (?,?,?,?,?,1,DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY))');
        $stmt->execute([$profileId,$orderId,$kind,$kind==='personalized'?$email:null,hash('sha256',$token)]);
        return ['url'=>app_absolute_url('openvpn-download.php?t='.$token), 'profile_url'=>$kind==='personalized' ? app_absolute_url('openvpn-profile.php?t='.$token) : app_absolute_url('openvpn-profile.php?p='.$profileId), 'public_url'=>app_absolute_url('openvpn-profile.php?p='.$profileId), 'token'=>$token, 'id'=>(int)$this->pdo->lastInsertId()];
    }

    public function resolveToken(string $token, bool $countAccess = true): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) throw new RuntimeException('پیوند معتبر نیست.');
        $stmt=$this->pdo->prepare('SELECT l.id,l.link_type,l.order_id,l.profile_id,p.name,p.profile_ciphertext FROM openvpn_links l JOIN openvpn_profiles p ON p.id=l.profile_id WHERE l.token_hash=? AND l.is_active=1 AND p.is_active=1 AND (l.expires_at IS NULL OR l.expires_at>UTC_TIMESTAMP()) LIMIT 1');
        $stmt->execute([hash('sha256',$token)]); $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('این پیوند منقضی یا غیرفعال شده است.');
        $username = '';
        $password = '';
        if ($row['link_type']==='personalized') {
            $check=$this->pdo->prepare("SELECT username,password_ciphertext FROM orders WHERE id=? AND payment_status='paid' LIMIT 1");
            $check->execute([(int)$row['order_id']]);
            $credentials=$check->fetch(PDO::FETCH_ASSOC);
            if (!$credentials || !is_string($credentials['password_ciphertext']) || $credentials['password_ciphertext']==='') throw new RuntimeException('اطلاعات ورود سفارش در دسترس نیست.');
            $username=(string)$credentials['username'];
            $password=app_decrypt_secret((string)$credentials['password_ciphertext']);
        }
        if ($countAccess) {
            $update=$this->pdo->prepare('UPDATE openvpn_links SET last_used_at=UTC_TIMESTAMP(), download_count=download_count+1 WHERE id=?');
            $update->execute([(int)$row['id']]);
        }
        return ['id'=>(int)$row['profile_id'],'name'=>(string)$row['name'],'content'=>self::validateProfile(app_decrypt_secret((string)$row['profile_ciphertext'])),'type'=>(string)$row['link_type'],'username'=>$username,'password'=>$password];
    }

    public function resolvePublicProfile(int $profileId): array
    {
        if ($profileId < 1) throw new RuntimeException('پروفایل عمومی معتبر نیست.');
        $stmt=$this->pdo->prepare('SELECT id,name,profile_ciphertext FROM openvpn_profiles WHERE id=? AND is_active=1 LIMIT 1');
        $stmt->execute([$profileId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('پروفایل عمومی فعال پیدا نشد.');
        return ['id'=>(int)$row['id'],'name'=>(string)$row['name'],'content'=>self::validateProfile(app_decrypt_secret((string)$row['profile_ciphertext'])),'type'=>'public','username'=>'','password'=>''];
    }

    public static function profileWithCredentials(string $content, string $username, string $password): string
    {
        $content=self::validateProfile($content);
        if ($username==='' || preg_match('/[^A-Za-z0-9_.@-]/', $username) || preg_match('/[<>\r\n\x00-\x1F\x7F]/', $password)) {
            throw new InvalidArgumentException('اطلاعات ورود برای ساخت پروفایل اختصاصی معتبر نیست.');
        }
        if (!preg_match('/^\s*auth-user-pass\s*$/mi', $content)) {
            throw new RuntimeException('پروفایل باید دقیقاً از دستور auth-user-pass بدون فایل جانبی استفاده کند.');
        }
        $content=preg_replace('/^\s*auth-user-pass\s*$/mi', "<auth-user-pass>\n".$username."\n".$password."\n</auth-user-pass>", $content, 1, $count);
        if ($count!==1) throw new RuntimeException('افزودن اطلاعات ورود به پروفایل ناموفق بود.');
        return self::validateProfile($content);
    }
 }
