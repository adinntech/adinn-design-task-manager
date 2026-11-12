<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use App\Support\AppRelease;

/**
 * Tells every phone with the app that a new version is ready: a push "Update available" whose tap opens the app, which then
 * re-checks /api/v1/app-update and shows the update screen. Used by `php artisan app:announce-update` and by the release pipeline.
 */
class AppUpdateAnnouncer
{
    public function __construct(private readonly FcmService $fcm) {}

    /**
     * @param  bool  $dryRun  only count who would be told
     * @param  bool  $onlyOnce  skip when this build has already been announced (the pipeline); a person running the command may resend
     * @return array{status: string, users: int, delivered: int, build: int}  status: sent | dry-run | no-release | no-fcm | already-announced
     */
    public function announce(bool $dryRun = false, bool $onlyOnce = false): array
    {
        $release = AppRelease::current();
        $build = (int) ($release['latest_build'] ?? 0);

        if ($build <= 0) {
            return ['status' => 'no-release', 'users' => 0, 'delivered' => 0, 'build' => 0];
        }

        if (! $this->fcm->isConfigured()) {
            return ['status' => 'no-fcm', 'users' => 0, 'delivered' => 0, 'build' => $build];
        }

        if ($onlyOnce && (int) (AppRelease::stored()['announced_build'] ?? 0) >= $build) {
            return ['status' => 'already-announced', 'users' => 0, 'delivered' => 0, 'build' => $build];
        }

        $userIds = DeviceToken::query()->distinct()->pluck('user_id');

        if ($dryRun) {
            return ['status' => 'dry-run', 'users' => $userIds->count(), 'delivered' => 0, 'build' => $build];
        }

        $version = ! empty($release['latest_version']) ? ' (v'.$release['latest_version'].')' : '';
        $delivered = 0;

        User::query()->whereIn('id', $userIds)->each(function (User $user) use ($version, $build, &$delivered) {
            $delivered += $this->fcm->send(
                $user,
                'Update available',
                "A new version of Adinn Design Tasks{$version} is ready. Tap to update.",
                null,
                'app-update',
                ['type' => 'app_update', 'build' => $build],
            );
        });

        AppRelease::put(['announced_build' => $build]);

        return ['status' => 'sent', 'users' => $userIds->count(), 'delivered' => $delivered, 'build' => $build];
    }
}
