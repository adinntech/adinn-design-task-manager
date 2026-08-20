<?php

namespace App\Livewire\Designer;

use App\Models\DesignTask;
use App\Models\User;
use App\Services\DesignTaskRequestService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class TaskRequestModal extends Component
{
    use WithFileUploads;

    public DesignTask $task;
    public bool $open = false;
    public string $type = '';

    public string $reason = '';
    public ?int $targetDesignerId = null;
    public ?int $creativeCount = null;
    public string $splitDetailsText = '';
    public string $notes = '';
    public $attachment = null;
    public array $attachments = [];

    protected $listeners = ['open-request-modal' => 'open'];

    public function mount(DesignTask $task): void
    {
        abort_unless(
            Auth::user()?->role === 'designer'
            && (int) $task->designer_id === (int) Auth::id(),
            403
        );

        $this->task = $task;
    }

    public function open(string $type): void
    {
        if (! in_array($type, app(DesignTaskRequestService::class)->allowedTypesForTask($this->task), true)) {
            return;
        }

        $this->resetFields();
        $this->type = $type;
        $this->open = true;
    }

    public function close(): void
    {
        $this->resetFields();
        $this->open = false;
    }

    public function getDesignersProperty(): Collection
    {
        return User::query()
            ->where('role', 'designer')
            ->where('is_active', true)
            ->where('id', '!=', Auth::id())
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function submit(): void
    {
        abort_unless(
            Auth::user()?->role === 'designer'
            && (int) $this->task->designer_id === (int) Auth::id(),
            403
        );

        if (! in_array($this->type, app(DesignTaskRequestService::class)->allowedTypesForTask($this->task), true)) {
            $this->addError('type', 'This request type is not available for the task\'s current status.');
            return;
        }

        $this->validate($this->rules());

        $splitDetails = match ($this->type) {
            'split' => [
                'creative_count' => $this->creativeCount,
                'details' => trim($this->splitDetailsText),
            ],
            'swap' => [
                'notes' => trim($this->notes),
            ],
            default => null,
        };

        $requestFiles = collect($this->attachments)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->values();

        // Backward compatibility for older tests / calls that still set the
        // singular attachment property.
        if ($this->attachment instanceof UploadedFile) {
            $requestFiles->push($this->attachment);
        }

        $attachments = $requestFiles
            ->map(fn (UploadedFile $file) => $this->storeAttachment($file))
            ->values()
            ->all();

        $attachments = $attachments !== [] ? $attachments : null;

        app(DesignTaskRequestService::class)->create(
            $this->task,
            Auth::user(),
            $this->type,
            trim($this->reason),
            [
                'target_designer_id' => $this->targetDesignerId,
                'split_details' => $splitDetails,
                'attachments' => $attachments,
            ]
        );

        $this->resetFields();
        $this->open = false;
        $this->dispatch('request-created', message: 'Request submitted successfully.');
    }

    private function rules(): array
    {
        $rules = [
            'reason' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:102400'],
            'attachments' => ['array', 'max:10'],
            'attachments.*' => ['file', 'max:102400'],
        ];

        if ($this->type === 'split') {
            $rules['creativeCount'] = ['required', 'integer', 'min:1', 'max:9999'];
            $rules['splitDetailsText'] = ['required', 'string', 'max:5000'];
            $rules['targetDesignerId'] = [
                'nullable',
                'exists:users,id',
            ];
        }

        if ($this->type === 'swap') {
            $rules['targetDesignerId'] = ['required', 'exists:users,id'];
            $rules['notes'] = ['nullable', 'string', 'max:5000'];
        }

        return $rules;
    }

    private function storeAttachment(UploadedFile $file): string
    {
        $originalBase = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $extension = strtolower($file->getClientOriginalExtension());
        $safeBase = $originalBase !== '' ? $originalBase : 'attachment';

        $fileName = sprintf(
            '%s__request-%s__%s__%s.%s',
            $this->task->task_id,
            $this->type,
            $safeBase,
            now()->format('Ymd-His-v'),
            $extension
        );

        $root = trim((string) env('DO_SPACES_ROOT', 'design_task_manager'), '/');
        $directory = implode('/', [
            $root,
            now()->format('Y'),
            $this->task->vertical,
            $this->task->task_id.'_'.Str::slug($this->task->task_name),
            Str::slug($this->task->task_nature),
            'requests',
            $this->type,
        ]);

        $path = $file->storePubliclyAs($directory, $fileName, 'spaces');

        return $path;
    }

    private function resetFields(): void
    {
        $this->reset(['type', 'reason', 'targetDesignerId', 'creativeCount', 'splitDetailsText', 'notes', 'attachment', 'attachments']);
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.designer.task-request-modal', [
            'designers' => $this->type === 'split' || $this->type === 'swap' ? $this->designers : collect(),
        ]);
    }
}
