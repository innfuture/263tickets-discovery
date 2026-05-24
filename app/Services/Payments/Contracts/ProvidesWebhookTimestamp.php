<?php

declare(strict_types=1);

namespace App\Services\Payments\Contracts;

use Illuminate\Http\Request;

/**
 * Drivers that can extract a signed timestamp from a webhook implement
 * this so the controller can reject replays outside a tolerance window.
 *
 * Drivers without a signed timestamp (cleartext providers, IP-allowlist
 * only) simply don't implement this contract — replay protection then
 * falls back to the body-hash dedupe alone.
 */
interface ProvidesWebhookTimestamp
{
    /**
     * Return the unix timestamp the provider signed into the payload,
     * or null if it cannot be extracted (in which case the controller
     * falls back to dedupe). Must be the timestamp from the signature
     * header, not the receipt time — that is the whole point.
     */
    public function webhookTimestamp(Request $request): ?int;

    /**
     * Tolerance window in seconds. Requests older than this are
     * rejected as replays. 300 seconds is the Stripe-standard default.
     */
    public function webhookReplayToleranceSeconds(): int;
}
