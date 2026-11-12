<?php

namespace App\Jobs;

use App\Services\AppUpdateAnnouncer;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Sends the "Update available" push for the release that was just published. It is dispatched after the HTTP response (one
 * request per device is slow, and the pipeline should not wait for it); it does nothing if this build was already announced.
 */
class AnnounceAppUpdateJob
{
    use Dispatchable;

    public function handle(AppUpdateAnnouncer $announcer): void
    {
        $announcer->announce(onlyOnce: true);
    }
}
