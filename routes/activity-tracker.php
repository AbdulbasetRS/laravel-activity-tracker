<?php

declare(strict_types=1);

use Abdulbaset\ActivityTracker\Http\Controllers\ActivityTrackerActivityController;
use Abdulbaset\ActivityTracker\Http\Controllers\ActivityTrackerAuthenticationController;
use Abdulbaset\ActivityTracker\Http\Controllers\ActivityTrackerBroadcastController;
use Abdulbaset\ActivityTracker\Http\Controllers\ActivityTrackerDashboardController;
use Abdulbaset\ActivityTracker\Http\Controllers\ActivityTrackerStatisticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Activity Tracker Dashboard Routes (authorized group)
|--------------------------------------------------------------------------
|
| Only ever loaded by the service provider when 'activity-tracker.ui.enabled'
| is true — see ActivityTrackerServiceProvider::registerUiRoutes(). The
| middleware group (including, conditionally, the `can:viewActivityTracker`
| authorization gate) is assembled there too, so nothing here needs to
| re-check configuration.
|
| The static asset route (CSS/JS) is registered separately by the service
| provider, outside this authorized group — see registerAssetRoute(). It
| serves no sensitive data and intentionally has no auth requirement.
|
*/

Route::get('/', [ActivityTrackerDashboardController::class, 'index'])->name('dashboard');

Route::get('/activities', [ActivityTrackerActivityController::class, 'index'])->name('activities.index');
Route::get('/activities/{activity}', [ActivityTrackerActivityController::class, 'show'])->name('activities.show');

Route::get('/statistics', [ActivityTrackerStatisticsController::class, 'index'])->name('statistics');

Route::get('/authentication', [ActivityTrackerAuthenticationController::class, 'index'])->name('authentication');

Route::get('/broadcasts', [ActivityTrackerBroadcastController::class, 'index'])->name('broadcasts');
Route::get('/broadcasts/{channel}', [ActivityTrackerBroadcastController::class, 'channel'])
    ->name('broadcasts.channel');
