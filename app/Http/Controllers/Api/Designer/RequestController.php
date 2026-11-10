<?php

namespace App\Http\Controllers\Api\Designer;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\User;
use App\Services\DesignerTaskAccessService;
use App\Services\DesignTaskRequestService;
use App\Services\TaskNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** Decline / split / swap requests (same rules as Livewire\Designer\TaskRequestModal). */
class RequestController extends Controller
{
    /** Which request types are open for this task now, plus the designers selectable as target. */
    public function options(Request $request, DesignTask $task, DesignerTaskAccessService $access, DesignTaskRequestService $requests): JsonResponse
    {
        $this->authorizeOwner($request, $task, $access);

        return response()->json([
            'allowed_types' => $requests->allowedTypesForTask($task),
            'designers' => User::query()->where('role', 'designer')->where('is_active', true)
                ->where('id', '!=', $request->user()->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, DesignTask $task, DesignerTaskAccessService $access, DesignTaskRequestService $requests, TaskNotificationService $notifications): JsonResponse
    {
        $this->authorizeOwner($request, $task, $access);

        $type = (string) $request->input('type');
        abort_unless(in_array($type, $requests->allowedTypesForTask($task), true), 422, 'This request type is not available for the task\'s current status.');

        $rules = [
            'type' => ['required', 'in:decline,split,swap'],
            'reason' => ['required', 'string', 'max:5000'],
            'attachments' => ['array', 'max:10'],
            'attachments.*' => ['file', 'max:102400'],
        ];

        if ($type === 'split') {
            $rules += [
                'creative_count' => ['required', 'integer', 'min:1', 'max:9999'],
                'details' => ['required', 'string', 'max:5000'],
                'target_designer_id' => ['nullable', 'exists:users,id'],
            ];
        }

        if ($type === 'swap') {
            $rules += [
                'target_designer_id' => ['required', 'exists:users,id'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ];
        }

        $data = $request->validate($rules);

        $splitDetails = match ($type) {
            'split' => ['creative_count' => (int) $data['creative_count'], 'details' => trim($data['details'])],
            'swap' => ['notes' => trim($data['notes'] ?? '')],
            default => null,
        };

        $paths = collect($request->file('attachments', []))
            ->filter(fn ($f) => $f instanceof UploadedFile)
            ->map(fn (UploadedFile $f) => $this->storeAttachment($task, $type, $f))
            ->values()->all();

        $created = $requests->create($task, $request->user(), $type, trim($data['reason']), [
            'target_designer_id' => $data['target_designer_id'] ?? null,
            'split_details' => $splitDetails,
            'attachments' => $paths ?: null,
        ]);

        $notifications->requestSubmitted($created);

        return response()->json([
            'request' => ['id' => $created->id, 'request_type' => $created->request_type, 'overall_status' => $created->overall_status],
            'message' => 'Request submitted successfully.',
        ], 201);
    }

    private function authorizeOwner(Request $request, DesignTask $task, DesignerTaskAccessService $service): void
    {
        $access = $service->resolve($request->user(), $task);
        abort_if($access === null || $access['read_only'], 403, 'You cannot raise requests on this task.');
    }

    private function storeAttachment(DesignTask $task, string $type, UploadedFile $file): string
    {
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'attachment';
        $name = sprintf('%s__request-%s__%s__%s.%s', $task->task_id, $type, $base, now()->format('Ymd-His-v'), strtolower($file->getClientOriginalExtension()));
        $root = trim((string) env('DO_SPACES_ROOT', 'design_task_manager'), '/');
        $dir = implode('/', [$root, now()->format('Y'), $task->vertical, $task->task_id.'_'.Str::slug($task->task_name), Str::slug($task->task_nature), 'requests', $type]);

        return $file->storePubliclyAs($dir, $name, 'spaces');
    }
}
