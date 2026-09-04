<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Serves the dashboard's CSS/JS without requiring a build step or a
 * `vendor:publish` call — the package "just works" out of the box, the same
 * way the tracking engine itself requires no setup beyond a migration.
 *
 * If the host application HAS published the assets (to customize them),
 * the published copy always takes precedence over the package's own copy.
 *
 * The `$file` parameter is matched against a fixed whitelist rather than
 * trusted as a filesystem path — this is a public, unauthenticated route,
 * so it must not be usable for path traversal or arbitrary file reads.
 */
final class ActivityTrackerAssetController extends Controller
{
    private const ALLOWED = [
        'css/app.css' => 'text/css; charset=UTF-8',
        'js/app.js' => 'application/javascript; charset=UTF-8',
    ];

    public function show(string $file): Response
    {
        if (! isset(self::ALLOWED[$file])) {
            abort(404);
        }

        $published = public_path('vendor/activity-tracker/'.$file);
        $packaged = __DIR__.'/../../../resources/dist/'.$file;

        $path = is_file($published) ? $published : $packaged;

        if (! is_file($path)) {
            abort(404);
        }

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => self::ALLOWED[$file],
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
