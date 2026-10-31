<?php

/*
 * Admin → Task Monitoring → Daily Report → "Live Excel": keeps a Google
 * Sheet updated with the Daily Report numbers (one tab per IST day).
 *
 * Auth is a Google Cloud service account: put its JSON key at the path below
 * (default storage/app/google/service-account.json — git-ignored) and share
 * the Google Sheet with the service account's email as Editor. The sheet
 * itself is chosen from the Live Excel panel and stored in app_settings.
 */
return [
    'credentials' => env('GOOGLE_SHEETS_CREDENTIALS', storage_path('app/google/service-account.json')),
    'timeout' => (int) env('GOOGLE_SHEETS_TIMEOUT', 20),
];
