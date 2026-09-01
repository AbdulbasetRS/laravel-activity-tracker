<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Console;

use Abdulbaset\ActivityTracker\Models\Activity;
use Illuminate\Console\Command;

final class PruneCommand extends Command
{
    protected $signature = 'activity:prune {--days= : Override the configured retention period}';

    protected $description = 'Delete activity records older than the configured (or given) retention period.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('activity-tracker.retention.days', 90));

        if ($days <= 0) {
            $this->error('Retention days must be a positive integer.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $count = Activity::query()->where('created_at', '<', $cutoff)->count();
        Activity::query()->where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$count} activity record(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
