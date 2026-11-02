<?php

/*
 * Browser desktop notifications (Web Push, see App\Services\WebPushService).
 *
 * Keys: run `php artisan webpush:vapid` once per environment — it writes
 * VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY into .env. Changing the keys later makes
 * every browser subscribe again (old subscriptions are dropped as they fail).
 */
return [
    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    'subject' => env('VAPID_SUBJECT', 'mailto:automation@adinn.co.in'),

    // Only these roles are offered desktop notifications.
    'roles' => ['designer', 'bd'],

    // Daily overdue reminder for designers (Asia/Kolkata).
    'overdue_at' => '11:00',

    // Statuses where the designer still owes work, so a passed due date is
    // worth a reminder (waiting_confirmation is with the BD; printing/completed
    // are past the design stage).
    'overdue_statuses' => ['assigned_tasks', 'review_analysis', 'need_clarification', 'yet_to_start', 'in_progress', 'rework'],

    // Seconds to wait for the browser push service (FCM / Mozilla / WNS).
    'timeout' => 8,
];
