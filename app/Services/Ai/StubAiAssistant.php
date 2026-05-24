<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Event;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Services\Ai\Contracts\AiAssistant;

/**
 * Deterministic, no-network assistant. Returns useful-looking
 * templated content so the dashboard panel can be wired end-to-end
 * before a real LLM key is plugged in.
 */
class StubAiAssistant implements AiAssistant
{
    public function identifier(): string
    {
        return 'stub';
    }

    public function generateEventCopy(Event $event, array $options = []): ?array
    {
        $venue = $event->venue_name ?? $event->city ?? 'the venue';
        $tone = (string) ($options['tone'] ?? 'punchy');

        return [
            'headline' => sprintf('%s — %s', (string) $event->name, $venue),
            'body' => sprintf(
                "%s — %s. %s\n\nDoors open %s. Limited capacity. (Tone: %s)",
                (string) $event->name,
                $venue,
                (string) ($event->short_description ?? 'Get your tickets before they\'re gone.'),
                optional($event->doors_open_at ?? $event->starts_at)->format('D, M j · H:i') ?? 'soon',
                $tone,
            ),
            'tags' => array_filter([
                $event->category?->name,
                $event->city,
                'live',
            ]),
        ];
    }

    public function draftRefundReply(RefundRequest $request, ?Order $order = null): ?array
    {
        $reason = (string) $request->reason_code;
        $recommend = match ($reason) {
            'event_cancelled' => 'approve',
            'fraud', 'duplicate_purchase' => 'approve',
            'date_change', 'not_attending' => 'negotiate',
            default => 'reject',
        };

        return [
            'recommended_action' => $recommend,
            'reply' => "Hi,\n\nThanks for reaching out. We've reviewed your request and we'll be in touch shortly.\n\nBest,\nThe Team",
            'reasoning' => "Reason `{$reason}` typically maps to `{$recommend}`. Override based on policy.",
        ];
    }

    public function generateSalesInsight(Event $event): ?array
    {
        $sold = (int) ($event->tickets_sold_count ?? 0);
        $cap = (int) ($event->capacity ?? 0);
        $pct = $cap > 0 ? (int) round($sold / $cap * 100) : 0;

        return [
            'insights' => [
                "Currently sold {$sold}/{$cap} ({$pct}%).",
                $pct >= 50
                    ? 'You\'re tracking above the category median at this stage.'
                    : 'Consider promoting via your media partners — similar events at this stage saw a 30% boost from media coverage.',
            ],
            'tactics' => [
                'Send a 48-hour discount code to past attendees.',
                'Add a "last few tickets" badge to the storefront listing.',
                'Schedule one paid-social burst 7 days before doors.',
            ],
        ];
    }
}
