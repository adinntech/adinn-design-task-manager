<?php

namespace App\Http\Controllers\Designer;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskCommentAttachment;
use App\Models\DesignTaskRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TaskAttachmentDownloadController extends Controller
{
    public function __invoke(Request $request, DesignTask $task)
    {
        abort_unless(
            Auth::user()?->role === 'designer'
            && (int) $task->designer_id === (int) Auth::id(),
            403
        );

        $token = (string) $request->query('file', '');
        $path = base64_decode($token, true);

        abort_unless(is_string($path) && $path !== '', 404);

        $commentAttachment = DesignTaskCommentAttachment::query()
            ->where('path', $path)
            ->whereHas('comment', function ($query) use ($task) {
                $query->where('design_task_id', $task->id);
            })
            ->first();

        if ($commentAttachment) {
            $disk = $commentAttachment->disk ?: 'spaces';

            abort_unless(Storage::disk($disk)->exists($commentAttachment->path), 404);

            return Storage::disk($disk)->download(
                $commentAttachment->path,
                $this->safeDownloadName($commentAttachment->original_name)
            );
        }

        $allowedPaths = $this->requirementAttachmentPaths($task->requirements ?? []);

        $requestPaths = DesignTaskRequest::query()
            ->where('design_task_id', $task->id)
            ->get(['attachments'])
            ->flatMap(function (DesignTaskRequest $taskRequest) {
                return collect($taskRequest->attachments ?? [])
                    ->filter(fn ($value) => is_string($value) && $value !== '');
            })
            ->values()
            ->all();

        $allowedPaths = array_values(array_unique(array_merge($allowedPaths, $requestPaths)));

        abort_unless(in_array($path, $allowedPaths, true), 403);
        abort_unless(Storage::disk('spaces')->exists($path), 404);

        return Storage::disk('spaces')->download(
            $path,
            $this->safeDownloadName(basename($path))
        );
    }

    private function requirementAttachmentPaths(array $requirements): array
    {
        $paths = [];

        $walk = function ($value) use (&$walk, &$paths): void {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $walk($item);
                }

                return;
            }

            if (! is_string($value)) {
                return;
            }

            $value = trim($value);

            if (
                $value === ''
                || filter_var($value, FILTER_VALIDATE_URL)
                || ! str_contains($value, '/')
                || pathinfo($value, PATHINFO_EXTENSION) === ''
            ) {
                return;
            }

            $paths[] = $value;
        };

        $walk($requirements);

        return array_values(array_unique($paths));
    }

    private function safeDownloadName(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 'attachment';
        }

        return str_replace(["\r", "\n", '"'], '', $name);
    }
}
