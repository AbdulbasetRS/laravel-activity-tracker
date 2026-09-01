<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Console;

use Abdulbaset\ActivityTracker\Models\Activity;
use Illuminate\Console\Command;

final class ClearCommand extends Command
{
    protected $signature = 'activity:clear {--force : Skip the confirmation prompt}';

    protected $description = 'Delete ALL recorded activities.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will delete ALL activity records. Continue?')) {
            $this->comment('Aborted.');

            return self::SUCCESS;
        }

        $count = Activity::query()->count();
        Activity::query()->truncate();

        $this->info("Deleted {$count} activity record(s).");

        return self::SUCCESS;
    }
}
