<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Console;

use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'activity:install';

    protected $description = 'Publish the activity tracker config and migration, then run migrations.';

    public function handle(): int
    {
        $this->info('Publishing configuration...');
        $this->call('vendor:publish', [
            '--provider' => \Abdulbaset\ActivityTracker\ActivityTrackerServiceProvider::class,
            '--tag' => 'activity-tracker-config',
        ]);

        $this->info('Publishing migration...');
        $this->call('vendor:publish', [
            '--provider' => \Abdulbaset\ActivityTracker\ActivityTrackerServiceProvider::class,
            '--tag' => 'activity-tracker-migrations',
        ]);

        if ($this->confirm('Run migrations now?', true)) {
            $this->call('migrate');
        }

        $this->info('Activity tracker installed. Tracking begins automatically — no code changes required.');

        return self::SUCCESS;
    }
}
