<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Handling;

use Abdulbaset\ActivityTracker\Services\ActivityTrackerExceptionService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Throwable;

/**
 * Wraps the application's real exception handler — via
 * Container::extend(), NOT a replacement binding — so this package can
 * observe exceptions without touching Laravel's own exception lifecycle in
 * any way. Every method delegates to the original handler unchanged;
 * report() additionally (and only additionally) records the exception as an
 * activity.
 *
 * Why this integration point specifically:
 * - `Container::extend()` decorates whatever the application already bound
 *   (its own custom handler, or Laravel's default) — it never replaces it,
 *   so custom `Handler::render()`/`Handler::register()` logic in the host
 *   application keeps working exactly as before.
 * - report()/shouldReport()/render()/renderForConsole() are all forwarded
 *   verbatim; this class adds behavior, it doesn't change any of them.
 * - A failure while recording (building the payload, writing to storage)
 *   is caught inside ActivityTrackerExceptionService — it can never prevent
 *   $this->handler->report($e) from still running with the ORIGINAL
 *   exception, so application behavior is unaffected either way.
 *
 * No return types are declared on the interface methods here to stay
 * compatible with Illuminate\Contracts\Debug\ExceptionHandler's own (loose)
 * signature across supported Laravel versions.
 */
final class ActivityTrackerExceptionHandlerDecorator implements ExceptionHandler
{
    public function __construct(
        private readonly ExceptionHandler $handler,
        private readonly ActivityTrackerExceptionService $exceptionService,
    ) {
    }

    public function report(Throwable $e)
    {
        $this->exceptionService->handle($e);

        return $this->handler->report($e);
    }

    public function shouldReport(Throwable $e)
    {
        return $this->handler->shouldReport($e);
    }

    public function render($request, Throwable $e)
    {
        return $this->handler->render($request, $e);
    }

    public function renderForConsole($output, Throwable $e)
    {
        return $this->handler->renderForConsole($output, $e);
    }
}
