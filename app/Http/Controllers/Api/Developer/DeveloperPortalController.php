<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Developer;

use App\Http\Controllers\Controller;
use App\Models\DeveloperAccount;
use App\Models\DeveloperApiKey;
use App\Models\DeveloperSubscription;
use App\Services\Developer\DeveloperTierPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Developer self-service for the public API.
 *
 *   POST   /api/developer/portal/accounts                register
 *   GET    /api/developer/portal/accounts/{uuid}         account + subscription
 *   POST   /api/developer/portal/accounts/{uuid}/keys    mint a key (plaintext once)
 *   GET    /api/developer/portal/accounts/{uuid}/keys    list keys (no secret)
 *   DELETE /api/developer/portal/accounts/{uuid}/keys/{key_uuid}  revoke
 *   POST   /api/developer/portal/accounts/{uuid}/subscribe       set tier
 *
 * Auth here is intentionally light (registration is open) — billing
 * lives in a separate Stripe-style flow; this controller only
 * captures intent + activates the tier on the account.
 */
class DeveloperPortalController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'name' => ['required', 'string', 'max:191'],
            'company' => ['nullable', 'string', 'max:191'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'website' => ['nullable', 'url', 'max:500'],
        ]);

        $account = DeveloperAccount::query()
            ->firstOrCreate(['email' => strtolower($validated['email'])], $validated);

        // Auto-start a Free subscription so the account is immediately
        // usable for issuing Free keys.
        DeveloperSubscription::query()->firstOrCreate(
            [
                'developer_account_id' => $account->id,
                'tier' => DeveloperSubscription::TIER_FREE,
            ],
            ['starts_at' => now(), 'billing_status' => 'active'],
        );

        return response()->json([
            'data' => [
                'uuid' => $account->uuid,
                'email' => $account->email,
                'tier' => DeveloperSubscription::TIER_FREE,
            ],
        ], $account->wasRecentlyCreated ? 201 : 200);
    }

    public function show(string $uuid): JsonResponse
    {
        $account = DeveloperAccount::query()->where('uuid', $uuid)->first();
        if (! $account) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $sub = $account->activeSubscription;

        return response()->json([
            'data' => [
                'uuid' => $account->uuid,
                'email' => $account->email,
                'name' => $account->name,
                'company' => $account->company,
                'status' => $account->status,
                'tier' => $sub?->tier ?? DeveloperSubscription::TIER_FREE,
                'tier_limits' => DeveloperTierPolicy::for($sub?->tier ?? DeveloperSubscription::TIER_FREE),
                'subscription' => $sub ? [
                    'starts_at' => optional($sub->starts_at)->toIso8601String(),
                    'renews_at' => optional($sub->renews_at)->toIso8601String(),
                    'expires_at' => optional($sub->expires_at)->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    public function issueKey(Request $request, string $uuid): JsonResponse
    {
        $account = DeveloperAccount::query()->where('uuid', $uuid)->first();
        if (! $account) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $sub = $account->activeSubscription;
        $tier = $sub?->tier ?? DeveloperSubscription::TIER_FREE;
        $policy = DeveloperTierPolicy::for($tier);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        // Defaults: every scope the tier permits.
        $requested = (array) ($validated['scopes'] ?? $policy['allowed_scopes']);
        $scopes = array_values(array_intersect($requested, (array) $policy['allowed_scopes']));
        if (empty($scopes) && ! in_array('*', $policy['allowed_scopes'], true)) {
            return response()->json(['error' => 'no_scopes_in_tier'], 422);
        }

        $plaintext = DeveloperApiKey::KEY_PREFIX.Str::random(48);

        $key = DeveloperApiKey::create([
            'developer_account_id' => $account->id,
            'label' => $validated['label'],
            'key_hash' => hash('sha256', $plaintext),
            'key_prefix' => substr($plaintext, 0, 12),
            'tier' => $tier,
            'scopes' => $scopes,
            'monthly_quota' => $policy['monthly_quota'],
            'quota_used_this_month' => 0,
            'quota_reset_at' => Carbon::now()->startOfMonth()->addMonthNoOverflow(),
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        return response()->json([
            'data' => [
                'key_uuid' => $key->uuid,
                'plaintext' => $plaintext,
                'prefix' => $key->key_prefix,
                'tier' => $key->tier,
                'scopes' => $key->scopes,
                'monthly_quota' => $key->monthly_quota,
                'note' => 'Token shown once — store it now.',
            ],
        ], 201);
    }

    public function listKeys(string $uuid): JsonResponse
    {
        $account = DeveloperAccount::query()->where('uuid', $uuid)->first();
        if (! $account) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json([
            'data' => $account->apiKeys()
                ->orderByDesc('id')
                ->get([
                    'uuid', 'label', 'key_prefix', 'tier', 'scopes',
                    'monthly_quota', 'quota_used_this_month',
                    'last_used_at', 'last_used_ip', 'expires_at', 'revoked_at',
                ]),
        ]);
    }

    public function revokeKey(string $uuid, string $keyUuid): JsonResponse
    {
        $account = DeveloperAccount::query()->where('uuid', $uuid)->first();
        $key = $account?->apiKeys()->where('uuid', $keyUuid)->first();
        if (! $key) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $key->forceFill(['revoked_at' => now()])->save();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function subscribe(Request $request, string $uuid): JsonResponse
    {
        $account = DeveloperAccount::query()->where('uuid', $uuid)->first();
        if (! $account) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $validated = $request->validate([
            'tier' => ['required', 'in:'.implode(',', DeveloperSubscription::ALL_TIERS)],
        ]);

        // End the prior active sub, start a new one. We don't bill
        // here — that's a Stripe-side concern. The dashboard would
        // gate this behind a paywall in front of the API.
        DeveloperSubscription::query()
            ->where('developer_account_id', $account->id)
            ->where('billing_status', 'active')
            ->update(['billing_status' => 'inactive', 'expires_at' => now()]);

        $sub = DeveloperSubscription::create([
            'developer_account_id' => $account->id,
            'tier' => $validated['tier'],
            'billing_status' => 'active',
            'starts_at' => now(),
            'renews_at' => now()->addMonth(),
        ]);

        return response()->json([
            'data' => [
                'tier' => $sub->tier,
                'starts_at' => $sub->starts_at?->toIso8601String(),
            ],
        ]);
    }
}
