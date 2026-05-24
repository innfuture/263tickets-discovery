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
 *   POST   /api/developer/portal/accounts                    register (open)
 *   GET    /api/developer/portal/accounts/{uuid}             account + subscription (bearer)
 *   POST   /api/developer/portal/accounts/{uuid}/keys        mint a key (bearer)
 *   GET    /api/developer/portal/accounts/{uuid}/keys        list keys (bearer)
 *   DELETE /api/developer/portal/accounts/{uuid}/keys/{u}    revoke key (bearer)
 *   POST   /api/developer/portal/accounts/{uuid}/subscribe   set tier (bearer)
 *   POST   /api/developer/portal/accounts/{uuid}/rotate-token rotate portal token (bearer)
 *
 * Registration is the only open endpoint. It returns the
 * portal_bootstrap_token in plaintext exactly once; every other
 * mutation requires `Authorization: Bearer <token>`.
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

        $existing = DeveloperAccount::query()
            ->where('email', strtolower($validated['email']))
            ->first();

        $plaintext = null;

        if ($existing) {
            $account = $existing;
        } else {
            $plaintext = $this->generateBootstrapToken();
            $account = DeveloperAccount::query()->create($validated + [
                'portal_bootstrap_token_hash' => hash('sha256', $plaintext),
                'portal_bootstrap_token_rotated_at' => now(),
            ]);
        }

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
                'portal_bootstrap_token' => $plaintext,
                'note' => $plaintext
                    ? 'Token shown once — store it. Use as Authorization: Bearer <token>.'
                    : 'Account already exists. Rotate the token to receive a new one.',
            ],
        ], $account->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $account = $this->resolveAccount($request, $uuid);
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
        $account = $this->resolveAccount($request, $uuid);
        $sub = $account->activeSubscription;
        $tier = $sub?->tier ?? DeveloperSubscription::TIER_FREE;
        $policy = DeveloperTierPolicy::for($tier);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

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

    public function listKeys(Request $request, string $uuid): JsonResponse
    {
        $account = $this->resolveAccount($request, $uuid);

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

    public function revokeKey(Request $request, string $uuid, string $keyUuid): JsonResponse
    {
        $account = $this->resolveAccount($request, $uuid);
        $key = $account->apiKeys()->where('uuid', $keyUuid)->first();
        if (! $key) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $key->forceFill(['revoked_at' => now()])->save();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function subscribe(Request $request, string $uuid): JsonResponse
    {
        $account = $this->resolveAccount($request, $uuid);

        $validated = $request->validate([
            'tier' => ['required', 'in:'.implode(',', DeveloperSubscription::ALL_TIERS)],
        ]);

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

    public function rotateToken(Request $request, string $uuid): JsonResponse
    {
        $account = $this->resolveAccount($request, $uuid);
        $plaintext = $this->generateBootstrapToken();

        $account->forceFill([
            'portal_bootstrap_token_hash' => hash('sha256', $plaintext),
            'portal_bootstrap_token_rotated_at' => now(),
        ])->save();

        return response()->json([
            'data' => [
                'portal_bootstrap_token' => $plaintext,
                'rotated_at' => $account->portal_bootstrap_token_rotated_at?->toIso8601String(),
                'note' => 'Token shown once — store it. Previous token is invalidated.',
            ],
        ]);
    }

    protected function resolveAccount(Request $request, string $uuid): DeveloperAccount
    {
        $cached = $request->attributes->get('developer_account');
        if ($cached instanceof DeveloperAccount && $cached->uuid === $uuid) {
            return $cached;
        }

        $account = DeveloperAccount::query()->where('uuid', $uuid)->firstOrFail();
        $request->attributes->set('developer_account', $account);

        return $account;
    }

    protected function generateBootstrapToken(): string
    {
        return 'dpt_'.Str::random(48);
    }
}
