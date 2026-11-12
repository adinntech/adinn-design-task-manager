<?php

namespace App\Console\Commands;

use App\Services\AppUpdateAnnouncer;
use Illuminate\Console\Command;

/**
 * Tells every phone with the app that a new version is ready: a push "Update available" whose tap opens the app's
 * Update screen (the app re-checks /api/v1/app-update when it opens). The release pipeline does this by itself after
 * publishing; run it by hand to resend (see mobile/RELEASING.md).
 */
class AnnounceAppUpdate extends Command
{
    protected $signature = 'app:announce-update {--dry-run : Only count who would be notified}';

    protected $description = 'Send an "Update available" push to every mobile device (after publishing a new app release)';

    public function handle(AppUpdateAnnouncer $announcer): int
    {
        $result = $announcer->announce(dryRun: (bool) $this->option('dry-run'));

        if ($result['status'] === 'no-release') {
            $this->error('No release is published (APP_LATEST_BUILD is not set), so there is nothing to announce.');

            return self::FAILURE;
        }

        if ($result['status'] === 'no-fcm') {
            $this->error('FCM is not configured (FCM_CREDENTIALS), so no push can be sent.');

            return self::FAILURE;
        }

        $this->info("{$result['users']} user(s) with the app installed.");

        if ($result['status'] === 'sent') {
            $this->info("Push accepted for {$result['delivered']} device(s).");
        }

        return self::SUCCESS;
    }
}
