<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Scanning;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\ScannerDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/scanning/me
 *
 * The mobile app polls this on launch + after any config change to
 * learn its capabilities and the events it's allowed to scan. The app
 * MUST trust this response over locally-cached values — it's how
 * organizers tighten/loosen access on the fly.
 */
class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var ScannerDevice $device */
        $device = $request->attributes->get('scanner_device');
        $profile = $device->profile;

        $allowed = (array) $profile->allowed_event_ids;
        $events = Event::query()
            ->when($allowed !== [], fn ($q) => $q->whereIn('id', $allowed))
            ->where('organisation_id', $profile->organisation_id)
            ->orderBy('starts_at')
            ->limit(200)
            ->get(['id', 'slug', 'name', 'starts_at', 'ends_at', 'venue_name', 'city'])
            ->map(fn (Event $e) => [
                'id' => (int) $e->id,
                'slug' => $e->slug,
                'name' => $e->name,
                'starts_at' => $e->starts_at?->toIso8601String(),
                'ends_at' => $e->ends_at?->toIso8601String(),
                'venue' => trim(($e->venue_name ?? '').($e->city ? ', '.$e->city : '')) ?: null,
            ]);

        return response()->json([
            'device' => [
                'uuid' => $device->uuid,
                'label' => $device->device_label,
                'platform' => $device->platform,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            ],
            'profile' => [
                'uuid' => $profile->uuid,
                'name' => $profile->name,
                'capabilities' => (array) $profile->capabilities,
                'max_scans_per_minute' => $profile->max_scans_per_minute,
                'duplicate_window_seconds' => $profile->duplicate_window_seconds,
                'allowed_event_ids' => (array) $profile->allowed_event_ids,
            ],
            'events' => $events,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
