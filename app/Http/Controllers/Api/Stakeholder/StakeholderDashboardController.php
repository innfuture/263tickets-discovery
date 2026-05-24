<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Stakeholder;

use App\Http\Controllers\Controller;
use App\Models\EventStakeholderEngagement;
use App\Models\StakeholderApplication;
use App\Models\StakeholderInvitation;
use App\Models\StakeholderProfile;
use App\Models\StakeholderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read surface + profile/service management for the authenticated
 * stakeholder.
 *
 *   GET   /api/v1/stakeholder/me
 *   PATCH /api/v1/stakeholder/me/profile
 *   GET   /api/v1/stakeholder/invitations
 *   GET   /api/v1/stakeholder/applications
 *   GET   /api/v1/stakeholder/engagements
 *   GET   /api/v1/stakeholder/engagements/{uuid}
 *   GET   /api/v1/stakeholder/services
 *   POST  /api/v1/stakeholder/services
 *   PATCH /api/v1/stakeholder/services/{uuid}
 *   DELETE /api/v1/stakeholder/services/{uuid}
 */
class StakeholderDashboardController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');
        $stakeholder->loadMissing('profile');

        return response()->json(['data' => [
            'uuid' => $stakeholder->uuid,
            'email' => $stakeholder->email,
            'name' => $stakeholder->name,
            'company' => $stakeholder->company,
            'phone' => $stakeholder->phone,
            'type' => $stakeholder->type?->value,
            'status' => $stakeholder->status,
            'is_verified' => $stakeholder->isVerified(),
            'profile' => $stakeholder->profile,
        ]]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');
        $validated = $request->validate([
            'bio' => ['nullable', 'string', 'max:5000'],
            'website' => ['nullable', 'url', 'max:500'],
            'social_links' => ['nullable', 'array'],
            'portfolio_items' => ['nullable', 'array'],
            'case_studies' => ['nullable', 'array'],
            'service_areas' => ['nullable', 'array'],
            'languages' => ['nullable', 'array'],
            'tags' => ['nullable', 'array'],
            'accepting_invitations' => ['nullable', 'boolean'],
            'listed_in_marketplace' => ['nullable', 'boolean'],
        ]);

        $profile = StakeholderProfile::query()
            ->firstOrCreate(['stakeholder_id' => $stakeholder->id]);
        $profile->fill(array_filter($validated, fn ($v) => $v !== null))->save();

        return response()->json(['data' => $profile->fresh()]);
    }

    public function invitations(Request $request): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');

        return response()->json([
            'data' => StakeholderInvitation::query()
                ->where('stakeholder_id', $stakeholder->id)
                ->with('event:id,name,slug,starts_at,city')
                ->orderByDesc('created_at')
                ->limit(200)
                ->get(),
        ]);
    }

    public function applications(Request $request): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');

        return response()->json([
            'data' => StakeholderApplication::query()
                ->where('stakeholder_id', $stakeholder->id)
                ->with('event:id,name,slug,starts_at,city')
                ->orderByDesc('created_at')
                ->limit(200)
                ->get(),
        ]);
    }

    public function engagements(Request $request): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');

        return response()->json([
            'data' => EventStakeholderEngagement::query()
                ->where('stakeholder_id', $stakeholder->id)
                ->with('event:id,name,slug,starts_at,city')
                ->withCount(['deliverables', 'payments'])
                ->orderByDesc('starts_at')
                ->limit(200)
                ->get(),
        ]);
    }

    public function engagement(Request $request, string $uuid): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');
        $engagement = EventStakeholderEngagement::query()
            ->where('stakeholder_id', $stakeholder->id)
            ->where('uuid', $uuid)
            ->with('event', 'deliverables', 'payments', 'documents')
            ->first();
        if (! $engagement) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json(['data' => $engagement]);
    }

    public function services(Request $request): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');

        return response()->json([
            'data' => StakeholderService::query()
                ->where('stakeholder_id', $stakeholder->id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeService(Request $request): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'base_price_cents' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'pricing_model' => ['nullable', 'in:hourly,daily,event,package,quote_only'],
            'attributes' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $service = StakeholderService::create($validated + [
            'stakeholder_id' => $stakeholder->id,
            'pricing_model' => $validated['pricing_model'] ?? 'quote_only',
        ]);

        return response()->json(['data' => $service], 201);
    }

    public function updateService(Request $request, string $uuid): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');
        $service = StakeholderService::query()
            ->where('stakeholder_id', $stakeholder->id)
            ->where('uuid', $uuid)
            ->first();
        if (! $service) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'base_price_cents' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'pricing_model' => ['nullable', 'in:hourly,daily,event,package,quote_only'],
            'attributes' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $service->fill(array_filter($validated, fn ($v) => $v !== null))->save();

        return response()->json(['data' => $service->fresh()]);
    }

    public function destroyService(Request $request, string $uuid): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');
        $service = StakeholderService::query()
            ->where('stakeholder_id', $stakeholder->id)
            ->where('uuid', $uuid)
            ->first();
        if (! $service) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $service->delete();

        return response()->json(['data' => ['ok' => true]]);
    }
}
