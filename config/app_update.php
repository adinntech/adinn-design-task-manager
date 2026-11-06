<?php

/*
 * Mobile in-app update gate (see Api\AppUpdateController).
 *
 * Builds are the Android versionCode (the number after "+" in mobile/pubspec.yaml, e.g. 1.0.0+7 => 7).
 *  - A phone whose build is BELOW min_build is blocked behind a full-screen "Update" screen.
 *  - A phone below latest_build (but at/above min_build) just sees a dismissible "update available" bar.
 *
 * To release: build the APK, upload it so APK_URL downloads it, then raise APP_LATEST_BUILD (and
 * APP_MIN_BUILD to force everyone). With APP_MIN_BUILD = 0 nothing is ever forced.
 */
return [
    'android' => [
        'min_build' => (int) env('APP_MIN_BUILD', 0),
        'latest_build' => (int) env('APP_LATEST_BUILD', 0),
        'latest_version' => env('APP_LATEST_VERSION'),
        'url' => env('APP_APK_URL'),
        'notes' => env('APP_UPDATE_NOTES'),
    ],
];
