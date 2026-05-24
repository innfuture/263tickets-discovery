<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 *   GET   /{org}/api/events/{event}/refund-policy
 *   PUT   /{org}/api/events/{event}/refund-policy
 *
 * Body shape mirrors what `RefundPolicyEvaluator` expects:
 *
 *   {
 *     "rules": [{"hours_before": 168, "percent": 100},
 *                {"hours_before": 24,  "percent": 50}],
 *     "default_percent": 0,
 *     "non_refundable_fees": true
 *   }
 */
class RefundPolicyController extends Controller
{
    public function show(Organization $currentOrganization, Event $event): JsonResponse
    {
        $this->assertOwned($event, $currentOrganization);

        return response()->json([
            'data' => $event->refund_policy_rules ?? [
                'rules' => [],
                'default_percent' => 0,
                'non_refundable_fees' => true,
            ],
        ]);
    }

    public function update(Organization $currentOrganization, Event $event, Request $request): JsonResponse
    {
        $this->assertOwned($event, $currentOrganization);

        $validated = $request->validate([
            'rules' => ['nullable', 'array'],
            'rules.*.hours_before' => ['required_with:rules.*.percent', 'integer', 'min:0'],
            'rules.*.percent' => ['required_with:rules.*.hours_before', 'integer', 'min:0', 'max:100'],
            'default_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'non_refundable_fees' => ['nullable', 'boolean'],
        ]);

        $event->forceFill([
            'refund_policy_rules' => [
                'rules' => $validated['rules'] ?? [],
                'default_percent' => (int) ($validated['default_percent'] ?? 0),
                'non_refundable_fees' => (bool) ($validated['non_refundable_fees'] ?? true),
            ],
        ])->save();

        return response()->json(['data' => $event->refund_policy_rules]);
    }

    protected function assertOwned(Event $event, Organization $org): void
    {
        abort_if($event->organisation_id !== $org->uuid, 404);
    }
}
