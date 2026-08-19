<?php

namespace App\Http\Controllers\Bd;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
        'events_activations' => ['proposal_designs', 'element_design_with_creative', 'element_design_without_creative', 'three_d_layout'],
        'media' => ['creative_adaptation', 'own_creative'],
    ];

    private const FILE_FIELDS = [
        'supporting_documents', 'content_images', 'logo_images', 'reference_images', 'additional_attachments',
        'site_photo', 'creative', 'reference_image', 'company_details_document', 'hoarding_artwork',
        'description_upload', 'vehicle_details', 'brand_details_upload', 'recce_report', 'client_format_manual',
        'fixture_details', 'material_specifications', 'dealer_details', 'technical_drawing', 'element_list',
        'requirement_list', 'brand_guidelines', 'previous_logo', 'client_audio', 'dimension_upload', 'size_upload',
        'video_clip', 'existing_audio_creative', 'sample_video_clip', 'creative_content_upload', 'logo_brand_image',
        'company_details_upload', 'attachments',
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
            'contact_person' => ['nullable', 'string', 'max:120'],
            'mobile_number' => ['nullable', 'digits:10'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_at' => [
                'required',
                'date',
                'after_or_equal:today',
                'before_or_equal:'.$this->maximumAllowedDueDate()->format('Y-m-d H:i:s'),
            ],
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

        $dimensionRows = collect($data['dimension_rows'] ?? [])
            ->filter(function ($row): bool {
                if (! is_array($row)) {
                    return false;
                }

                return filled($row['name'] ?? null)
                    || filled($row['width'] ?? null)
                    || filled($row['height'] ?? null);
            })
            ->map(function ($row): array {
                $width = (float) ($row['width'] ?? 0);
                $height = (float) ($row['height'] ?? 0);

                return [
                    'name' => trim((string) ($row['name'] ?? '')),
                    'width' => $width,
                    'height' => $height,
                    'unit' => 'feet',
                    'area' => round($width * $height, 2),
                ];
            })
            ->values()
            ->all();

        unset($requirements['dimension_rows']);

        if ($dimensionRows !== []) {
            $requirements['board_details'] = $dimensionRows;
        }

        $sizeRows = collect($data['size_rows'] ?? [])
            ->filter(function ($row): bool {
                if (! is_array($row)) {
                    return false;
                }

                return filled($row['name'] ?? null)
                    || filled($row['width'] ?? null)
                    || filled($row['height'] ?? null);
            })
            ->map(function ($row): array {
                $width = (float) ($row['width'] ?? 0);
                $height = (float) ($row['height'] ?? 0);

                return [
                    'name' => trim((string) ($row['name'] ?? '')),
                    'width' => $width,
                    'height' => $height,
                    'unit' => 'feet',
                    'area' => round($width * $height, 2),
                ];
            })
            ->values()
            ->all();

        unset($requirements['size_rows']);

        if ($sizeRows !== []) {
            $requirements['size_details'] = $sizeRows;
        }

        $mediaSizeRows = collect($data['media_size_rows'] ?? [])
            ->filter(fn ($row) => is_array($row))
            ->filter(fn ($row) =>
                filled($row['name'] ?? null)
                || filled($row['width'] ?? null)
                || filled($row['height'] ?? null)
                || filled($row['ratio'] ?? null)
            )
            ->map(fn ($row) => [
                'name' => trim((string) ($row['name'] ?? '')),
                'width' => (float) ($row['width'] ?? 0),
                'height' => (float) ($row['height'] ?? 0),
                'ratio' => trim((string) ($row['ratio'] ?? '')),
            ])
            ->values()
            ->all();

        unset($requirements['media_size_rows']);

        if ($mediaSizeRows !== []) {
            $requirements['creative_size_details'] = $mediaSizeRows;
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
                'contact_person' => $data['contact_person'] ?? '',
                'mobile_number' => $data['mobile_number'] ?? '',
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
                'note' => 'Task Created',
            ]);

            DesignTaskStatusHistory::create([
                'design_task_id' => $task->id,
                'from_status' => 'assigned_tasks',
                'to_status' => 'assigned_tasks',
                'changed_by' => auth()->id(),
                'change_source' => 'task_assigned',
                'note' => 'Task Assigned',
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
            'other_details' => ['nullable', 'string', 'max:10000'],
            'reference_notes' => ['nullable', 'string', 'max:10000'],
            'media_type' => ['nullable', Rule::in(['Theatre Ads', 'Newspaper Ads', 'TV Ads'])],
            'brand_name' => ['nullable', 'string', 'max:180'],
            'creative_contact_person' => ['nullable', 'string', 'max:120'],
            'creative_mobile_number' => ['nullable', 'digits:10'],
            'address' => ['nullable', 'string', 'max:3000'],
            'company_details_other' => ['nullable', 'string', 'max:5000'],
            'company_details' => ['nullable', 'string', 'max:10000'],
            'brand_details' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'vehicle_quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'email_id' => ['nullable', 'email', 'max:255'],
            'website_link' => ['nullable', 'url', 'max:2048'],
            'instagram_link' => ['nullable', 'url', 'max:2048'],
            'facebook_link' => ['nullable', 'url', 'max:2048'],
            'mockup_type' => ['nullable', 'string', 'max:120'],
            'roadshow_subtype' => ['nullable', 'string', 'max:180'],
            'vehicle_type' => [
                'nullable',
                Rule::in([
                '3 Side LED 14 feet',
                '3 Side LED 18 feet',
                '7x5 LED Hybrid 8 feet',
                'Box Model Triangle Roof',
                'Center Portion Triangle Roof',
                'Center Portion Without Roof',
                'L-Model Box Roof with Utility Room',
                'L-Model Box Roof',
                'L-Model Without Roof',
                'L-Shape LED',
                'Single Side LED 17 feet',
                'Static Model'
                ]),
            ],
            'media' => ['nullable', 'string', 'max:180'],
            'signage_subtype' => ['nullable', 'string', 'max:180'],
            'pop_subtype' => ['nullable', 'string', 'max:180'],
            'events_subtype' => ['nullable', 'string', 'max:180'],
            'design_type' => ['nullable', 'string', 'max:180'],
            'product_type' => ['nullable', Rule::in([
                'Leaflets', 'Poster', 'Brochure', 'Visiting Card', 'Pocket Card', 'Dangler',
                'Roll Up Standee', 'Sunpack Sheet', 'Calendar', 'ID Card', 'Other',
            ])],
            'product_type_other' => ['nullable', 'required_if:product_type,Other', 'string', 'max:180'],
            'vehicle_type_other' => ['nullable', 'required_if:vehicle_type,Other', 'string', 'max:180'],
            'media_other' => ['nullable', 'required_if:media,Other', 'string', 'max:180'],
            'media_task_nature' => ['nullable', Rule::in(['Creative Adaptation', 'Own Creative'])],
            'theatre_screen_name' => ['nullable', 'string', 'max:180'],
            'ad_type' => ['nullable', Rule::in(['Slide', 'Video'])],
            'screen_width' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'screen_height' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'screen_ratio' => ['nullable', 'string', 'max:100'],
            'fm_station' => ['nullable', 'string', 'max:180'],
            'tv_type' => ['nullable', Rule::in(['Local', 'Satellite', 'Channel'])],
            'creative_width' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'creative_height' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'size_unit' => ['nullable', Rule::in(['px', 'mm', 'cm', 'inch', 'ft'])],
            'creative_content_details' => ['nullable', 'string', 'max:10000'],
            'ratio' => ['nullable', 'string', 'max:50'],
            'outdoor_type' => ['nullable', Rule::in([
                'Bus Shelter', 'Unipole', 'Standard', 'Auto Branding', 'Pole Kiosk', 'Digital', 'Signal Post',
            ])],
            'board_type' => ['nullable', Rule::in(['Static', 'Digital'])],
            'dimension_rows' => [
                'nullable',
                'array',
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $vertical): void {
                    if ($vertical !== 'outdoor') {
                        return;
                    }

                    $rows = is_array($value) ? $value : [];
                    $nonEmptyRows = collect($rows)->filter(function ($row): bool {
                        if (! is_array($row)) {
                            return false;
                        }

                        return filled($row['name'] ?? null)
                            || filled($row['width'] ?? null)
                            || filled($row['height'] ?? null);
                    });

                    foreach ($nonEmptyRows as $row) {
                        if (
                            blank($row['name'] ?? null)
                            || blank($row['width'] ?? null)
                            || blank($row['height'] ?? null)
                        ) {
                            $fail('Complete Name, Width and Height for every Board Details row, or remove the incomplete row.');
                            return;
                        }
                    }

                    $hasCompleteRow = $nonEmptyRows->contains(function ($row): bool {
                        return filled($row['name'] ?? null)
                            && is_numeric($row['width'] ?? null)
                            && (float) $row['width'] > 0
                            && is_numeric($row['height'] ?? null)
                            && (float) $row['height'] > 0;
                    });

                    if (! $hasCompleteRow && ! $request->hasFile('dimension_upload')) {
                        $fail('Provide at least one complete Board Details row or upload a Board Details file.');
                    }
                },
            ],
            'dimension_rows.*.name' => ['nullable', 'string', 'max:180'],
            'dimension_rows.*.width' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'dimension_rows.*.height' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'dimension_rows.*.area' => ['nullable', 'numeric', 'min:0'],
            'media_size_rows' => [
                'nullable',
                'array',
                function (string $attribute, mixed $value, \Closure $fail) use ($vertical): void {
                    if ($vertical !== 'media') {
                        return;
                    }

                    $rows = collect(is_array($value) ? $value : [])
                        ->filter(fn ($row) => is_array($row))
                        ->filter(fn ($row) =>
                            filled($row['name'] ?? null)
                            || filled($row['width'] ?? null)
                            || filled($row['height'] ?? null)
                            || filled($row['ratio'] ?? null)
                        );

                    if ($rows->isEmpty()) {
                        $fail('Add at least one complete Creative Size Details row.');
                        return;
                    }

                    foreach ($rows as $row) {
                        if (
                            blank($row['name'] ?? null)
                            || ! is_numeric($row['width'] ?? null)
                            || (float) ($row['width'] ?? 0) <= 0
                            || ! is_numeric($row['height'] ?? null)
                            || (float) ($row['height'] ?? 0) <= 0
                            || blank($row['ratio'] ?? null)
                        ) {
                            $fail('Complete Name, Width, Height and Ratio for every Creative Size Details row.');
                            return;
                        }
                    }
                },
            ],
            'media_size_rows.*.name' => ['nullable', 'string', 'max:180'],
            'media_size_rows.*.width' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'media_size_rows.*.height' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'media_size_rows.*.ratio' => ['nullable', 'string', 'max:100'],

            'size_rows' => [
                'nullable',
                'array',
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $vertical, $nature): void {
                    if ($vertical !== 'pop_offsets' || ! in_array($nature, ['design_adaptation', 'creative_design'], true)) {
                        return;
                    }

                    $rows = is_array($value) ? $value : [];
                    $nonEmptyRows = collect($rows)->filter(function ($row): bool {
                        if (! is_array($row)) {
                            return false;
                        }

                        return filled($row['name'] ?? null)
                            || filled($row['width'] ?? null)
                            || filled($row['height'] ?? null);
                    });

                    foreach ($nonEmptyRows as $row) {
                        if (
                            blank($row['name'] ?? null)
                            || blank($row['width'] ?? null)
                            || blank($row['height'] ?? null)
                        ) {
                            $fail('Complete Name, Width and Height for every Size Details row, or remove the incomplete row.');
                            return;
                        }
                    }

                    $hasCompleteRow = $nonEmptyRows->contains(function ($row): bool {
                        return filled($row['name'] ?? null)
                            && is_numeric($row['width'] ?? null)
                            && (float) $row['width'] > 0
                            && is_numeric($row['height'] ?? null)
                            && (float) $row['height'] > 0;
                    });

                    if (! $hasCompleteRow && ! $request->hasFile('size_upload')) {
                        $fail('Provide at least one complete Size Details row or upload a Size Details file.');
                    }
                },
            ],
            'size_rows.*.name' => ['nullable', 'string', 'max:180'],
            'size_rows.*.width' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'size_rows.*.height' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'size_rows.*.area' => ['nullable', 'numeric', 'min:0'],

        ];

        foreach (self::FILE_FIELDS as $field) {
            $common[$field] = ['nullable', 'array', 'max:20'];
            $common["{$field}.*"] = $field === 'client_audio'
                ? ['file', 'mimes:mp3,wav,m4a,aac,ogg', 'max:51200']
                : ['file', 'max:102400'];
        }

        $required = match ("{$vertical}.{$nature}") {
            'outdoor.mockup_requirements' => ['description', 'mockup_type'],
            'outdoor.creative_adaptation' => ['description'],
            'outdoor.new_creative_design' => ['description', 'brand_name'],
            'outdoor.cutout_size_calculation' => ['description', 'hoarding_artwork'],

            'roadshow.creative_adaptation_requirements' => ['roadshow_subtype', 'vehicle_type'],
            'roadshow.new_creative_design' => ['roadshow_subtype', 'vehicle_type'],

            'fixtures.design_with_creative' => ['description', 'recce_report', 'fixture_details'],
            'fixtures.design_without_creative' => ['description', 'recce_report', 'fixture_details'],

            'signage.mockup' => ['description', 'recce_report'],
            'signage.creative_adaptation' => ['description', 'creative', 'material_specifications'],
            'signage.new_creative' => ['description', 'recce_report', 'material_specifications'],
            'signage.technical_drawing' => ['description', 'recce_report'],
            'signage.three_d_design' => ['description', 'recce_report'],
            'signage.technical_and_three_d' => ['description', 'recce_report'],

            'pop_offsets.mockup_design' => ['description', 'product_type', 'company_details'],
            'pop_offsets.design_adaptation' => ['description', 'product_type', 'company_details'],
            'pop_offsets.creative_design' => ['description', 'product_type', 'company_details'],


            'events_activations.proposal_designs' => ['description', 'requirement_list'],
            'events_activations.element_design_with_creative' => ['description', 'creative', 'recce_report', 'requirement_list'],
            'events_activations.element_design_without_creative' => ['description'],
            'events_activations.three_d_layout' => ['description', 'requirement_list'],

            'media.creative_adaptation' => ['media_type', 'description', 'creative'],
            'media.own_creative' => ['media_type', 'description', 'company_details'],

            default => [],
        };

        foreach ($required as $field) {
            $common[$field][0] = 'required';
        }

        if ($vertical === 'outdoor') {
            $common['outdoor_type'][0] = 'required';

            if (in_array($nature, ['mockup_requirements', 'creative_adaptation', 'new_creative_design'], true)) {
                $common['board_type'][0] = 'required';
            }
        }

        if ($vertical === 'roadshow') {
            $common['description'] = ['nullable', 'required_without:description_upload', 'string', 'max:10000'];
            $common['description_upload'] = ['nullable', 'required_without:description', 'array', 'max:20'];
        }

        if ($vertical === 'media') {
            $common['media_type'][0] = 'required';
            $common['media_size_rows'][0] = 'required';
            $common['description'][0] = 'required';

            if ($nature === 'creative_adaptation') {
                $common['creative'][0] = 'required';
            }

            if ($nature === 'own_creative') {
                $common['company_details'][0] = 'required';
            }
        }

        return $common;
    }

    private function maximumAllowedDueDate(): Carbon
    {
        $date = now()->copy()->startOfDay();
        $workingDaysAdded = 0;

        while ($workingDaysAdded < 7) {
            $date->addDay();

            if (! $date->isWeekend()) {
                $workingDaysAdded++;
            }
        }

        return $date->endOfDay();
    }

}
