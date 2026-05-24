<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Collaboration;

use App\Events\EventDraftUpdated;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventDraft;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Server-side persistence for the real-time collaborative event
 * editor. Client uses Yjs (https://yjs.dev) — frames the doc state
 * as an opaque binary blob; we base64-encode for JSON transport.
 *
 *   GET   /api/back-office/events/{event}/draft           latest snapshot
 *   POST  /api/back-office/events/{event}/draft/apply     persist new state + broadcast
 *   POST  /api/back-office/events/{event}/draft/publish   write draft into event row
 *
 * Concurrent edits are merged client-side via Yjs CRDT semantics;
 * we just persist whichever state the client sends. Presence is
 * handled via the `event.{slug}.draft` broadcast channel.
 */
class EventDraftController extends Controller
{
    public function show(Organization $currentOrganization, Event $event): JsonResponse
    {
        $this->assertOwned($event, $currentOrganization);

        $draft = EventDraft::query()->where('event_id', $event->id)->first();

        return response()->json([
            'data' => [
                'event_slug' => $event->slug,
                'version' => $draft?->version ?? 0,
                'ydoc_base64' => $draft?->ydoc_base64,
                'last_editor_user_id' => $draft?->last_editor_user_id,
                'updated_at' => optional($draft?->updated_at)->toIso8601String(),
            ],
        ]);
    }

    public function apply(Organization $currentOrganization, Event $event, Request $request): JsonResponse
    {
        $this->assertOwned($event, $currentOrganization);

        $validated = $request->validate([
            'ydoc_base64' => ['required', 'string', 'max:5242880'], // 5MB cap
            'base_version' => ['nullable', 'integer'],
        ]);

        $draft = EventDraft::query()->firstOrNew(['event_id' => $event->id]);

        // Optimistic conflict-detection — Yjs is conflict-free on the
        // client side, so this is informational only (lets the FE
        // surface "5 changes have happened since you joined").
        $conflict = isset($validated['base_version'])
            && $draft->exists
            && (int) $validated['base_version'] !== (int) $draft->version;

        $draft->fill([
            'ydoc_base64' => $validated['ydoc_base64'],
            'version' => ($draft->version ?? 0) + 1,
            'last_editor_user_id' => $request->user()?->id,
        ])->save();

        EventDraftUpdated::dispatch($event, $draft);

        return response()->json([
            'data' => [
                'version' => $draft->version,
                'updated_at' => $draft->updated_at?->toIso8601String(),
                'conflict_detected' => $conflict,
            ],
        ]);
    }

    public function publish(Organization $currentOrganization, Event $event, Request $request): JsonResponse
    {
        $this->assertOwned($event, $currentOrganization);

        $validated = $request->validate([
            'changes' => ['required', 'array'],
        ]);

        // Frontend resolves the Yjs doc → typed `changes` object →
        // posts here. We just apply to the Event row + the editable
        // child collections (lineup, agenda, etc. — TBD per change
        // shape). Today: shallow update of whitelisted scalars.
        $whitelist = array_intersect_key($validated['changes'], array_flip([
            'name', 'short_description', 'description', 'venue_name',
            'address_line_1', 'city', 'country_code', 'starts_at', 'ends_at',
        ]));

        if (! empty($whitelist)) {
            $event->fill($whitelist)->save();
        }

        // Reset draft version — next collaborator starts fresh from
        // the published state.
        EventDraft::query()->where('event_id', $event->id)->update([
            'version' => 0,
            'last_editor_user_id' => $request->user()?->id,
        ]);

        return response()->json(['data' => ['published_at' => now()->toIso8601String()]]);
    }

    protected function assertOwned(Event $event, Organization $org): void
    {
        abort_if($event->organisation_id !== $org->uuid, 404);
    }
}
