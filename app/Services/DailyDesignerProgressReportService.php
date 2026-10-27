<?php

namespace App\Services;

use App\Http\Controllers\Admin\DailyReportingController;
use App\Models\AppSetting;
use App\Models\DailyReportRun;
use App\Models\DesignTask;
use App\Models\DesignTaskEodRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Nightly Admin "Daily Reporting" mail: lists every active Designer with ZERO
 * persisted progress submissions (design_task_eod_records, progress or rework,
 * on any of their tasks) during the report day in Asia/Kolkata, in one list:
 *  - with PENDING tasks (any status except Completed, Waiting for BD Review or
 *    Transferred; drafts never count) → those tasks are shown;
 *  - with no pending task (free: no tasks / only Completed / only BD Review)
 *    → shown with "No pending tasks".
 * A Designer who submitted any update today is never listed, even if that
 * ticket has since moved to Completed or BD Review. Read-only against task and
 * progress data.
 *
 * Delivery is idempotent per report date via the scheduled idempotency_key.
 */
class DailyDesignerProgressReportService
{
    /** Valid daily submission = a successfully committed Task Updation or Rework submission. */
    public const PROGRESS_UPDATE_TYPES = ['progress', 'rework'];

    /** Not pending for the Designer: BD owns the next step, the task is finished, or it was transferred away. */
    public const NOT_PENDING_STATUSES = ['waiting_confirmation', 'completed', 'swap_tasks'];

    /** @return array<int, string> Every workflow status that still needs the Designer (drafts are not in STATUSES). */
    public static function pendingStatuses(): array
    {
        return array_values(array_diff(array_keys(DesignTaskStatusService::STATUSES), self::NOT_PENDING_STATUSES));
    }

    public function idempotencyKey(string $date): string
    {
        return 'daily-designer-progress-'.$date;
    }

    /**
     * @return array{report_date:string, timezone:string, window:array{start:string,end:string},
     *     designers_evaluated:int, active_designers:int, submitters_count:int, counts:array<string,int>, included:array<int,array>}
     */
    public function build(string $date): array
    {
        $tz = (string) config('daily-report.timezone');
        $start = CarbonImmutable::createFromFormat('Y-m-d', $date, $tz)->startOfDay();
        $end = $start->endOfDay();

        // submitted_at is written with now() in the app timezone, so compare in that timezone.
        $appTz = (string) config('app.timezone');

        $designers = User::query()
            ->where('role', 'designer')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code']);

        $submitterIds = DesignTaskEodRecord::query()
            ->whereIn('update_type', self::PROGRESS_UPDATE_TYPES)
            ->whereIn('designer_id', $designers->pluck('id'))
            ->whereBetween('submitted_at', [
                $start->setTimezone($appTz)->format('Y-m-d H:i:s'),
                $end->setTimezone($appTz)->format('Y-m-d H:i:s'),
            ])
            ->distinct()
            ->pluck('designer_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Current pending tasks are shown per Designer; the submission check
        // above spans all of the Designer's tasks, so an update submitted
        // earlier today still counts after a status change.
        $activeTasksByDesigner = DesignTask::query()
            ->whereIn('designer_id', $designers->pluck('id'))
            ->whereIn('status', self::pendingStatuses())
            ->orderBy('due_at')
            ->get(['id', 'task_id', 'task_name', 'status', 'designer_id'])
            ->groupBy('designer_id');

        $activeDesigners = $designers->filter(fn (User $d) => $activeTasksByDesigner->has($d->id))->values();
        $counts = ['active_no_progress' => 0, 'no_pending_tasks' => 0];
        $included = [];

        foreach ($designers as $designer) {
            if (in_array((int) $designer->id, $submitterIds, true)) {
                continue;
            }

            $active = $activeTasksByDesigner->get($designer->id, collect());
            $category = $active->isEmpty() ? 'no_pending_tasks' : 'active_no_progress';
            $counts[$category]++;

            $included[] = [
                'designer_id' => (int) $designer->id,
                'name' => (string) $designer->name,
                'employee_code' => $designer->employee_code,
                'category' => $category,
                'active_task_count' => $active->count(),
                'note' => $active->isEmpty() ? 'No pending tasks' : null,
                'tasks' => $active->map(fn ($t) => [
                    'task_id' => (string) $t->task_id,
                    'task_name' => (string) $t->task_name,
                    'status' => (string) $t->status,
                    'status_label' => DesignTaskStatusService::STATUSES[$t->status] ?? Str::headline((string) $t->status),
                ])->values()->all(),
            ];
        }

        return [
            'report_date' => $date,
            'timezone' => $tz,
            'window' => ['start' => $start->toIso8601String(), 'end' => $end->format('Y-m-d\TH:i:s.vP')],
            'designers_evaluated' => $designers->count(),
            'active_designers' => $activeDesigners->count(),
            'submitters_count' => count($submitterIds),
            'counts' => $counts,
            'included' => $included,
        ];
    }

    /**
     * Claims the report date, builds (or reuses) the report and calls the mail
     * API. Safe to call repeatedly: a date already "sent" is never resent, and
     * a concurrent "sending" claim is respected until it goes stale.
     *
     * @return array{outcome: 'sent'|'failed'|'skipped', message: string, run: ?DailyReportRun}
     */
    public function send(string $date, bool $retryOnly = false): array
    {
        try {
            [$run, $skipReason] = $this->claim($date, $retryOnly);
        } catch (UniqueConstraintViolationException) {
            return ['outcome' => 'skipped', 'message' => 'Another process just started this report date.', 'run' => null];
        }

        if (! $run) {
            return ['outcome' => 'skipped', 'message' => $skipReason, 'run' => $this->scheduledRun($date)];
        }

        return $this->deliver($run);
    }

    /**
     * Admin "Send Mail": an immediate, fresh report for today (IST). Every
     * click is its own run (trigger = manual) with its own idempotency key, so
     * it never touches — or suppresses — that date's scheduled 23:59 run.
     * Concurrent clicks are serialised by the caller (cache lock).
     *
     * @return array{outcome: 'sent'|'failed', message: string, run: DailyReportRun}
     */
    public function sendManual(?int $userId): array
    {
        $date = CarbonImmutable::now((string) config('daily-report.timezone'))->toDateString();

        $run = DailyReportRun::create([
            'report_date' => $date,
            'trigger' => 'manual',
            'triggered_by' => $userId,
            'idempotency_key' => $this->idempotencyKey($date).'-manual-'.Str::uuid(),
            'timezone' => (string) config('daily-report.timezone'),
            'status' => 'sending',
            'attempts' => 1,
            'last_attempt_at' => now(),
        ]);

        return $this->deliver($run);
    }

    /** Builds (or reuses the run's snapshot of) the report and calls the mail API. */
    private function deliver(DailyReportRun $run): array
    {
        $date = $run->report_date->toDateString();

        try {
            $report = $run->report_snapshot ?: $this->build($date);

            $saved = AppSetting::getValue(DailyReportingController::SETTING_KEY, []);
            $to = array_values(array_filter(array_column($saved['to'] ?? [], 'mail')));
            $cc = array_values(array_filter(array_column($saved['cc'] ?? [], 'mail')));

            $run->fill([
                'report_snapshot' => $report,
                'designers_evaluated' => $report['designers_evaluated'],
                'submitters_count' => $report['submitters_count'],
                'included_count' => count($report['included']),
                'active_designers_count' => $report['active_designers'] ?? 0,
                'active_no_progress_count' => $report['counts']['active_no_progress'] ?? 0,
                // Listed Designers with no pending task (free); older snapshots used "no_tasks".
                'no_tasks_count' => $report['counts']['no_pending_tasks'] ?? $report['counts']['no_tasks'] ?? 0,
                // Legacy breakdown columns — only populated by snapshots built under an older rule.
                'all_completed_count' => $report['counts']['all_completed'] ?? 0,
                'awaiting_review_count' => $report['counts']['awaiting_review'] ?? 0,
                'to_recipients' => $to,
                'cc_recipients' => $cc,
            ])->save();

            if ($to === []) {
                return $this->fail($run, null, 'No To recipients are saved in Admin → Daily Reporting.');
            }

            // Saved recipients were validated on save; re-check so a bad stored value never reaches the API.
            $invalid = array_filter(array_merge($to, $cc), fn ($mail) => filter_var($mail, FILTER_VALIDATE_EMAIL) === false);
            if ($invalid !== []) {
                return $this->fail($run, null, 'Saved recipients contain an invalid email address: '.implode(', ', $invalid).'.');
            }

            $url = (string) config('daily-report.api_url');
            if ($url === '') {
                return $this->fail($run, null, 'Mail API URL is not configured (DAILY_REPORT_MAIL_API_URL).');
            }

            $response = Http::timeout((int) config('daily-report.api_timeout', 30))
                ->acceptJson()
                ->post($url, $this->payload($report, $to, $cc, $run->idempotency_key, $run->trigger));
        } catch (Throwable $e) {
            return $this->fail($run, null, 'Mail API request failed: '.Str::limit($e->getMessage(), 300));
        }

        $message = (string) ($response->json('message') ?? '');

        if (! $response->successful() || $response->json('status') !== 'success') {
            return $this->fail($run, $response->status(), $message !== '' ? $message : Str::limit($response->body(), 500));
        }

        $run->update([
            'status' => 'sent',
            'sent_at' => now(),
            'api_status_code' => $response->status(),
            'api_message' => Str::limit($message !== '' ? $message : 'success', 1000),
        ]);

        Log::info('Daily designer progress report sent', ['report_date' => $date, 'trigger' => $run->trigger, 'run_id' => $run->id, 'included' => $run->included_count]);

        return ['outcome' => 'sent', 'message' => 'Report sent.', 'run' => $run->fresh()];
    }

    public function subject(string $date): string
    {
        return 'Daily Designer Progress Report - '.CarbonImmutable::createFromFormat('Y-m-d', $date)->format('d M Y');
    }

    public function renderBody(array $report): string
    {
        return view('emails.daily-designer-progress-report', [
            'report' => $report,
            'displayDate' => CarbonImmutable::createFromFormat('Y-m-d', $report['report_date'])->format('d M Y'),
        ])->render();
    }

    /** Same contract style as the Printing File mail API (to/cc/subject/mail_content + sender). */
    public function payload(array $report, array $to, array $cc, ?string $idempotencyKey = null, string $trigger = 'scheduled'): array
    {
        return [
            'mailtype' => (string) config('daily-report.mailtype'),
            'idempotency_key' => $idempotencyKey ?? $this->idempotencyKey($report['report_date']),
            'trigger' => $trigger,
            'report_date' => $report['report_date'],
            'timezone' => $report['timezone'],
            'to' => $to,
            'cc' => $cc,
            'subject' => $this->subject($report['report_date']),
            'mail_content' => $this->renderBody($report),
            'attachments' => [],
            'username' => (string) config('app.name'),
            'email' => (string) config('mail.from.address'),
            'rolename' => 'System',
            'phone' => '',
            'summary' => [
                'designers_evaluated' => $report['designers_evaluated'],
                'active_designers' => $report['active_designers'] ?? null,
                'submitted_progress' => $report['submitters_count'],
                'included' => count($report['included']),
            ] + $report['counts'],
            'designers' => array_map(fn (array $row) => [
                'name' => $row['name'],
                'employee_code' => $row['employee_code'],
                'active_task_count' => $row['active_task_count'],
                'note' => $row['note'],
                'tasks' => $row['tasks'],
            ], $report['included']),
        ];
    }

    public function scheduledRun(string $date): ?DailyReportRun
    {
        return DailyReportRun::query()->where('idempotency_key', $this->idempotencyKey($date))->first();
    }

    /** @return array{0: ?DailyReportRun, 1: ?string} */
    private function claim(string $date, bool $retryOnly): array
    {
        return DB::transaction(function () use ($date, $retryOnly): array {
            // Manual "Send Mail" runs are separate rows and never count here.
            $run = DailyReportRun::query()
                ->where('idempotency_key', $this->idempotencyKey($date))
                ->lockForUpdate()
                ->first();

            if (! $run) {
                if ($retryOnly) {
                    return [null, 'No run exists for this date — nothing to retry.'];
                }

                // The unique idempotency_key makes this insert the one-per-date guard.
                $run = DailyReportRun::create([
                    'report_date' => $date,
                    'trigger' => 'scheduled',
                    'idempotency_key' => $this->idempotencyKey($date),
                    'timezone' => (string) config('daily-report.timezone'),
                    'status' => 'pending',
                ]);
            }

            if ($run->status === 'sent') {
                return [null, 'Already sent for this date — duplicate send prevented.'];
            }

            $staleBefore = now()->subMinutes((int) config('daily-report.stale_sending_minutes', 15));
            if ($run->status === 'sending' && $run->last_attempt_at && $run->last_attempt_at->gt($staleBefore)) {
                return [null, 'A send for this date is already in progress.'];
            }

            if ($run->attempts >= (int) config('daily-report.max_attempts', 5)) {
                return [null, 'Maximum send attempts reached for this date.'];
            }

            $run->update(['status' => 'sending', 'attempts' => $run->attempts + 1, 'last_attempt_at' => now()]);

            return [$run, null];
        });
    }

    private function fail(DailyReportRun $run, ?int $statusCode, string $message): array
    {
        $run->update([
            'status' => 'failed',
            'api_status_code' => $statusCode,
            'api_message' => Str::limit($message, 1000),
        ]);

        // Never log the API URL/payload — only identifiers and the outcome.
        Log::warning('Daily designer progress report not sent', [
            'report_date' => $run->report_date?->toDateString(),
            'run_id' => $run->id,
            'attempt' => $run->attempts,
            'status_code' => $statusCode,
            'message' => Str::limit($message, 300),
        ]);

        return ['outcome' => 'failed', 'message' => $message, 'run' => $run->fresh()];
    }
}
