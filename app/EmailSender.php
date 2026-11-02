<?php
declare(strict_types=1);

/**
 * Host-mail transport for customer email. Uses PHP mail() so shared cPanel
 * hosting does not require an external SMTP account or background worker.
 */
final class EmailSender
{
    public static function send(PDO $pdo, string $to, string $subject, string $html, string $text): bool
    {
        $to = trim($to);
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('نشانی ایمیل گیرنده معتبر نیست.');
        }

        $fromEmail = self::setting($pdo, 'email_from_address');
        $fromName = self::setting($pdo, 'email_from_name');
        if (filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('نشانی فرستنده ایمیل تنظیم نشده یا معتبر نیست.');
        }
        $fromName = trim(preg_replace('/[\\r\\n]+/', ' ', $fromName) ?? '');
        $subject = trim(preg_replace('/[\\r\\n]+/', ' ', $subject) ?? '');
        if ($subject === '') {
            throw new InvalidArgumentException('موضوع ایمیل خالی است.');
        }

        $boundary = 'atd_' . bin2hex(random_bytes(16));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedName = '=?UTF-8?B?' . base64_encode($fromName !== '' ? $fromName : $fromEmail) . '?=';
        $headers = [
            'From: ' . $encodedName . ' <' . $fromEmail . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: ATD IBSng Store',
        ];
        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text)) . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html)) . '--' . $boundary . "--\r\n";

        return mail($to, $encodedSubject, $body, implode("\r\n", $headers), '-f' . $fromEmail);
    }

    private static function setting(PDO $pdo, string $key): string
    {
        $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return is_string($value) ? $value : '';
    }
}
