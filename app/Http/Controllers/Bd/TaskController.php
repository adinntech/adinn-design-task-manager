<?php

namespace App\Http\Controllers\Bd;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TaskController extends Controller
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

    private const ARRAY_FILES = [
        'supporting_documents', 'content_images', 'logo_images', 'reference_images', 'additional_attachments',
    ];

    private const SINGLE_FILES = [
        'site_photo', 'creative', 'reference_image', 'company_details_document', 'hoarding_artwork',
        'description_upload', 'vehicle_details', 'brand_details_upload', 'recce_report', 'client_format_manual',
        'fixture_details', 'material_specifications', 'dealer_details', 'technical_drawing', 'element_list',
        'requirement_list', 'brand_guidelines', 'previous_logo', 'client_audio',
    ];

    public function create()
    {
        $designers = User::query()
            ->where('role', 'designer')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('bd.tasks.create', compact('designers'));
    }

    public function store(Request $request)
    {
        $verticals = array_keys(self::NATURES);

        $rules = [
            'task_name' => ['required', 'string', 'max:180'],
            'vertical' => ['required', Rule::in($verticals)],
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
            'due_at' => ['required', 'date', 'after:now'],
            'designer_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'designer')->where('is_active', true)),
            ],
            'total_creatives' => ['required', 'integer', 'min:1', 'max:9999'],
        ];

        $rules = array_merge($rules, $this->requirementRules($request));
        $data = $request->validate($rules);

        $baseKeys = [
            'task_name', 'vertical', 'task_nature', 'party_type', 'party_name', 'contact_person',
            'mobile_number', 'priority', 'due_at', 'designer_id', 'total_creatives',
        ];

        $requirements = collect($data)
            ->except($baseKeys)
            ->reject(function (mixed $value): bool {
                if ($value instanceof UploadedFile) {
                    return true;
                }

                return is_array($value)
                    && collect($value)->contains(fn ($item) => $item instanceof UploadedFile);
            })
            ->all();

        if (isset($data['board_width'], $data['board_height'])) {
            $width = (float) $data['board_width'];
            $height = (float) $data['board_height'];
            unset($requirements['board_width'], $requirements['board_height']);
            $requirements['board_size'] = [
                'width' => $width,
                'height' => $height,
                'unit' => 'feet',
                'square_feet' => round($width * $height, 2),
            ];
        }

        $task = DB::transaction(function () use ($data): DesignTask {
            $task = DesignTask::create([
                'task_id' => 'PENDING-'.Str::uuid(),
                'assigned_at' => now(),
                'assigned_by' => auth()->id(),
                'task_name' => $data['task_name'],
                'vertical' => $data['vertical'],
                'task_nature' => $data['task_nature'],
                'party_type' => $data['party_type'],
                'party_name' => $data['party_name'],
                'contact_person' => $data['contact_person'],
                'mobile_number' => $data['mobile_number'],
                'priority' => $data['priority'],
                'due_at' => $data['due_at'],
                'designer_id' => $data['designer_id'],
                'total_creatives' => $data['total_creatives'],
                'status' => 'assigned_tasks',
                'requirements' => [],
            ]);

            $task->update([
                'task_id' => sprintf('DT-%s-%05d', now()->format('Y'), $task->id),
            ]);

            DesignTaskStatusHistory::create([
                'design_task_id' => $task->id,
                'from_status' => null,
                'to_status' => 'assigned_tasks',
                'changed_by' => auth()->id(),
                'change_source' => 'task_created',
                'note' => 'Task created by '.auth()->user()->name.'.',
                'created_at' => $task->created_at,
                'updated_at' => $task->created_at,
            ]);

            $designerName = User::query()->whereKey($data['designer_id'])->value('name') ?? 'Designer';
            DesignTaskStatusHistory::create([
                'design_task_id' => $task->id,
                'from_status' => 'assigned_tasks',
                'to_status' => 'assigned_tasks',
                'changed_by' => auth()->id(),
                'change_source' => 'task_assigned',
                'note' => 'Task assigned to '.$designerName.'.',
                'created_at' => $task->assigned_at,
                'updated_at' => $task->assigned_at,
            ]);

            return $task->fresh();
        });

        $rootFolder = trim((string) env('DO_SPACES_ROOT', 'design_task_manager'), '/');

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
            foreach (self::SINGLE_FILES as $field) {
                if ($request->hasFile($field)) {
                    $requirements[$field] = $this->storeSingleFile(
                        file: $request->file($field),
                        directory: "{$taskFolder}/{$field}",
                        taskId: $task->task_id,
                        fieldName: $field
                    );
                }
            }

            foreach (self::ARRAY_FILES as $field) {
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

        return redirect()
            ->route('bd.tasks.show', $task)
            ->with('success', 'Design task created successfully.');
    }

    public function show(DesignTask $task)
    {
        $task->load(['designer', 'assigner']);

        return view('bd.tasks.show', compact('task'));
    }


    private function storeSingleFile(
        UploadedFile $file,
        string $directory,
        string $taskId,
        string $fieldName,
        int $sequence = 1
    ): string {
        if (! $file->isValid()) {
            throw new \RuntimeException(
                "The upload {$file->getClientOriginalName()} is not valid."
            );
        }

        $originalBaseName = pathinfo(
            $file->getClientOriginalName(),
            PATHINFO_FILENAME
        );

        $cleanOriginalName = Str::slug($originalBaseName);

        if ($cleanOriginalName === '') {
            $cleanOriginalName = 'uploaded-file';
        }

        $cleanFieldName = Str::slug(
            str_replace('_', '-', $fieldName)
        );

        $extension = strtolower(
            $file->getClientOriginalExtension()
        );

        if ($extension === '') {
            $extension = $file->extension() ?: 'bin';
        }

        $timestamp = now()->format('Ymd-His-v');

        $fileName = sprintf(
            '%s__%s__%s__%s__%02d.%s',
            $taskId,
            $cleanFieldName,
            $cleanOriginalName,
            $timestamp,
            $sequence,
            $extension
        );

        $path = $file->storePubliclyAs(
            $directory,
            $fileName,
            'spaces'
        );

        if ($path === false) {
            throw new \RuntimeException(
                "The upload {$file->getClientOriginalName()} could not be saved."
            );
        }

        return $path;
    }

    private function storeMultipleFiles(
        array|UploadedFile|null $files,
        string $directory,
        string $taskId,
        string $fieldName
    ): array {
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        $paths = [];

        foreach ($files ?? [] as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $paths[] = $this->storeSingleFile(
                file: $file,
                directory: $directory,
                taskId: $taskId,
                fieldName: $fieldName,
                sequence: $index + 1
            );
        }

        return $paths;
    }

    private function requirementRules(Request $request): array
    {
        $vertical = $request->input('vertical');
        $nature = $request->input('task_nature');

        $common = [
            'description' => ['nullable', 'string', 'max:10000'],
            'brand_name' => ['nullable', 'string', 'max:180'],
            'creative_contact_person' => ['nullable', 'string', 'max:120'],
            'creative_mobile_number' => ['nullable', 'digits:10'],
            'address' => ['nullable', 'string', 'max:3000'],
            'company_details_other' => ['nullable', 'string', 'max:5000'],
            'brand_details' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'vehicle_quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'email_id' => ['nullable', 'email', 'max:255'],
            'website_link' => ['nullable', 'url', 'max:2048'],
            'instagram_link' => ['nullable', 'url', 'max:2048'],
            'facebook_link' => ['nullable', 'url', 'max:2048'],
            'mockup_type' => ['nullable', 'string', 'max:120'],
            'roadshow_subtype' => ['nullable', 'string', 'max:180'],
            'vehicle_type' => ['nullable', 'string', 'max:180'],
            'media' => ['nullable', 'string', 'max:180'],
            'signage_subtype' => ['nullable', 'string', 'max:180'],
            'pop_subtype' => ['nullable', 'string', 'max:180'],
            'events_subtype' => ['nullable', 'string', 'max:180'],
            'design_type' => ['nullable', 'string', 'max:180'],
            'ratio' => ['nullable', 'string', 'max:50'],
            'board_width' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'board_height' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
        ];

        foreach (self::SINGLE_FILES as $field) {
            $common[$field] = $field === 'client_audio'
                ? ['nullable', 'file', 'mimes:mp3,wav,m4a,aac,ogg', 'max:51200']
                : ['nullable', 'file', 'max:102400'];
        }

        foreach (self::ARRAY_FILES as $field) {
            $common[$field] = ['nullable', 'array', 'max:20'];
            $common["{$field}.*"] = ['file', 'max:102400'];
        }

        $required = match ("{$vertical}.{$nature}") {
            'outdoor.mockup_requirements' => ['description', 'mockup_type', 'board_width', 'board_height'],
            'outdoor.creative_adaptation' => ['description', 'board_width', 'board_height'],
            'outdoor.new_creative_design' => ['description', 'brand_name'],
            'outdoor.cutout_size_calculation' => ['description', 'hoarding_artwork'],

            'roadshow.creative_adaptation_requirements' => ['roadshow_subtype', 'vehicle_type', 'media'],
            'roadshow.new_creative_design' => ['roadshow_subtype', 'vehicle_type', 'media'],

            'fixtures.design_with_creative' => ['description', 'recce_report', 'fixture_details'],
            'fixtures.design_without_creative' => ['description', 'recce_report', 'fixture_details'],

            'signage.mockup' => ['description', 'recce_report'],
            'signage.creative_adaptation' => ['description', 'creative', 'material_specifications'],
            'signage.new_creative' => ['description', 'recce_report', 'material_specifications'],
            'signage.technical_drawing' => ['description', 'recce_report', 'material_specifications'],
            'signage.three_d_design' => ['signage_subtype', 'description', 'recce_report', 'material_specifications'],
            'signage.technical_and_three_d' => ['description', 'recce_report', 'material_specifications'],

            'pop_offsets.mockup_design' => ['description'],
            'pop_offsets.design_adaptation' => ['description'],
            'pop_offsets.creative_design' => ['pop_subtype', 'description'],

            'digital_marketing.proposal' => ['description'],
            'digital_marketing.logo_design' => ['description'],
            'digital_marketing.poster_design' => ['description'],
            'digital_marketing.video_design' => ['description'],

            'events_activations.proposal_designs' => ['description', 'requirement_list'],
            'events_activations.element_design_with_creative' => ['description', 'creative', 'recce_report', 'requirement_list'],
            'events_activations.element_design_without_creative' => ['description'],
            'events_activations.three_d_layout' => ['events_subtype', 'description', 'requirement_list'],
            default => [],
        };

        foreach ($required as $field) {
            $common[$field][0] = 'required';
        }

        if ($vertical === 'roadshow') {
            $common['description'] = ['nullable', 'required_without:description_upload', 'string', 'max:10000'];
            $common['description_upload'] = ['nullable', 'required_without:description', 'file', 'max:102400'];
        }

        return $common;
    }
}