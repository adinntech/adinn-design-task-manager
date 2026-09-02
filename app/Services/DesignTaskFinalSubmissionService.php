<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskEodRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Maintains ONE consolidated final ZIP for a task that lives inside the
 * `task-updation` folder:
 *
 *     task-updation/final-submission-{task_id}_{task-name-slug}.zip
 *
 * The ZIP is the source of truth for the latest full task package and is
 * rebuilt in place (same cloud key, overwritten, never duplicated) as new
 * progress or rework files arrive:
 *
 *     submission-01/...
 *     submission-02/...
 *     submission-03/...
 *     rework/rework-1/...
 *     rework/rework-2/...
 *
 * Rebuild is idempotent and safe:
 *   - rewrites via a temporary key then moves over the target (a failure leaves
 *     the previous valid ZIP intact),
 *   - deletes the standalone uploaded source objects ONLY after the rewrite has
 *     been uploaded and verified,
 *   - records the ZIP path + merged paths on the task requirements JSON (no new
 *     schema), mirroring the `_split_request_id` convention.
 */
class DesignTaskFinalSubmissionService
{
    private string $disk;

    public function __construct(?string $disk = null)
    {
        $this->disk = $disk ?? (string) config('filesystems.final_submission_disk', 'spaces');
    }

    public function pathFor(DesignTask $task): ?string
    {
        return $task->requirements['_final_submission_path'] ?? null;
    }

    public function targetPathFor(DesignTask $task): string
    {
        return rtrim($this->directoryFor($task), '/').'/'.$this->filenameFor($task);
    }

    /**
     * The task root + `task-updation` folder, matching the folder used for
     * individual progress submissions.
     */
    public function directoryFor(DesignTask $task): string
    {
        $root = trim((string) env('DO_SPACES_ROOT', 'design_task_manager'), '/');

        return implode('/', [
            $root,
            ($task->assigned_at ?? $task->created_at)?->format('Y') ?? now()->format('Y'),
            $task->vertical,
            $task->task_id.'_'.Str::slug($task->task_name),
            Str::slug($task->task_nature),
            'task-updation',
        ]);
    }

    public function filenameFor(DesignTask $task): string
    {
        return 'final-submission-'.$task->task_id.'_'.Str::slug($task->task_name).'.zip';
    }

    /**
     * Reconciles the task's single final ZIP to reflect the latest package.
     *
     * @throws \RuntimeException if the ZIP cannot be built or verified; on any
     *                           failure the previous valid ZIP and all standalone
     *                           source objects are left untouched.
     */
    public function ensureLatest(DesignTask $task): void
    {
        $target = $this->targetPathFor($task);
        $tempRoot = storage_path('app/private/final-submission-'.uniqid((string) $task->id, true));

        try {
            $sources = $this->pendingSources($task);

            // Nothing new and a valid ZIP already exists -> nothing to do.
            if ($sources === [] && Storage::disk($this->disk)->exists($target)) {
                $this->recordMeta($task, $target, []);

                return;
            }

            $this->buildAndReplace($task, $tempRoot, $target, $sources);

            // Success: the new sources are now reflected in the verified ZIP.
            $this->recordMeta($task, $target, $sources);
            $this->deleteSources($sources);
        } finally {
            $this->clearTemp($tempRoot);
        }
    }

    /**
     * Sources currently on the disk that still need merging (progress + rework).
     */
    private function pendingSources(DesignTask $task): array
    {
        $merged = $task->requirements['_final_merged_paths'] ?? [];
        $merged = is_array($merged) ? array_flip($merged) : [];

        $progress = DesignTaskEodRecord::query()
            ->where('design_task_id', $task->id)
            ->where('update_type', 'progress')
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        $rework = DesignTaskEodRecord::query()
            ->where('design_task_id', $task->id)
            ->where('update_type', 'rework')
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        $files = [];

        foreach ($progress->values() as $index => $record) {
            if (isset($merged[$record->attachment_path])) {
                continue;
            }
            $path = $record->attachment_path;
            if (! $path || ! Storage::disk($this->disk)->exists($path)) {
                continue;
            }
            $files[] = $this->fileEntry(
                $path,
                $record->attachment_original_name,
                'submission-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)
            );
        }

        // Chronological rework-submission numbering across all cycles. The offset
        // (count of already-merged rework files) keeps folders from colliding when
        // a later cycle is merged into the same `rework/` folder.
        $reworkPending = $rework->values()
            ->filter(fn ($r) => ! isset($merged[$r->attachment_path]))->values();
        $offset = $this->mergedReworkCount($task);

        foreach ($reworkPending as $i => $record) {
            $path = $record->attachment_path;
            if (! $path || ! Storage::disk($this->disk)->exists($path)) {
                continue;
            }
            $files[] = $this->fileEntry(
                $path,
                $record->attachment_original_name,
                'rework/rework-submission-'.str_pad((string) ($offset + $i + 1), 2, '0', STR_PAD_LEFT)
            );
        }

        return $files;
    }

    private function mergedReworkCount(DesignTask $task): int
    {
        $merged = $task->requirements['_final_merged_paths'] ?? [];

        if (! is_array($merged)) {
            return 0;
        }

        return DesignTaskEodRecord::query()
            ->where('design_task_id', $task->id)
            ->where('update_type', 'rework')
            ->whereIn('attachment_path', $merged)
            ->count();
    }

    private function fileEntry(string $source, ?string $originalName, string $folder): array
    {
        return [
            'source' => $source,
            'name' => $folder.'/'.$this->sanitizeName($originalName ?: basename($source)),
        ];
    }

    /**
     * Builds the ZIP from the existing final ZIP (if present) plus the new
     * standalone sources, then atomically replaces the target.
     */
    private function buildAndReplace(DesignTask $task, string $tempRoot, string $target, array $sources): void
    {
        $archiveDir = $tempRoot.'/package';
        @mkdir($archiveDir, 0777, true);

        if (Storage::disk($this->disk)->exists($target)) {
            $this->extractZipTo($target, $archiveDir);
        }

        $zipPath = $this->buildZip($archiveDir, $sources);
        $tempKey = $target.'.tmp-'.uniqid('', true);

        try {
            $stream = fopen($zipPath, 'rb');
            Storage::disk($this->disk)->put($tempKey, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (! Storage::disk($this->disk)->exists($tempKey)) {
                throw new \RuntimeException('Final submission ZIP failed to store.');
            }

            Storage::disk($this->disk)->move($tempKey, $target);

            if (! Storage::disk($this->disk)->exists($target)) {
                throw new \RuntimeException('Final submission ZIP failed to replace.');
            }
        } catch (\Throwable $e) {
            // Never leave the temporary key behind.
            if (Storage::disk($this->disk)->exists($tempKey)) {
                Storage::disk($this->disk)->delete($tempKey);
            }

            throw $e;
        }
    }

    private function extractZipTo(string $zipPath, string $targetDir): void
    {
        $local = $this->downloadToLocal($zipPath);

        $archive = new \ZipArchive;
        if ($archive->open($local) !== true) {
            throw new \RuntimeException('Unable to read existing final ZIP.');
        }

        @mkdir($targetDir, 0777, true);

        for ($i = 0; $i < $archive->numFiles; $i++) {
            $name = $archive->getNameIndex($i);
            if (substr($name, -1) === '/' || strpos($name, '..') !== false) {
                continue;
            }
            $out = $targetDir.'/'.$name;
            @mkdir(dirname($out), 0777, true);
            file_put_contents($out, $archive->getFromIndex($i));
        }

        $archive->close();
        @unlink($local);
    }

    private function buildZip(string $archiveDir, array $sources): string
    {
        $zipPath = dirname($archiveDir).'/final.zip';
        $archive = new \ZipArchive;

        if ($archive->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to open final ZIP archive.');
        }

        $this->addDirectoryToZip($archive, $archiveDir, $archiveDir);

        $used = [];

        foreach ($sources as $file) {
            $content = Storage::disk($this->disk)->get($file['source']);
            if ($content === null) {
                continue;
            }

            $entry = $file['name'];
            $candidate = $entry;
            $i = 1;
            while (in_array($candidate, $used, true) || $archive->locateName($candidate, \ZipArchive::FL_NOCASE) !== false) {
                $info = pathinfo($entry);
                $candidate = $info['dirname'].'/'.$info['filename'].'-'.($i++).'.'.($info['extension'] ?? 'zip');
            }

            $archive->addFromString($candidate, $content);
            $used[] = $candidate;
        }

        if ($archive->numFiles < 1) {
            $archive->close();
            throw new \RuntimeException('Final ZIP would contain no files.');
        }

        $archive->close();

        return $zipPath;
    }

    private function addDirectoryToZip(\ZipArchive $archive, string $dir, string $base): void
    {
        foreach (glob($dir.'/*', GLOB_NOSORT) ?: [] as $item) {
            if (is_dir($item)) {
                $this->addDirectoryToZip($archive, $item, $base);

                continue;
            }

            $relative = ltrim(substr($item, strlen($base)), '/\\');
            $archive->addFile($item, str_replace('\\', '/', $relative));
        }
    }

    private function downloadToLocal(string $path): string
    {
        $local = tempnam(sys_get_temp_dir(), 'final-src-');

        $stream = Storage::disk($this->disk)->readStream($path);
        if ($stream === null) {
            throw new \RuntimeException('Unable to read final ZIP source.');
        }

        file_put_contents($local, stream_get_contents($stream));
        fclose($stream);

        return $local;
    }

    private function recordMeta(DesignTask $task, string $path, array $sources): void
    {
        $requirements = $task->requirements ?? [];
        $requirements['_final_submission_path'] = $path;

        $merged = $requirements['_final_merged_paths'] ?? [];
        $merged = is_array($merged) ? $merged : [];

        foreach ($sources as $file) {
            if (! in_array($file['source'], $merged, true)) {
                $merged[] = $file['source'];
            }
        }

        $requirements['_final_merged_paths'] = $merged;

        DB::transaction(function () use ($task, $requirements): void {
            DesignTask::query()->whereKey($task->id)->update(['requirements' => $requirements]);
        });
    }

    private function deleteSources(array $sources): void
    {
        foreach ($sources as $file) {
            try {
                Storage::disk($this->disk)->delete($file['source']);
            } catch (\Throwable) {
                // Cleanup is best-effort; merged paths are recorded so download
                // resolution never depends on the standalone object surviving.
            }
        }
    }

    private function sanitizeName(string $name): string
    {
        $name = trim(str_replace(['\\', '/'], '_', (string) $name));

        return $name === '' ? 'attachment.zip' : $name;
    }

    private function clearTemp(string $tempRoot): void
    {
        if (! is_dir($tempRoot)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $fileInfo) {
            $fileInfo->isDir() ? @rmdir($fileInfo->getRealPath()) : @unlink($fileInfo->getRealPath());
        }

        @rmdir($tempRoot);
    }
}
