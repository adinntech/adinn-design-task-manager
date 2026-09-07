<?php

namespace App\Livewire;

use App\Services\TaskNotificationUrlResolver;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationBell extends Component
{
    #[On('notification-received')]
    public function refresh(): void
    {
        // Livewire re-renders on any listened event — picks up the newly
        // broadcast notification from the database on next render().
    }

    public function markAsRead(string $id): void
    {
        Auth::user()->notifications()->whereKey($id)->first()?->markAsRead();
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    /**
     * Marks the notification read, then hands the URL to the browser via a
     * dispatched event — Livewire actions can't return a redirect from a
     * dropdown that must also close, so navigation happens client-side.
     */
    public function openNotification(string $id): void
    {
        $notification = Auth::user()->notifications()->whereKey($id)->first();

        if (! $notification) {
            return;
        }

        $notification->markAsRead();

        $this->dispatch('notification-open-url', url: $this->urlFor($notification));
    }

    public function render()
    {
        $notifications = Auth::user()
            ->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notification',
                'message' => $n->data['message'] ?? '',
                'task_ref' => $n->data['task_ref'] ?? null,
                'task_name' => $n->data['task_name'] ?? null,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at->format('d M Y \a\t h:i A'),
                'url' => $this->urlFor($n),
            ]);

        $unread = $notifications->where('read', false)->values();

        $this->dispatch('notifications-updated', items: $unread
            ->map(fn (array $n) => ['id' => $n['id'], 'title' => $n['title'], 'message' => $n['message'], 'url' => $n['url']])
            ->all());

        return view('livewire.notification-bell', [
            'notifications' => $notifications,
            'unreadCount' => $unread->count(),
        ]);
    }

    /**
     * A normal-comment notification opens straight to the Comments tab; a
     * clarification-comment notification opens Overview (where the
     * Clarification section already lives) — everything else opens the
     * task's default landing tab, unchanged.
     */
    private function urlFor(DatabaseNotification $notification): ?string
    {
        $taskId = $notification->data['task_id'] ?? null;

        if (! $taskId) {
            return null;
        }

        $isNormalComment = ($notification->data['category'] ?? null) === 'comment'
            && ! ($notification->data['is_clarification'] ?? false);

        return TaskNotificationUrlResolver::forTask(Auth::user(), (int) $taskId, $isNormalComment ? 'comments' : null);
    }
}
