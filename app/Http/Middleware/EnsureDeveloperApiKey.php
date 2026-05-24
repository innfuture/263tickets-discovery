<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DeveloperApiKey;
use App\Services\Developer\DeveloperTierPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Authorization: Bearer dvk_…` lookup for the public developer API.
 * Three checks in order:
 *
 *   1. Key exists + not revoked + not expired.
 *   2. Required scope (per-route) is in the key's scopes AND in the
 *      tier's allowed_scopes (so a downgraded tier can't keep using
 *      premium scopes via a stale key).
 *   3. Monthly quota not exceeded.
 *
 * Usage: `middleware('developer.key:events.read')`
 *
 * Attaches the resolved key to the request so downstream middleware
 * (rate limiter + usage tracker) can find it.
 */
class EnsureDeveloperApiKey
{
    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        $bearer = $request->bearerToken();
        if (! $bearer || ! str_starts_with($bearer, DeveloperApiKey::KEY_PREFIX)) {
            return $this->reject('missing_token', 401);
        }

        $key = DeveloperApiKey::query()
            ->where('key_hash', hash('sha256', $bearer))
            ->first();

        if (! $key || ! $key->isActive()) {
            return $this->reject('invalid_token', 401);
        }

        if ($requiredScope !== null) {
            if (! $key->hasScope($requiredScope)) {
                return $this->reject('insufficient_scope', 403, ['required' => $requiredScope]);
            }
            if (! DeveloperTierPolicy::tierAllowsScope((string) $key->tier, $requiredScope)) {
                return $this->reject('scope_not_in_tier', 403, [
                    'required' => $requiredScope,
                    'tier' => $key->tier,
                ]);
            }
        }

        if ($key->quotaExceeded()) {
            return $this->reject('quota_exceeded', 429, [
                'tier' => $key->tier,
                'monthly_quota' => $key->monthly_quota,
                'reset_at' => optional($key->quota_reset_at)->toIso8601String(),
            ]);
        }

        $request->attributes->set('developer_api_key', $key);
        $request->attributes->set('developer_tier', (string) $key->tier);

        // Touch last-used + atomic counter increments before delegating.
        $key->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $request->ip(),
        ])->saveQuietly();

        DB::table('developer_api_keys')
            ->where('id', $key->id)
            ->increment('quota_used_this_month');

        return $next($request);
    }

    /** @param array<string, mixed> $extras */
    protected function reject(string $code, int $status, array $extras = []): Response
    {
        return response()->json(['error' => $code] + $extras, $status);
    }
}
