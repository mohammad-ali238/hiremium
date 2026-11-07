<?php

namespace App\Console\Commands;

use App\Services\Tools\ToolPurger;
use App\Support\ToolSettings;
use Illuminate\Console\Command;

/**
 * Nightly housekeeping for the free tools: drop the links nobody opened, and
 * drop everything past its retention date.
 *
 * Both halves are switched separately in Free Tools settings, and both are also
 * available as buttons there — this is the same work on a schedule.
 */
class ToolsCleanupCommand extends Command
{
    protected $signature = 'hiremium:tools-cleanup
                            {--force : Run both sweeps even if their settings are off}
                            {--limit=500 : Maximum runs to purge per sweep}';

    protected $description = 'Purge expired unopened Free Tools runs and anything past its retention date';

    public function handle(ToolPurger $purger): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');

        $expired = ($force || ToolSettings::autoCleanupExpired())
            ? $purger->purgeExpiredUnopened($limit)
            : 0;

        $retained = ($force || ToolSettings::autoCleanupRetention())
            ? $purger->purgeRetention($limit)
            : 0;

        $this->info("Free tools cleanup: {$expired} expired unopened, {$retained} past retention.");

        return self::SUCCESS;
    }
}
