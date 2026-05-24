<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Services\Sms\Contracts\SmsProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Twilio Messages API driver. Talks REST directly (no twilio-php
 * SDK) — same low-dependency reasoning as the Stripe driver.
 *
 * Failures are logged and swallowed (return null). A single failed
 * SMS in a broadcast batch shouldn't kill the rest; the caller can
 * inspect the return value to retry per-recipient if it cares.
 */
class TwilioSmsProvider implements SmsProvider
{
    public function __construct(
        protected string $accountSid,
        protected string $authToken,
        protected string $fromNumber,
        protected int $timeoutSeconds = 10,
    ) {}

    public function send(string $toMsisdn, string $body, ?string $from = null): ?string
    {
        try {
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->asForm()
                ->timeout($this->timeoutSeconds)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
                    'To' => $toMsisdn,
                    'From' => $from ?? $this->fromNumber,
                    'Body' => $body,
                ]);

            $payload = $response->json();
            if ($response->failed() || ! is_array($payload) || ! isset($payload['sid'])) {
                Log::warning('sms.twilio.send_failed', [
                    'to' => $toMsisdn,
                    'status' => $response->status(),
                    'error' => is_array($payload) ? ($payload['message'] ?? null) : null,
                ]);

                return null;
            }

            return (string) $payload['sid'];
        } catch (\Throwable $e) {
            Log::warning('sms.twilio.send_exception', [
                'to' => $toMsisdn,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function identifier(): string
    {
        return 'twilio';
    }
}
