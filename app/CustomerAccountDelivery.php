<?php
declare(strict_types=1);

require_once __DIR__ . '/Crypto.php';
require_once __DIR__ . '/EmailSender.php';
require_once __DIR__ . '/EmailTemplates.php';

/**
 * Send account details once provisioning for an order has reached a terminal
 * state. A durable unique delivery row prevents callback/attempt replays.
 */
final class CustomerAccountDelivery
{
    public static function afterProvisioning(PDO $pdo, int $orderId): void
    {
        if ($orderId < 1) return;

        $pdo->beginTransaction();
        try {
            $orderStmt = $pdo->prepare('SELECT id, public_id, buyer_name, buyer_email, username, password_ciphertext, payment_status FROM orders WHERE id=? FOR UPDATE');
            $orderStmt->execute([$orderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            if (!$order || $order['payment_status'] !== 'paid' || trim((string)$order['buyer_email']) === '') {
                $pdo->commit();
                return;
            }

            $attemptsStmt = $pdo->prepare(
                "SELECT a.status, a.server_id, COALESCE(NULLIF(TRIM(s.service_name),''), '') AS service_name
                 FROM provisioning_attempts a JOIN ibsng_servers s ON s.id=a.server_id
                 WHERE a.order_id=? AND a.operation='create' ORDER BY a.id"
            );
            $attemptsStmt->execute([$orderId]);
            $attempts = $attemptsStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$attempts || array_filter($attempts, static fn(array $a): bool => in_array($a['status'], ['queued','running'], true))) {
                $pdo->commit();
                return;
            }
            $successful = array_values(array_filter($attempts, static fn(array $a): bool => $a['status'] === 'succeeded'));
            if (!$successful) {
                $pdo->commit();
                return;
            }

            $claim = $pdo->prepare(
                "INSERT IGNORE INTO customer_email_deliveries (order_id,event_key,status,created_at,updated_at)
                 VALUES (?, 'account_delivery', 'sending', UTC_TIMESTAMP(), UTC_TIMESTAMP())"
            );
            $claim->execute([$orderId]);
            if ($claim->rowCount() !== 1) {
                $pdo->commit();
                return;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        try {
            $password = app_decrypt_secret((string)$order['password_ciphertext']);
            $siteStmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key='site_title' LIMIT 1");
            $siteStmt->execute();
            $siteName = (string)($siteStmt->fetchColumn() ?: 'ATD IBSng Store');
            $orderUrl = app_url('order-status.php?id=' . rawurlencode((string)$order['public_id']));
            $guideUrl = app_url('guide.php');
            $destinations = '';
            $destinationsText = '';
            foreach ($successful as $destination) {
                $name = trim((string)$destination['service_name']);
                if ($name === '') $name = 'سرویس فعال';
                $destinations .= '<li>' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
                $destinationsText .= '- ' . $name . "\n";
            }
            $detailsHtml = '<section><h3>اطلاعات اکانت</h3><p>نام کاربری: <strong>' . htmlspecialchars((string)$order['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong></p><p>رمز عبور: <strong>' . htmlspecialchars($password, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong></p><p>مقصدهای فعال:</p><ul>' . $destinations . '</ul></section>';
            $detailsText = "اطلاعات اکانت\nنام کاربری: " . $order['username'] . "\nرمز عبور: " . $password . "\nمقصدهای فعال:\n" . $destinationsText;
            $template = EmailTemplates::get($pdo, 'purchase_success');
            $vars = [
                'site_name' => $siteName,
                'customer_name' => trim((string)$order['buyer_name']) ?: 'مشتری',
                'order_id' => (string)$order['public_id'],
                'order_url' => $orderUrl,
                'guide_url' => $guideUrl,
                'account_details_html' => $detailsHtml,
                'account_details_text' => $detailsText,
            ];
            $sent = EmailSender::send(
                $pdo,
                (string)$order['buyer_email'],
                EmailTemplates::render($template['subject'], $vars),
                EmailTemplates::render($template['html'], $vars),
                EmailTemplates::render($template['text'], $vars)
            );
            $state = $sent ? 'sent' : 'failed';
            $error = $sent ? null : 'PHP mail() returned false';
        } catch (Throwable $e) {
            $state = 'failed';
            $error = substr($e->getMessage(), 0, 1000);
            error_log('Account delivery email failed for order ' . $orderId . ': ' . $error);
        }

        $finish = $pdo->prepare("UPDATE customer_email_deliveries SET status=?, attempts=attempts+1, last_error=?, sent_at=IF(?='sent',UTC_TIMESTAMP(),sent_at), updated_at=UTC_TIMESTAMP() WHERE order_id=? AND event_key='account_delivery'");
        $finish->execute([$state, $error, $state, $orderId]);
    }
}
