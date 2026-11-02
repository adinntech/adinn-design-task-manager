<?php

namespace App\Console\Commands;

use App\Models\DesignTask;
use App\Models\User;
use App\Services\TaskNotificationUrlResolver;
use App\Services\WebPushService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * 11:00 IST: desktop reminder to each designer who still has work past its due
 * date (config webpush.overdue_statuses). One task → that task; several → one
 * summary that opens their task list. Once per designer per day, so a re-run
 * or a retry never repeats it.
 */
class SendOverdueTaskReminders extends Command
{
    protected $signature = 'notifications:overdue-reminders {--force : Send again even if today\'s reminder already went out}';

    protected $description = 'Desktop reminder to designers with overdue tasks';

    public function handle(WebPushService $push): int
    {
        if (! $push->isConfigured()) {
            $this->warn('Web Push is not configured (VAPID keys missing) — nothing sent.');

            return self::SUCCESS;
        }

        $today = now('Asia/Kolkata')->toDateString();

        $overdueByDesigner = DesignTask::query()
            ->whereNotNull('designer_id')
            ->whereIn('status', config('webpush.overdue_statuses'))
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->get()
            ->groupBy('designer_id');

        $sent = 0;

        foreach ($overdueByDesigner as $designerId => $tasks) {
            $designer = User::query()->where('role', 'designer')->where('is_active', true)->find($designerId);
            if (! $designer) {
                continue;
            }

            $key = "overdue-reminder:{$today}:{$designer->id}";
            if (! $this->option('force') && Cache::has($key)) {
                continue;
            }

            if ($tasks->count() === 1) {
                $task = $tasks->first();
                $name = $task->display_task_name ?? $task->task_name;
                $days = max(1, (int) $task->due_at->startOfDay()->diffInDays(now()->startOfDay()));
                $title = 'Overdue task reminder';
                $body = "{$task->task_id} — {$name}\nWas due {$task->due_at->format('d M, h:i A')} ({$days} ".($days === 1 ? 'day' : 'days').' ago). Please update it today.';
                $url = TaskNotificationUrlResolver::forTask($designer, (int) $task->id);
            } else {
                $title = "You have {$tasks->count()} overdue tasks";
                $body = $tasks->take(3)->pluck('task_id')->implode(', ')
                    .($tasks->count() > 3 ? ' and '.($tasks->count() - 3).' more' : '')
                    ."\nPlease update them today.";
                $url = route('designer.tasks.index');
            }

            if ($push->send($designer, $title, $body, $url, 'overdue') > 0) {
                Cache::put($key, true, now()->addDays(2));
                $sent++;
            }
        }

        $this->info("Overdue reminders sent to {$sent} designer(s); {$overdueByDesigner->count()} designer(s) have overdue tasks.");

        return self::SUCCESS;
    }
}
