<?php

namespace App\Http\Controllers;

use App\Support\AppRelease;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "Get the app": a signed-in BD or Designer downloads the Android APK straight from here.
 *
 * The APK is the file published with `php artisan app:publish-apk <path>` (stored on the server, so it downloads
 * as a file with the right type). It is off until APP_DOWNLOAD_ENABLED=true. If none has been published but APP_APK_URL is set, the user is sent to that link
 * instead, so the button also works for an APK hosted elsewhere.
 */
class AppDownloadController extends Controller
{
    public const DISK_PATH = 'apk/adinn-design-tasks.apk';

    /** Whether there is anything to download (decides if the top-bar button is shown). */
    public static function available(): bool
    {
        return (bool) config('app_update.android.download_enabled')
            && (Storage::disk('local')->exists(self::DISK_PATH) || filled(AppRelease::current()['url'] ?? null));
    }

    public function android(): BinaryFileResponse|RedirectResponse
    {
        abort_unless((bool) config('app_update.android.download_enabled'), 404);

        $disk = Storage::disk('local');

        if ($disk->exists(self::DISK_PATH)) {
            $version = (string) (AppRelease::current()['latest_version'] ?? '');
            $name = 'adinn-design-tasks'.($version !== '' ? '-'.preg_replace('/[^0-9A-Za-z.\-]/', '', $version) : '').'.apk';

            return response()->download($disk->path(self::DISK_PATH), $name, [
                'Content-Type' => 'application/vnd.android.package-archive',
                'Cache-Control' => 'private, no-cache',
            ]);
        }

        $url = AppRelease::current()['url'] ?? null;
        abort_unless(filled($url), 404, 'The app has not been published yet.');

        return redirect()->away($url);
    }
}
