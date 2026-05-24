<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventTemplateController extends Controller
{
    public function index(Organization $currentOrganization): JsonResponse
    {
        return response()->json([
            'data' => EventTemplate::query()
                ->where('organisation_id', $currentOrganization->uuid)
                ->with('sourceEvent:id,name,slug,starts_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function store(Organization $currentOrganization, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'source_event_id' => ['required', 'integer'],
            'cadence' => ['required', 'in:daily,weekly,monthly,nth_weekday_of_month'],
            'cadence_meta' => ['nullable', 'array'],
            'repeat_until' => ['nullable', 'date'],
            'max_instances' => ['nullable', 'integer', 'min:1'],
            'next_run_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $source = Event::query()
            ->where('id', $validated['source_event_id'])
            ->where('organisation_id', $currentOrganization->uuid)
            ->first();
        abort_if(! $source, 422, 'Source event not in this organization.');

        $template = EventTemplate::create([
            'organisation_id' => $currentOrganization->uuid,
            'name' => $validated['name'],
            'source_event_id' => $validated['source_event_id'],
            'cadence' => $validated['cadence'],
            'cadence_meta' => $validated['cadence_meta'] ?? null,
            'repeat_until' => $validated['repeat_until'] ?? null,
            'max_instances' => $validated['max_instances'] ?? null,
            'next_run_at' => $validated['next_run_at'] ?? now(),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return response()->json(['data' => $template], 201);
    }

    public function update(Organization $currentOrganization, EventTemplate $template, Request $request): JsonResponse
    {
        abort_if($template->organisation_id !== $currentOrganization->uuid, 404);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:191'],
            'cadence' => ['nullable', 'in:daily,weekly,monthly,nth_weekday_of_month'],
            'cadence_meta' => ['nullable', 'array'],
            'repeat_until' => ['nullable', 'date'],
            'max_instances' => ['nullable', 'integer', 'min:0'],
            'next_run_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template->fill(array_filter($validated, fn ($v) => $v !== null))->save();

        return response()->json(['data' => $template->fresh()]);
    }

    public function destroy(Organization $currentOrganization, EventTemplate $template): JsonResponse
    {
        abort_if($template->organisation_id !== $currentOrganization->uuid, 404);
        $template->delete();

        return response()->json(['data' => ['ok' => true]]);
    }
}
