<?php

namespace App\Http\Controllers\Designer;

use App\Http\Controllers\Bd\TaskController as BdTaskController;
use App\Models\DesignTask;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use App\Services\TaskNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Designer-initiated task creation — the task sits in pending_bd_approval
 * (not the normal assigned_tasks) until the selected BD confirms it via
 * App\Livewire\Bd\TaskKanban::approveConfirmation()/rejectConfirmation().
 * Extends Bd\TaskController purely to reuse its dynamic requirements-form
 * validation/file-handling (NATURES, requirementRules(), storeMultipleFiles(),
 * buildRequirementsPayload()) — none of the BD-only behaviour (drafts,
 * designer assignment, taskAssigned notification) is inherited into store().
 */
class TaskController extends BdTaskController
{
    public function create(Request $request)
    {
        $bds = User::query()
            ->where('role', 'bd')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'experienced_verticals']);

        return view('bd.tasks.create', [
            'actorRole' => 'designer',
            'assignees' => $bds,
        ]);
    }

    public function store(Request $request)
    {
        $verticals = array_keys(self::NATURES);

        $rules = [
            'task_name' => ['required', 'string', 'max:180'],
            'vertical' => ['required', Rule::in($verticals)],
            'zoho_project_number' => ['nullable', 'string', 'max:60'],
            'task_nature' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $allowed = self::NATURES[$request->input('vertical')] ?? [];
                    if (! in_array($value, $allowed, true)) {
                        $fail('The selected task nature is invalid for the chosen vertical.');
                    }
                },
            ],
            'party_type' => ['required', Rule::in(['client', 'agency'])],
            'party_name' => ['required', 'string', 'max:180'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'mobile_number' => ['nullable', 'digits:10'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_at' => [
                'required',
                'date',
                'after_or_equal:today',
                'before_or_equal:'.$this->maximumAllowedDueDate()->format('Y-m-d H:i:s'),
            ],
            'bd_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'bd')->where('is_active', true)),
            ],
            'total_creatives' => ['required', 'integer', 'min:1', 'max:9999'],
        ];

        $rules = array_merge($rules, $this->requirementRules($request));
        $data = $request->validate($rules);

        $requirements = $this->buildRequirementsPayload($data);

        $rootFolder = trim((string) env('DO_SPACES_ROOT', 'design_task_manager'), '/');

        $task = DB::transaction(function () use ($data): DesignTask {
            $task = DesignTask::create([
                'task_id' => 'PENDING-'.Str::uuid(),
                'assigned_at' => now(),
                'assigned_by' => (int) $data['bd_id'],
                'task_name' => $data['task_name'],
                'vertical' => $data['vertical'],
                'zoho_project_number' => $data['zoho_project_number'] ?? null,
                'task_nature' => $data['task_nature'],
                'party_type' => $data['party_type'],
                'party_name' => $data['party_name'],
                'contact_person' => $data['contact_person'] ?? '',
                'mobile_number' => $data['mobile_number'] ?? '',
                'priority' => $data['priority'],
                'due_at' => $data['due_at'],
                'designer_id' => auth()->id(),
                'total_creatives' => $data['total_creatives'],
                'status' => 'pending_bd_approval',
                'bd_approval_status' => 'pending',
                'requirements' => [],
            ]);

            $task->update([
                'task_id' => sprintf('DT-%s-%05d', now()->format('Y'), $task->id),
            ]);

            DesignTaskStatusHistory::create([
                'design_task_id' => $task->id,
                'from_status' => null,
                'to_status' => 'pending_bd_approval',
                'changed_by' => auth()->id(),
                'change_source' => 'designer_task_created',
                'note' => 'Task created by Designer, awaiting BD confirmation.',
            ]);

            return $task->fresh();
        });

        $taskNameSlug = Str::slug($task->task_name);
        $taskNatureSlug = Str::slug(str_replace('_', '-', $task->task_nature));
        $verticalSlug = Str::slug(str_replace('_', '-', $task->vertical));

        $taskFolder = implode('/', [
            $rootFolder,
            now()->format('Y'),
            $verticalSlug,
            "{$task->task_id}_{$taskNameSlug}",
            $taskNatureSlug,
        ]);

        try {
            foreach (self::FILE_FIELDS as $field) {
                if ($request->hasFile($field)) {
                    $requirements[$field] = $this->storeMultipleFiles(
                        files: $request->file($field),
                        directory: "{$taskFolder}/{$field}",
                        taskId: $task->task_id,
                        fieldName: $field
                    );
                }
            }

            $task->update(['requirements' => $requirements]);
        } catch (\Throwable $exception) {
            Storage::disk('spaces')->deleteDirectory($taskFolder);
            $task->delete();
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'upload' => 'The task could not be created because one or more files could not be saved. Please try again.',
                ]);
        }

        app(TaskNotificationService::class)->bdApprovalRequested($task, auth()->user());

        return redirect()
            ->route('designer.tasks.show', $task)
            ->with('success', 'Task submitted. Waiting for BD confirmation.');
    }
}
