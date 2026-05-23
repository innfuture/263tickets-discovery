<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments\Sandbox;

use App\Http\Controllers\Controller;
use App\Models\SandboxMerchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Merchant admin — for the sandbox, the only operation that matters
 * day-to-day is rotating the webhook signing secret with a 24h
 * overlap window (§9). Tests use this to exercise the consumer's
 * dual-secret verification path.
 */
class MerchantController extends Controller
{
    public function show(SandboxMerchant $merchant): JsonResponse
    {
        return response()->json(['merchant' => $this->serialise($merchant)]);
    }

    public function rotateWebhookSecret(SandboxMerchant $merchant): JsonResponse
    {
        $merchant->webhook_signing_secret_previous = $merchant->webhook_signing_secret;
        $merchant->webhook_signing_secret = 'whsec_sbx_'.bin2hex(random_bytes(24));
        $merchant->webhook_secret_rotated_at = now();
        $merchant->save();

        return response()->json([
            'merchant' => $this->serialise($merchant),
            'new_secret' => $merchant->webhook_signing_secret,
            'previous_secret_valid_until' => $merchant->webhook_secret_rotated_at->addDay()->toIso8601String(),
        ]);
    }

    public function update(Request $request, SandboxMerchant $merchant): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'webhook_endpoint' => ['sometimes', 'nullable', 'url', 'max:1000'],
            'emulate_default' => ['sometimes', 'string', 'max:32'],
            'default_currency' => ['sometimes', 'string', 'size:3'],
            'is_private' => ['sometimes', 'boolean'],
            'webhooks_parallel' => ['sometimes', 'boolean'],
            'rules' => ['sometimes', 'nullable', 'array'],
        ]);

        $merchant->fill($data)->save();

        return response()->json(['merchant' => $this->serialise($merchant)]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialise(SandboxMerchant $merchant): array
    {
        $rotatedAt = $merchant->webhook_secret_rotated_at;

        return [
            'slug' => $merchant->slug,
            'name' => $merchant->name,
            'environment' => $merchant->environment,
            'emulate_default' => $merchant->emulate_default,
            'default_currency' => $merchant->default_currency,
            'webhook_endpoint' => $merchant->webhook_endpoint,
            'webhook_secret_last4' => $merchant->webhook_signing_secret
                ? substr($merchant->webhook_signing_secret, -4)
                : null,
            'previous_secret_active' => $merchant->webhook_signing_secret_previous !== null
                && $rotatedAt !== null
                && $rotatedAt->gt(now()->subDay()),
            'rotated_at' => $rotatedAt?->toIso8601String(),
            'is_private' => $merchant->is_private,
            'webhooks_parallel' => $merchant->webhooks_parallel,
            'rules' => $merchant->rules,
        ];
    }
}
