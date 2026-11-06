<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Console\Command;

/**
 * Tells every phone with the app that a new version is ready: a push "Update available" whose tap opens the app's
 * Update screen (the app re-checks /api/v1/app-update when it opens). Run it after publishing the release values
 * in .env (see config/app_update.php and mobile/RELEASING.md).
 */
class AnnounceAppUpdate extends Command
{
    protected $signature = 'app:announce-update {--dry-run : Only count who would be notified}';

    protected $description = 'Send an "Update available" push to every mobile device (after publishing a new app release)';

    public function handle(FcmService $fcm): int
    {
        $release = config('app_update.android');
        $build = (int) ($release['latest_build'] ?? 0);

        if ($build <= 0) {
            $this->error('APP_LATEST_BUILD is not set, so there is no release to announce.');

            return self::FAILURE;
        }

        if (! $fcm->isConfigured()) {
            $this->error('FCM is not configured (FCM_CREDENTIALS), so no push can be sent.');

            return self::FAILURE;
        }

        $userIds = DeviceToken::query()->distinct()->pluck('user_id');
        $this->info("{$userIds->count()} user(s) with the app installed.");

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $version = $release['latest_version'] ? ' (v'.$release['latest_version'].')' : '';
        $delivered = 0;

        User::query()->whereIn('id', $userIds)->each(function (User $user) use ($fcm, $version, $build, &$delivered) {
            $delivered += $fcm->send(
                $user,
                'Update available',
                "A new version of Adinn Tasks{$version} is ready. Tap to update.",
                null,
                'app-update',
                ['type' => 'app_update', 'build' => $build],
            );
        });

        $this->info("Push accepted for {$delivered} device(s).");

        return self::SUCCESS;
    }
}
