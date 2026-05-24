<?php

declare(strict_types=1);

namespace App\Services\Telemetry;

use App\Services\Telemetry\Contracts\Telemetry;
use Throwable;

/**
 * Sentry-backed impl. Active when the `sentry/sentry-laravel` package
 * is installed (`composer require sentry/sentry-laravel`) AND
 * SENTRY_LARAVEL_DSN is set.
 *
 * Detects missing dependencies via `function_exists` so unbound
 * deployments don't crash — falls back to no-op behaviour.
 */
class SentryTelemetry implements Telemetry
{
    public function breadcrumb(string $category, string $message, array $context = []): void
    {
        if (! function_exists('\\Sentry\\addBreadcrumb')) {
            return;
        }
        \Sentry\addBreadcrumb(new \Sentry\Breadcrumb(
            level: \Sentry\Breadcrumb::LEVEL_INFO,
            type: \Sentry\Breadcrumb::TYPE_DEFAULT,
            category: $category,
            message: $message,
            metadata: $context,
        ));
    }

    public function counter(string $name, int $value = 1, array $tags = []): void
    {
        if (! function_exists('\\Sentry\\metrics')) {
            return;
        }
        \Sentry\metrics()->increment(key: $name, value: $value, tags: $tags);
    }

    public function span(string $name, callable $work, array $context = []): mixed
    {
        if (! function_exists('\\Sentry\\startTransaction')) {
            return $work();
        }

        $transaction = \Sentry\startTransaction(
            new \Sentry\Tracing\TransactionContext($name),
        );
        try {
            $result = $work();
            $transaction->setStatus(\Sentry\Tracing\SpanStatus::ok());

            return $result;
        } catch (Throwable $e) {
            $transaction->setStatus(\Sentry\Tracing\SpanStatus::internalError());
            $this->captureException($e, $context);
            throw $e;
        } finally {
            $transaction->finish();
        }
    }

    public function captureException(Throwable $e, array $context = []): void
    {
        if (! function_exists('\\Sentry\\captureException')) {
            return;
        }
        \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($e, $context): void {
            foreach ($context as $k => $v) {
                $scope->setExtra((string) $k, $v);
            }
            \Sentry\captureException($e);
        });
    }
}
