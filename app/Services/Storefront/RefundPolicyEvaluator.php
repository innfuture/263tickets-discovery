<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\Event;
use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * Evaluates an event's per-event refund policy against a specific
 * order at a specific point in time, returning the eligible refund
 * percentage + an `is_refundable` flag.
 *
 * Schema (in `events.refund_policy_rules` JSON):
 *
 *   {
 *     "rules": [
 *       {"hours_before": 168, "percent": 100},
 *       {"hours_before": 24,  "percent": 50}
 *     ],
 *     "default_percent": 0,
 *     "non_refundable_fees": true
 *   }
 *
 * Semantics: walk rules in descending hours_before; first match wins.
 * If no rule matches (request too close to door), use default_percent.
 * `non_refundable_fees=true` excludes platform + processor fee from
 * the refundable amount.
 */
class RefundPolicyEvaluator
{
    /** @return array{is_refundable: bool, percent: int, refundable_cents: int, reason: string} */
    public function evaluate(Order $order, ?Event $event = null, ?Carbon $now = null): array
    {
        $event ??= $order->event;
        $now ??= Carbon::now();

        // Default — if no event or starts_at, fall back to the model's
        // status-based heuristic.
        if (! $event || ! $event->starts_at) {
            return [
                'is_refundable' => $order->isRefundable(),
                'percent' => $order->isRefundable() ? 100 : 0,
                'refundable_cents' => $order->isRefundable() ? (int) $order->total_cents : 0,
                'reason' => $order->isRefundable() ? 'order_status_eligible' : 'order_status_ineligible',
            ];
        }

        $rules = (array) ($event->refund_policy_rules ?? []);
        $list = (array) ($rules['rules'] ?? []);
        $defaultPercent = (int) ($rules['default_percent'] ?? 0);
        $excludeFees = (bool) ($rules['non_refundable_fees'] ?? false);

        $hoursOut = $now->diffInHours($event->starts_at, false);

        // Descending: longest lead time first.
        usort($list, fn ($a, $b) => (int) ($b['hours_before'] ?? 0) <=> (int) ($a['hours_before'] ?? 0));

        $matched = null;
        foreach ($list as $rule) {
            $threshold = (int) ($rule['hours_before'] ?? 0);
            if ($hoursOut >= $threshold) {
                $matched = $rule;
                break;
            }
        }

        $percent = $matched ? (int) ($matched['percent'] ?? 0) : $defaultPercent;
        $base = (int) $order->total_cents;
        if ($excludeFees) {
            $base -= (int) ($order->fee_cents ?? 0);
        }

        $refundable = max(0, (int) floor(($base * $percent) / 100));

        return [
            'is_refundable' => $percent > 0,
            'percent' => $percent,
            'refundable_cents' => $refundable,
            'reason' => $matched ? 'rule_matched' : ($percent > 0 ? 'default_applied' : 'no_eligible_window'),
        ];
    }
}
