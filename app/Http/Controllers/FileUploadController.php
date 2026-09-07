<?php

namespace App\Http\Controllers;

use App\Models\DesignTask;
use App\Models\FileUpload;
use App\Services\CloudMultipartUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Orchestrates direct browser-to-Spaces multipart uploads for the Progress
 * Update / Rework ZIP fields (see App\Services\CloudMultipartUploadService).
 * File bytes never pass through these endpoints — only presigned URLs and
 * lightweight metadata.
 */
class FileUploadController extends Controller
{
    public function __construct(private readonly CloudMultipartUploadService $uploads) {}

    public function initiate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'task_id' => ['required', 'integer'],
            'purpose' => ['required', Rule::in(CloudMultipartUploadService::PURPOSES)],
            'filename' => ['required', 'string', 'max:255'],
            'size_bytes' => ['required', 'integer', 'min:1'],
        ]);

        $task = $this->authorizedTask((int) $data['task_id']);

        $upload = $this->uploads->initiate(Auth::user(), $task, $data['purpose'], $data['filename'], (int) $data['size_bytes']);

        return response()->json($this->uploadPayload($upload));
    }

    public function active(Request $request): JsonResponse
    {
        $data = $request->validate([
            'task_id' => ['required', 'integer'],
            'purpose' => ['required', Rule::in(CloudMultipartUploadService::PURPOSES)],
        ]);

        $task = $this->authorizedTask((int) $data['task_id']);

        $upload = FileUpload::query()
            ->where('user_id', Auth::id())
            ->where('design_task_id', $task->id)
            ->where('purpose', $data['purpose'])
            ->where('status', 'uploading')
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $upload) {
            return response()->json(['upload' => null]);
        }

        return response()->json(['upload' => $this->uploadPayload($upload)]);
    }

    public function partUrl(Request $request, FileUpload $upload): JsonResponse
    {
        $this->authorizedUpload($upload);

        $data = $request->validate(['part_number' => ['required', 'integer', 'min:1']]);

        return response()->json(['url' => $this->uploads->partUrl($upload, (int) $data['part_number'])]);
    }

    public function parts(FileUpload $upload): JsonResponse
    {
        $this->authorizedUpload($upload);

        $parts = $this->uploads->listedParts($upload);
        $uploadedBytes = array_sum(array_column($parts, 'size'));

        $upload->update(['uploaded_bytes' => $uploadedBytes]);

        return response()->json([
            'uploaded_bytes' => $uploadedBytes,
            'part_numbers' => array_keys($parts),
        ]);
    }

    public function complete(FileUpload $upload): JsonResponse
    {
        $this->authorizedUpload($upload);

        $upload = $this->uploads->complete($upload);

        return response()->json($this->uploadPayload($upload));
    }

    public function abort(FileUpload $upload): JsonResponse
    {
        $this->authorizedUpload($upload);

        $this->uploads->abort($upload);

        return response()->json(['status' => 'abandoned']);
    }

    private function authorizedTask(int $taskId): DesignTask
    {
        $user = Auth::user();
        abort_unless($user?->role === 'designer', 403);

        $task = DesignTask::query()->findOrFail($taskId);

        abort_unless((int) $task->designer_id === (int) $user->id, 403);
        abort_if((bool) data_get($task->requirements, '_swap_shadow', false), 403);

        return $task;
    }

    private function authorizedUpload(FileUpload $upload): void
    {
        abort_unless((int) $upload->user_id === (int) Auth::id(), 403);
    }

    private function uploadPayload(FileUpload $upload): array
    {
        return [
            'id' => $upload->id,
            'purpose' => $upload->purpose,
            'original_filename' => $upload->original_filename,
            'size_bytes' => (int) $upload->size_bytes,
            'part_size_bytes' => (int) $upload->part_size_bytes,
            'uploaded_bytes' => (int) $upload->uploaded_bytes,
            'status' => $upload->status,
        ];
    }
}
