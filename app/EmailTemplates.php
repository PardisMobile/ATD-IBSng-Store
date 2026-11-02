<?php
declare(strict_types=1);

/**
 * Customer email templates. Transport configuration is deliberately separate
 * from event content so template edits never alter payment/provisioning logic.
 */
final class EmailTemplates
{
    public const EVENTS = [
        'purchase_success' => 'خرید موفق',
        'renewal_success' => 'تمدید موفق',
        'order_recovery' => 'بازیابی سفارش / ارسال اطلاعات خرید',
        'email_verification' => 'تأیید ایمیل',
        'expiration_reminder' => 'یادآوری انقضا',
    ];

    private const DEFAULTS = [
        'purchase_success' => [
            'subject' => 'خرید شما در {{site_name}} ثبت شد',
            'html' => '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><body style="margin:0;background:#f3f5fa;font-family:Tahoma,Arial,sans-serif;color:#172033"><main style="max-width:600px;margin:24px auto;padding:28px;background:#fff;border-radius:16px"><h2>{{site_name}}</h2><p>سلام {{customer_name}}،</p><p>خرید شما با موفقیت ثبت شد.</p><p>شناسه سفارش: <strong>{{order_id}}</strong></p><p><a href="{{order_url}}">مشاهده سفارش</a></p></main></body></html>',
            'text' => "{{site_name}}\nسلام {{customer_name}}،\nخرید شما با موفقیت ثبت شد.\nشناسه سفارش: {{order_id}}\nمشاهده سفارش: {{order_url}}",
        ],
        'renewal_success' => [
            'subject' => 'تمدید سرویس شما در {{site_name}} موفق بود',
            'html' => '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><body style="margin:0;background:#f3f5fa;font-family:Tahoma,Arial,sans-serif;color:#172033"><main style="max-width:600px;margin:24px auto;padding:28px;background:#fff;border-radius:16px"><h2>{{site_name}}</h2><p>سلام {{customer_name}}،</p><p>تمدید سرویس با موفقیت انجام شد.</p><p>شناسه سفارش: <strong>{{order_id}}</strong></p><p><a href="{{order_url}}">جزئیات سفارش</a></p></main></body></html>',
            'text' => "{{site_name}}\nسلام {{customer_name}}،\nتمدید سرویس موفق بود.\nشناسه سفارش: {{order_id}}\nجزئیات: {{order_url}}",
        ],
        'order_recovery' => [
            'subject' => 'کد بازیابی سفارش‌های شما در {{site_name}}',
            'html' => '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><body style="margin:0;background:#f3f5fa;font-family:Tahoma,Arial,sans-serif;color:#172033"><main style="max-width:600px;margin:24px auto;padding:28px;background:#fff;border-radius:16px"><h2>{{site_name}}</h2><p>برای مشاهده سفارش‌های مرتبط با این ایمیل، کد زیر را در سایت وارد کنید:</p><p style="font-size:28px;letter-spacing:5px"><strong>{{verification_code}}</strong></p><p>این کد تا {{expires_in}} معتبر است. اگر این درخواست از طرف شما نبوده، این پیام را نادیده بگیرید.</p></main></body></html>',
            'text' => "{{site_name}}\nکد بازیابی: {{verification_code}}\nمدت اعتبار: {{expires_in}}\nاگر این درخواست از طرف شما نبوده، پیام را نادیده بگیرید.",
        ],
        'email_verification' => [
            'subject' => 'تأیید ایمیل در {{site_name}}',
            'html' => '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><body style="margin:0;background:#f3f5fa;font-family:Tahoma,Arial,sans-serif;color:#172033"><main style="max-width:600px;margin:24px auto;padding:28px;background:#fff;border-radius:16px"><h2>{{site_name}}</h2><p>برای تأیید ایمیل خود از پیوند زیر استفاده کنید:</p><p><a href="{{verification_url}}">تأیید ایمیل</a></p><p>این پیوند تا {{expires_in}} معتبر است.</p></main></body></html>',
            'text' => "{{site_name}}\nتأیید ایمیل: {{verification_url}}\nمدت اعتبار: {{expires_in}}",
        ],
        'expiration_reminder' => [
            'subject' => 'یادآوری انقضای سرویس در {{site_name}}',
            'html' => '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><body style="margin:0;background:#f3f5fa;font-family:Tahoma,Arial,sans-serif;color:#172033"><main style="max-width:600px;margin:24px auto;padding:28px;background:#fff;border-radius:16px"><h2>{{site_name}}</h2><p>سلام {{customer_name}}،</p><p>اعتبار سرویس شما در تاریخ {{expiration_date}} به پایان می‌رسد.</p><p><a href="{{service_url}}">مشاهده سرویس</a></p></main></body></html>',
            'text' => "{{site_name}}\nسلام {{customer_name}}،\nاعتبار سرویس در {{expiration_date}} پایان می‌یابد.\nمشاهده سرویس: {{service_url}}",
        ],
    ];

    public static function get(PDO $pdo, string $event): array
    {
        if (!isset(self::DEFAULTS[$event])) {
            throw new InvalidArgumentException('Unknown email template event.');
        }

        $result = self::DEFAULTS[$event];
        $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        foreach (['subject', 'html', 'text'] as $field) {
            $stmt->execute(['email_template_' . $event . '_' . $field]);
            $value = $stmt->fetchColumn();
            if (is_string($value) && $value !== '') {
                $result[$field] = $value;
            }
        }
        return $result;
    }

    public static function render(string $template, array $variables): string
    {
        $replace = [];
        foreach ($variables as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]*$/D', $key)) {
                continue;
            }
            $replace['{{' . $key . '}}'] = (string)$value;
        }
        return strtr($template, $replace);
    }

    public static function defaults(): array
    {
        return self::DEFAULTS;
    }
}
