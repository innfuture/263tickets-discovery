<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox;

use App\Enums\SandboxState;

/**
 * The shape returned by ScenarioResolver — a deterministic recipe the
 * SandboxKernel applies to one transaction.
 *
 *   immediateState   the state we land on synchronously inside charge()
 *   followUpState    the state we land on later (null if `immediateState`
 *                    is already terminal); applied by the webhook
 *                    dispatcher when its timer fires
 *   webhookDelay     wall-time delay before the follow-up webhook is sent
 *   webhookEvents    sequence of event types to deliver (e.g. ['payment.authorized', 'payment.captured'])
 *   reasonCode       provider-vocab code attached to FAILED/declined states
 *   redirectUrl      hosted-page URL (3DS challenge, mock checkout)
 *   responseDelayMs  artificial latency on the synchronous response
 *   duplicateCount   how many times to deliver each webhook (for webhook.duplicate)
 *   outOfOrder       whether to deliver the second event before the first
 *   failNextWebhooks deliver N HTTP 500s before succeeding (for retry tests)
 */
final class Outcome
{
    /**
     * @param  array<int, string>  $webhookEvents
     */
    public function __construct(
        public readonly SandboxState $immediateState,
        public readonly ?SandboxState $followUpState = null,
        public readonly int $webhookDelaySeconds = 1,
        public readonly array $webhookEvents = [],
        public readonly ?string $reasonCode = null,
        public readonly ?string $redirectUrl = null,
        public readonly int $responseDelayMs = 0,
        public readonly int $duplicateCount = 1,
        public readonly bool $outOfOrder = false,
        public readonly int $failNextWebhooks = 0,
        public readonly ?string $instructions = null,
    ) {}
}
