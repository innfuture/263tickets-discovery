<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Automation;

use App\Http\Controllers\Controller;
use App\Jobs\Automation\BroadcastEventMessageJob;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Broadcast a message to every attendee of an event. Scope:
 * `messages.write`. The actual delivery is queued so the API call
 * returns quickly even for events with thousands of attendees.
 *
 *   POST /api/v1/automations/events/{slug}/broadcast
 *      body: { subject, body, channel: 'email'|'sms', dry_run? }
 */
class AutomationMessageController extends Controller
{
    public function broadcast(Request $request, string $slug): JsonResponse
    {
        $org = $request->attributes->get('automation_org');

        $event = Event::query()
            ->where('organisation_id', $org->uuid)
            ->where('slug', $slug)
            ->first();
        if (! $event) {
            return response()->json(['error' => 'event_not_found'], 404);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:10000'],
            'channel' => ['required', 'in:email,sms'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $recipientCount = $event->load('orders')->orders
            ?->where('status', 'paid')
            ->pluck('buyer_email')
            ->filter()
            ->unique()
            ->count() ?? 0;

        if (! ($validated['dry_run'] ?? false)) {
            BroadcastEventMessageJob::dispatch(
                eventId: $event->id,
                subject: $validated['subject'],
                body: $validated['body'],
                channel: $validated['channel'],
            )->onQueue((string) config('automation.queue', 'automations'));
        }

        return response()->json([
            'data' => [
                'event_slug' => $event->slug,
                'recipient_count' => $recipientCount,
                'dispatched' => ! ($validated['dry_run'] ?? false),
            ],
        ], 202);
    }
}
