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
 * Sitemap shard endpoints — `sitemap-events-{shard}.xml`,
 * `sitemap-organizers-{shard}.xml`, plus an index `sitemap-index.xml`
 * that lists every shard. Used when the single-file `sitemap.xml`
 * pushes against the 50k entry cap.
 *
 * Shard size is fixed at 5,000 per file — well below the sitemap
 * spec's 50k cap, leaves headroom for adding tier-level URLs later.
 */
class SitemapIndexController extends Controller
{
    public const SHARD_SIZE = 5_000;

    public function index(): Response
    {
        $base = rtrim((string) config('storefront.public_url'), '/');
        $publicStatuses = array_map(
            fn (EventStatus $s) => $s->value,
            array_filter(EventStatus::cases(), fn ($s) => $s->isPubliclyVisible())
        );

        $eventCount = Event::query()
            ->whereIn('status', $publicStatuses)
            ->where('visibility', EventVisibility::Public->value)
            ->count();
        $orgCount = Organization::query()->count();

        $eventShards = (int) max(1, ceil($eventCount / self::SHARD_SIZE));
        $orgShards = (int) max(1, ceil($orgCount / self::SHARD_SIZE));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        for ($i = 0; $i < $eventShards; $i++) {
            $xml .= "  <sitemap><loc>{$base}/sitemap-events-{$i}.xml</loc></sitemap>\n";
        }
        for ($i = 0; $i < $orgShards; $i++) {
            $xml .= "  <sitemap><loc>{$base}/sitemap-organizers-{$i}.xml</loc></sitemap>\n";
        }
        $xml .= '</sitemapindex>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function events(int $shard): Response
    {
        $base = rtrim((string) config('storefront.public_url'), '/');
        $publicStatuses = array_map(
            fn (EventStatus $s) => $s->value,
            array_filter(EventStatus::cases(), fn ($s) => $s->isPubliclyVisible())
        );

        $events = Event::query()
            ->whereIn('status', $publicStatuses)
            ->where('visibility', EventVisibility::Public->value)
            ->orderBy('id')
            ->skip($shard * self::SHARD_SIZE)
            ->take(self::SHARD_SIZE)
            ->get(['slug', 'updated_at']);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($events as $event) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($base.'/events/'.$event->slug, ENT_QUOTES).'</loc>'."\n";
            $xml .= '    <lastmod>'.optional($event->updated_at)->toIso8601String().'</lastmod>'."\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function organizers(int $shard): Response
    {
        $base = rtrim((string) config('storefront.public_url'), '/');

        $orgs = Organization::query()
            ->orderBy('id')
            ->skip($shard * self::SHARD_SIZE)
            ->take(self::SHARD_SIZE)
            ->get(['slug', 'updated_at']);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
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
