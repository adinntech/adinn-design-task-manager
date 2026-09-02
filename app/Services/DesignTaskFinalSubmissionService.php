<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskEodRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds a single consolidated final ZIP containing every normal progress
 * submission for a fully-completed task, and reuses it (idempotently) for all
 * subsequent final downloads.
 *
 * No new DB schema — the final ZIP path is stored on the task requirements JSON
 * under `_final_submission_path` (same convention as `_split_request_id`).
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

    /**
     * The task-level folder (without the "task-updation" sub-folder) under which
     * the final ZIP is stored: `final-submission-{task_id}_{task-name-slug}.zip`.
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
        ]);
    }

    public function filenameFor(DesignTask $task): string
    {
        return 'final-submission-'.$task->task_id.'_'.Str::slug($task->task_name).'.zip';
    }

    /**
     * All ordinary (non-rework) progress submission files, newest first, each
     * tagged with its 1-based submission number.
     */
    private function sourceFiles(DesignTask $task): array
    {
        $records = DesignTaskEodRecord::query()
            ->where('design_task_id', $task->id)
            ->where('update_type', 'progress')
            ->where('attachment_path', '!=', '')
            ->whereNotNull('attachment_path')
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        $files = [];

        foreach ($records as $index => $record) {
            $path = $record->attachment_path;
            if (! Storage::disk($this->disk)->exists($path)) {
                continue;
            }

            $name = $this->sanitizeName($record->attachment_original_name ?: basename($path));
            $files[] = [
                'source' => $path,
                'name' => 'Submission-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'/'.$name,
            ];
        }

        return $files;
    }

    /**
     * Idempotent. If a final ZIP already exists (verified on the disk) it is left
     * untouched and reused. Otherwise builds, uploads, verifies, then records the
     * path on the task requirements inside a transaction.
     *
     * Throws on failure BEFORE recording the path, so writer callers can roll back
     * their own transaction without leaving a broken final-file reference.
     */
    public function ensureFinalPackage(DesignTask $task): void
    {
        if ($this->finalZipExistsOnDbAndDisk($task)) {
            return;
        }

        $directory = $this->directoryFor($task);
        $filename = $this->filenameFor($task);
        $target = rtrim($directory, '/').'/'.$filename;

        if (Storage::disk($this->disk)->exists($target)) {
            $this->recordPath($task, $target);

            return;
        }

        $tempRoot = storage_path('app/private/final-submission-'.uniqid((string) $task->id, true));

        try {
            $sourceFiles = $this->sourceFiles($task);
            $zipPath = $this->buildZip($tempRoot, $sourceFiles);

            $stream = fopen($zipPath, 'rb');
            Storage::disk($this->disk)->put($target, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (! Storage::disk($this->disk)->exists($target)) {
                throw new \RuntimeException('Final submission ZIP failed to store.');
            }

            $this->recordPath($task, $target);
        } finally {
            $this->clearTemp($tempRoot);
        }
    }

    private function finalZipExistsOnDbAndDisk(DesignTask $task): bool
    {
        $path = $this->pathFor($task);

        return $path !== null && $path !== '' && Storage::disk($this->disk)->exists($path);
    }

    private function recordPath(DesignTask $task, string $path): void
    {
        $requirements = $task->requirements ?? [];
        $requirements['_final_submission_path'] = $path;

        DB::transaction(function () use ($task, $requirements): void {
            DesignTask::query()->whereKey($task->id)->update(['requirements' => $requirements]);
        });
    }

    private function buildZip(string $tempRoot, array $sourceFiles): string
    {
        @mkdir($tempRoot, 0777, true);
        $zipName = $tempRoot.'/final.zip';
        $archive = new \ZipArchive;

        if ($archive->open($zipName, \ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Unable to open final ZIP archive.');
        }

        try {
            $used = [];

            foreach ($sourceFiles as $file) {
                $content = Storage::disk($this->disk)->get($file['source']);
                $entry = $file['name'];

                $candidate = $entry;
                $i = 1;
                while (isset($used[$candidate])) {
                    $info = pathinfo($entry);
                    $candidate = $info['dirname'].'/'.$info['filename'].'-'.($i++).'.'.($info['extension'] ?? 'zip');
                }

                if (! $content) {
                    continue;
                }

                $archive->addFromString($candidate, $content);
                $used[$candidate] = true;
            }

            if ($archive->numFiles < 1) {
                throw new \RuntimeException('Final ZIP would contain no source files.');
            }
        } finally {
            $archive->close();
        }

        return $zipName;
    }

    private function sanitizeName(string $name): string
    {
        $name = trim(str_replace(['\\', '/'], '_', (string) $name));

        return $name === '' ? 'attachment.zip' : $name;
    }

    private function clearTemp(string $tempRoot): void
    {
        if (is_dir($tempRoot)) {
            array_map('unlink', glob($tempRoot.'/*') ?: []);
            @rmdir($tempRoot);
        }
    }
}
