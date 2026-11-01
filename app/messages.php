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
        'buyer_name_required' => 'نام خریدار الزامی است.',
        'buyer_phone_invalid' => 'شماره تماس معتبر وارد کنید.',
        'buyer_email_invalid' => 'ایمیل واردشده معتبر نیست.',
        'username_format_invalid' => 'نام کاربری باید از حروف لاتین، عدد و . _ - تشکیل شود.',
        'password_length_invalid' => 'رمز حساب باید حداقل ۸ کاراکتر باشد.',
        'checkout_session_invalid' => 'نشست فرم معتبر نیست. صفحه را دوباره باز کنید.',
        'product_unavailable' => 'این پلن دیگر برای فروش فعال نیست.',
        'product_not_found' => 'پلن انتخاب‌شده فعال نیست یا نگاشت سرور/گروه معتبر ندارد.',
        'ibsng_server_not_found' => 'سرور IBSng فعال و متصل به این پلن پیدا نشد.',
        'order_pending_payment_notice' => 'این سفارش هنوز پرداخت نشده و هیچ حسابی در IBSng ایجاد نشده است. مرحله پرداخت و فعال‌سازی پس از اتصال درگاه معتبر تکمیل می‌شود.',
        'order_submitted_notice' => 'سفارش ثبت شد و در انتظار پرداخت است. هنوز حسابی در سرویس‌دهنده ایجاد نشده است.',
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
        'admin_login_csrf_invalid' => 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.',
        'admin_login_throttled' => 'تلاش‌های ورود بیش از حد بوده است. ۱۵ دقیقه دیگر دوباره تلاش کنید.',
        'admin_login_invalid_credentials' => 'نام کاربری یا رمز عبور صحیح نیست.',
        'admin_login_unavailable' => 'ورود در حال حاضر ممکن نیست. وضعیت نصب و دیتابیس را بررسی کنید.',
        'generic_error' => 'انجام عملیات ممکن نشد. لطفاً دوباره تلاش کنید.',
    ],
    'en' => [
        'username_required' => 'Please enter a username.',
        'buyer_name_required' => 'Buyer name is required.',
        'buyer_phone_invalid' => 'Enter a valid phone number.',
        'buyer_email_invalid' => 'The email address is invalid.',
        'username_format_invalid' => 'Username may contain Latin letters, numbers, and . _ - only.',
        'password_length_invalid' => 'The account password must be at least 8 characters.',
        'checkout_session_invalid' => 'The form session is invalid. Please reopen the page.',
        'product_unavailable' => 'This plan is no longer available for sale.',
        'product_not_found' => 'The selected plan is inactive or has no valid server/group mapping.',
        'ibsng_server_not_found' => 'No active IBSng server mapped to this plan was found.',
        'order_pending_payment_notice' => 'This order is not paid yet, and no IBSng account has been created. Payment and activation will be completed after a valid gateway is connected.',
        'order_submitted_notice' => 'Your order was created and is awaiting payment. No service account has been created yet.',
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
        'admin_login_csrf_invalid' => 'Invalid request. Refresh the page and try again.',
        'admin_login_throttled' => 'Too many login attempts. Please try again in 15 minutes.',
        'admin_login_invalid_credentials' => 'The username or password is incorrect.',
        'admin_login_unavailable' => 'Login is currently unavailable. Check installation and database status.',
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
