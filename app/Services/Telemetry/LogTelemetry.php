<?php

declare(strict_types=1);

namespace App\Services\Telemetry;

use App\Services\Telemetry\Contracts\Telemetry;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Structured-log impl. Suitable for dev and small deployments —
 * everything goes to the `telemetry` log channel (or `default` if
 * not configured). Production should bind SentryTelemetry instead.
 */
class LogTelemetry implements Telemetry
{
    public function __construct(protected string $channel = 'default') {}

    public function breadcrumb(string $category, string $message, array $context = []): void
    {
        Log::channel($this->channel)->info("telemetry.{$category}", $context + ['message' => $message]);
    }

    public function counter(string $name, int $value = 1, array $tags = []): void
    {
        Log::channel($this->channel)->info("telemetry.counter.{$name}", ['value' => $value, 'tags' => $tags]);
    }

    public function span(string $name, callable $work, array $context = []): mixed
    {
        $start = microtime(true);
        try {
            $result = $work();
            Log::channel($this->channel)->info("telemetry.span.{$name}", $context + [
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'ok' => true,
            ]);

            return $result;
        } catch (Throwable $e) {
            Log::channel($this->channel)->error("telemetry.span.{$name}", $context + [
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'ok' => false,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function captureException(Throwable $e, array $context = []): void
    {
        Log::channel($this->channel)->error('telemetry.exception', $context + [
            'class' => $e::class,
            'message' => $e->getMessage(),
            'file' => $e->getFile().':'.$e->getLine(),
        ]);
    }
}
