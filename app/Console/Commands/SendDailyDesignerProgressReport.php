<?php

namespace App\Console\Commands;

use App\Services\DailyDesignerProgressReportService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Admin → Daily Reporting nightly mail (scheduled 23:59 Asia/Kolkata in
 * bootstrap/app.php). Idempotent per report date — re-running never resends
 * a date that was already delivered.
 */
class SendDailyDesignerProgressReport extends Command
{
    protected $signature = 'reports:daily-designer-progress
        {--date= : Report date (Y-m-d, Asia/Kolkata). Defaults to today, or yesterday with --retry-failed}
        {--retry-failed : Only retry an existing failed/stale run; never starts a new one}
        {--force : Allow sending today\'s report before the scheduled send time}';

    protected $description = 'Send the Daily Reporting mail listing designers with no progress submitted for the day';

    public function handle(DailyDesignerProgressReportService $reports): int
    {
        $tz = (string) config('daily-report.timezone');
        $now = CarbonImmutable::now($tz);
        $retryOnly = (bool) $this->option('retry-failed');

        $date = $this->option('date') ?: ($retryOnly ? $now->subDay() : $now)->toDateString();

        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', (string) $date, $tz);
        } catch (\Throwable) {
            $parsed = null;
        }

        if (! $parsed || $parsed->toDateString() !== $date) {
            $this->error('Invalid --date; expected Y-m-d.');

            return self::INVALID;
        }

        if ($parsed->isAfter($now->startOfDay())) {
            $this->error('Cannot report on a future date.');

            return self::INVALID;
        }

        // A partial-day report would also block tonight's real send, so today's
        // report only goes out at/after the scheduled time unless forced.
        if ($date === $now->toDateString() && $now->format('H:i') < (string) config('daily-report.send_at') && ! $this->option('force')) {
            $this->warn("Today's report is sent at ".config('daily-report.send_at')." {$tz}. Use --force to send now.");

            return self::SUCCESS;
        }

        $result = $reports->send($date, $retryOnly);
        $run = $result['run'];

        $this->line("[{$result['outcome']}] {$date}: {$result['message']}");

        if ($run) {
            $this->line(sprintf(
                'Designers %d · submitted progress %d · not submitted %d · attempts %d · API %s',
                $run->designers_evaluated, $run->submitters_count, $run->included_count,
                $run->attempts, $run->api_status_code ?? '—'
            ));
        }

        return $result['outcome'] === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
