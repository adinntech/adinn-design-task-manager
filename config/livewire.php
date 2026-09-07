<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Temporary File Upload Endpoint Configuration
    |---------------------------------------------------------------------------
    |
    | Overrides only this key from vendor/livewire/livewire/config/livewire.php
    | (merged shallowly via mergeConfigFrom, so every sub-key must be present
    | here or it reverts to Livewire's own default). Every other top-level
    | Livewire config key keeps using the package default.
    |
    | Raised to support up to 6 GB single-file uploads (Progress Update /
    | Rework ZIP, Task Creation reference files, client call recording) —
    | Livewire's own default `max:12288` (12 MB) preflight was silently
    | capping every upload well below the app's own validation rules.
    |
    */

    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'), // Default: 'default'
        'rules' => ['required', 'file', 'max:'.(6 * 1024 * 1024)], // 6 GB
        'directory' => null, // Default: 'livewire-tmp'
        'middleware' => null, // Default: 'throttle:60,1'
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 60, // minutes — was 5; large/slow uploads need longer before the temp file is invalidated
        'cleanup' => true,
    ],

];
