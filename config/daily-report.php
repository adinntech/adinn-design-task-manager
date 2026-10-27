<?php

/*
 * Admin → Daily Reporting: nightly "designers with no progress today" mail.
 * The mail is delivered by an external HTTP mail API (same contract style as
 * the Printing File mail API). Leave DAILY_REPORT_MAIL_API_URL empty until
 * that endpoint exists — runs are then recorded as failed, nothing is sent.
 */
return [
    'api_url' => env('DAILY_REPORT_MAIL_API_URL'),
    'api_timeout' => (int) env('DAILY_REPORT_MAIL_API_TIMEOUT', 30),
    'mailtype' => 'daily_designer_progress_report',

    // Report day boundaries and the schedule are always evaluated in this
    // timezone, independent of the server/app default.
    'timezone' => 'Asia/Kolkata',
    'send_at' => '23:59',

    // Total send attempts per report date (the 23:59 run + scheduled retries).
    'max_attempts' => (int) env('DAILY_REPORT_MAX_ATTEMPTS', 5),

    // A run stuck in "sending" longer than this is treated as crashed and may be retried.
    'stale_sending_minutes' => 15,
];
