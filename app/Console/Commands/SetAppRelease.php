<?php

namespace App\Console\Commands;

use App\Support\AppRelease;
use Illuminate\Console\Command;

/** Show the current Android release, or change how hard it is enforced (the kill switch: `--min-build=0` un-forces everyone at once). */
class SetAppRelease extends Command
{
    protected $signature = 'app:release {--min-build= : Build below which the app is blocked (0 = nobody is forced)}';

    protected $description = 'Show the published Android release, or set its minimum build (0 releases phones blocked by a bad update)';

    public function handle(): int
    {
        if ($this->option('min-build') !== null) {
            $min = (int) $this->option('min-build');
            AppRelease::put(['min_build' => max(0, $min)]);
            $this->info("min_build is now {$min}.");
        }

        $r = AppRelease::current();
        $this->line('latest_build: '.($r['latest_build'] ?? 0));
        $this->line('min_build:    '.($r['min_build'] ?? 0));
        $this->line('version:      '.($r['latest_version'] ?? '-'));
        $this->line('url:          '.($r['url'] ?? '-'));

        return self::SUCCESS;
    }
}
