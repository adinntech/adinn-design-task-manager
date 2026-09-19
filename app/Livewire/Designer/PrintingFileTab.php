<?php

namespace App\Livewire\Designer;

use App\Mail\PrintingFileMail;
use App\Models\AllUsersMail;
use App\Models\DesignTask;
use App\Models\DesignTaskPrintingFileMail;
use App\Models\FileUpload;
use App\Services\CloudMultipartUploadService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Designer-only "Printing File" tab — compose + send the printing-handoff
 * mail (WeTransfer link + cloud attachments) for a task in the
 * prepare_printing_file status, with per-task send history and resend.
 * Fully isolated from TaskDetail so the existing tabs/status flows are
 * never touched by this feature.
 */
class PrintingFileTab extends Component
{
    public DesignTask $task;

    public string $weTransferLink = '';

    /** @var array<int, array{id:?int, name:string, size_bytes:int, url?:string, reused?:bool}> */
    public array $attachments = [];

    public string $toQuery = '';

    public string $ccQuery = '';

    /** @var array<int, array{id:int,name:string,mail:string}> */
    public array $toResults = [];

    /** @var array<int, array{id:int,name:string,mail:string}> */
    public array $ccResults = [];

    /** @var array<int, array{name:string,mail:string}> */
    public array $toRecipients = [];

    /** @var array<int, array{name:string,mail:string}> */
    public array $ccRecipients = [];

    public string $subject = '';

    public string $body = '';

    public bool $sending = false;

    public bool $sent = false;

    public ?string $sentAtLabel = null;

    public ?int $viewingHistoryId = null;

    public function mount(DesignTask $task): void
    {
        abort_unless(
            Auth::user()?->role === 'designer'
            && (int) $task->designer_id === (int) Auth::id(),
            403
        );
        abort_unless($task->status === 'prepare_printing_file', 403);

        $this->task = $task;
        $this->subject = $this->buildDefaultSubject();
        $this->body = $this->buildDefaultBody();
    }

    public function updatedWeTransferLink(): void
    {
        $this->body = $this->buildDefaultBody();
    }

    public function updatedToQuery(): void
    {
        $this->toResults = $this->searchRecipients($this->toQuery, $this->toRecipients);
    }

    public function updatedCcQuery(): void
    {
        $this->ccResults = $this->searchRecipients($this->ccQuery, $this->ccRecipients);
    }

    /** @return array<int, array{id:int,name:string,mail:string}> */
    private function searchRecipients(string $query, array $exclude): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $excludeMails = array_map(fn ($r) => strtolower($r['mail']), $exclude);

        return AllUsersMail::query()
            ->where(fn ($q) => $q->where('name', 'like', "%{$query}%")->orWhere('mail', 'like', "%{$query}%"))
            ->limit(10)
            ->get(['id', 'name', 'mail'])
            ->filter(fn ($u) => ! in_array(strtolower($u->mail), $excludeMails, true))
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'mail' => $u->mail])
            ->values()
            ->all();
    }

    public function addRecipient(string $type, int $id): void
    {
        $user = AllUsersMail::query()->find($id, ['id', 'name', 'mail']);
        if (! $user) {
            return;
        }

        $property = $type === 'cc' ? 'ccRecipients' : 'toRecipients';
        $existing = array_map(fn ($r) => strtolower($r['mail']), $this->{$property});

        if (! in_array(strtolower($user->mail), $existing, true)) {
            $this->{$property}[] = ['name' => $user->name, 'mail' => $user->mail];
        }

        if ($type === 'cc') {
            $this->ccQuery = '';
            $this->ccResults = [];
        } else {
            $this->toQuery = '';
            $this->toResults = [];
        }
    }

    public function removeRecipient(string $type, int $index): void
    {
        $property = $type === 'cc' ? 'ccRecipients' : 'toRecipients';
        $list = $this->{$property};
        unset($list[$index]);
        $this->{$property} = array_values($list);
    }

    public function addAttachment(array $upload): void
    {
        $id = (int) ($upload['id'] ?? 0);
        if ($id <= 0 || collect($this->attachments)->contains('id', $id)) {
            return;
        }

        $this->attachments[] = [
            'id' => $id,
            'name' => (string) ($upload['name'] ?? 'file'),
            'size_bytes' => (int) ($upload['size'] ?? 0),
            // Staging-key URL, display-only (View/Download in the compose
            // list) — sendMail()'s resolveAttachment() always re-promotes
            // from the FileUpload id and computes its own final URL, so
            // this has no effect on what actually gets sent/stored.
            'url' => $upload['url'] ?? null,
        ];
    }

    public function removeAttachment(int $id): void
    {
        $this->attachments = array_values(array_filter($this->attachments, fn ($a) => (int) $a['id'] !== $id));
    }

    public function viewHistory(int $historyId): void
    {
        // Scoped to this task so a designer can never view another task's
        // (or another designer's) mail history by guessing/changing the id.
        DesignTaskPrintingFileMail::query()
            ->where('design_task_id', $this->task->id)
            ->findOrFail($historyId);

        $this->viewingHistoryId = $historyId;
    }

    public function closeHistoryModal(): void
    {
        $this->viewingHistoryId = null;
    }

    public function resendFrom(int $historyId): void
    {
        $history = DesignTaskPrintingFileMail::query()
            ->where('design_task_id', $this->task->id)
            ->findOrFail($historyId);

        $this->toRecipients = $history->to_recipients ?? [];
        $this->ccRecipients = $history->cc_recipients ?? [];
        $this->subject = $history->subject;
        $this->body = $history->body;
        $this->weTransferLink = (string) $history->transfer_url;
        $this->attachments = collect($history->attachments ?? [])
            ->map(fn ($a) => [
                'id' => $a['id'] ?? null,
                'name' => $a['name'],
                'size_bytes' => $a['size_bytes'] ?? 0,
                'url' => $a['url'] ?? null,
                'reused' => true,
            ])
            ->all();
        $this->sent = false;
    }

    public function sendMail(): void
    {
        if ($this->sending) {
            return;
        }

        $this->task->refresh();
        abort_unless($this->task->status === 'prepare_printing_file', 403);

        $this->validate([
            'toRecipients' => ['required', 'array', 'min:1'],
            'toRecipients.*.mail' => ['required', 'email'],
            'ccRecipients.*.mail' => ['nullable', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'weTransferLink' => ['nullable', 'url'],
        ]);

        $this->sending = true;

        try {
            $attachmentMeta = array_map(fn ($a) => $this->resolveAttachment($a), $this->attachments);

            Mail::to(array_column($this->toRecipients, 'mail'))
                ->cc(array_column($this->ccRecipients, 'mail'))
                ->send(new PrintingFileMail($this->subject, $this->body, $this->weTransferLink ?: null, $attachmentMeta));

            $sentAt = now();

            DesignTaskPrintingFileMail::create([
                'design_task_id' => $this->task->id,
                'sender_id' => Auth::id(),
                'to_recipients' => $this->toRecipients,
                'cc_recipients' => $this->ccRecipients,
                'subject' => $this->subject,
                'body' => $this->body,
                'transfer_url' => $this->weTransferLink ?: null,
                'attachments' => $attachmentMeta,
                'sent_at' => $sentAt,
            ]);

            $this->sentAtLabel = $sentAt->format('d M Y').' • '.$sentAt->format('h:i A');
            $this->sent = true;
            $this->attachments = [];
        } finally {
            $this->sending = false;
        }
    }

    /** @return array{id: ?int, name: string, size_bytes: int, url: string} */
    private function resolveAttachment(array $attachment): array
    {
        // Reused from a Resend — already a permanent cloud URL, nothing to promote.
        if (! empty($attachment['reused']) && ! empty($attachment['url'])) {
            return [
                'id' => $attachment['id'] ?? null,
                'name' => $attachment['name'],
                'size_bytes' => (int) ($attachment['size_bytes'] ?? 0),
                'url' => $attachment['url'],
            ];
        }

        $upload = FileUpload::query()
            ->where('id', $attachment['id'])
            ->where('user_id', Auth::id())
            ->where('design_task_id', $this->task->id)
            ->where('purpose', 'mail_attachment')
            ->where('status', 'completed')
            ->whereNull('consumed_at')
            ->firstOrFail();

        $root = trim((string) env('DO_SPACES_ROOT', 'design_task_manager'), '/');
        $extension = strtolower(pathinfo($upload->original_filename, PATHINFO_EXTENSION)) ?: 'zip';
        $directory = implode('/', [
            $root,
            now()->format('Y'),
            $this->task->vertical,
            $this->task->task_id.'_'.Str::slug($this->task->task_name),
            Str::slug($this->task->task_nature),
            'printing-file-mail',
        ]);
        $filename = $this->task->task_id.'__mail-attachment__'
            .Str::slug(pathinfo($upload->original_filename, PATHINFO_FILENAME))
            .'__'.now()->format('Ymd-His-v').'.'.$extension;
        $path = $directory.'/'.$filename;

        app(CloudMultipartUploadService::class)->promoteToFinalPath($upload, $path);

        return [
            'id' => $upload->id,
            'name' => $upload->original_filename,
            'size_bytes' => (int) $upload->size_bytes,
            'url' => Storage::disk('spaces')->url($path),
        ];
    }

    private function buildDefaultSubject(): string
    {
        $parts = [];

        if (filled($this->task->zoho_project_number)) {
            $parts[] = 'Project no : '.$this->task->zoho_project_number;
        }
        if (filled($this->task->vertical)) {
            $parts[] = ucwords(str_replace('_', ' ', $this->task->vertical));
        }
        if (filled($this->task->party_name)) {
            $parts[] = $this->task->party_name;
        }
        if (filled($this->task->display_task_name)) {
            $parts[] = $this->task->display_task_name;
        }

        return implode(' || ', $parts);
    }

    private function buildDefaultBody(): string
    {
        return "Hi team,\n\nPlease process the following URL to proceed printing.\n\n{$this->weTransferLink}\n\nThanks and regards\n"
            .(Auth::user()->email ?? '');
    }

    public function render()
    {
        $history = DesignTaskPrintingFileMail::query()
            ->with('sender')
            ->where('design_task_id', $this->task->id)
            ->latest('sent_at')
            ->get();

        // Always queried fresh (never taken from a client-side snapshot) so
        // the modal shows the exact record as currently stored, including
        // one that was only just created by sendMail() in this same request.
        $viewingRecord = $this->viewingHistoryId
            ? $history->firstWhere('id', $this->viewingHistoryId)
            : null;

        return view('livewire.designer.printing-file-tab', [
            'history' => $history,
            'viewingRecord' => $viewingRecord,
        ]);
    }
}
