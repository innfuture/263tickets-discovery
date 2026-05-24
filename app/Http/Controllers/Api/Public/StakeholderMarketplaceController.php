<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Stakeholder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated browse for the stakeholder marketplace.
 * Returns only verified + listed stakeholders.
 *
 *   GET /api/v1/public/stakeholders                  ?type=&city=&search=
 *   GET /api/v1/public/stakeholders/{uuid}
 */
class StakeholderMarketplaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = Stakeholder::query()
            ->where('status', Stakeholder::STATUS_VERIFIED)
            ->whereHas('profile', fn ($q) => $q->where('listed_in_marketplace', true))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('search'), fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")
                    ->orWhere('company', 'like', "%{$s}%");
            }))
            ->with('profile:id,stakeholder_id,bio,logo_path,website,tags,service_areas')
            ->orderBy('name')
            ->limit(min(200, (int) $request->integer('limit', 50)))
            ->get(['id', 'uuid', 'type', 'name', 'company', 'country_code']);

        return response()->json([
            'data' => $rows->map(fn (Stakeholder $s) => $this->summary($s))->all(),
        ]);
    }

    public function show(string $uuid): JsonResponse
    {
        $stakeholder = Stakeholder::query()
            ->where('uuid', $uuid)
            ->where('status', Stakeholder::STATUS_VERIFIED)
            ->whereHas('profile', fn ($q) => $q->where('listed_in_marketplace', true))
            ->with('profile', 'services')
            ->first();
        if (! $stakeholder) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json([
            'data' => array_merge($this->summary($stakeholder), [
                'profile' => $stakeholder->profile,
                'services' => $stakeholder->services->where('is_active', true)->values(),
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    protected function summary(Stakeholder $s): array
    {
        return [
            'uuid' => $s->uuid,
            'type' => $s->type?->value,
            'type_label' => $s->type?->label(),
            'name' => $s->name,
            'company' => $s->company,
            'country_code' => $s->country_code,
            'logo_path' => optional($s->profile)->logo_path,
            'tags' => optional($s->profile)->tags,
        ];
    }
}
