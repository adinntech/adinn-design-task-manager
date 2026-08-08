<?php

namespace App\Livewire\Designer;

use App\Models\DesignTask;
use App\Models\User;
use App\Services\DesignTaskRequestService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $this->task->refresh();

        if (! in_array($type, app(DesignTaskRequestService::class)->allowedTypes($this->task->status), true)) {
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
            ->whereKeyNot(Auth::id())
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function submit(): void
    {
        $this->task->refresh();

        abort_unless(
            Auth::user()?->role === 'designer'
            && (int) $this->task->designer_id === (int) Auth::id(),
            403
        );

        if (! in_array($this->type, app(DesignTaskRequestService::class)->allowedTypes($this->task->status), true)) {
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

        $attachments = $this->attachment instanceof UploadedFile
            ? [$this->storeAttachment($this->attachment)]
            : null;

        try {
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
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0] ?? 'Unable to create request.');
            }
            return;
        }

        $this->resetFields();
        $this->open = false;
        $this->dispatch('request-created', message: 'Request submitted successfully and is pending approval.');
    }

    private function rules(): array
    {
        $activeDesignerRule = Rule::exists('users', 'id')->where(function ($query) {
            $query->where('role', 'designer')
                ->where('is_active', true)
                ->where('id', '!=', Auth::id());
        });

        $rules = [
            'reason' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:102400'],
        ];

        if ($this->type === 'split') {
            $maxSplit = max(1, ((int) $this->task->total_creatives) - 1);
            $rules['creativeCount'] = ['required', 'integer', 'min:1', 'max:'.$maxSplit];
            $rules['splitDetailsText'] = ['required', 'string', 'max:5000'];
            $rules['targetDesignerId'] = ['nullable', $activeDesignerRule];
        }

        if ($this->type === 'swap') {
            $rules['targetDesignerId'] = ['required', $activeDesignerRule];
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
            Str::slug($this->task->vertical),
            $this->task->task_id.'_'.Str::slug($this->task->task_name),
            Str::slug($this->task->task_nature),
            'requests',
            $this->type,
        ]);

        return $file->storePubliclyAs($directory, $fileName, 'spaces');
    }

    private function resetFields(): void
    {
        $this->reset(['type', 'reason', 'targetDesignerId', 'creativeCount', 'splitDetailsText', 'notes', 'attachment']);
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.designer.task-request-modal', [
            'designers' => in_array($this->type, ['split', 'swap'], true) ? $this->designers : collect(),
        ]);
    }
}
