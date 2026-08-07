<?php

namespace App\Livewire\Designer;

use App\Models\DesignTask;
use App\Models\DesignTaskComment;
use App\Models\DesignTaskCommentAttachment;
use App\Models\DesignTaskStatusHistory;
use App\Services\DesignTaskStatusService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class TaskDetail extends Component
{
    use WithFileUploads;

    public DesignTask $task;
    public string $comment = '';
    public array $attachments = [];

    public function mount(DesignTask $task): void
    {
        abort_unless(
            Auth::user()?->role === 'designer'
            && (int) $task->designer_id === (int) Auth::id(),
            403
        );

        $this->task = $task;
    }

    public function moveToNextStatus(): void
    {
        $nextStatus = app(DesignTaskStatusService::class)
            ->nextDesignerStatus($this->task->status);

        if ($nextStatus === null) {
            $this->addError('status', 'No further Designer status movement is available.');
            return;
        }

        $this->task = app(DesignTaskStatusService::class)
            ->moveAsDesigner($this->task, Auth::user(), $nextStatus, 'detail_button');

        $this->dispatch('task-status-changed', message: 'Task moved successfully.');
    }

    public function addComment(): void
    {
        $this->validate([
            'comment' => ['required', 'string', 'max:10000'],
            'attachments' => ['array', 'max:10'],
            'attachments.*' => ['file', 'max:102400'],
        ]);

        DB::transaction(function () {
            $newComment = DesignTaskComment::create([
                'design_task_id' => $this->task->id,
                'user_id' => Auth::id(),
                'status_at_comment' => $this->task->status,
                'comment' => trim($this->comment),
            ]);

            foreach ($this->attachments as $index => $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $originalBase = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                $extension = strtolower($file->getClientOriginalExtension());
                $safeBase = $originalBase !== '' ? $originalBase : 'attachment';

                $fileName = sprintf(
                    '%s__comment-%05d__%s__%s__%02d.%s',
                    $this->task->task_id,
                    $newComment->id,
                    $safeBase,
                    now()->format('Ymd-His-v'),
                    $index + 1,
                    $extension
                );

                $root = trim((string) env('DO_SPACES_ROOT', 'design_task_manager'), '/');
                $directory = implode('/', [
                    $root,
                    now()->format('Y'),
                    $this->task->vertical,
                    $this->task->task_id.'_'.Str::slug($this->task->task_name),
                    Str::slug($this->task->task_nature),
                    'comments',
                    'comment-'.$newComment->id,
                ]);

                $path = $file->storePubliclyAs($directory, $fileName, 'spaces');

                DesignTaskCommentAttachment::create([
                    'design_task_comment_id' => $newComment->id,
                    'disk' => 'spaces',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        });

        $this->reset(['comment', 'attachments']);
        $this->dispatch('comment-added', message: 'Comment added successfully.');
    }

    public function render()
    {
        $this->task->refresh()->load(['designer:id,name', 'assigner:id,name']);

        return view('livewire.designer.task-detail', [
            'statuses' => DesignTaskStatusService::STATUSES,
            'nextStatus' => app(DesignTaskStatusService::class)
                ->nextDesignerStatus($this->task->status),
            'comments' => DesignTaskComment::query()
                ->with(['user:id,name', 'attachments'])
                ->where('design_task_id', $this->task->id)
                ->latest()
                ->get(),
            'history' => DesignTaskStatusHistory::query()
                ->with('changedBy:id,name')
                ->where('design_task_id', $this->task->id)
                ->latest()
                ->get(),
        ]);
    }
}
