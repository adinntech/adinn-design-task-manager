<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskComment;
use App\Models\DesignTaskEditHistory;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use App\Services\DesignTaskStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskMonitoringController extends Controller
{
    private const NATURES = [
        'outdoor' => ['mockup_requirements', 'creative_adaptation', 'new_creative_design', 'cutout_size_calculation'],
        'roadshow' => ['creative_adaptation_requirements', 'new_creative_design'],
        'fixtures' => ['design_with_creative', 'design_without_creative'],
        'signage' => ['mockup', 'creative_adaptation', 'new_creative', 'technical_drawing', 'three_d_design', 'technical_and_three_d'],
        'pop_offsets' => ['mockup_design', 'design_adaptation', 'creative_design'],
        'digital_marketing' => ['proposal', 'logo_design', 'poster_design', 'video_design'],
        'events_activations' => ['proposal_designs', 'element_design_with_creative', 'element_design_without_creative', 'three_d_layout'],
    ];

    private const CORE_FIELDS = [
        'task_name' => 'Task Name',
        'vertical' => 'Vertical',
        'task_nature' => 'Task Nature',
        'party_type' => 'Client / Agency Type',
        'party_name' => 'Client / Agency Name',
        'contact_person' => 'Contact Person',
        'mobile_number' => 'Mobile Number',
        'priority' => 'Priority',
        'due_at' => 'Due Date',
        'designer_id' => 'Assigned Designer',
        'total_creatives' => 'Total Creatives',
        'status' => 'Status',
    ];

    public function index(Request $request): View
    {
        $tasks = DesignTask::query()
            ->with(['designer:id,name', 'assigner:id,name'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.trim((string) $request->input('search')).'%';

                $query->where(function ($q) use ($term) {
                    $q->where('task_id', 'like', $term)
                        ->orWhere('task_name', 'like', $term)
                        ->orWhere('party_name', 'like', $term);
                });
            })
            ->when($request->filled('vertical'), fn ($query) => $query->where('vertical', $request->input('vertical')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->input('priority')))
            ->when($request->filled('designer_id'), fn ($query) => $query->where('designer_id', $request->input('designer_id')))
            ->latest('assigned_at')
            ->paginate(20)
            ->withQueryString();

        $designers = User::query()
            ->where('role', 'designer')
            ->orderBy('name')
            ->get(['id', 'name']);

        $statuses = DesignTaskStatusService::STATUSES;

        return view('admin.tasks.index', compact('tasks', 'designers', 'statuses'));
    }

    public function show(DesignTask $task): View
    {
        $task->load(['designer:id,name,email', 'assigner:id,name,email']);

        $history = DesignTaskStatusHistory::query()
            ->with('changedBy:id,name')
            ->where('design_task_id', $task->id)
            ->latest()
            ->get();

        $comments = DesignTaskComment::query()
            ->with(['user:id,name', 'attachments'])
            ->where('design_task_id', $task->id)
            ->latest()
            ->get();

        $statuses = DesignTaskStatusService::STATUSES;

        $editHistory = collect();

        if (Schema::hasTable('design_task_edit_histories')) {
            $editHistory = DesignTaskEditHistory::query()
                ->with('editor:id,name,role')
                ->where('design_task_id', $task->id)
                ->latest('created_at')
                ->get()
                ->groupBy('edit_batch_id');
        }

        return view('admin.tasks.show', compact(
            'task',
            'history',
            'comments',
            'statuses',
            'editHistory'
        ));
    }

    public function edit(DesignTask $task): View
    {
        $designers = User::query()
            ->where('role', 'designer')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.tasks.edit', [
            'task' => $task,
            'designers' => $designers,
            'statuses' => DesignTaskStatusService::STATUSES,
            'natures' => self::NATURES,
        ]);
    }

    public function update(Request $request, DesignTask $task): RedirectResponse
    {
        $statuses = array_keys(DesignTaskStatusService::STATUSES);

        $data = $request->validate([
            'task_name' => ['required', 'string', 'max:180'],
            'vertical' => ['required', Rule::in(array_keys(self::NATURES))],
            'task_nature' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $allowed = self::NATURES[$request->input('vertical')] ?? [];

                    if (! in_array($value, $allowed, true)) {
                        $fail('The selected task nature is invalid for the selected vertical.');
                    }
                },
            ],
            'party_type' => ['required', Rule::in(['client', 'agency'])],
            'party_name' => ['required', 'string', 'max:180'],
            'contact_person' => ['required', 'string', 'max:120'],
            'mobile_number' => ['required', 'string', 'max:30'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_at' => ['required', 'date'],
            'designer_id' => [
                'required',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query
                        ->where('role', 'designer')
                        ->where('is_active', true)
                ),
            ],
            'total_creatives' => ['required', 'integer', 'min:1', 'max:9999'],
            'status' => ['required', Rule::in($statuses)],
            'requirements' => ['sometimes', 'array'],
        ]);

        $oldDesigner = User::query()->whereKey($task->designer_id)->value('name') ?? '—';
        $newDesigner = User::query()->whereKey($data['designer_id'])->value('name') ?? '—';
        $oldStatus = $task->status;
        $batchId = (string) Str::uuid();
        $historyRows = [];

        DB::transaction(function () use (
            $task,
            $data,
            $request,
            $oldDesigner,
            $newDesigner,
            $oldStatus,
            $batchId,
            &$historyRows
        ) {
            $updates = [];

            foreach (self::CORE_FIELDS as $field => $label) {
                $oldValue = $task->{$field};
                $newValue = $data[$field];

                $oldComparable = $field === 'due_at'
                    ? optional($task->due_at)->format('Y-m-d H:i:s')
                    : (string) ($oldValue ?? '');

                $newComparable = $field === 'due_at'
                    ? date('Y-m-d H:i:s', strtotime((string) $newValue))
                    : (string) ($newValue ?? '');

                if ($oldComparable === $newComparable) {
                    continue;
                }

                $updates[$field] = $newValue;

                $historyRows[] = [
                    'design_task_id' => $task->id,
                    'edited_by' => $request->user()->id,
                    'edit_batch_id' => $batchId,
                    'field_name' => $label,
                    'old_value' => $field === 'designer_id'
                        ? $oldDesigner
                        : $this->displayValue($field, $oldValue),
                    'new_value' => $field === 'designer_id'
                        ? $newDesigner
                        : $this->displayValue($field, $newValue),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $oldRequirements = $task->requirements ?? [];
            $newRequirements = $oldRequirements;

            foreach (($data['requirements'] ?? []) as $key => $submittedValue) {
                if (str_starts_with((string) $key, '_')) {
                    continue;
                }

                $oldValue = $oldRequirements[$key] ?? null;

                if ($this->stringify($oldValue) === $this->stringify($submittedValue)) {
                    continue;
                }

                $newRequirements[$key] = $submittedValue;

                $historyRows[] = [
                    'design_task_id' => $task->id,
                    'edited_by' => $request->user()->id,
                    'edit_batch_id' => $batchId,
                    'field_name' => 'Requirement · '.Str::headline((string) $key),
                    'old_value' => $this->stringify($oldValue),
                    'new_value' => $this->stringify($submittedValue),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($updates !== []) {
                $task->fill($updates);
            }

            if ($newRequirements !== $oldRequirements) {
                $task->requirements = $newRequirements;
            }

            if ($task->isDirty()) {
                $task->save();
            }

            if (
                $oldStatus !== $data['status']
                && Schema::hasTable('design_task_status_histories')
            ) {
                DesignTaskStatusHistory::create([
                    'design_task_id' => $task->id,
                    'from_status' => $oldStatus,
                    'to_status' => $data['status'],
                    'changed_by' => $request->user()->id,
                    'change_source' => 'admin_edit',
                    'note' => 'Task status updated by Admin.',
                ]);
            }

            if (
                $historyRows !== []
                && Schema::hasTable('design_task_edit_histories')
            ) {
                DesignTaskEditHistory::query()->insert($historyRows);
            }
        });

        return redirect()
            ->route('admin.tasks.show', $task)
            ->with('success', $historyRows === []
                ? 'No task changes were detected.'
                : 'Task updated successfully.');
    }

    public function destroy(DesignTask $task): RedirectResponse
    {
        $taskId = $task->task_id;
        $taskName = $task->task_name;

        $task->delete();

        return redirect()
            ->route('admin.tasks.index')
            ->with('success', "Task {$taskId} - {$taskName} deleted successfully.");
    }

    private function displayValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($field === 'due_at') {
            try {
                return \Illuminate\Support\Carbon::parse($value)->format('d M Y');
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        if ($field === 'status') {
            return DesignTaskStatusService::STATUSES[(string) $value]
                ?? Str::headline((string) $value);
        }

        if (in_array($field, ['vertical', 'task_nature'], true)) {
            return Str::headline((string) $value);
        }

        if (in_array($field, ['priority', 'party_type'], true)) {
            return ucfirst((string) $value);
        }

        return $this->stringify($value);
    }

    private function stringify(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }
}
