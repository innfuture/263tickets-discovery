<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Event;
use App\Models\EventDraft;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushed to every collaborator subscribed to `event.{slug}.draft`
 * after a successful `EventDraftController::apply()`. Carries the
 * new version + a small diff hint so the client can reconcile
 * without re-fetching the full doc.
 */
class EventDraftUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Event $event,
        public EventDraft $draft,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('event.'.$this->event->slug.'.draft')];
    }

    public function broadcastAs(): string
    {
        return 'draft.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'event_slug' => $this->event->slug,
            'version' => (int) $this->draft->version,
            'last_editor_user_id' => $this->draft->last_editor_user_id,
            'updated_at' => optional($this->draft->updated_at)->toIso8601String(),
        ];
    }

    public function broadcastWhen(): bool
    {
        return (string) config('broadcasting.default') !== 'null';
    }
}
