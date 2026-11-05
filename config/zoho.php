<?php

return [
    // Zoho People OAuth token used for the BD "Today Check-in Status" lookup.
    // Never exposed to the frontend — only consumed server-side by ZohoAttendanceService.
    // Zoho access tokens expire after ~1 hour: this value is only the starting
    // token — ZohoAttendanceService refreshes it itself (see the keys below).
    'access_token' => env('ZOHO_ACCESS_TOKEN'),

    // Used to mint a new access token when Zoho answers 401 (expired/invalid).
    'refresh_token' => env('ZOHO_REFRESH_TOKEN'),
    'client_id' => env('ZOHO_CLIENT_ID'),
    'client_secret' => env('ZOHO_CLIENT_SECRET'),
    'accounts_url' => env('ZOHO_ACCOUNTS_URL', 'https://accounts.zoho.com'),

    'attendance_url' => env('ZOHO_ATTENDANCE_URL', 'https://people.zoho.com/api/attendance/getUserAvailability'),
];
