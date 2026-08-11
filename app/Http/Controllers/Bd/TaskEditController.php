<?php

namespace App\Http\Controllers\Bd;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskEditHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskEditController extends Controller
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
    ];

    public function edit(Request $request, DesignTask $task): View
    {
        $this->authorizeBdTask($request, $task);

        $designers = User::query()
            ->where('role', 'designer')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('bd.tasks.edit', [
            'task' => $task,
            'designers' => $designers,
            'natures' => self::NATURES,
        ]);
    }

    public function update(Request $request, DesignTask $task)
    {
        $this->authorizeBdTask($request, $task);

        $data = $request->validate([
            'task_name' => ['required', 'string', 'max:180'],
            'vertical' => ['required', Rule::in(array_keys(self::NATURES))],
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
            'contact_person' => ['required', 'string', 'max:120'],
            'mobile_number' => ['required', 'digits:10'],
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
            'requirements' => ['sometimes', 'array'],
        ]);

        $oldDesignerName = User::query()->whereKey($task->designer_id)->value('name') ?? '—';
        $newDesignerName = User::query()->whereKey($data['designer_id'])->value('name') ?? '—';

        $batchId = (string) Str::uuid();
        $historyRows = [];

        DB::transaction(function () use (
            $task,
            $data,
            $request,
            $batchId,
            $oldDesignerName,
            $newDesignerName,
            &$historyRows
        ) {
            $coreUpdates = [];

            foreach (self::CORE_FIELDS as $field => $label) {
                $oldRaw = $task->{$field};
                $newRaw = $data[$field];

                if ($field === 'due_at') {
                    $oldComparable = optional($task->due_at)->format('Y-m-d H:i:s');
                    $newComparable = date('Y-m-d H:i:s', strtotime((string) $newRaw));
                } else {
                    $oldComparable = is_object($oldRaw) && method_exists($oldRaw, '__toString')
                        ? (string) $oldRaw
                        : (string) ($oldRaw ?? '');
                    $newComparable = (string) ($newRaw ?? '');
                }

                if ($oldComparable === $newComparable) {
                    continue;
                }

                $coreUpdates[$field] = $newRaw;

                $historyRows[] = [
                    'design_task_id' => $task->id,
                    'edited_by' => $request->user()->id,
                    'edit_batch_id' => $batchId,
                    'field_name' => $label,
                    'old_value' => $field === 'designer_id'
                        ? $oldDesignerName
                        : $this->displayValue($field, $oldRaw),
                    'new_value' => $field === 'designer_id'
                        ? $newDesignerName
                        : $this->displayValue($field, $newRaw),
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

                // Uploaded-file paths are display-only in the edit page and are not
                // submitted. This prevents accidental deletion of existing files.
                if ($this->valuesEqual($oldValue, $submittedValue)) {
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

            if ($coreUpdates !== []) {
                $task->fill($coreUpdates);
            }

            if ($newRequirements !== $oldRequirements) {
                $task->requirements = $newRequirements;
            }

            if ($task->isDirty()) {
                $task->save();
            }

            if ($historyRows !== []) {
                DesignTaskEditHistory::query()->insert($historyRows);
            }
        });

        if ($historyRows === []) {
            return redirect()
                ->route('bd.tasks.show', $task)
                ->with('success', 'No changes were detected.');
        }

        return redirect()
            ->route('bd.tasks.show', ['task' => $task, 'tab' => 'edit-history'])
            ->with('success', 'Task updated successfully. Edit History has been recorded.');
    }

    private function authorizeBdTask(Request $request, DesignTask $task): void
    {
        abort_unless(
            $request->user()?->role === 'bd'
            && (int) $task->assigned_by === (int) $request->user()->id,
            403
        );
    }

    private function displayValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($field === 'due_at') {
            try {
                return \Illuminate\Support\Carbon::parse($value)->format('d M Y, h:i A');
            } catch (\Throwable) {
                return (string) $value;
            }
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

    private function valuesEqual(mixed $oldValue, mixed $newValue): bool
    {
        return $this->stringify($oldValue) === $this->stringify($newValue);
    }
}
