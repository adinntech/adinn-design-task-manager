<?php

namespace App\Console\Commands;

use App\Services\LiveSheetSyncService;
use Illuminate\Console\Command;

/**
 * Admin → Task Monitoring → Daily Report → Live Excel: the nightly 23:59 IST
 * rewrite of all Live Excel tabs (scheduled in bootstrap/app.php).
 */
class SyncLiveSheetCommand extends Command
{
    protected $signature = 'reports:live-sheet-sync';

    protected $description = 'Rewrite all tabs of the Live Excel Google Sheet';

    public function handle(LiveSheetSyncService $live): int
    {
        if (! $live->active()) {
            $this->line('Live Excel is not connected — nothing to sync.');

            return self::SUCCESS;
        }

        if ($live->sync()) {
            $this->line('Live Excel: Daily, Weekly, Monthly, Yearly and AI Insights tabs updated.');

            return self::SUCCESS;
        }

        $this->error('Live Excel sync failed: '.($live->settings()['last_error'] ?? 'unknown error'));

        return self::FAILURE;
    }
}
