<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Stakeholder;

use App\Enums\StakeholderType;
use App\Http\Controllers\Controller;
use App\Models\Stakeholder;
use App\Models\StakeholderProfile;
use App\Services\Stakeholders\StakeholderAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 *   POST /api/v1/stakeholder/auth/register      (open — creates pending account)
 *   POST /api/v1/stakeholder/auth/request-link  (always-202)
 *   POST /api/v1/stakeholder/auth/verify        → bearer
 *   POST /api/v1/stakeholder/auth/logout        (auth'd)
 */
class StakeholderAuthController extends Controller
{
    public function __construct(protected StakeholderAuthService $auth) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'name' => ['required', 'string', 'max:191'],
            'company' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'type' => ['required', 'in:'.implode(',', array_column(StakeholderType::cases(), 'value'))],
        ]);

        // Idempotent — re-registering with the same email returns
        // the existing row + auto-sends a fresh magic link.
        $stakeholder = Stakeholder::query()->where('email', strtolower($validated['email']))->first();
        if (! $stakeholder) {
            $stakeholder = Stakeholder::create($validated);
            StakeholderProfile::create(['stakeholder_id' => $stakeholder->id]);
        }

        $this->auth->requestLink($stakeholder->email, $request->ip());

        return response()->json([
            'data' => [
                'uuid' => $stakeholder->uuid,
                'status' => $stakeholder->status,
                'note' => 'Account created. Check your email for the sign-in link.',
            ],
        ], 201);
    }

    public function requestLink(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:191']]);
        $this->auth->requestLink($validated['email'], $request->ip());

        return response()->json([
            'data' => ['message' => 'If that email is registered, a sign-in link is on its way.'],
        ], 202);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate(['token' => ['required', 'string', 'size:48']]);

        try {
            $result = $this->auth->verifyToken($validated['token'], $request->ip());
        } catch (\Throwable $e) {
            return response()->json(['error' => 'invalid_or_expired', 'message' => $e->getMessage()], 401);
        }

        return response()->json([
            'data' => [
                'bearer' => $result['bearer'],
                'stakeholder' => [
                    'uuid' => $result['stakeholder']->uuid,
                    'email' => $result['stakeholder']->email,
                    'name' => $result['stakeholder']->name,
                    'type' => $result['stakeholder']->type?->value,
                    'status' => $result['stakeholder']->status,
                ],
                'expires_at' => $result['session']->expires_at?->toIso8601String(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $session = $request->attributes->get('stakeholder_session');
        if ($session) {
            $this->auth->revokeSession($session);
        }

        return response()->json(['data' => ['ok' => true]]);
    }
}
