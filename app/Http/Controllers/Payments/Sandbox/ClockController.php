<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments\Sandbox;

use App\Http\Controllers\Controller;
use App\Models\SandboxMerchant;
use App\Services\Payments\Sandbox\SandboxClock;
use App\Services\Payments\Sandbox\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Virtual-clock control plane (§3.6). A successful advance also flushes
 * any webhooks whose `scheduled_for` is now ≤ now() — otherwise tests
 * would advance the clock and still have to wait wall-time for
 * deliveries.
 */
class ClockController extends Controller
{
    public function __construct(
        protected SandboxClock $clock,
        protected WebhookDispatcher $webhooks,
    ) {}

    public function show(SandboxMerchant $merchant): JsonResponse
    {
        return response()->json([
            'merchant' => $merchant->slug,
            'virtual_time' => $this->clock->now($merchant)->toIso8601String(),
            'wall_time' => now()->toIso8601String(),
        ]);
    }

    public function advance(Request $request, SandboxMerchant $merchant): JsonResponse
    {
        $data = $request->validate([
            'seconds' => ['required', 'integer', 'min:1', 'max:31536000'], // ≤ 1 year per call
        ]);

        $newTime = $this->clock->advance($merchant, (int) $data['seconds']);
        $delivered = $this->webhooks->flushDue($merchant);

        return response()->json([
            'merchant' => $merchant->slug,
            'virtual_time' => $newTime->toIso8601String(),
            'webhooks_flushed' => count($delivered),
        ]);
    }

    public function reset(SandboxMerchant $merchant): JsonResponse
    {
        $this->clock->reset($merchant);

        return response()->json(['merchant' => $merchant->slug, 'mode' => 'real']);
    }
}
