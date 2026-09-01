<?php

declare(strict_types=1);

use Abdulbaset\ActivityTracker\Http\Controllers\ActivityController;
use Abdulbaset\ActivityTracker\Http\Controllers\ActivityDashboardController;
use Abdulbaset\ActivityTracker\Http\Controllers\ActivityStatisticsController;
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

Route::get('/', [ActivityDashboardController::class, 'index'])->name('dashboard');

Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');
Route::get('/activities/{activity}', [ActivityController::class, 'show'])->name('activities.show');

Route::get('/statistics', [ActivityStatisticsController::class, 'index'])->name('statistics');
