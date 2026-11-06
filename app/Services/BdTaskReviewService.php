<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskBdReview;
use App\Models\DesignTaskComment;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * BD review actions (rework request, completion with rating) for the mobile API.
 * Mirrors Bd\AssignedTaskController::submitRework() / completeWithRating(); the caller must have
 * verified the BD owns the task (assigned_by).
 */
class BdTaskReviewService
{
    public function __construct(private readonly DesignTaskProgressService $progress, private readonly TaskNotificationService $notifications) {}

    public function requestRework(User $bd, DesignTask $task, int $count, string $comment, ?UploadedFile $zip = null): DesignTask
    {
        DB::transaction(function () use ($bd, $task, $count, $comment, $zip) {
            $locked = DesignTask::query()->lockForUpdate()->findOrFail($task->id);

            if ($locked->status !== 'waiting_confirmation') {
                throw ValidationException::withMessages(['status' => 'Rework can be requested only while the task is Waiting for Confirmation.']);
            }

            if ($count > (int) $locked->total_creatives) {
                throw ValidationException::withMessages(['number_of_creatives' => 'Rework creatives cannot exceed the current total number of creatives.']);
            }

            $review = DesignTaskBdReview::create([
                'design_task_id' => $locked->id,
                'submitted_by' => $bd->id,
                'action' => 'rework',
                'number_of_creatives' => $count,
                'comment' => trim($comment),
            ]);

            if ($zip) {
                $root = trim((string) env('DO_SPACES_ROOT', 'design_task_manager'), '/');
                $directory = implode('/', [$root, now()->format('Y'), $locked->vertical, $locked->task_id.'_'.Str::slug($locked->task_name), Str::slug($locked->task_nature), 'rework-request', 'review-'.$review->id]);
                $path = $zip->storePubliclyAs($directory, $locked->task_id.'__rework-request-'.$review->id.'__'.now()->format('Ymd-His-v').'.zip', 'spaces');

                $review->update([
                    'attachment_disk' => 'spaces',
                    'attachment_path' => $path,
                    'attachment_original_name' => $zip->getClientOriginalName(),
                    'attachment_size' => $zip->getSize(),
                ]);
            }

            DesignTaskComment::create([
                'design_task_id' => $locked->id,
                'user_id' => $bd->id,
                'status_at_comment' => $locked->status,
                'comment' => 'Rework requested for '.$count.' creative(s).'."\n".trim($comment),
            ]);

            $from = $locked->status;
            $locked->update(['status' => 'rework']);

            DesignTaskStatusHistory::create([
                'design_task_id' => $locked->id,
                'from_status' => $from,
                'to_status' => 'rework',
                'changed_by' => $bd->id,
                'change_source' => 'bd_rework',
                'note' => 'BD requested rework for '.$count.' creative(s).',
            ]);
        });

        $fresh = $task->fresh();
        $this->notifications->reworkRequested($fresh, $this->progress->reworkCount($fresh), $count, trim($comment), $bd);

        return $fresh;
    }

    /**
     * @param  array{designer_attitude:mixed,design_satisfaction:mixed,rework_iteration:mixed,meeting_deadline:mixed,client_satisfaction:mixed}  $ratings
     */
    public function completeWithRating(User $bd, DesignTask $task, array $ratings, ?string $comment, string $completionType): DesignTask
    {
        $target = $completionType === 'prepare_printing_file' ? 'prepare_printing_file' : 'completed';

        $overall = DesignTaskBdReview::roundToHalfStar((
            (float) $ratings['designer_attitude'] + (float) $ratings['design_satisfaction'] + (float) $ratings['rework_iteration']
            + (float) $ratings['meeting_deadline'] + (float) $ratings['client_satisfaction']
        ) / 5);

        $comment = trim((string) $comment);

        if ($overall < 3 && $comment === '') {
            throw ValidationException::withMessages(['rating_comment' => 'Comments are mandatory when the overall rating is below 3 stars.']);
        }

        DB::transaction(function () use ($bd, $task, $ratings, $comment, $overall, $target) {
            $locked = DesignTask::query()->lockForUpdate()->findOrFail($task->id);

            if ($locked->status !== 'waiting_confirmation') {
                throw ValidationException::withMessages(['status' => 'A task can be completed only from Waiting for Confirmation.']);
            }

            if ($this->progress->percentage($locked) < 100) {
                throw ValidationException::withMessages(['status' => 'The task cannot be completed until creative progress reaches 100%.']);
            }

            if (DesignTaskBdReview::query()->where('design_task_id', $locked->id)->where('action', 'completed')->exists()) {
                throw ValidationException::withMessages(['status' => 'A completion rating has already been submitted for this task.']);
            }

            DesignTaskBdReview::create([
                'design_task_id' => $locked->id,
                'submitted_by' => $bd->id,
                'action' => 'completed',
                'number_of_creatives' => (int) $locked->total_creatives,
                'comment' => $comment !== '' ? $comment : null,
                'designer_attitude' => $ratings['designer_attitude'],
                'design_satisfaction' => $ratings['design_satisfaction'],
                'rework_iteration' => $ratings['rework_iteration'],
                'meeting_deadline' => $ratings['meeting_deadline'],
                'client_satisfaction' => $ratings['client_satisfaction'],
                'overall_rating' => $overall,
            ]);

            $from = $locked->status;
            $locked->update(['status' => $target]);

            DesignTaskStatusHistory::create([
                'design_task_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $target,
                'changed_by' => $bd->id,
                'change_source' => $target === 'completed' ? 'bd_completion_rating' : 'bd_prepare_printing_file',
                'note' => $target === 'completed'
                    ? 'Task completed by BD with an overall rating of '.number_format($overall, 2).' / 5.'
                    : 'Task moved to Prepare Printing File by BD with an overall rating of '.number_format($overall, 2).' / 5. Reassigned to the Designer for printing file preparation.',
            ]);
        });

        $fresh = $task->fresh();

        if ($target === 'prepare_printing_file') {
            $this->notifications->preparePrintingFile($fresh, $bd);
        } else {
            $review = DesignTaskBdReview::query()->with('submitter:id,name,role')
                ->where('design_task_id', $task->id)->where('action', 'completed')->latest()->first();

            if ($review) {
                $this->notifications->taskRated($fresh, $review);
            }
        }

        return $fresh;
    }
}
