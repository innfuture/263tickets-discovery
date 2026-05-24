<?php

declare(strict_types=1);

namespace App\Services\Telemetry;

use App\Services\Telemetry\Contracts\Telemetry;
use Throwable;

/**
 * Always-quiet impl. Bound when `telemetry.driver=null` — used by
 * the test suite and minimal local runs.
 */
class NullTelemetry implements Telemetry
{
    public function breadcrumb(string $category, string $message, array $context = []): void {}

    public function counter(string $name, int $value = 1, array $tags = []): void {}

    public function span(string $name, callable $work, array $context = []): mixed
    {
        return $work();
    }

    public function captureException(Throwable $e, array $context = []): void {}
}
