<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\FileUpload;
use App\Models\User;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Direct-to-cloud multipart upload for large (up to 6 GB) ZIP submissions —
 * bytes go straight from the browser to the `spaces` disk via presigned
 * per-part URLs, never through PHP. Files land in a staging key first;
 * promoteToFinalPath() does a bucket-internal S3 CopyObject (not a PHP
 * re-upload) to the same human-readable path convention the rest of the
 * app already uses, once the owning workflow (Progress Update / Rework)
 * actually submits.
 */
class CloudMultipartUploadService
{
    public const PURPOSES = ['progress_update', 'rework'];

    public const MAX_SIZE_BYTES = 6 * 1024 * 1024 * 1024; // 6 GB — matches the app-wide cap

    public const PART_SIZE_BYTES = 64 * 1024 * 1024; // 64 MB (S3 minimum is 5 MB)

    private const DISK = 'spaces';

    private const STAGING_PREFIX = '_uploads-staging';

    public function initiate(User $user, DesignTask $task, string $purpose, string $filename, int $sizeBytes): FileUpload
    {
        if (! in_array($purpose, self::PURPOSES, true)) {
            throw ValidationException::withMessages(['purpose' => 'Invalid upload purpose.']);
        }

        if ($sizeBytes < 1 || $sizeBytes > self::MAX_SIZE_BYTES) {
            throw ValidationException::withMessages(['file' => 'Maximum file size is 6 GB.']);
        }

        if (strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) !== 'zip') {
            throw ValidationException::withMessages(['file' => 'Only ZIP files are accepted.']);
        }

        $existing = FileUpload::query()
            ->where('user_id', $user->id)
            ->where('design_task_id', $task->id)
            ->where('purpose', $purpose)
            ->where('status', 'uploading')
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($existing) {
            // Same file re-selected (e.g. after navigating away or reopening
            // the browser) — hand back the same session so already-uploaded
            // parts can be skipped instead of re-sent.
            if ($existing->original_filename === $filename && (int) $existing->size_bytes === $sizeBytes) {
                return $existing;
            }

            // A different file for the same task+purpose: the old session is
            // now orphaned. Abort it immediately rather than waiting for the
            // scheduled sweep, so no abandoned parts linger unnecessarily.
            $this->abort($existing);
        }

        $uuid = (string) Str::uuid();
        $key = self::STAGING_PREFIX.'/'.$purpose.'/'.$uuid.'.zip';

        $result = $this->client()->createMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'ACL' => 'public-read',
        ]);

        return FileUpload::create([
            'uuid' => $uuid,
            'user_id' => $user->id,
            'design_task_id' => $task->id,
            'purpose' => $purpose,
            'original_filename' => $filename,
            'size_bytes' => $sizeBytes,
            'cloud_disk' => self::DISK,
            'cloud_key' => $key,
            'multipart_upload_id' => $result['UploadId'],
            'part_size_bytes' => self::PART_SIZE_BYTES,
            'status' => 'uploading',
        ]);
    }

    public function partUrl(FileUpload $upload, int $partNumber): string
    {
        $command = $this->client()->getCommand('UploadPart', [
            'Bucket' => $this->bucket(),
            'Key' => $upload->cloud_key,
            'UploadId' => $upload->multipart_upload_id,
            'PartNumber' => $partNumber,
        ]);

        return (string) $this->client()->createPresignedRequest($command, '+2 hours')->getUri();
    }

    /** @return array<int, array{etag: string, size: int}> keyed by part number, ascending */
    public function listedParts(FileUpload $upload): array
    {
        $parts = [];
        $marker = null;

        do {
            $args = [
                'Bucket' => $this->bucket(),
                'Key' => $upload->cloud_key,
                'UploadId' => $upload->multipart_upload_id,
            ];

            if ($marker !== null) {
                $args['PartNumberMarker'] = $marker;
            }

            $result = $this->client()->listParts($args);

            foreach ($result['Parts'] ?? [] as $part) {
                $parts[(int) $part['PartNumber']] = ['etag' => $part['ETag'], 'size' => (int) $part['Size']];
            }

            $marker = ($result['IsTruncated'] ?? false) ? $result['NextPartNumberMarker'] : null;
        } while ($marker);

        return $parts;
    }

    public function complete(FileUpload $upload): FileUpload
    {
        $parts = $this->listedParts($upload);

        if ($parts === []) {
            throw ValidationException::withMessages(['file' => 'No uploaded data found — the upload may have been abandoned. Please start again.']);
        }

        $uploadedBytes = array_sum(array_column($parts, 'size'));

        if ($uploadedBytes !== (int) $upload->size_bytes) {
            $upload->update(['uploaded_bytes' => $uploadedBytes]);

            throw ValidationException::withMessages(['file' => 'The upload is incomplete. Please finish uploading before submitting.']);
        }

        $this->client()->completeMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $upload->cloud_key,
            'UploadId' => $upload->multipart_upload_id,
            'MultipartUpload' => [
                'Parts' => collect($parts)->map(fn ($part, $number) => ['ETag' => $part['etag'], 'PartNumber' => $number])->values()->all(),
            ],
        ]);

        if (! $this->looksLikeZip($upload)) {
            $this->deleteObject($upload->cloud_key);
            $upload->update(['status' => 'failed']);

            throw ValidationException::withMessages(['file' => 'The uploaded file is not a valid ZIP archive.']);
        }

        $upload->update(['status' => 'completed', 'completed_at' => now(), 'uploaded_bytes' => $uploadedBytes]);

        return $upload->fresh();
    }

    public function abort(FileUpload $upload): void
    {
        try {
            $this->client()->abortMultipartUpload([
                'Bucket' => $this->bucket(),
                'Key' => $upload->cloud_key,
                'UploadId' => $upload->multipart_upload_id,
            ]);
        } catch (Throwable $e) {
            report($e);
        }

        $upload->update(['status' => 'abandoned']);
    }

    public function promoteToFinalPath(FileUpload $upload, string $finalPath): void
    {
        Storage::disk($upload->cloud_disk)->copy($upload->cloud_key, $finalPath);
        $this->deleteObject($upload->cloud_key);
        $upload->update(['consumed_at' => now()]);
    }

    private function deleteObject(string $key): void
    {
        try {
            Storage::disk(self::DISK)->delete($key);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Content-based check (ZIP local-file-header / empty-archive magic
     * bytes), not the client-reported filename — reads only the first few
     * KB via an S3 ranged GetObject, never the whole file.
     */
    private function looksLikeZip(FileUpload $upload): bool
    {
        try {
            $head = $this->client()->getObject([
                'Bucket' => $this->bucket(),
                'Key' => $upload->cloud_key,
                'Range' => 'bytes=0-4103',
            ]);

            $bytes = (string) $head['Body'];
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return str_starts_with($bytes, "PK\x03\x04") || str_starts_with($bytes, "PK\x05\x06");
    }

    private function client(): S3Client
    {
        return Storage::disk(self::DISK)->getClient();
    }

    private function bucket(): string
    {
        return (string) config('filesystems.disks.'.self::DISK.'.bucket');
    }
}
