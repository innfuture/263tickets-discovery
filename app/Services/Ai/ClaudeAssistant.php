<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Event;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Services\Ai\Contracts\AiAssistant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Anthropic Claude integration via the Messages API. Set
 * `ANTHROPIC_API_KEY` in env + flip `AI_ASSISTANT_DRIVER=claude` to
 * activate.
 *
 * Returns null on transport / parse failure so the dashboard
 * degrades to a "draft unavailable" state rather than 500-ing.
 *
 * Why one method per task instead of a generic `prompt()`:
 * - Each task has a stable system-prompt + a stable JSON schema
 *   for the response → easier to test, easier to gate releases on.
 * - Misuse via free-form chat is impossible.
 */
class ClaudeAssistant implements AiAssistant
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'claude-sonnet-4-6',
        protected int $timeoutSeconds = 20,
    ) {}

    public function identifier(): string
    {
        return 'claude';
    }

    public function generateEventCopy(Event $event, array $options = []): ?array
    {
        $tone = (string) ($options['tone'] ?? 'punchy');
        $payload = $this->call(
            system: 'You are a marketing copywriter for live events. Return strict JSON with keys: headline, body, tags (array of <=6 strings). No prose.',
            user: 'Event: '.json_encode([
                'name' => $event->name,
                'description' => $event->short_description ?? $event->description,
                'venue' => $event->venue_name,
                'city' => $event->city,
                'tone' => $tone,
            ]),
        );

        return $this->coerceShape($payload, ['headline', 'body', 'tags']);
    }

    public function draftRefundReply(RefundRequest $request, ?Order $order = null): ?array
    {
        $payload = $this->call(
            system: 'You triage refund requests for an event organizer. Return strict JSON with keys: recommended_action (one of approve|reject|negotiate), reply (markdown, max 200 words, addressed to the buyer), reasoning (one paragraph for the operator).',
            user: 'Refund request: '.json_encode([
                'reason_code' => $request->reason_code,
                'notes' => $request->notes,
                'order_reference' => $order?->reference,
                'order_total_cents' => $order?->total_cents,
                'event_starts_at' => optional($order?->event?->starts_at)->toIso8601String(),
            ]),
        );

        return $this->coerceShape($payload, ['recommended_action', 'reply', 'reasoning']);
    }

    public function generateSalesInsight(Event $event): ?array
    {
        $payload = $this->call(
            system: 'You analyse event sales velocity. Return strict JSON with keys: insights (list of bullets), tactics (list of suggested actions).',
            user: 'Event: '.json_encode([
                'name' => $event->name,
                'category' => $event->category?->name,
                'tickets_sold' => $event->tickets_sold_count,
                'capacity' => $event->capacity,
                'starts_at' => optional($event->starts_at)->toIso8601String(),
            ]),
        );

        return $this->coerceShape($payload, ['insights', 'tactics']);
    }

    protected function call(string $system, string $user): ?array
    {
        if ($this->apiKey === '') {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->timeoutSeconds)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $this->model,
                    'max_tokens' => 1024,
                    'system' => $system,
                    'messages' => [
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('ai.claude.transport_error', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('ai.claude.api_error', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);

            return null;
        }

        $text = $response->json('content.0.text');
        if (! is_string($text)) {
            return null;
        }
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string, mixed>|null $payload @param list<string> $keys */
    protected function coerceShape(?array $payload, array $keys): ?array
    {
        if ($payload === null) {
            return null;
        }
        foreach ($keys as $k) {
            if (! array_key_exists($k, $payload)) {
                return null;
            }
        }

        return $payload;
    }
}
