<?php
declare(strict_types=1);

/**
 * Customer email templates. Locale selection is content-only and does not
 * change the delivery transport or order/provisioning state machine.
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
            'html' => '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"></head><body dir="rtl" style="margin:0;background:#f3f5fa;font-family:Tahoma,Arial,sans-serif;color:#172033;direction:rtl;text-align:right"><main dir="rtl" style="direction:rtl;text-align:right;max-width:600px;margin:24px auto;padding:28px;background:#fff;border-radius:16px"><h2>{{site_name}}</h2><p>سلام {{customer_name}}،</p><p>خرید شما با موفقیت ثبت و اکانت آماده شده است.</p>{{account_details_html}}<p><a href="{{guide_url}}">راهنمای استفاده</a></p><p>شناسه سفارش: <strong dir="ltr">{{order_id}}</strong></p><p><a href="{{order_url}}">پیگیری سفارش</a></p></main></body></html>',
            'text' => "{{site_name}}\nسلام {{customer_name}}،\nاکانت شما آماده است.\n{{account_details_text}}\nراهنمای استفاده: {{guide_url}}\nشناسه سفارش: {{order_id}}\nپیگیری سفارش: {{order_url}}",
        ],
        'renewal_success' => [
            'subject' => 'تمدید سرویس شما در {{site_name}} موفق بود',
            'html' => '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"></head><body dir="rtl" style="margin:0;background:#f3f5fa;font-family:Tahoma,Arial,sans-serif;color:#172033;direction:rtl;text-align:right"><main dir="rtl" style="direction:rtl;text-align:right;max-width:600px;margin:24px auto;padding:28px;background:#fff;border-radius:16px"><h2>{{site_name}}</h2><p>سلام {{customer_name}}،</p><p>تمدید سرویس با موفقیت انجام شد.</p><p>شناسه سفارش: <strong dir="ltr">{{order_id}}</strong></p><p><a href="{{order_url}}">جزئیات سفارش</a></p></main></body></html>',
            'text' => "{{site_name}}\nسلام {{customer_name}}،\nتمدید سرویس موفق بود.\nشناسه سفارش: {{order_id}}\nجزئیات: {{order_url}}",
        ],
        'order_recovery' => [
            'subject' => 'کد بازیابی سفارش‌های شما در {{site_name}}',
            'html' => '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"></head><body dir="rtl" style="margin:0;background:#f3f5fa;font-family:Tahoma,Arial,sans-serif;color:#172033;direction:rtl;text-align:right"><main dir="rtl" style="direction:rtl;text-align:right;max-width:600px;margin:24px auto;padding:28px;background:#fff;border-radius:16px"><h2>{{site_name}}</h2><p>برای مشاهده سفارش‌های مرتبط با این ایمیل، کد زیر را در سایت وارد کنید:</p><p dir="ltr" style="direction:ltr;text-align:center;font-size:28px;letter-spacing:5px"><strong>{{verification_code}}</strong></p><p>این کد تا {{expires_in}} معتبر است. اگر این درخواست از طرف شما نبوده، این پیام را نادیده بگیرید.</p></main></body></html>',
            'text' => "{{site_name}}\nکد بازیابی: {{verification_code}}\nمدت اعتبار: {{expires_in}}\nاگر این درخواست از طرف شما نبوده، پیام را نادیده بگیرید.",
        ],
        'email_verification' => [
            'subject' => 'تأیید ایمیل در {{site_name}}',
            'html' => '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"></head><body dir="rtl" style="margin:0;background:#f3f5fa;font-family:Tahoma,Arial,sans-serif;color:#172033;direction:rtl;text-align:right"><main dir="rtl" style="direction:rtl;text-align:right;max-width:600px;margin:24px auto;padding:28px;background:#fff;border-radius:16px"><h2>{{site_name}}</h2><p>برای تأیید ایمیل خود از پیوند زیر استفاده کنید:</p><p><a href="{{verification_url}}">تأیید ایمیل</a></p><p>این پیوند تا {{expires_in}} معتبر است.</p></main></body></html>',
            'text' => "{{site_name}}\nتأیید ایمیل: {{verification_url}}\nمدت اعتبار: {{expires_in}}",
        ],
        'expiration_reminder' => [
            'subject' => 'یادآوری انقضای سرویس در {{site_name}}',
            'html' => '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"></head><body dir="rtl" style="margin:0;background:#f3f5fa;font-family:Tahoma,Arial,sans-serif;color:#172033;direction:rtl;text-align:right"><main dir="rtl" style="direction:rtl;text-align:right;max-width:600px;margin:24px auto;padding:28px;background:#fff;border-radius:16px"><h2>{{site_name}}</h2><p>سلام {{customer_name}}،</p><p>اعتبار سرویس شما در تاریخ {{expiration_date}} به پایان می‌رسد.</p><p><a href="{{service_url}}">مشاهده سرویس</a></p></main></body></html>',
            'text' => "{{site_name}}\nسلام {{customer_name}}،\nاعتبار سرویس در {{expiration_date}} پایان می‌یابد.\nمشاهده سرویس: {{service_url}}",
        ],
    ];

    private const DEFAULTS_EN = [
        'purchase_success' => [
            'subject' => 'Your purchase at {{site_name}} is ready',
            'html' => '<!doctype html><html lang="en" dir="ltr"><head><meta charset="utf-8"></head><body dir="ltr" style="margin:0;background:#f3f5fa;font-family:Arial,sans-serif;color:#172033;direction:ltr;text-align:left"><main dir="ltr" style="direction:ltr;text-align:left;max-width:600px;margin:24px auto;padding:28px;background:#fff;border-radius:16px"><h2>{{site_name}}</h2><p>Hello {{customer_name}},</p><p>Your purchase was successful and your account is ready.</p>{{account_details_html}}<p><a href="{{guide_url}}">Account setup guide</a></p><p>Order ID: <strong>{{order_id}}</strong></p><p><a href="{{order_url}}">Track your order</a></p></main></body></html>',
            'text' => "{{site_name}}\nHello {{customer_name}},\nYour account is ready.\n{{account_details_text}}\nAccount setup guide: {{guide_url}}\nOrder ID: {{order_id}}\nTrack your order: {{order_url}}",
        ],
        'renewal_success' => [
            'subject' => 'Your service renewal at {{site_name}} was successful',
            'html' => '<!doctype html><html lang="en" dir="ltr"><head><meta charset="utf-8"></head><body dir="ltr" style="margin:0;background:#f3f5fa;font-family:Arial,sans-serif;color:#172033;direction:ltr;text-align:left"><main dir="ltr" style="direction:ltr;text-align:left;max-width:600px;margin:24px auto;padding:28px;background:#fff;border-radius:16px"><h2>{{site_name}}</h2><p>Hello {{customer_name}},</p><p>Your service has been renewed successfully.</p><p>Order ID: <strong>{{order_id}}</strong></p><p><a href="{{order_url}}">Order details</a></p></main></body></html>',
            'text' => "{{site_name}}\nHello {{customer_name}},\nYour service renewal was successful.\nOrder ID: {{order_id}}\nDetails: {{order_url}}",
        ],
        'order_recovery' => [
            'subject' => 'Your order recovery code for {{site_name}}',
            'html' => '<!doctype html><html lang="en" dir="ltr"><head><meta charset="utf-8"></head><body dir="ltr" style="margin:0;background:#f3f5fa;font-family:Arial,sans-serif;color:#172033;direction:ltr;text-align:left"><main dir="ltr" style="direction:ltr;text-align:left;max-width:600px;margin:24px auto;padding:28px;background:#fff;border-radius:16px"><h2>{{site_name}}</h2><p>Enter the code below on the website to view orders associated with this email address:</p><p dir="ltr" style="direction:ltr;text-align:center;font-size:28px;letter-spacing:5px"><strong>{{verification_code}}</strong></p><p>This code is valid for {{expires_in}}. If you did not request it, you can ignore this email.</p></main></body></html>',
            'text' => "{{site_name}}\nOrder recovery code: {{verification_code}}\nValidity: {{expires_in}}\nIf you did not request this code, ignore this email.",
        ],
        'email_verification' => [
            'subject' => 'Verify your email at {{site_name}}',
            'html' => '<!doctype html><html lang="en" dir="ltr"><head><meta charset="utf-8"></head><body dir="ltr" style="margin:0;background:#f3f5fa;font-family:Arial,sans-serif;color:#172033;direction:ltr;text-align:left"><main dir="ltr" style="direction:ltr;text-align:left;max-width:600px;margin:24px auto;padding:28px;background:#fff;border-radius:16px"><h2>{{site_name}}</h2><p>Use the link below to verify your email address:</p><p><a href="{{verification_url}}">Verify email</a></p><p>This link is valid for {{expires_in}}.</p></main></body></html>',
            'text' => "{{site_name}}\nVerify your email: {{verification_url}}\nValidity: {{expires_in}}",
        ],
        'expiration_reminder' => [
            'subject' => 'Service expiration reminder from {{site_name}}',
            'html' => '<!doctype html><html lang="en" dir="ltr"><head><meta charset="utf-8"></head><body dir="ltr" style="margin:0;background:#f3f5fa;font-family:Arial,sans-serif;color:#172033;direction:ltr;text-align:left"><main dir="ltr" style="direction:ltr;text-align:left;max-width:600px;margin:24px auto;padding:28px;background:#fff;border-radius:16px"><h2>{{site_name}}</h2><p>Hello {{customer_name}},</p><p>Your service is scheduled to expire on {{expiration_date}}.</p><p><a href="{{service_url}}">View service</a></p></main></body></html>',
            'text' => "{{site_name}}\nHello {{customer_name}},\nYour service expires on {{expiration_date}}.\nView service: {{service_url}}",
        ],
    ];

    public static function get(PDO $pdo, string $event, string $language = 'fa'): array
    {
        if (!isset(self::DEFAULTS[$event])) {
            throw new InvalidArgumentException('Unknown email template event.');
        }
        $language = $language === 'en' ? 'en' : 'fa';
        $result = ($language === 'en' ? self::DEFAULTS_EN : self::DEFAULTS)[$event];
        $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        foreach (['subject', 'html', 'text'] as $field) {
            $key = $language === 'en'
                ? 'email_template_' . $event . '_en_' . $field
                : 'email_template_' . $event . '_' . $field;
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            if (is_string($value) && $value !== '') $result[$field] = $value;
        }
        $result['html'] = self::applyDocumentDirection($result['html'], $language);
        return $result;
    }

    private static function applyDocumentDirection(string $html, string $language): string
    {
        $direction = $language === 'en' ? 'ltr' : 'rtl';
        $alignment = $language === 'en' ? 'left' : 'right';
        return preg_replace_callback('/<(html|body|main)\\b([^>]*)>/i', static function (array $match) use ($language, $direction, $alignment): string {
            $tag = strtolower($match[1]);
            $attributes = $match[2];
            if (preg_match('~\\sdir\\s*=\\s*"([^"]*)"~i', $attributes)) {
                $attributes = preg_replace('~\\sdir\\s*=\\s*"([^"]*)"~i', ' dir="' . $direction . '"', $attributes, 1) ?? $attributes;
            } else {
                $attributes .= ' dir="' . $direction . '"';
            }
            if ($tag === 'html') {
                if (preg_match('~\\slang\\s*=\\s*"([^"]*)"~i', $attributes)) {
                    $attributes = preg_replace('~\\slang\\s*=\\s*"([^"]*)"~i', ' lang="' . $language . '"', $attributes, 1) ?? $attributes;
                } else {
                    $attributes .= ' lang="' . $language . '"';
                }
            }
            if ($tag !== 'html') {
                $css = 'direction:' . $direction . ';text-align:' . $alignment . ';';
                if (preg_match('~\\sstyle\\s*=\\s*"([^"]*)"~i', $attributes, $style)) {
                    $attributes = preg_replace('~\\sstyle\\s*=\\s*"([^"]*)"~i', ' style="' . htmlspecialchars(rtrim($style[1], ';') . ';' . $css, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"', $attributes, 1) ?? $attributes;
                } else {
                    $attributes .= ' style="' . $css . '"';
                }
            }
            return '<' . $tag . $attributes . '>';
        }, $html) ?? $html;
    }

    public static function render(string $template, array $variables): string
    {
        $replace = [];
        foreach ($variables as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]*$/D', $key)) continue;
            $replace['{{' . $key . '}}'] = (string)$value;
        }
        return strtr($template, $replace);
    }

    public static function defaults(): array
    {
        return self::DEFAULTS;
    }
}
