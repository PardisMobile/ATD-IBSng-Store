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
            'html' => <<<'HTML'
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>تأیید ایمیل</title>
</head>
<body dir="rtl" style="margin:0;padding:0;background-color:#f3f5f9;color:#172033;font-family:Tahoma,Arial,sans-serif;direction:rtl;text-align:right;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background-color:#f3f5f9;border-collapse:collapse;">
<tr><td align="center" style="padding:28px 12px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:100%;max-width:600px;border-collapse:separate;border-spacing:0;background-color:#ffffff;border:1px solid #e5eaf2;border-radius:14px;overflow:hidden;">
<tr>
<td style="padding:22px 26px;background-color:#ffffff;border-bottom:1px solid #e8edf5;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
<tr>
<td align="right" style="font-size:23px;line-height:1.4;font-weight:700;letter-spacing:-.4px;color:#172033;">
<span style="color:#1769d2;">ATD</span> IBSng Store
</td>
<td align="left" style="font-size:11px;line-height:1.8;color:#718096;">فروشگاه اکانت‌های IBSng</td>
</tr>
</table>
</td>
</tr>
<tr>
<td align="center" style="padding:34px 28px 12px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0">
<tr><td align="center" style="width:54px;height:54px;border-radius:50%;background-color:#eaf2ff;color:#1769d2;font-size:27px;line-height:54px;font-weight:700;">✓</td></tr>
</table>
<h1 style="margin:18px 0 8px;font-size:23px;line-height:1.7;color:#172033;font-weight:700;">تأیید نشانی ایمیل</h1>
<p style="margin:0;font-size:14px;line-height:2;color:#64748b;">برای فعال‌سازی و اطمینان از صحت نشانی ایمیل، آن را تأیید کنید.</p>
</td>
</tr>
<tr>
<td style="padding:18px 28px 8px;">
<p style="margin:0 0 18px;font-size:15px;line-height:2;color:#334155;">سلام،</p>
<p style="margin:0 0 22px;font-size:14px;line-height:2;color:#475569;">برای تأیید ایمیل خود، روی دکمه زیر بزنید. این پیوند فقط برای تأیید نشانی ایمیل شماست.</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 22px;">
<tr><td align="center" bgcolor="#1769d2" style="border-radius:9px;">
<a href="{{verification_url}}" target="_blank" style="display:inline-block;padding:13px 34px;border:1px solid #1769d2;border-radius:9px;color:#ffffff;text-decoration:none;font-size:15px;line-height:1.5;font-weight:700;">تأیید ایمیل</a>
</td></tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f6f8fc;border:1px solid #e8edf5;border-radius:10px;">
<tr><td style="padding:14px 16px;font-size:12px;line-height:2;color:#64748b;">
<strong style="color:#334155;">مدت اعتبار پیوند:</strong> {{expires_in}}<br>
اگر دکمه بالا کار نکرد، نشانی زیر را در مرورگر خود باز کنید:<br>
<a href="{{verification_url}}" target="_blank" style="color:#1769d2;word-break:break-all;text-decoration:underline;">{{verification_url}}</a>
</td></tr>
</table>
<p style="margin:20px 0 0;font-size:12px;line-height:2;color:#8491a5;">اگر شما درخواست تأیید ایمیل نداده‌اید، این پیام را نادیده بگیرید.</p>
</td>
</tr>
<tr>
<td style="padding:22px 28px;background-color:#f8fafc;border-top:1px solid #e8edf5;text-align:center;">
<p style="margin:0 0 5px;font-size:13px;line-height:1.8;color:#475569;">با احترام، تیم پشتیبانی</p>
<p style="margin:0;font-size:13px;line-height:1.8;font-weight:700;color:#172033;">{{site_name}}</p>
<p style="margin:10px 0 0;font-size:11px;line-height:1.8;color:#94a3b8;">این ایمیل به‌صورت خودکار ارسال شده است؛ لطفاً به آن پاسخ ندهید.</p>
</td>
</tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML,
            'text' => "{{site_name}}\nتأیید نشانی ایمیل\nبرای تأیید ایمیل خود، پیوند زیر را باز کنید:\n{{verification_url}}\nمدت اعتبار پیوند: {{expires_in}}\nاگر شما درخواست تأیید ایمیل نداده‌اید، این پیام را نادیده بگیرید.",
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
            'html' => <<<'HTML'
<!doctype html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>Verify your email</title>
</head>
<body dir="ltr" style="margin:0;padding:0;background-color:#f3f5f9;color:#172033;font-family:Arial,sans-serif;direction:ltr;text-align:left;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background-color:#f3f5f9;border-collapse:collapse;">
<tr><td align="center" style="padding:28px 12px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:100%;max-width:600px;border-collapse:separate;border-spacing:0;background-color:#ffffff;border:1px solid #e5eaf2;border-radius:14px;overflow:hidden;">
<tr>
<td style="padding:22px 26px;background-color:#ffffff;border-bottom:1px solid #e8edf5;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
<tr>
<td align="left" style="font-size:23px;line-height:1.4;font-weight:700;letter-spacing:-.4px;color:#172033;">
<span style="color:#1769d2;">ATD</span> IBSng Store
</td>
<td align="right" style="font-size:11px;line-height:1.8;color:#718096;">IBSng Accounts Store</td>
</tr>
</table>
</td>
</tr>
<tr>
<td align="center" style="padding:34px 28px 12px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0">
<tr><td align="center" style="width:54px;height:54px;border-radius:50%;background-color:#eaf2ff;color:#1769d2;font-size:27px;line-height:54px;font-weight:700;">✓</td></tr>
</table>
<h1 style="margin:18px 0 8px;font-size:23px;line-height:1.7;color:#172033;font-weight:700;">Verify your email address</h1>
<p style="margin:0;font-size:14px;line-height:2;color:#64748b;">Confirm your email address to continue.</p>
</td>
</tr>
<tr>
<td style="padding:18px 28px 8px;">
<p style="margin:0 0 18px;font-size:15px;line-height:2;color:#334155;">Hello,</p>
<p style="margin:0 0 22px;font-size:14px;line-height:2;color:#475569;">Click the button below to verify your email address. This link is intended only for email verification.</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 22px;">
<tr><td align="center" bgcolor="#1769d2" style="border-radius:9px;">
<a href="{{verification_url}}" target="_blank" style="display:inline-block;padding:13px 34px;border:1px solid #1769d2;border-radius:9px;color:#ffffff;text-decoration:none;font-size:15px;line-height:1.5;font-weight:700;">Verify email</a>
</td></tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f6f8fc;border:1px solid #e8edf5;border-radius:10px;">
<tr><td style="padding:14px 16px;font-size:12px;line-height:2;color:#64748b;">
<strong style="color:#334155;">Link validity:</strong> {{expires_in}}<br>
If the button does not work, open this address in your browser:<br>
<a href="{{verification_url}}" target="_blank" style="color:#1769d2;word-break:break-all;text-decoration:underline;">{{verification_url}}</a>
</td></tr>
</table>
<p style="margin:20px 0 0;font-size:12px;line-height:2;color:#8491a5;">If you did not request this email verification, you can safely ignore this message.</p>
</td>
</tr>
<tr>
<td style="padding:22px 28px;background-color:#f8fafc;border-top:1px solid #e8edf5;text-align:center;">
<p style="margin:0 0 5px;font-size:13px;line-height:1.8;color:#475569;">Regards, Support Team</p>
<p style="margin:0;font-size:13px;line-height:1.8;font-weight:700;color:#172033;">{{site_name}}</p>
<p style="margin:10px 0 0;font-size:11px;line-height:1.8;color:#94a3b8;">This is an automated email. Please do not reply.</p>
</td>
</tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML,
            'text' => "{{site_name}}\nVerify your email address\nOpen the link below to verify your email:\n{{verification_url}}\nLink validity: {{expires_in}}\nIf you did not request this verification, ignore this message.",
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
