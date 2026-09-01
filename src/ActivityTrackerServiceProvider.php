<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker;

use Abdulbaset\ActivityTracker\Console\ClearCommand;
use Abdulbaset\ActivityTracker\Console\InstallCommand;
use Abdulbaset\ActivityTracker\Console\PruneCommand;
use Abdulbaset\ActivityTracker\Contracts\ActivityLoggerInterface;
use Abdulbaset\ActivityTracker\Contracts\ActivityStorageInterface;
use Abdulbaset\ActivityTracker\Contracts\ActivityTransformerInterface;
use Abdulbaset\ActivityTracker\Contracts\QueryClassifierInterface;
use Abdulbaset\ActivityTracker\Contracts\SensitiveDataSanitizerInterface;
use Abdulbaset\ActivityTracker\Listeners\DatabaseQueryListener;
use Abdulbaset\ActivityTracker\Observers\EloquentActivityObserver;
use Abdulbaset\ActivityTracker\Http\Controllers\AssetController;
use Abdulbaset\ActivityTracker\Services\ActivityRepository;
use Abdulbaset\ActivityTracker\Services\ActivityTracker;
use Abdulbaset\ActivityTracker\Services\ActivityTransformer;
use Abdulbaset\ActivityTracker\Services\QueryClassifier;
use Abdulbaset\ActivityTracker\Services\RetrievalFlusher;
use Abdulbaset\ActivityTracker\Services\SensitiveDataSanitizer;
use Abdulbaset\ActivityTracker\Support\CauserResolver;
use Abdulbaset\ActivityTracker\Support\RequestContextResolver;
use Abdulbaset\ActivityTracker\Support\TrackingContext;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots automatic database activity tracking with zero application code
 * changes. Laravel discovers this provider automatically via composer.json's
 * "extra.laravel.providers" entry, so `composer require` is genuinely all
 * that is required to start collecting activities (a migration still needs
 * to run — see InstallCommand / #42 in the design brief).
 */
final class ActivityTrackerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/activity-tracker.php', 'activity-tracker');

        $this->app->singleton(TrackingContext::class);

        $this->app->singleton(QueryClassifierInterface::class, QueryClassifier::class);
        $this->app->singleton(SensitiveDataSanitizerInterface::class, SensitiveDataSanitizer::class);

        $this->app->singleton(CauserResolver::class, fn ($app) => new CauserResolver($app->make(AuthFactory::class)));

        $this->app->bind(RequestContextResolver::class, function ($app) {
            $request = $app->bound('request') ? $app->make('request') : null;

            return new RequestContextResolver($request);
        });

        $this->app->singleton(ActivityTransformerInterface::class, ActivityTransformer::class);
        $this->app->singleton(ActivityStorageInterface::class, ActivityRepository::class);
        $this->app->singleton(ActivityLoggerInterface::class, ActivityTracker::class);

        $this->app->singleton(RetrievalFlusher::class);
        $this->app->singleton(EloquentActivityObserver::class);
        $this->app->singleton(DatabaseQueryListener::class);
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->publishMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'activity-tracker');
        $this->publishViews();
        $this->publishAssets();
        $this->registerDefaultGate();
        $this->registerUiRoutes();

        if (! config('activity-tracker.enabled', true)) {
            return;
        }

        $this->registerEloquentTracking();
        $this->registerQueryTracking();
        $this->registerRetrievalFlushing();
        $this->registerQueueLifecycle();
        $this->registerCommands();
    }

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__.'/../config/activity-tracker.php' => config_path('activity-tracker.php'),
        ], 'activity-tracker-config');
    }

    private function publishMigrations(): void
    {
        // Published with a fresh timestamp so it sorts after the app's
        // existing migrations, following standard Laravel package convention
        // (the package also auto-loads its own copy via loadMigrationsFrom,
        // so publishing is optional and only needed to customize the schema).
        $timestamp = date('Y_m_d_His');

        $this->publishes([
            __DIR__.'/../database/migrations/create_activities_table.php.stub' => database_path("migrations/{$timestamp}_create_activities_table.php"),
        ], 'activity-tracker-migrations');
    }

    private function registerEloquentTracking(): void
    {
        Event::listen('eloquent.*', [EloquentActivityObserver::class, 'handle']);
    }

    private function registerQueryTracking(): void
    {
        Event::listen(QueryExecuted::class, [DatabaseQueryListener::class, 'handle']);
    }

    private function registerRetrievalFlushing(): void
    {
        // Fires at the end of both HTTP requests and console commands
        // (both kernels call $app->terminate()), which is the natural point
        // to collapse every buffered "retrieved" event into one activity
        // per model class for this request/command.
        $this->app->terminating(function () {
            $this->app->make(RetrievalFlusher::class)->flush();
        });
    }

    private function registerQueueLifecycle(): void
    {
        // Long-running queue workers reuse the same process (and therefore
        // the same singleton TrackingContext) across many jobs. Without an
        // explicit reset, a causer, batch ID, or buffered retrieval from job
        // N could leak into the activity records for job N+1.
        Event::listen(JobProcessing::class, function () {
            $context = $this->app->make(TrackingContext::class);
            $context->reset();
            $context->markInQueueJob(true);
        });

        Event::listen(JobProcessed::class, function () {
            $this->app->make(RetrievalFlusher::class)->flush();
            $context = $this->app->make(TrackingContext::class);
            $context->reset();
            $context->markInQueueJob(false);
        });
    }

    private function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallCommand::class,
            ClearCommand::class,
            PruneCommand::class,
        ]);
    }

    private function publishViews(): void
    {
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/activity-tracker'),
        ], 'activity-tracker-views');
    }

    private function publishAssets(): void
    {
        $this->publishes([
            __DIR__.'/../resources/dist' => public_path('vendor/activity-tracker'),
        ], 'activity-tracker-assets');
    }

    /**
     * A safe, closed-by-default fallback: dashboard access is only granted
     * automatically in the local environment. Production access requires
     * the host application to define its own 'viewActivityTracker' Gate
     * (documented in the README) — this package intentionally never
     * assumes a role system, an `isAdmin()` method, or a specific User
     * model. Because service providers boot before the application's own
     * AuthServiceProvider in the normal Laravel bootstrap order, a Gate
     * definition from the host application overrides this default.
     */
    private function registerDefaultGate(): void
    {
        Gate::define('viewActivityTracker', function ($user = null) {
            return $this->app->environment('local');
        });
    }

    private function registerUiRoutes(): void
    {
        if (! config('activity-tracker.ui.enabled', true)) {
            return;
        }

        $prefix = trim((string) config('activity-tracker.ui.prefix', 'activity-tracker'), '/');

        // Static assets: no auth requirement, no sensitive data.
        Route::group([
            'prefix' => $prefix,
            'middleware' => ['web'],
            'as' => 'activity-tracker.',
        ], function () {
            Route::get('/assets/{file}', [AssetController::class, 'show'])
                ->where('file', '.*')
                ->name('assets');
        });

        $middleware = (array) config('activity-tracker.ui.middleware', ['web']);

        if (config('activity-tracker.ui.authorize', true)) {
            $middleware[] = 'can:viewActivityTracker';
        }

        Route::group([
            'prefix' => $prefix,
            'middleware' => $middleware,
            'as' => 'activity-tracker.',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/activity-tracker.php');
        });
    }
}
