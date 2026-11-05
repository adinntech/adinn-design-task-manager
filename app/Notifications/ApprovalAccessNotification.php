<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\BroadcastsSynchronously;
use Illuminate\Notifications\Notification;

/**
 * Sent to a Designer when an Admin grants them "Permission to approve
 * (Swap / Split)". Not tied to a task — the bell opens the Approvals page
 * (see TaskNotificationUrlResolver::forNotificationData()).
 */
class ApprovalAccessNotification extends Notification
{
    use BroadcastsSynchronously;

    public function __construct(private User $grantedBy) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Approval access granted',
            'message' => "{$this->grantedBy->name} gave you permission to approve Swap and Split requests from your teammates. Open the Approvals tab to review them.",
            'task_id' => null,
            'task_ref' => null,
            'task_name' => null,
            'category' => 'approval_access',
        ];
    }
}
