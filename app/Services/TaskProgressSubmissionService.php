<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskEodRecord;
use App\Models\DesignTaskStatusHistory;
use App\Models\FileUpload;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Task Updation (EOD) and Rework submissions for the mobile API. Same rules and writes as
 * Livewire\Designer\TaskDetail::submitEod() / submitReworkUpdate(); callers must have already
 * verified the user owns the task with full (non read-only) access.
 */
class TaskProgressSubmissionService
{
    public function __construct(
        private readonly CloudMultipartUploadService $uploads,
        private readonly DesignTaskProgressService $progress,
        private readonly DesignTaskRequestService $requests,
        private readonly TaskNotificationService $notifications,
    ) {}

    /** @return array{auto_completed:bool,task:DesignTask} */
    public function submitEod(User $user, DesignTask $task, int $count, ?string $uploadIds): array
    {
        if ($task->status !== 'in_progress') {
            throw ValidationException::withMessages(['completed_count' => 'Task Updation is enabled only while the task is In Progress.']);
        }

        $files = $this->resolveCompletedUploads($user, $task, $uploadIds, 'progress_update', 'Upload at least one file for this Task Updation.');
        $autoCompleted = false;

        DB::transaction(function () use ($user, $task, $count, $files, &$autoCompleted): void {
            $locked = DesignTask::query()->lockForUpdate()->findOrFail($task->id);

            if ((int) $locked->designer_id !== (int) $user->id || $locked->status !== 'in_progress') {
                throw ValidationException::withMessages(['completed_count' => 'Task Updation is no longer available for this task.']);
            }

            $already = $this->progress->completed($locked);
            $remainingBefore = max(0, (int) $locked->total_creatives - $already);

            if ($remainingBefore <= 0) {
                throw ValidationException::withMessages(['completed_count' => 'Creative progress is already 100%.']);
            }

            if ($count > $remainingBefore) {
                throw ValidationException::withMessages(['completed_count' => 'You can add only '.$remainingBefore.' remaining creative'.($remainingBefore === 1 ? '' : 's').'.']);
            }

            $cumulative = $already + $count;
            $remainingAfter = max(0, (int) $locked->total_creatives - $cumulative);
            $directory = $this->directory($locked, 'task-updation');
            $first = $files[0];
            $stamp = now()->format('Ymd-His-v');
            $path = $directory.'/'.$locked->task_id.'__task-updation__'.$stamp.'.'.(strtolower(pathinfo($first->original_filename, PATHINFO_EXTENSION)) ?: 'zip');
            $this->uploads->promoteToFinalPath($first, $path);
            $extra = $this->promoteExtra(array_slice($files, 1), $directory, $locked->task_id.'__task-updation__'.$stamp);

            DesignTaskEodRecord::create([
                'design_task_id' => $locked->id,
                'designer_id' => $user->id,
                'update_type' => 'progress',
                'completed_count' => $count,
                'total_creatives_snapshot' => (int) $locked->total_creatives,
                'cumulative_completed' => $cumulative,
                'remaining_creatives' => $remainingAfter,
                'rework_count_snapshot' => null,
                'attachment_disk' => 'spaces',
                'attachment_path' => $path,
                'attachment_original_name' => $first->original_filename,
                'extra_attachments' => $extra,
                'submitted_at' => now(),
            ]);

            DesignTaskStatusHistory::create([
                'design_task_id' => $locked->id,
                'from_status' => $locked->status,
                'to_status' => $locked->status,
                'changed_by' => $user->id,
                'change_source' => 'task_updation',
                'note' => 'Task Updation submitted: '.$count.' progress added, '.$remainingAfter.' remaining.',
            ]);

            if ($this->progress->isComplete($locked)) {
                $from = $locked->status;
                $locked->update(['status' => 'waiting_confirmation']);
                $this->requests->autoRejectPendingForStatus($locked, 'waiting_confirmation', $user);

                DesignTaskStatusHistory::create([
                    'design_task_id' => $locked->id,
                    'from_status' => $from,
                    'to_status' => 'waiting_confirmation',
                    'changed_by' => $user->id,
                    'change_source' => 'progress_completed',
                    'note' => 'All '.$locked->total_creatives.' creative(s) completed. Automatically moved to Waiting for BD Review.',
                ]);

                $autoCompleted = true;
            }
        });

        $fresh = $task->fresh();

        if ($autoCompleted) {
            $this->notifications->statusChanged($fresh, 'waiting_confirmation', $user);
        }

        return ['auto_completed' => $autoCompleted, 'task' => $fresh];
    }

    /** @return array{next_stage:string,pending_after:int,task:DesignTask} */
    public function submitRework(User $user, DesignTask $task, int $count, ?string $uploadIds): array
    {
        if ($task->status !== 'rework') {
            throw ValidationException::withMessages(['completed_count' => 'Rework submission is available only while the task is in Rework.']);
        }

        $pendingBefore = $this->progress->currentReworkPending($task);

        if ($count > $pendingBefore) {
            throw ValidationException::withMessages(['completed_count' => 'You cannot submit more creatives than the current Rework pending count.']);
        }

        $files = $this->resolveCompletedUploads($user, $task, $uploadIds, 'rework', 'Upload at least one file for this Rework.');
        $nextStage = 'rework';
        $pendingAfter = $pendingBefore;

        DB::transaction(function () use ($user, $task, $count, $files, &$nextStage, &$pendingAfter): void {
            $locked = DesignTask::query()->lockForUpdate()->findOrFail($task->id);

            if ((int) $locked->designer_id !== (int) $user->id || $locked->status !== 'rework') {
                throw ValidationException::withMessages(['completed_count' => 'Rework submission is no longer available for this task.']);
            }

            $reworkCount = $this->progress->reworkCount($locked);
            $pending = $this->progress->currentReworkPending($locked);

            if ($reworkCount < 1 || $pending < 1) {
                throw ValidationException::withMessages(['completed_count' => 'No pending creatives remain in the current Rework cycle.']);
            }

            if ($count > $pending) {
                throw ValidationException::withMessages(['completed_count' => 'You cannot submit more creatives than the current Rework pending count.']);
            }

            $directory = $this->directory($locked, 'rework').'/rework-'.$reworkCount;
            $first = $files[0];
            $baseName = $locked->task_id.'__rework-'.$reworkCount.'__'.now()->format('Ymd-His-v');
            $path = $directory.'/'.$baseName.'.'.(strtolower(pathinfo($first->original_filename, PATHINFO_EXTENSION)) ?: 'zip');
            $this->uploads->promoteToFinalPath($first, $path);
            $extra = $this->promoteExtra(array_slice($files, 1), $directory, $baseName);

            $record = DesignTaskEodRecord::create([
                'design_task_id' => $locked->id,
                'designer_id' => $user->id,
                'update_type' => 'rework',
                'completed_count' => $count,
                'total_creatives_snapshot' => (int) $locked->total_creatives,
                'cumulative_completed' => 0,
                'remaining_creatives' => 0,
                'rework_count_snapshot' => $reworkCount,
                'attachment_disk' => 'spaces',
                'attachment_path' => $path,
                'attachment_original_name' => $first->original_filename,
                'extra_attachments' => $extra,
                'submitted_at' => now(),
            ]);

            $pendingAfter = $this->progress->currentReworkPending($locked);
            $record->update([
                'cumulative_completed' => $this->progress->completed($locked),
                'remaining_creatives' => $this->progress->remaining($locked),
            ]);

            DesignTaskStatusHistory::create([
                'design_task_id' => $locked->id,
                'from_status' => 'rework',
                'to_status' => 'rework',
                'changed_by' => $user->id,
                'change_source' => 'rework_upload',
                'note' => 'Rework #'.$reworkCount.': '.$count.' creative(s) resubmitted. '.$pendingAfter.' rework creative(s) pending.',
            ]);

            $complete = $this->progress->isComplete($locked);

            if ($complete || $pendingAfter === 0) {
                $nextStage = $complete ? 'waiting_confirmation' : 'in_progress';
                if ($complete) {
                    $pendingAfter = 0;
                }

                $locked->update(['status' => $nextStage]);
                $this->requests->autoRejectPendingForStatus($locked, $nextStage, $user);

                DesignTaskStatusHistory::create([
                    'design_task_id' => $locked->id,
                    'from_status' => 'rework',
                    'to_status' => $nextStage,
                    'changed_by' => $user->id,
                    'change_source' => 'rework_completed',
                    'note' => $nextStage === 'waiting_confirmation'
                        ? 'Rework #'.$reworkCount.' reached 100% and returned to Waiting for Confirmation.'
                        : 'Rework #'.$reworkCount.' completed and returned to In Progress because overall progress is below 100%.',
                ]);
            }
        });

        $fresh = $task->fresh();

        if ($nextStage === 'waiting_confirmation') {
            $this->notifications->statusChanged($fresh, 'waiting_confirmation', $user);
        }

        return ['next_stage' => $nextStage, 'pending_after' => $pendingAfter, 'task' => $fresh];
    }

    /** @return array<int, FileUpload> */
    private function resolveCompletedUploads(User $user, DesignTask $task, ?string $ids, string $purpose, string $message): array
    {
        $ids = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $ids)), 'ctype_digit')));

        if ($ids === [] || count($ids) > 20) {
            throw ValidationException::withMessages(['upload_ids' => $message]);
        }

        $found = FileUpload::query()
            ->whereIn('id', $ids)
            ->where('user_id', $user->id)
            ->where('design_task_id', $task->id)
            ->where('purpose', $purpose)
            ->where('status', 'completed')
            ->whereNull('consumed_at')
            ->get()
            ->keyBy('id');

        if ($found->count() !== count($ids)) {
            throw ValidationException::withMessages(['upload_ids' => $message]);
        }

        $files = array_map(fn ($id) => $found[(int) $id], $ids);
        $singleZip = count($files) === 1 && strtolower(pathinfo($files[0]->original_filename, PATHINFO_EXTENSION)) === 'zip';

        if (! $singleZip && array_sum(array_map(fn ($u) => (int) $u->size_bytes, $files)) >= CloudMultipartUploadService::PROGRESS_UPDATE_ZIP_ONLY_THRESHOLD_BYTES) {
            throw ValidationException::withMessages(['upload_ids' => 'Files totalling 350 MB or more must be uploaded as one ZIP.']);
        }

        return $files;
    }

    private function directory(DesignTask $task, string $leaf): string
    {
        $root = trim((string) env('DO_SPACES_ROOT', 'design_task_manager'), '/');

        return implode('/', [$root, now()->format('Y'), $task->vertical, $task->task_id.'_'.Str::slug($task->task_name), Str::slug($task->task_nature), $leaf]);
    }

    /** @param array<int, FileUpload> $extra */
    private function promoteExtra(array $extra, string $directory, string $baseName): ?array
    {
        $out = [];

        foreach (array_values($extra) as $n => $upload) {
            $path = $directory.'/'.$baseName.'-'.($n + 2).'.'.(strtolower(pathinfo($upload->original_filename, PATHINFO_EXTENSION)) ?: 'bin');
            $this->uploads->promoteToFinalPath($upload, $path);
            $out[] = ['disk' => 'spaces', 'path' => $path, 'name' => $upload->original_filename];
        }

        return $out ?: null;
    }
}
