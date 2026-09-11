<?php

namespace App\Livewire\Bd;

use App\Models\DesignTask;
use App\Services\DesignTaskBdApprovalService;
use App\Services\TaskNotificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * "New Task Confirmation Request" approve/reject panel embedded on the BD
 * Ticket Details page (resources/views/bd/tasks/show.blade.php) when a task
 * is still pending_bd_approval — the same decision surfaced on the Kanban
 * card (App\Livewire\Bd\TaskKanban), routed through the same
 * DesignTaskBdApprovalService so the two never diverge.
 */
class TaskConfirmationActions extends Component
{
    public DesignTask $task;

    public string $comment = '';

    public bool $rejecting = false;

    public function mount(DesignTask $task): void
    {
        abort_unless(
            Auth::user()?->role === 'bd'
            && (int) $task->assigned_by === (int) Auth::id()
            && $task->status === 'pending_bd_approval',
            403
        );

        $this->task = $task;
    }

    public function startReject(): void
    {
        $this->rejecting = true;
        $this->comment = '';
    }

    public function cancelReject(): void
    {
        $this->rejecting = false;
        $this->comment = '';
    }

    /**
     * Redirects back to this same Ticket Details page rather than just
     * updating $this->task in place — the surrounding classic Blade page
     * (bd/tasks/show.blade.php) computed its status badge/tabs/edit-button
     * once from the controller, so a full reload is what makes every part
     * of the page agree with the new status.
     */
    public function approve()
    {
        $comment = trim($this->comment) !== '' ? trim($this->comment) : null;

        $task = app(DesignTaskBdApprovalService::class)->approve($this->task, Auth::user(), $comment);

        app(TaskNotificationService::class)->bdApprovalDecided($task, 'approved', Auth::user(), $comment);

        session()->flash('success', 'Task confirmed and moved to New Assignments.');

        return redirect()->route('bd.tasks.show', $task);
    }

    public function reject()
    {
        $this->validate([
            'comment' => ['required', 'string', 'max:5000'],
        ], [], ['comment' => 'comment']);

        $task = app(DesignTaskBdApprovalService::class)->reject($this->task, Auth::user(), trim($this->comment));

        app(TaskNotificationService::class)->bdApprovalDecided($task, 'rejected', Auth::user(), trim($this->comment));

        session()->flash('success', 'Task rejected.');

        return redirect()->route('bd.tasks.show', $task);
    }

    public function render()
    {
        return view('livewire.bd.task-confirmation-actions');
    }
}
