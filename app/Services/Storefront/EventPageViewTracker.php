<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\Event;
use App\Models\EventPageView;
use Illuminate\Http\Request;

/**
 * Writes one row to event_page_views per public detail request. Cheap
 * enough for inline use; if the volume ever justifies it, swap this
 * for a queued job (the call site already accepts a Request — passing
 * fewer args).
 */
class EventPageViewTracker
{
    public function record(Event $event, Request $request): void
    {
        EventPageView::create([
            'event_id' => $event->id,
            'session_id' => substr(hash('sha256', (string) $request->ip().'|'.(string) $request->userAgent()), 0, 32),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'referrer' => $request->headers->get('Referer'),
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
            'utm_term' => $request->query('utm_term'),
            'utm_content' => $request->query('utm_content'),
            'event_type' => 'page_view',
            'event_data' => [
                'slug' => $event->slug,
            ],
        ]);
    }
}
