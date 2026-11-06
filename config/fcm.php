<?php

/*
 * Mobile push (Firebase Cloud Messaging HTTP v1, see App\Services\FcmService).
 *
 * Set FCM_CREDENTIALS to the path of the Firebase service-account JSON (or FCM_CREDENTIALS_JSON to the
 * raw JSON, handy for container secrets). FCM_PROJECT_ID is optional — it defaults to the JSON's
 * project_id. With neither set, mobile push is simply off.
 */
return [
    'project_id' => env('FCM_PROJECT_ID'),
    'credentials' => env('FCM_CREDENTIALS'),
    'credentials_json' => env('FCM_CREDENTIALS_JSON'),
    'timeout' => 8,
];
