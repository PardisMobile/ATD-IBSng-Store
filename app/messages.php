<?php
declare(strict_types=1);

/**
 * Stable UI message catalog.
 *
 * Keep customer-facing copy here. IBSng responses, exception messages and
 * transport details must be translated to one of these keys by the caller;
 * never display raw remote HTML or exception text to customers.
 */
$catalog = [
    'fa' => [
        'username_required' => 'لطفاً نام کاربری را وارد کنید.',
        'username_taken' => 'این نام کاربری قبلاً در سامانه وجود دارد. نام دیگری انتخاب کنید.',
        'username_available_check_failed' => 'در حال حاضر امکان بررسی نام کاربری وجود ندارد. چند لحظه بعد دوباره تلاش کنید.',
        'ibsng_connection_failed' => 'ارتباط با سرور سرویس برقرار نشد. لطفاً بعداً دوباره تلاش کنید.',
        'ibsng_authentication_failed' => 'اتصال سرویس‌دهنده نیاز به بررسی تنظیمات دارد.',
        'ibsng_invalid_response' => 'پاسخ سرویس‌دهنده قابل تشخیص نبود. عملیات انجام نشد.',
        'order_created_pending_payment' => 'سفارش ثبت شد و در انتظار پرداخت است. هنوز حسابی در سرویس‌دهنده ایجاد نشده است.',
        'payment_not_confirmed' => 'پرداخت هنوز تأیید نشده است.',
        'payment_verification_failed' => 'تأیید پرداخت انجام نشد. در صورت کسر وجه، با پشتیبانی تماس بگیرید.',
        'provisioning_failed' => 'پرداخت ثبت شده است، اما فعال‌سازی سرویس کامل نشد. سفارش برای بررسی نگهداری شد.',
        'renewal_failed' => 'تمدید سرویس انجام نشد. وضعیت سفارش را بررسی کنید یا با پشتیبانی تماس بگیرید.',
        'order_recovery_email_required' => 'برای بازیابی سفارش، ایمیل ثبت‌شده را وارد کنید.',
        'order_recovery_email_unverified' => 'ایمیل واردشده تأیید نشده یا با سفارش مطابقت ندارد.',
        'order_not_found' => 'سفارشی با اطلاعات واردشده پیدا نشد.',
        'generic_error' => 'انجام عملیات ممکن نشد. لطفاً دوباره تلاش کنید.',
    ],
    'en' => [
        'username_required' => 'Please enter a username.',
        'username_taken' => 'This username already exists. Please choose another one.',
        'username_available_check_failed' => 'The username cannot be checked right now. Please try again shortly.',
        'ibsng_connection_failed' => 'The service server could not be reached. Please try again later.',
        'ibsng_authentication_failed' => 'The service connection settings need to be checked.',
        'ibsng_invalid_response' => 'The service returned an unreadable response. No operation was completed.',
        'order_created_pending_payment' => 'Your order was created and is awaiting payment. No service account has been created yet.',
        'payment_not_confirmed' => 'The payment has not been confirmed yet.',
        'payment_verification_failed' => 'Payment verification failed. Contact support if your account was charged.',
        'provisioning_failed' => 'Payment was recorded, but service activation did not complete. The order was retained for review.',
        'renewal_failed' => 'The service could not be renewed. Check the order status or contact support.',
        'order_recovery_email_required' => 'Enter the email address used for the order.',
        'order_recovery_email_unverified' => 'The email address is not verified or does not match the order.',
        'order_not_found' => 'No order matched the supplied information.',
        'generic_error' => 'The operation could not be completed. Please try again.',
    ],
];

$GLOBALS['app_message_catalog'] = $catalog;

/**
 * Resolve a message for a supported locale, with a safe English/Persian
 * fallback. Unknown keys never expose arbitrary exception text.
 */
if (!function_exists('app_message')) {
    function app_message(string $key, string $locale = 'fa'): string
    {
        static $catalog = null;
        if ($catalog === null) {
            $loaded = $GLOBALS['app_message_catalog'] ?? [];
            $catalog = is_array($loaded) ? $loaded : [];
        }

        $locale = isset($catalog[$locale]) ? $locale : 'fa';
        return (string)($catalog[$locale][$key] ?? $catalog['fa']['generic_error'] ?? 'انجام عملیات ممکن نشد.');
    }
}

return $catalog;
