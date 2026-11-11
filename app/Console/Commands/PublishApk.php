<?php

namespace App\Console\Commands;

use App\Http\Controllers\AppDownloadController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Puts a built APK on the server so the "Get the app" button (BD and Designer top bar) downloads it.
 * Run it after building: php artisan app:publish-apk mobile/build/app/outputs/flutter-apk/app-release.apk
 */
class PublishApk extends Command
{
    protected $signature = 'app:publish-apk {path : The built .apk file}';

    protected $description = 'Publish an Android APK for the "Get the app" download';

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("No such file: {$path}");

            return self::FAILURE;
        }

        // An APK is a zip with an AndroidManifest.xml; refuse anything else rather than publish a wrong file.
        $zip = new ZipArchive;
        if ($zip->open($path) !== true || $zip->locateName('AndroidManifest.xml') === false) {
            $this->error('That does not look like an APK (no AndroidManifest.xml inside).');

            return self::FAILURE;
        }
        $zip->close();

        $stream = fopen($path, 'rb');
        Storage::disk('local')->put(AppDownloadController::DISK_PATH, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $mb = number_format(filesize($path) / 1048576, 1);
        $this->info("Published ({$mb} MB, sha256 ".hash_file('sha256', $path).').');
        $this->line('Users now get it from "Get the app". For the in-app update screen also set APP_LATEST_BUILD / APP_LATEST_VERSION (see mobile/RELEASING.md).');

        return self::SUCCESS;
    }
}
