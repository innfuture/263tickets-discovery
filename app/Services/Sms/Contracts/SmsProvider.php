<?php

declare(strict_types=1);

namespace App\Services\Sms\Contracts;

/**
 * Provider-agnostic SMS sender contract. Implementations are bound
 * via config('sms.driver') and are responsible for upstream API
 * calls, retry, and failure handling. Callers (jobs, listeners)
 * never know which provider they're talking to.
 */
interface SmsProvider
{
    /**
     * Send a single SMS. Returns provider-side message id, or null
     * on failure (logged by the implementation, not raised, so a
     * single failed SMS doesn't poison a broadcast batch).
     */
    public function send(string $toMsisdn, string $body, ?string $from = null): ?string;

    /**
     * Identifier for telemetry / debug logs.
     */
    public function identifier(): string;
}
