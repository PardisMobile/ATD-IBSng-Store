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
        'product_not_found' => 'پلن انتخاب‌شده فعال نیست یا سرور و گروه معتبری برای آن انتخاب نشده است.',
        'ibsng_server_not_found' => 'سرور فعال و متصل به این پلن پیدا نشد.',
        'order_pending_payment_notice' => 'این سفارش هنوز پرداخت نشده و هیچ اکانتی ایجاد نشده است. پرداخت و فعال‌سازی پس از برقراری ارتباط با درگاه معتبر تکمیل می‌شود.',
        'order_submitted_notice' => 'سفارش ثبت شد و در انتظار پرداخت است. هنوز هیچ اکانتی ایجاد نشده است.',
        'username_taken' => 'این نام کاربری قبلاً در سامانه وجود دارد. نام دیگری انتخاب کنید.',
        'username_available_check_failed' => 'در حال حاضر امکان بررسی نام کاربری وجود ندارد. چند لحظه بعد دوباره تلاش کنید.',
        'ibsng_connection_failed' => 'ارتباط با سرور سرویس برقرار نشد. لطفاً بعداً دوباره تلاش کنید.',
        'ibsng_authentication_failed' => 'اتصال سرویس‌دهنده نیاز به بررسی تنظیمات دارد.',
        'ibsng_invalid_response' => 'پاسخ سرویس‌دهنده قابل تشخیص نبود. عملیات انجام نشد.',
        'order_created_pending_payment' => 'سفارش ثبت شد و در انتظار پرداخت است. هنوز هیچ اکانتی ایجاد نشده است.',
        'payment_not_confirmed' => 'پرداخت هنوز تأیید نشده است.',
        'payment_details_heading' => 'مشخصات پرداخت',
        'payment_tracking_code' => 'کد رهگیری سفارش',
        'payment_gateway_reference' => 'شناسه پرداخت درگاه',
        'payment_tracking_hint' => 'لطفاً کد رهگیری را برای پیگیری‌های بعدی نزد خود نگه دارید.',
        'payment_verification_failed' => 'تأیید پرداخت انجام نشد. در صورت کسر وجه، با پشتیبانی تماس بگیرید.',
        'provisioning_failed' => 'پرداخت ثبت شده است، اما فعال‌سازی سرویس کامل نشد. سفارش برای بررسی نگهداری شد.',
        'renewal_failed' => 'تمدید سرویس انجام نشد. وضعیت سفارش را بررسی کنید یا با پشتیبانی تماس بگیرید.',
        'order_recovery_email_required' => 'برای بازیابی سفارش، ایمیل ثبت‌شده را وارد کنید.',
        'order_recovery_email_unverified' => 'ایمیل واردشده تأیید نشده یا با سفارش مطابقت ندارد.',
        'admin_forgot_generic' => 'اگر ایمیل متعلق به مدیر فعال باشد، لینک بازیابی برای آن ارسال می‌شود.',
        'admin_forgot_invalid_request' => 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.',
        'admin_forgot_email_invalid' => 'ایمیل معتبر وارد کنید.',
        'admin_reset_invalid_request' => 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.',
        'admin_reset_token_invalid' => 'لینک بازیابی نامعتبر یا منقضی شده است. دوباره درخواست دهید.',
        'admin_reset_password_length' => 'رمز جدید باید بین ۱۲ تا ۲۰۰ کاراکتر باشد.',
        'admin_reset_password_mismatch' => 'تکرار رمز با رمز جدید یکسان نیست.',
        'admin_reset_save_failed' => 'ذخیره رمز جدید انجام نشد. دوباره تلاش کنید.',
        'customer_account_captcha_invalid' => 'کد چهاررقمی صحیح نیست؛ دوباره تلاش کنید.',
        'customer_account_credentials_required' => 'نام کاربری و رمز عبور خود را وارد کنید.',
        'customer_account_servers_unavailable' => 'در حال حاضر امکان بررسی اکانت وجود ندارد.',
        'customer_account_not_found' => 'نام کاربری یا رمز عبور صحیح نیست.',
        'customer_account_invalid_request' => 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.',
        'customer_account_session_invalid' => 'نشست ورود معتبر نیست؛ دوباره وارد شوید.',
        'customer_account_password_fields_required' => 'تمام فیلدهای رمز عبور را تکمیل کنید.',
        'customer_account_password_mismatch' => 'رمز عبور جدید و تکرار آن یکسان نیستند.',
        'customer_account_password_too_long' => 'طول رمز عبور جدید بیش از حد مجاز است.',
        'customer_account_current_password_invalid' => 'رمز عبور فعلی صحیح نیست.',
        'customer_account_password_changed' => 'تغییر رمز عبور انجام شد. لطفاً با رمز جدید دوباره وارد شوید.',
        'customer_account_server_unavailable' => 'سرور مربوط به این اکانت در دسترس نیست.',
        'customer_account_lookup_failed' => 'بررسی حساب انجام نشد. لطفاً اطلاعات را بررسی و دوباره تلاش کنید.',
        'customer_account_password_change_failed' => 'تغییر رمز عبور انجام نشد. لطفاً دوباره تلاش کنید.',
        'customer_account_account_unavailable' => 'دریافت اطلاعات اکانت ممکن نیست. لطفاً دوباره وارد شوید.',
        'order_recovery_invalid_request' => 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.',
        'order_recovery_email_invalid' => 'ایمیل واردشده معتبر نیست.',
        'order_recovery_rate_limited' => 'درخواست‌های زیادی ثبت شده است. کمی بعد دوباره تلاش کنید.',
        'order_recovery_code_sent_generic' => 'اگر این ایمیل سفارش ثبت‌شده‌ای داشته باشد، کد تأیید برای آن ارسال می‌شود.',
        'order_recovery_code_invalid' => 'کد معتبر نیست یا منقضی شده است.',
        'order_recovery_email_verified' => 'ایمیل با موفقیت تأیید شد.',
        'order_recovery_logged_out' => 'از بخش سفارش‌های من خارج شدید.',
        'order_recovery_unavailable' => 'در حال حاضر امکان انجام درخواست وجود ندارد. لطفاً بعداً تلاش کنید.',
        'order_recovery_list_unavailable' => 'دریافت فهرست سفارش‌ها موقتاً ممکن نیست.',
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
        'ibsng_server_not_found' => 'No active server is mapped to this plan.',
        'order_pending_payment_notice' => 'This order has not been paid yet, and no account has been created. Payment and activation will be completed once a valid payment gateway is available.',
        'order_submitted_notice' => 'Your order was created and is awaiting payment. No account has been created yet.',
        'username_taken' => 'This username already exists. Please choose another one.',
        'username_available_check_failed' => 'The username cannot be checked right now. Please try again shortly.',
        'ibsng_connection_failed' => 'The service server could not be reached. Please try again later.',
        'ibsng_authentication_failed' => 'The service connection settings need to be checked.',
        'ibsng_invalid_response' => 'The service returned an unreadable response. No operation was completed.',
        'order_created_pending_payment' => 'Your order was created and is awaiting payment. No account has been created yet.',
        'payment_not_confirmed' => 'The payment has not been confirmed yet.',
        'payment_details_heading' => 'Payment details',
        'payment_tracking_code' => 'Order tracking code',
        'payment_gateway_reference' => 'Gateway payment reference',
        'payment_tracking_hint' => 'Please keep your tracking code for future reference.',
        'payment_verification_failed' => 'Payment verification failed. Contact support if your account was charged.',
        'provisioning_failed' => 'Payment was recorded, but service activation did not complete. The order was retained for review.',
        'renewal_failed' => 'The service could not be renewed. Check the order status or contact support.',
        'order_recovery_email_required' => 'Enter the email address used for the order.',
        'order_recovery_email_unverified' => 'The email address is not verified or does not match the order.',
        'admin_forgot_generic' => 'If this email belongs to an active administrator, a recovery link will be sent.',
        'admin_forgot_invalid_request' => 'Invalid request. Refresh the page and try again.',
        'admin_forgot_email_invalid' => 'Enter a valid email address.',
        'admin_reset_invalid_request' => 'Invalid request. Refresh the page and try again.',
        'admin_reset_token_invalid' => 'The recovery link is invalid or expired. Request a new one.',
        'admin_reset_password_length' => 'The new password must be 12–200 characters long.',
        'admin_reset_password_mismatch' => 'The confirmation does not match the new password.',
        'admin_reset_save_failed' => 'The new password could not be saved. Please try again.',
        'customer_account_captcha_invalid' => 'The four-digit code is incorrect. Please try again.',
        'customer_account_credentials_required' => 'Enter your username and password.',
        'customer_account_servers_unavailable' => 'Account lookup is currently unavailable.',
        'customer_account_not_found' => 'The username or password is incorrect.',
        'customer_account_invalid_request' => 'Invalid request. Refresh the page and try again.',
        'customer_account_session_invalid' => 'The account session is invalid. Please sign in again.',
        'customer_account_password_fields_required' => 'Complete all password fields.',
        'customer_account_password_mismatch' => 'The new password and confirmation do not match.',
        'customer_account_password_too_long' => 'The new password is too long.',
        'customer_account_current_password_invalid' => 'The current password is incorrect.',
        'customer_account_password_changed' => 'Your password has been changed. Please sign in again with your new password.',
        'customer_account_server_unavailable' => 'The server for this account is unavailable.',
        'customer_account_lookup_failed' => 'The account could not be verified. Check your details and try again.',
        'customer_account_password_change_failed' => 'The password could not be changed. Please try again.',
        'customer_account_account_unavailable' => 'Your account details are unavailable. Please sign in again.',
        'order_recovery_invalid_request' => 'Invalid request. Refresh the page and try again.',
        'order_recovery_email_invalid' => 'The email address is invalid.',
        'order_recovery_rate_limited' => 'Too many requests. Please try again shortly.',
        'order_recovery_code_sent_generic' => 'If this email has an order, a verification code will be sent.',
        'order_recovery_code_invalid' => 'The code is invalid or has expired.',
        'order_recovery_email_verified' => 'Email verified successfully.',
        'order_recovery_logged_out' => 'You have signed out of My Orders.',
        'order_recovery_unavailable' => 'The request cannot be completed right now. Please try again later.',
        'order_recovery_list_unavailable' => 'The order list is temporarily unavailable.',
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
    function app_message(string $key, ?string $locale = null): string
    {
        static $catalog = null;
        if ($catalog === null) {
            $loaded = $GLOBALS['app_message_catalog'] ?? [];
            $catalog = is_array($loaded) ? $loaded : [];
        }

        if ($locale === null) {
            $preferredLocale = (string)($_COOKIE['atd-ui-language'] ?? 'fa');
            $locale = in_array($preferredLocale, ['fa', 'en'], true) ? $preferredLocale : 'fa';
        }
        $locale = isset($catalog[$locale]) ? $locale : 'fa';
        return (string)($catalog[$locale][$key] ?? $catalog['fa']['generic_error'] ?? 'انجام عملیات ممکن نشد.');
    }
}

return $catalog;
