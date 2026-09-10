<?php

return [
    // Zoho People OAuth token used for the BD "Today Check-in Status" lookup.
    // Never exposed to the frontend — only consumed server-side by ZohoAttendanceService.
    'access_token' => env('ZOHO_ACCESS_TOKEN'),

    'attendance_url' => env('ZOHO_ATTENDANCE_URL', 'https://people.zoho.com/api/attendance/getUserAvailability'),
];
