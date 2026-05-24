<?php

declare(strict_types=1);

namespace App\Services\Telemetry\Contracts;

use Throwable;

/**
 * Vendor-agnostic observability surface. Wraps spans, breadcrumbs,
 * counters, and error capture so the application code doesn't import
 * Sentry / OpenTelemetry / DataDog directly.
 *
 * Default binding is `LogTelemetry` (structured Log lines). Production
 * binds `SentryTelemetry` once the Sentry SDK is installed.
 */
interface Telemetry
{
    public function breadcrumb(string $category, string $message, array $context = []): void;

    /** Increments a named counter — used for funnel + delivery metrics. */
    public function counter(string $name, int $value = 1, array $tags = []): void;

    /** Wrap a closure; failures get reported AND re-thrown. */
    public function span(string $name, callable $work, array $context = []): mixed;

    public function captureException(Throwable $e, array $context = []): void;
}
