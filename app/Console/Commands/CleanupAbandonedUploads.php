<?php

namespace App\Console\Commands;

use App\Models\FileUpload;
use App\Services\CloudMultipartUploadService;
use Illuminate\Console\Command;

/**
 * Sweeps large-file multipart uploads (see CloudMultipartUploadService)
 * that were left in "uploading" status with no activity for a while —
 * the user navigated away/closed the browser and never returned to
 * finish or explicitly restart. Aborts the S3/Spaces multipart session
 * (which discards any uploaded parts) so nothing lingers in the bucket.
 */
class CleanupAbandonedUploads extends Command
{
    protected $signature = 'uploads:cleanup-abandoned {--hours=24 : Age in hours since last activity before an in-progress upload is considered abandoned}';

    protected $description = 'Abort and clean up large-file uploads left incomplete for too long';

    public function handle(CloudMultipartUploadService $uploads): int
    {
        $cutoff = now()->subHours((int) $this->option('hours'));

        $abandoned = FileUpload::query()
            ->where('status', 'uploading')
            ->where('updated_at', '<', $cutoff)
            ->get();

        foreach ($abandoned as $upload) {
            $uploads->abort($upload);
        }

        $this->info("Cleaned up {$abandoned->count()} abandoned upload(s).");

        return self::SUCCESS;
    }
}
