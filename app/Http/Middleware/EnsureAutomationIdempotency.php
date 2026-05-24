<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AutomationIdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replay-safe `Idempotency-Key` header handling on POST automation
 * endpoints. n8n retries (workflow re-runs, manual re-fires) hit us
 * with the same key — we return the cached response without
 * re-executing.
 *
 * Validates that the body hash matches the previously-stored hash;
 * if the body differs under the same key, returns 409 to surface
 * the bug rather than silently masking it.
 */
class EnsureAutomationIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) ($request->header('Idempotency-Key') ?? '');
        if ($key === '') {
            return $next($request);
        }

        $token = $request->attributes->get('automation_token');
        if (! $token) {
            return $next($request);
        }

        $rawBody = (string) $request->getContent();
        $bodyHash = hash('sha256', $rawBody);

        $existing = AutomationIdempotencyKey::query()
            ->where('automation_token_id', $token->id)
            ->where('key', $key)
            ->first();

        if ($existing) {
            if ($existing->request_hash !== $bodyHash) {
                return response()->json([
                    'error' => 'idempotency_key_conflict',
                    'message' => 'This Idempotency-Key was previously used with a different body.',
                ], 409);
            }

            return response()->json(
                $existing->response_body ?? ['cached' => true],
                $existing->response_status,
            );
        }

        $response = $next($request);

        // Cache the response. Don't cache 5xx (let the caller retry
        // a fresh run) and don't cache 401/403 (auth changes shouldn't
        // be sticky).
        if ($response->getStatusCode() < 500 && ! in_array($response->getStatusCode(), [401, 403], true)) {
            $decoded = json_decode((string) $response->getContent(), true);
            AutomationIdempotencyKey::create([
                'automation_token_id' => $token->id,
                'key' => $key,
                'request_hash' => $bodyHash,
                'response_status' => $response->getStatusCode(),
                'response_body' => is_array($decoded) ? $decoded : null,
            ]);
        }

        return $response;
    }
}
