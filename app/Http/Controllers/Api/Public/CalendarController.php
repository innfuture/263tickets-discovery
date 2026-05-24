<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves an .ics file for an order so the buyer can add the event
 * to their calendar app. Reachable only via signed URL minted by
 * the receipt page / confirmation email.
 *
 *   GET /api/v1/public/orders/{reference}/calendar.ics?signature=…
 *
 * RFC 5545 compliant minimal VCALENDAR — Apple Calendar, Google
 * Calendar, Outlook all parse it.
 */
class CalendarController extends Controller
{
    public function show(Request $request, string $reference): Response
    {
        if (! $request->hasValidSignature()) {
            return response('signature_invalid', 403);
        }

        $order = Order::query()
            ->with('event', 'organization')
            ->where('reference', $reference)
            ->first();

        if (! $order || ! $order->event) {
            return response('not_found', 404);
        }

        $event = $order->event;
        $uid = $event->event_id.'@'.parse_url((string) config('app.url'), PHP_URL_HOST);
        $dtStart = optional($event->starts_at)->utc()->format('Ymd\THis\Z') ?? '';
        $dtEnd = optional($event->ends_at)->utc()->format('Ymd\THis\Z') ?? $dtStart;
        $now = Carbon::now()->utc()->format('Ymd\THis\Z');

        $organizer = optional($order->organization)->brand_name
            ?? optional($order->organization)->name
            ?? 'Event';

        $location = trim(implode(', ', array_filter([
            $event->venue_name, $event->address_line_1, $event->city, $event->country_code,
        ])));

        $description = $this->escape((string) ($event->short_description
            ?? substr((string) $event->description, 0, 500)));

        $body = "BEGIN:VCALENDAR\r\n"
            ."VERSION:2.0\r\n"
            ."PRODID:-//example-app//storefront//EN\r\n"
            ."CALSCALE:GREGORIAN\r\n"
            ."METHOD:PUBLISH\r\n"
            ."BEGIN:VEVENT\r\n"
            ."UID:{$uid}\r\n"
            ."DTSTAMP:{$now}\r\n"
            ."DTSTART:{$dtStart}\r\n"
            ."DTEND:{$dtEnd}\r\n"
            .'SUMMARY:'.$this->escape((string) $event->name)."\r\n"
            ."DESCRIPTION:{$description}\r\n"
            .'LOCATION:'.$this->escape($location)."\r\n"
            .'ORGANIZER;CN='.$this->escape($organizer).':MAILTO:'.($order->organization->contact_email ?? 'no-reply@example.com')."\r\n"
            ."STATUS:CONFIRMED\r\n"
            ."END:VEVENT\r\n"
            ."END:VCALENDAR\r\n";

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"event-{$event->slug}.ics\"",
        ]);
    }

    /**
     * RFC 5545 §3.3.11 — escape commas, semicolons, backslashes, and
     * newlines in text values.
     */
    protected function escape(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", ',', ';'],
            ['\\\\', '\\n', '\\n', '\\,', '\\;'],
            $value,
        );
    }
}
