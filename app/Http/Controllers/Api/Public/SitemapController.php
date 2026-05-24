<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Http\Response;

/**
 * Sitemap.xml for crawlers. Public events + organizer profiles only.
 *
 *   GET /sitemap.xml
 *
 * Front-end owns the canonical URL shape; we read the prefix from
 * `storefront.public_url`. The list is truncated to 50_000 entries
 * per the sitemap spec — when the catalog outgrows that, partition
 * into a sitemap index.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $base = rtrim((string) config('storefront.public_url'), '/');

        $visibleStatuses = array_map(
            fn (EventStatus $s) => $s->value,
            array_filter(EventStatus::cases(), fn ($s) => $s->isPubliclyVisible())
        );

        $events = Event::query()
            ->whereIn('status', $visibleStatuses)
            ->where('visibility', EventVisibility::Public->value)
            ->orderByDesc('updated_at')
            ->limit(25_000)
            ->get(['slug', 'updated_at']);

        $orgs = Organization::query()
            ->orderByDesc('updated_at')
            ->limit(25_000)
            ->get(['slug', 'updated_at']);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($events as $event) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($base.'/events/'.$event->slug, ENT_QUOTES).'</loc>'."\n";
            $xml .= '    <lastmod>'.optional($event->updated_at)->toIso8601String().'</lastmod>'."\n";
            $xml .= "  </url>\n";
        }
        foreach ($orgs as $org) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($base.'/o/'.$org->slug, ENT_QUOTES).'</loc>'."\n";
            $xml .= '    <lastmod>'.optional($org->updated_at)->toIso8601String().'</lastmod>'."\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
