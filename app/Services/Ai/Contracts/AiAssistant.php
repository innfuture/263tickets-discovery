<?php

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Models\Event;
use App\Models\Order;
use App\Models\RefundRequest;

/**
 * Organizer-facing AI helper. Methods are intentionally narrow + task-
 * specific (not "general chat") so the assistant stays grounded in
 * platform data and prompts can be carefully scoped.
 *
 * Implementations:
 *   StubAiAssistant   deterministic, no external API (dev/tests)
 *   ClaudeAssistant   Anthropic Claude via Messages API
 *   OpenAiAssistant   OpenAI Responses / Chat Completions
 *
 * All methods return either a typed result array or null on transient
 * failure — never throw, so the dashboard panel degrades gracefully.
 */
interface AiAssistant
{
    public function identifier(): string;

    /**
     * Draft promotional copy for an event. Returns:
     *   ['headline' => string, 'body' => string, 'tags' => list<string>]
     *
     * @return array{headline: string, body: string, tags: list<string>}|null
     */
    public function generateEventCopy(Event $event, array $options = []): ?array;

    /**
     * Suggest a reply to a refund request that the organizer can
     * accept / edit / reject. Returns:
     *   ['recommended_action' => 'approve'|'reject'|'negotiate',
     *    'reply' => string,
     *    'reasoning' => string]
     *
     * @return array{recommended_action: string, reply: string, reasoning: string}|null
     */
    public function draftRefundReply(RefundRequest $request, ?Order $order = null): ?array;

    /**
     * Summarise comparable events in the org's category that sold
     * faster. Returns:
     *   ['insights' => list<string>, 'tactics' => list<string>]
     *
     * @return array{insights: list<string>, tactics: list<string>}|null
     */
    public function generateSalesInsight(Event $event): ?array;
}
