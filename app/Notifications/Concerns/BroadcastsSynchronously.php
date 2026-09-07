<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\BroadcastMessage;

/**
 * Laravel's own Illuminate\Notifications\Events\BroadcastNotificationCreated
 * implements ShouldBroadcast (not ShouldBroadcastNow), so — independently of
 * whether the Notification class itself implements ShouldQueue — the actual
 * "send this to Reverb" step is dispatched onto the app's default queue
 * connection and needs a running `queue:work` to ever be delivered. This app
 * has no queue worker process, so every broadcast was silently piling up in
 * the `jobs` table forever (found: 197 stuck rows) — the notification/bell
 * DB row was still created instantly, but the live push never arrived,
 * which is why refresh-shake only ever showed up after a manual reload.
 *
 * Forcing just the broadcast onto the built-in 'sync' queue connection makes
 * it dispatch immediately, in-request, with zero extra infrastructure —
 * without touching the app's actual QUEUE_CONNECTION or requiring a worker.
 */
trait BroadcastsSynchronously
{
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))->onConnection('sync');
    }
}
