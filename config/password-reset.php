<?php

/*
 * "Forgot password" OTP mail. Delivered by an external HTTP mail API
 * (password_reset_otp_mail.php, same style as the Designer Daily Warning mail API).
 * Leave PASSWORD_RESET_MAIL_API_URL empty to send through Laravel's own mailer (MAIL_*) instead.
 */
return [
    'api_url' => env('PASSWORD_RESET_MAIL_API_URL'),
    // Same value as PR_API_TOKEN in the mail API file; sent as the X-API-Token header.
    'api_token' => env('PASSWORD_RESET_MAIL_API_TOKEN'),
    'api_timeout' => (int) env('PASSWORD_RESET_MAIL_API_TIMEOUT', 20),
    'mailtype' => 'password_reset_otp',

    'otp_length' => 6,
    'otp_ttl_minutes' => (int) env('PASSWORD_RESET_OTP_TTL', 10),
    // Wrong guesses allowed per OTP before it is discarded and a new one must be requested.
    'max_attempts' => 5,
    // A new OTP for the same account is not sent again within this many seconds.
    'resend_seconds' => 60,
];
