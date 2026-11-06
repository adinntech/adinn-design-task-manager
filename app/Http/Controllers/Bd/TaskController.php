<?php

namespace App\Http\Controllers\Bd;

use App\Http\Controllers\Controller;
use App\Models\ClientDetail;
use App\Models\DesignTask;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use App\Services\TaskNotificationService;
use App\Services\ZohoAttendanceService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    /**
     * Canonical vertical list + display labels — the single source of truth
     * reused by the Admin Designer-profile form and Designer info displays so
     * they never drift from the keys validated below in NATURES.
     */
    public const VERTICALS = [
        'outdoor' => 'Outdoor',
        'roadshow' => 'Road Show',
        'fixtures' => 'Fixtures',
        'signage' => 'Signage',
        'pop_offsets' => 'Print / POP',
        'events_activations' => 'Events & Activations',
        'media' => 'Media',
        'digital_inhouse' => 'Digital / Inhouse',
    ];

    protected const NATURES = [
        'outdoor' => ['mockup_requirements', 'creative_adaptation', 'new_creative_design', 'cutout_size_calculation'],
        'roadshow' => ['creative_adaptation_requirements', 'new_creative_design'],
        'fixtures' => ['design_with_creative', 'design_without_creative'],
        'signage' => ['mockup', 'creative_adaptation', 'new_creative', 'technical_drawing', 'three_d_design', 'technical_and_three_d'],
        'pop_offsets' => ['mockup_design', 'design_adaptation', 'creative_design'],
        'events_activations' => ['proposal_designs', 'element_design_with_creative', 'element_design_without_creative', 'three_d_layout'],
        'media' => ['creative_adaptation', 'own_creative'],
        'digital_inhouse' => ['digital', 'inhouse'],
    ];

    protected const FILE_FIELDS = [
        'supporting_documents', 'content_images', 'logo_images', 'reference_images', 'additional_attachments',
        'site_photo', 'creative', 'reference_image', 'company_details_document', 'hoarding_artwork',
        'description_upload', 'vehicle_details', 'brand_details_upload', 'recce_report', 'client_format_manual',
        'fixture_details', 'material_specifications', 'dealer_details', 'technical_drawing', 'element_list',
        'requirement_list', 'brand_guidelines', 'previous_logo', 'client_audio', 'dimension_upload', 'size_upload',
        'video_clip', 'existing_audio_creative', 'sample_video_clip', 'creative_content_upload', 'logo_brand_image',
        'company_details_upload', 'attachments',
    ];

    /**
     * The client call-recording reference accepts only MP3/WAV — enforced by
     * extension AND real MIME type (not Laravel's `mimes:` rule, which infers
     * extension from MIME and can misclassify browser-supplied WAV variants
     * such as audio/x-wav).
     */
    protected const AUDIO_EXTENSIONS = ['mp3', 'wav'];

    protected const AUDIO_MIME_TYPES = [
        'audio/mpeg', 'audio/mp3',
        'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave', 'audio/vnd.wav',
    ];

    /** Per-file cap for every non-audio FILE_FIELDS entry (reference/knowledge files). */
    protected const MAX_GENERAL_FILE_KB = 6 * 1024 * 1024; // 6 GB

    /** Call recordings don't need the 6 GB ceiling; keep them generous but bounded. */
    protected const MAX_AUDIO_FILE_KB = 1024 * 1024; // 1 GB

    /** Above this, a general file must be a ZIP — below it, existing type rules apply unchanged. */
    protected const ZIP_ONLY_THRESHOLD_BYTES = 500 * 1024 * 1024; // 500 MB

    public function create(Request $request)
    {
        $designers = User::query()
            ->where('role', 'designer')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'experienced_verticals', 'skills']);

        $draft = null;
        $draftFiles = [];

        if ($request->query('draft')) {
            $draft = DesignTask::query()
                ->where('assigned_by', $request->user()->id)
                ->where('status', 'draft')
                ->findOrFail((int) $request->query('draft'));

            $draftFiles = collect($this->collectRequirementAttachments(
                is_array($draft->requirements) ? $draft->requirements : []
            ))->mapWithKeys(fn (array $group) => [$group['key'] => $group['files']])->all();
        }

        // An In-house draft is edited on the In-house tab, so keep it out of the Client form's draft handling.
        $inhouseDraft = null;

        if ($draft && $draft->task_category === 'in_house') {
            $inhouseDraft = $draft;
            $draft = null;
            $draftFiles = [];
        }

        return view('bd.tasks.create', compact('designers', 'draft', 'draftFiles', 'inhouseDraft'));
    }

    /**
     * Informational-only workload snapshot for the "Designer Availability" meter
     * shown under the Preferred Designer field. Deterministic count-based formula
     * (no AI) reusing the same "active = not completed" definition already used
     * by the Kanban boards (App\Livewire\*\TaskKanban) — never affects assignment.
     */
    public function availability(User $designer)
    {
        abort_unless($designer->role === 'designer' && $designer->is_active, 404);

        return response()->json(app(\App\Services\DesignerAvailabilityService::class)->snapshot($designer));
    }

    /**
     * Live client-name autocomplete for the task-create form. BD-authenticated
     * via the enclosing route group; returns only the fields the form needs.
     */
    public function searchClients(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return response()->json([]);
        }

        $clients = ClientDetail::query()
            ->where('client_name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%')
            ->orderBy('client_name')
            ->limit(10)
            ->get(['id', 'client_name', 'contact_person_name', 'mobile_number']);

        return response()->json($clients);
    }

    /**
     * Live search for "Clone Existing Ticket" — scoped to the requesting BD's
     * own non-draft tasks only (security-critical: filtered server-side, not
     * just by the frontend). Returns only the fields the dropdown needs.
     */
    public function cloneSearch(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return response()->json([]);
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        $tasks = DesignTask::query()
            ->where('assigned_by', $request->user()->id)
            ->where('status', '!=', 'draft')
            ->when(
                $request->query('category') === 'in_house',
                fn ($query) => $query->where('task_category', 'in_house'),
                fn ($query) => $query->where('task_category', '!=', 'in_house')
            )
            ->where(function ($query) use ($like) {
                $query->where('task_id', 'like', $like)
                    ->orWhere('zoho_project_number', 'like', $like)
                    ->orWhere('task_name', 'like', $like)
                    ->orWhere('party_name', 'like', $like)
                    ->orWhere('contact_person', 'like', $like)
                    ->orWhere('mobile_number', 'like', $like);
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'task_id', 'zoho_project_number', 'task_name', 'party_name', 'party_type']);

        return response()->json($tasks);
    }

    /**
     * Full clone payload for one ticket, fetched only after the BD picks it
     * from the search dropdown. Ownership is enforced here (not just by the
     * search list) so a crafted request for another BD's ticket is blocked.
     */
    public function cloneData(Request $request, DesignTask $task)
    {
        abort_unless((int) $task->assigned_by === (int) $request->user()->id, 403);

        $requirements = is_array($task->requirements) ? $task->requirements : [];

        $rowFieldMap = [
            'board_details' => 'dimension_rows',
            'size_details' => 'size_rows',
            'creative_size_details' => 'media_size_rows',
        ];

        $flatRows = [];
        foreach ($rowFieldMap as $reqKey => $jsKey) {
            if (array_key_exists($reqKey, $requirements)) {
                $flatRows[$jsKey] = $requirements[$reqKey];
            }
        }

        $fileFieldKeys = array_values(array_intersect(array_keys($requirements), self::FILE_FIELDS));

        $textRequirements = collect($requirements)
            ->except(array_merge(array_keys($rowFieldMap), $fileFieldKeys))
            ->all();

        return response()->json([
            'task' => [
                'task_name' => $task->task_name,
                'vertical' => $task->vertical,
                'zoho_project_number' => $task->zoho_project_number,
                'task_nature' => $task->task_nature,
                'party_type' => $task->party_type,
                'party_name' => $task->party_name,
                'contact_person' => $task->contact_person,
                'mobile_number' => $task->mobile_number,
                'priority' => $task->priority,
                'designer_id' => $task->designer_id,
                'total_creatives' => $task->total_creatives,
            ],
            'requirements' => array_merge($flatRows, $textRequirements),
            'attachments' => collect($this->collectRequirementAttachments($requirements))
                ->mapWithKeys(fn (array $group) => [$group['key'] => $group['files']])
                ->all(),
            'source_task_id' => $task->id,
        ]);
    }

    /**
     * Validates the posted "kept cloned attachment" paths against the source
     * task's OWN stored requirement files — the security boundary that stops
     * a crafted request from copying an arbitrary Spaces path, or another
     * BD's ticket's files, into the new task.
     */
    private function resolveClonedAttachments(Request $request): array
    {
        $sourceTaskId = (int) $request->input('cloned_from_task_id', 0);
        $requested = json_decode((string) $request->input('cloned_source_files_json', '{}'), true);

        if ($sourceTaskId <= 0 || ! is_array($requested) || $requested === []) {
            return ['source_id' => null, 'fields' => []];
        }

        $sourceTask = DesignTask::query()
            ->where('assigned_by', $request->user()->id)
            ->find($sourceTaskId);

        if (! $sourceTask) {
            return ['source_id' => null, 'fields' => []];
        }

        $sourceGroups = collect($this->collectRequirementAttachments(
            is_array($sourceTask->requirements) ? $sourceTask->requirements : []
        ))->keyBy('key');

        $resolved = [];

        foreach ($requested as $field => $paths) {
            if (! in_array($field, self::FILE_FIELDS, true) || ! is_array($paths)) {
                continue;
            }

            $allowedPaths = collect($sourceGroups->get((string) $field)['files'] ?? [])->pluck('path')->all();
            $keep = array_values(array_intersect($paths, $allowedPaths));

            if ($keep !== []) {
                $resolved[$field] = $keep;
            }
        }

        return ['source_id' => $sourceTask->id, 'fields' => $resolved];
    }

    /**
     * Server-side object copy (no download/re-upload) so a cloned reference
     * file becomes an independent object under the new task's own folder,
     * named with the same convention storeSingleFile() uses for new uploads.
     */
    private function copyClonedFile(string $sourcePath, string $directory, string $taskId, string $fieldName, int $sequence): string
    {
        $cleanOriginalName = Str::slug(pathinfo($sourcePath, PATHINFO_FILENAME)) ?: 'cloned-file';
        $cleanFieldName = Str::slug(str_replace('_', '-', $fieldName));
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) ?: 'bin';
        $timestamp = now()->format('Ymd-His-v');

        $fileName = sprintf('%s__%s__%s__%s__%02d.%s', $taskId, $cleanFieldName, $cleanOriginalName, $timestamp, $sequence, $extension);
        $destination = trim($directory, '/').'/'.$fileName;

        if (! Storage::disk('spaces')->copy($sourcePath, $destination)) {
            throw new \RuntimeException('A cloned reference file could not be copied.');
        }

        return $destination;
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
            'designer_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'designer')->where('is_active', true)),
            ],
            'total_creatives' => ['required', 'integer', 'min:1', 'max:9999'],
        ];

        $rules = array_merge($rules, $this->requirementRules($request));
        $data = $request->validate($rules);

        $requirements = $this->buildRequirementsPayload($data);

        $isDraftConversion = (bool) $request->input('draft_id');
        $existingDraft = null;

        if ($isDraftConversion) {
            $existingDraft = DesignTask::query()
                ->where('assigned_by', $request->user()->id)
                ->where('status', 'draft')
                ->findOrFail((int) $request->input('draft_id'));
        }

        $rootFolder = trim((string) env('DO_SPACES_ROOT', 'design_task_manager'), '/');

        if ($isDraftConversion && $existingDraft) {
            // Convert-a-draft path: uploads happen BEFORE any DB mutation so an
            // upload failure leaves the draft fully intact (no half-conversion).
            // The new files land in the final task folder under the exact DT id
            // this conversion will assign, so storage and record always agree.
            $finalTaskId = sprintf('DT-%s-%05d', now()->format('Y'), $existingDraft->id);

            $requirements = array_merge(
                is_array($existingDraft->requirements) ? $existingDraft->requirements : [],
                $requirements
            );

            // A draft moved off Digital / Inhouse must not carry its vertical-only values.
            if ($data['vertical'] !== 'digital_inhouse') {
                unset($requirements['creative_type'], $requirements['brief']);
            }

            $taskNameSlug = Str::slug($data['task_name']);
            $taskNatureSlug = Str::slug(str_replace('_', '-', $data['task_nature']));
            $verticalSlug = Str::slug(str_replace('_', '-', $data['vertical']));

            $taskFolder = implode('/', [
                $rootFolder,
                now()->format('Y'),
                $verticalSlug,
                "{$finalTaskId}_{$taskNameSlug}",
                $taskNatureSlug,
            ]);

            $requirements = $this->removeMarkedFiles($requirements, $this->parseRemovedFiles($request));

            try {
                foreach (self::FILE_FIELDS as $field) {
                    if ($request->hasFile($field)) {
                        $requirements[$field] = $this->storeMultipleFiles(
                            files: $request->file($field),
                            directory: "{$taskFolder}/{$field}",
                            taskId: $finalTaskId,
                            fieldName: $field
                        );
                    }
                }
            } catch (\Throwable $exception) {
                Storage::disk('spaces')->deleteDirectory($taskFolder);
                report($exception);

                return back()
                    ->withInput()
                    ->withErrors([
                        'upload' => 'The task could not be created because one or more files could not be saved. Please try again.',
                    ]);
            }

            $task = DB::transaction(function () use ($data, $existingDraft, $finalTaskId, $requirements): DesignTask {
                $existingDraft->update([
                    'assigned_at' => now(),
                    'assigned_by' => auth()->id(),
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
                    'designer_id' => $data['designer_id'],
                    'total_creatives' => $data['total_creatives'],
                    'status' => 'assigned_tasks',
                    'task_id' => $finalTaskId,
                    'requirements' => $requirements,
                ]);

                $task = $existingDraft->fresh();

                DesignTaskStatusHistory::create([
                    'design_task_id' => $task->id,
                    'from_status' => 'draft',
                    'to_status' => 'assigned_tasks',
                    'changed_by' => auth()->id(),
                    'change_source' => 'draft_created',
                    'note' => 'Task assigned from draft.',
                ]);

                DesignTaskStatusHistory::create([
                    'design_task_id' => $task->id,
                    'from_status' => 'assigned_tasks',
                    'to_status' => 'assigned_tasks',
                    'changed_by' => auth()->id(),
                    'change_source' => 'task_assigned',
                    'note' => 'Task Assigned',
                ]);

                return $task;
            });

            app(TaskNotificationService::class)->taskAssigned($task, auth()->user(), $task->designer ?? User::find($task->designer_id));

            ClientDetail::rememberFromTask($data['party_name'], $data['contact_person'] ?? null, $data['mobile_number'] ?? null);

            return redirect()
                ->route('bd.tasks.show', $task)
                ->with('success', 'Design task created successfully.');
        }

        $clonedAttachments = $this->resolveClonedAttachments($request);

        $task = DB::transaction(function () use ($data, $clonedAttachments): DesignTask {
            $task = DesignTask::create([
                'task_id' => 'PENDING-'.Str::uuid(),
                'assigned_at' => now(),
                'assigned_by' => auth()->id(),
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
                'designer_id' => $data['designer_id'],
                'total_creatives' => $data['total_creatives'],
                'status' => 'assigned_tasks',
                'requirements' => [],
                'cloned_from_task_id' => $clonedAttachments['source_id'],
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
                $paths = $request->hasFile($field)
                    ? $this->storeMultipleFiles(
                        files: $request->file($field),
                        directory: "{$taskFolder}/{$field}",
                        taskId: $task->task_id,
                        fieldName: $field
                    )
                    : [];

                foreach ($clonedAttachments['fields'][$field] ?? [] as $sourcePath) {
                    $paths[] = $this->copyClonedFile(
                        sourcePath: $sourcePath,
                        directory: "{$taskFolder}/{$field}",
                        taskId: $task->task_id,
                        fieldName: $field,
                        sequence: count($paths) + 1
                    );
                }

                if ($paths !== []) {
                    $requirements[$field] = $paths;
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

        app(TaskNotificationService::class)->taskAssigned($task, auth()->user(), $task->designer ?? User::find($task->designer_id));

        ClientDetail::rememberFromTask($data['party_name'], $data['contact_person'] ?? null, $data['mobile_number'] ?? null);

        return redirect()
            ->route('bd.tasks.show', $task)
            ->with('success', 'Design task created successfully.');
    }

    /**
     * Task natures offered on the BD "In-house" tab: every existing nature of the vertical
     * plus "Agreement" for all verticals, and "Vehicle Permit Document" for Road Show.
     * The Client form keeps using NATURES unchanged.
     */
    public static function inhouseNatures(): array
    {
        $natures = array_map(fn (array $list) => array_values(array_unique([...$list, 'agreement'])), self::NATURES);
        $natures['roadshow'][] = 'vehicle_permit_document';

        // "Others" lets BD / Designer type their own nature (stored as the typed text on the task).
        return array_map(fn (array $list) => [...$list, 'others'], $natures);
    }

    /** Folder every In-house task's files live in (final task id, vertical, name, nature). */
    protected function inhouseFolder(string $taskId, string $vertical, string $taskName, string $nature): string
    {
        return implode('/', [
            trim((string) env('DO_SPACES_ROOT', 'design_task_manager'), '/'),
            now()->format('Y'),
            Str::slug(str_replace('_', '-', $vertical ?: 'draft')),
            "{$taskId}_".Str::slug($taskName ?: 'untitled-task'),
            Str::slug(str_replace('_', '-', $nature ?: 'draft')),
        ]);
    }

    /** Newly uploaded files plus kept cloned files, stored under {folder}/attachments. Throws on failure. */
    protected function storeInhouseFiles(Request $request, array $clonedAttachments, string $folder, string $taskId): array
    {
        $paths = $request->hasFile('attachments')
            ? $this->storeMultipleFiles(
                files: $request->file('attachments'),
                directory: "{$folder}/attachments",
                taskId: $taskId,
                fieldName: 'attachments'
            )
            : [];

        foreach ($clonedAttachments['fields']['attachments'] ?? [] as $sourcePath) {
            $paths[] = $this->copyClonedFile(
                sourcePath: $sourcePath,
                directory: "{$folder}/attachments",
                taskId: $taskId,
                fieldName: 'attachments',
                sequence: count($paths) + 1
            );
        }

        return $paths;
    }

    /** The BD's own In-house draft named by draft_id, or null when none was sent. */
    private function ownedInhouseDraft(Request $request): ?DesignTask
    {
        $draftId = (int) $request->input('draft_id');

        if ($draftId <= 0) {
            return null;
        }

        return DesignTask::query()
            ->where('assigned_by', $request->user()->id)
            ->where('status', 'draft')
            ->where('task_category', 'in_house')
            ->findOrFail($draftId);
    }

    /** The nature to store: the typed text when "Others" was picked, otherwise the chosen key. */
    protected function resolveInhouseNature(array $data): string
    {
        return ($data['task_nature'] ?? '') === 'others'
            ? trim((string) ($data['task_nature_other'] ?? ''))
            : (string) $data['task_nature'];
    }

    protected function inhouseNatureRule(array $natures, Request $request): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request, $natures): void {
            if ($value !== null && $value !== '' && ! in_array($value, $natures[$request->input('vertical')] ?? [], true)) {
                $fail('The selected task nature is invalid for the chosen vertical.');
            }
        };
    }

    /**
     * BD-raised internal ("In-house") task: Task Name, Vertical, Task Nature, Description,
     * Attachments and Assigned Designer only. Everything else the shared task columns require
     * (party, priority, due date, creative count) gets a fixed default, and the task then follows
     * the exact same lifecycle as a Client task. Marked with task_category = 'in_house'.
     * Also converts a saved In-house draft (draft_id) into a real task.
     */
    public function storeInhouse(Request $request)
    {
        $natures = self::inhouseNatures();

        $data = $request->validate([
            'task_name' => ['required', 'string', 'max:180'],
            'vertical' => ['required', Rule::in(array_keys($natures))],
            'task_nature' => ['required', $this->inhouseNatureRule($natures, $request)],
            'task_nature_other' => ['nullable', 'required_if:task_nature,others', 'string', 'max:120'],
            'designer_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'designer')->where('is_active', true)),
            ],
            'total_creatives' => ['required', 'integer', 'min:1', 'max:9999'],
            'description' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*' => $this->generalFileRule(),
            'draft_id' => ['nullable', 'integer'],
        ]);

        $nature = $this->resolveInhouseNature($data);
        $draft = $this->ownedInhouseDraft($request);
        $clonedAttachments = $this->resolveClonedAttachments($request);

        $columns = [
            'assigned_at' => now(),
            'assigned_by' => auth()->id(),
            'task_name' => $data['task_name'],
            'vertical' => $data['vertical'],
            'task_nature' => $nature,
            'task_category' => 'in_house',
            'party_type' => 'client',
            'party_name' => 'In-house',
            'contact_person' => '',
            'mobile_number' => '',
            'priority' => 'medium',
            'due_at' => $this->maximumAllowedDueDate(),
            'designer_id' => $data['designer_id'],
            'total_creatives' => (int) $data['total_creatives'],
            'status' => 'assigned_tasks',
        ];

        $history = function (DesignTask $task, ?string $from, string $source, string $note): void {
            DesignTaskStatusHistory::create([
                'design_task_id' => $task->id,
                'from_status' => $from,
                'to_status' => 'assigned_tasks',
                'changed_by' => auth()->id(),
                'change_source' => $source,
                'note' => $note,
            ]);
        };

        if ($draft) {
            // Convert-a-draft: upload new files first so a failure leaves the draft fully intact.
            $finalTaskId = sprintf('DT-%s-%05d', now()->format('Y'), $draft->id);
            $folder = $this->inhouseFolder($finalTaskId, $data['vertical'], $data['task_name'], $nature);

            try {
                $newPaths = $this->storeInhouseFiles($request, $clonedAttachments, $folder, $finalTaskId);
            } catch (\Throwable $exception) {
                Storage::disk('spaces')->deleteDirectory($folder);
                report($exception);

                return back()->withInput()->withErrors(['upload' => 'The task could not be created because one or more files could not be saved. Please try again.']);
            }

            $kept = $this->removeMarkedFiles(is_array($draft->requirements) ? $draft->requirements : [], $this->parseRemovedFiles($request));
            $paths = array_values(array_merge($kept['attachments'] ?? [], $newPaths));
            $requirements = ['description' => trim($data['description'])] + ($paths !== [] ? ['attachments' => $paths] : []);

            $task = DB::transaction(function () use ($draft, $columns, $finalTaskId, $requirements, $history): DesignTask {
                $draft->update($columns + ['task_id' => $finalTaskId, 'requirements' => $requirements]);
                $task = $draft->fresh();

                $history($task, 'draft', 'draft_created', 'Task assigned from draft.');
                $history($task, 'assigned_tasks', 'task_assigned', 'Task Assigned');

                return $task;
            });
        } else {
            $task = DB::transaction(function () use ($columns, $data, $clonedAttachments, $history): DesignTask {
                $task = DesignTask::create($columns + [
                    'task_id' => 'PENDING-'.Str::uuid(),
                    'requirements' => ['description' => trim($data['description'])],
                    'cloned_from_task_id' => $clonedAttachments['source_id'],
                ]);

                $task->update(['task_id' => sprintf('DT-%s-%05d', now()->format('Y'), $task->id)]);

                $history($task, null, 'task_created', 'Task Created');
                $history($task, 'assigned_tasks', 'task_assigned', 'Task Assigned');

                return $task->fresh();
            });

            if ($request->hasFile('attachments') || ($clonedAttachments['fields']['attachments'] ?? []) !== []) {
                $folder = $this->inhouseFolder($task->task_id, $task->vertical, $task->task_name, $task->task_nature);

                try {
                    $paths = $this->storeInhouseFiles($request, $clonedAttachments, $folder, $task->task_id);
                    $task->update(['requirements' => array_merge($task->requirements ?? [], ['attachments' => $paths])]);
                } catch (\Throwable $exception) {
                    Storage::disk('spaces')->deleteDirectory($folder);
                    $task->delete();
                    report($exception);

                    return back()->withInput()->withErrors(['upload' => 'The task could not be created because one or more files could not be saved. Please try again.']);
                }
            }
        }

        app(TaskNotificationService::class)->taskAssigned($task, auth()->user(), $task->designer ?? User::find($task->designer_id));

        return redirect()
            ->route('bd.tasks.show', $task)
            ->with('success', 'In-house task created successfully.');
    }

    /**
     * Save (or update) an In-house draft. Only the task name is required; the designer is parked in
     * requirements (not designer_id) so a draft never shows up on a Designer's board.
     */
    public function storeInhouseDraft(Request $request)
    {
        $natures = self::inhouseNatures();

        $data = $request->validate([
            'task_name' => ['required', 'string', 'max:180'],
            'vertical' => ['nullable', Rule::in(array_keys($natures))],
            'task_nature' => ['nullable', 'string', 'max:120', $this->inhouseNatureRule($natures, $request)],
            'task_nature_other' => ['nullable', 'string', 'max:120'],
            'designer_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'designer')->where('is_active', true)),
            ],
            'total_creatives' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'description' => ['nullable', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*' => $this->generalFileRule(),
            'draft_id' => ['nullable', 'integer'],
        ]);

        $draft = $this->ownedInhouseDraft($request);

        $columns = [
            'task_name' => $data['task_name'],
            'vertical' => $data['vertical'] ?? '',
            'task_nature' => $data['task_nature'] ?? '',
            'total_creatives' => isset($data['total_creatives']) ? (int) $data['total_creatives'] : null,
        ];

        if (! $draft) {
            $draft = DesignTask::create($columns + [
                'task_id' => 'PENDING-'.Str::uuid(),
                'assigned_at' => now(),
                'assigned_by' => auth()->id(),
                'task_category' => 'in_house',
                'party_type' => 'client',
                'party_name' => 'In-house',
                'contact_person' => '',
                'mobile_number' => '',
                'priority' => 'medium',
                'due_at' => null,
                'designer_id' => null,
                'total_creatives' => null,
                'status' => 'draft',
                'requirements' => [],
            ]);
        } else {
            $draft->update($columns);
        }

        $requirements = $this->removeMarkedFiles(is_array($draft->requirements) ? $draft->requirements : [], $this->parseRemovedFiles($request));
        $requirements['description'] = trim((string) ($data['description'] ?? ''));
        $requirements['_draft_designer_id'] = $data['designer_id'] ?? null;
        $requirements['_nature_other'] = ($data['task_nature'] ?? '') === 'others' ? trim((string) ($data['task_nature_other'] ?? '')) : null;

        $folder = $this->inhouseFolder($draft->task_id, $draft->vertical, $draft->task_name, $draft->task_nature);

        try {
            if ($request->hasFile('attachments')) {
                $requirements['attachments'] = array_values(array_merge(
                    $requirements['attachments'] ?? [],
                    $this->storeMultipleFiles(
                        files: $request->file('attachments'),
                        directory: "{$folder}/attachments",
                        taskId: $draft->task_id,
                        fieldName: 'attachments'
                    )
                ));
            }
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['upload' => 'The draft could not be saved because one or more files could not be stored. Please try again.']);
        }

        $draft->update(['requirements' => $requirements]);

        return redirect()
            ->route('bd.tasks.create', ['draft' => $draft->id])
            ->with('success', 'Task saved as draft.');
    }

    /**
     * Collapse everything outside the base task columns and the uploaded files
     * into the requirements JSON blob, including the dimension/size/media-size
     * row groups. Shared by Bd\TaskController::store() and
     * Designer\TaskController::store() — both validate the same requirement
     * rules via requirementRules() and only differ in the base task columns.
     */
    protected function buildRequirementsPayload(array $data): array
    {
        $baseKeys = [
            'task_name', 'vertical', 'zoho_project_number', 'task_nature', 'party_type', 'party_name', 'contact_person',
            'mobile_number', 'priority', 'due_at', 'designer_id', 'bd_id', 'total_creatives',
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
            ->filter(fn ($row) => filled($row['name'] ?? null)
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

        return $requirements;
    }

    /**
     * Save (or update) a BD Draft. Uses the same dynamic requirement rules but
     * WITHOUT the final-validation requireds, so a partially-completed form can
     * be stored. No designer assignment, no availability check, no notification.
     */
    public function storeDraft(Request $request)
    {
        $draftId = (int) $request->input('draft_id');

        if ($draftId) {
            return $this->updateDraft($request, DesignTask::findOrFail($draftId));
        }

        $data = $this->validateDraftPayload($request);

        $requirements = $this->collectRequirementValues($request, $data);

        $task = DesignTask::create([
            'task_id' => 'PENDING-'.Str::uuid(),
            'assigned_at' => now(),
            'assigned_by' => auth()->id(),
            'task_name' => $data['task_name'] ?? '',
            'vertical' => $data['vertical'] ?? '',
            'zoho_project_number' => $data['zoho_project_number'] ?? null,
            'task_nature' => $data['task_nature'] ?? '',
            'party_type' => $data['party_type'] ?? 'client',
            'party_name' => $data['party_name'] ?? '',
            'contact_person' => $data['contact_person'] ?? '',
            'mobile_number' => $data['mobile_number'] ?? '',
            'priority' => $data['priority'] ?? 'low',
            'due_at' => $data['due_at'] ?? null,
            'designer_id' => null,
            'total_creatives' => $data['total_creatives'] ?? null,
            'status' => 'draft',
            'requirements' => [],
        ]);

        return $this->persistDraft($request, $task, $requirements);
    }

    public function updateDraft(Request $request, DesignTask $task)
    {
        abort_unless(
            $request->user()?->role === 'bd'
            && (int) $task->assigned_by === (int) $request->user()->id
            && $task->status === 'draft',
            403
        );

        $data = $this->validateDraftPayload($request);

        $requirements = $this->collectRequirementValues($request, $data);

        $task->update([
            'task_name' => $data['task_name'] ?? $task->task_name,
            'vertical' => $data['vertical'] ?? $task->vertical,
            'zoho_project_number' => array_key_exists('zoho_project_number', $data) ? $data['zoho_project_number'] : $task->zoho_project_number,
            'task_nature' => $data['task_nature'] ?? $task->task_nature,
            'party_type' => $data['party_type'] ?? $task->party_type,
            'party_name' => $data['party_name'] ?? $task->party_name,
            'contact_person' => array_key_exists('contact_person', $data) ? $data['contact_person'] : $task->contact_person,
            'mobile_number' => array_key_exists('mobile_number', $data) ? $data['mobile_number'] : $task->mobile_number,
            'priority' => $data['priority'] ?? $task->priority,
            'due_at' => array_key_exists('due_at', $data) && $data['due_at'] ? $data['due_at'] : $task->due_at,
            'total_creatives' => $data['total_creatives'] ?? $task->total_creatives,
        ]);

        $requirements = array_merge(is_array($task->requirements) ? $task->requirements : [], $requirements);

        return $this->persistDraft($request, $task->fresh(), $requirements);
    }

    private function persistDraft(Request $request, DesignTask $task, array $requirements)
    {
        $rootFolder = trim((string) env('DO_SPACES_ROOT', 'design_task_manager'), '/');

        $taskNameSlug = Str::slug($task->task_name ?: 'untitled-task');
        $taskNatureSlug = Str::slug(str_replace('_', '-', $task->task_nature ?: 'draft'));
        $verticalSlug = Str::slug(str_replace('_', '-', $task->vertical ?: 'draft'));

        $taskFolder = implode('/', [
            $rootFolder,
            now()->format('Y'),
            $verticalSlug,
            "{$task->task_id}_{$taskNameSlug}",
            $taskNatureSlug,
        ]);

        $requirements = $this->removeMarkedFiles($requirements, $this->parseRemovedFiles($request));

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
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'upload' => 'The draft could not be saved because one or more files could not be stored. Please try again.',
                ]);
        }

        return redirect()
            ->route('bd.tasks.create', ['draft' => $task->id])
            ->with('success', 'Task saved as draft.');
    }

    /**
     * Files the BD explicitly removed from a draft (via the "Remove" button on
     * an already-attached file, see create.blade.php) come in as
     * {field: [stored paths]}. Only paths that are actually present in that
     * field's current requirements value are deleted/dropped — never trust a
     * client-supplied path blindly.
     */
    private function parseRemovedFiles(Request $request): array
    {
        $raw = json_decode((string) $request->input('removed_files_json', '{}'), true);

        return is_array($raw) ? $raw : [];
    }

    private function removeMarkedFiles(array $requirements, array $removed): array
    {
        foreach ($removed as $field => $paths) {
            if (! is_array($requirements[$field] ?? null) || ! is_array($paths)) {
                continue;
            }

            $toRemove = array_intersect($requirements[$field], $paths);

            foreach ($toRemove as $path) {
                Storage::disk('spaces')->delete($path);
            }

            $requirements[$field] = array_values(array_diff($requirements[$field], $toRemove));
        }

        return $requirements;
    }

    /**
     * Groups stored file paths inside a task's requirements JSON by field name,
     * for display (draft prefill needs this so previously uploaded files show
     * up under the correct dynamic field instead of disappearing). Mirrors
     * Bd\AssignedTaskController::collectRequirementAttachments().
     */
    private function collectRequirementAttachments(array $requirements): array
    {
        $groups = [];

        foreach ($requirements as $key => $value) {
            if (str_starts_with((string) $key, '_')) {
                continue;
            }

            $files = [];
            $this->extractStoredFiles($value, $files);

            if ($files === []) {
                continue;
            }

            $groups[] = [
                'key' => (string) $key,
                'files' => $files,
            ];
        }

        return $groups;
    }

    private function extractStoredFiles(mixed $value, array &$files): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->extractStoredFiles($item, $files);
            }

            return;
        }

        if (! is_string($value) || ! $this->looksLikeStoredFilePath($value)) {
            return;
        }

        $files[] = [
            'path' => $value,
            'name' => basename($value),
            'extension' => strtoupper(pathinfo($value, PATHINFO_EXTENSION) ?: 'FILE'),
            'url' => Storage::disk('spaces')->url($value),
        ];
    }

    private function looksLikeStoredFilePath(string $value): bool
    {
        $value = trim($value);

        return $value !== ''
            && ! filter_var($value, FILTER_VALIDATE_URL)
            && str_contains($value, '/')
            && pathinfo($value, PATHINFO_EXTENSION) !== '';
    }

    /**
     * Minimal validation for draft saves: only basic shape rules are enforced,
     * not the per-(vertical,nature) final required fields.
     */
    private function validateDraftPayload(Request $request): array
    {
        $verticals = array_keys(self::NATURES);

        $rules = [
            'task_name' => ['required', 'string', 'max:180'],
            'vertical' => ['required', Rule::in($verticals)],
            'zoho_project_number' => ['nullable', 'string', 'max:60'],
            'task_nature' => ['required', 'string', 'max:120'],
            'party_type' => ['nullable', Rule::in(['client', 'agency'])],
            'party_name' => ['nullable', 'string', 'max:180'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'mobile_number' => ['nullable', 'digits:10'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_at' => ['nullable', 'date'],
            'designer_id' => ['nullable', 'integer'],
            'total_creatives' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'draft_id' => ['nullable', 'integer'],
        ];

        return $request->validate($rules);
    }

    /**
     * Collapse everything outside the base task columns and the uploaded files
     * into the requirements JSON blob (mirrors store()'s logic, but tolerant of
     * incomplete content).
     */
    private function collectRequirementValues(Request $request, array $data): array
    {
        $baseKeys = [
            'task_name', 'vertical', 'zoho_project_number', 'task_nature', 'party_type', 'party_name', 'contact_person',
            'mobile_number', 'priority', 'due_at', 'designer_id', 'total_creatives', 'draft_id',
            '_token', '_method',
        ];

        // Drafts use minimal validation rules that deliberately drop the dynamic
        // requirement fields, so collect those values straight from the raw
        // request (uploaded files and their arrays are filtered out below).
        $raw = $request->all();
        $raw = array_merge($data, $raw);

        $requirements = collect($raw)
            ->except($baseKeys)
            ->reject(function (mixed $value): bool {
                if ($value instanceof UploadedFile) {
                    return true;
                }

                return is_array($value)
                    && collect($value)->contains(fn ($item) => $item instanceof UploadedFile);
            })
            ->filter(function (mixed $value): bool {
                return $value !== null && $value !== '';
            })
            ->all();

        $dimensionRows = collect($data['dimension_rows'] ?? $raw['dimension_rows'] ?? [])
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

        $sizeRows = collect($data['size_rows'] ?? $raw['size_rows'] ?? [])
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

        $mediaSizeRows = collect($data['media_size_rows'] ?? $raw['media_size_rows'] ?? [])
            ->filter(fn ($row) => is_array($row))
            ->filter(fn ($row) => filled($row['name'] ?? null)
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

        return $requirements;
    }

    public function show(DesignTask $task)
    {
        $task->load(['designer', 'assigner']);

        return view('bd.tasks.show', compact('task'));
    }

    protected function storeSingleFile(
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

    protected function storeMultipleFiles(
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

    protected function requirementRules(Request $request): array
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
                    'Static Model',
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
                        ->filter(fn ($row) => filled($row['name'] ?? null)
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
                ? $this->audioFileRule()
                : $this->generalFileRule();
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

            'digital_inhouse.digital', 'digital_inhouse.inhouse' => ['creative_type', 'brief'],

            default => [],
        };

        // Digital / Inhouse-only fields; other verticals never receive these rules.
        if ($vertical === 'digital_inhouse') {
            $common['creative_type'] = ['nullable', Rule::in(['Image Creative', 'Video Creative'])];
            $common['brief'] = ['nullable', 'string', 'max:10000'];
            $common['attachments.*'] = $this->generalFileRule(zipAtThreshold: true);
        }

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

    protected function audioFileRule(): array
    {
        return [
            'file',
            'max:'.self::MAX_AUDIO_FILE_KB,
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $value instanceof UploadedFile) {
                    return;
                }

                $extension = strtolower((string) $value->getClientOriginalExtension());
                $mime = strtolower((string) $value->getMimeType());

                if (! in_array($extension, self::AUDIO_EXTENSIONS, true) || ! in_array($mime, self::AUDIO_MIME_TYPES, true)) {
                    $fail('The Audio Reference must be an MP3 or WAV file.');
                }
            },
        ];
    }

    /**
     * Reference/knowledge files: unrestricted type up to 500 MB (unchanged
     * behaviour), ZIP-only between 500 MB and the 6 GB hard cap. Reuses
     * Laravel's own `mimes:zip` rule (already proven for the Progress Update
     * ZIP field) via a sub-validation instead of hand-rolling a MIME list.
     */
    protected function generalFileRule(bool $zipAtThreshold = false): array
    {
        return [
            'file',
            'max:'.self::MAX_GENERAL_FILE_KB,
            function (string $attribute, mixed $value, \Closure $fail) use ($zipAtThreshold): void {
                if (! $value instanceof UploadedFile) {
                    return;
                }

                // Digital / Inhouse: exactly 500 MB already needs ZIP; other verticals keep "> 500 MB".
                $size = $value->getSize();
                if ($zipAtThreshold ? $size < self::ZIP_ONLY_THRESHOLD_BYTES : $size <= self::ZIP_ONLY_THRESHOLD_BYTES) {
                    return;
                }

                if (Validator::make(['file' => $value], ['file' => 'mimes:zip'])->fails()) {
                    $fail($zipAtThreshold
                        ? 'Files that are 500 MB or larger must be in ZIP format.'
                        : 'Files larger than 500 MB must be ZIP format.');
                }
            },
        ];
    }

    protected function maximumAllowedDueDate(): Carbon
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
