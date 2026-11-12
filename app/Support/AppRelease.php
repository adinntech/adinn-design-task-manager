<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

/**
 * The Android release the in-app update gate announces.
 *
 * Values come from the database (written by the release pipeline or `php artisan app:release`); anything not stored there falls
 * back to the .env values in config/app_update.php, so the old hand-edited .env still works until the first automated release.
 * The stored value is cached briefly because the public /api/v1/app-update feed is called by every phone on every launch.
 */
final class AppRelease
{
    public const KEY = 'app_release.android';

    /** The fields the stored release may override. */
    public const FIELDS = ['min_build', 'latest_build', 'latest_version', 'url', 'notes'];

    /** @return array<string, mixed> min_build, latest_build, latest_version, url, notes (+ download_enabled from config) */
    public static function current(): array
    {
        $config = (array) config('app_update.android');
        $stored = self::stored();

        return array_merge($config, array_intersect_key($stored, array_flip(self::FIELDS)));
    }

    /** @return array<string, mixed> everything stored, including bookkeeping such as announced_build and sha256 */
    public static function stored(): array
    {
        try {
            return Cache::remember(self::KEY, 300, fn () => (array) AppSetting::getValue(self::KEY, []));
        } catch (\Throwable) {
            return []; // no database (or no table yet): the .env values apply
        }
    }

    /** Merge values into the stored release. */
    public static function put(array $values): void
    {
        AppSetting::putValue(self::KEY, array_merge(self::stored(), $values));
        Cache::forget(self::KEY);
    }
}
