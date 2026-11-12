<?php

namespace App\Services;

use App\Support\ApkInspector;
use App\Support\AppRelease;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

/**
 * Publishes an APK as the current Android release: stores it where phones can download it without signing in, records it
 * as the release (forced: min_build = build), and prunes old files. The caller announces it afterwards.
 */
class AppReleasePublisher
{
    /**
     * @return array<string, mixed> the stored release
     *
     * @throws ValidationException when the file is not an APK, its checksum differs, or the build is not newer than the current one
     */
    public function publish(UploadedFile $apk, int $build, string $version, ?string $notes, ?string $sha256 = null): array
    {
        $actualSha = hash_file('sha256', $apk->getRealPath());

        if ($sha256 !== null && ! hash_equals(strtolower($actualSha), strtolower($sha256))) {
            throw ValidationException::withMessages(['sha256' => 'The uploaded file does not match its checksum (damaged upload).']);
        }

        if (! ApkInspector::isApk($apk->getRealPath())) {
            throw ValidationException::withMessages(['apk' => 'That file is not an Android APK.']);
        }

        $current = (int) (AppRelease::current()['latest_build'] ?? 0);
        if ($build <= $current) {
            throw ValidationException::withMessages(['build' => "Build {$build} is not newer than the published build {$current}."]);
        }

        $safeVersion = preg_replace('/[^0-9A-Za-z.\-]/', '', $version);
        $name = "adinn-design-tasks-{$safeVersion}-build{$build}.apk";
        $dir = public_path('downloads');

        File::ensureDirectoryExists($dir);
        $apk->move($dir, $name);

        $release = [
            'build' => $build,
            'min_build' => $build, // every release is a forced update
            'latest_build' => $build,
            'latest_version' => $safeVersion,
            'url' => url('downloads/'.$name),
            'notes' => $notes,
            'sha256' => $actualSha,
            'published_at' => now()->toIso8601String(),
        ];

        AppRelease::put($release);
        $this->prune($name);

        return $release;
    }

    /** Keep the newest few APKs (the one just published always stays). */
    private function prune(string $keepName): void
    {
        $files = collect(File::glob(public_path('downloads/adinn-design-tasks-*-build*.apk')))
            ->sortByDesc(fn ($f) => (int) (preg_match('/-build(\d+)\.apk$/', $f, $m) ? $m[1] : 0))
            ->values();

        $files->slice(max(1, (int) config('app_update.keep_apks', 3)))
            ->reject(fn ($f) => basename($f) === $keepName)
            ->each(fn ($f) => File::delete($f));
    }
}
