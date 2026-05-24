<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ScanEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after every scan attempt. Both a synchronous listener
 * (QueueScanWebhook) and the broadcasting layer subscribe.
 *
 * Broadcasting:
 *   - Implements ShouldBroadcast so any non-`null` BROADCAST_CONNECTION
 *     fans this event out automatically. No code edits needed to
 *     switch between Reverb / Pusher / Ably / log.
 *   - Channel is per-organisation private; the auth callback in
 *     routes/channels.php gates membership.
 *   - The broadcast payload is intentionally lean — UI clients
 *     refetch from the REST API for full detail if they want it.
 *     Keeps the WS frame small and avoids relation loading on the
 *     hot path.
 *
 * When BROADCAST_CONNECTION=null (the default), `broadcastWhen()`
 * short-circuits so we don't dispatch a broadcast job for nothing.
 */
class TicketScanned implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly ScanEvent $scanEvent) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $orgUuid = $this->scanEvent->profile?->organisation_id ?? 'unknown';

        return [
            new PrivateChannel("organization.{$orgUuid}.scans"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.scanned';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $s = $this->scanEvent;

        return [
            'scan_uuid' => $s->uuid,
            'verdict' => $s->verdict,
            'reason_code' => $s->reason_code,
            'was_admitted' => (bool) $s->was_admitted,
            'was_duplicate' => (bool) $s->was_duplicate,
            'was_voided' => (bool) $s->was_voided,
            'payload' => $s->payload,
            'event_id' => $s->event_id,
            'profile_uuid' => $s->profile?->uuid,
            'device_uuid' => $s->device?->uuid,
            'flags' => $s->fraud_flags,
            'created_at' => $s->created_at?->toIso8601String(),
        ];
    }

    /**
     * Skip the broadcast queue entirely on the null driver. Saves a
     * queue dispatch on every scan in deployments without WS infra.
     */
    public function broadcastWhen(): bool
    {
        return (string) config('broadcasting.default', 'null') !== 'null';
    }
}
