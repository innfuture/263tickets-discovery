<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Discovery surface — the read-side that powers the public events
 * list, organizer profile pages, and the per-event detail endpoint.
 *
 * All queries here are PUBLIC: they filter to events whose visibility
 * is `public` AND whose status is publicly visible (Published,
 * SoldOut, Postponed). Draft / Cancelled / Ended events are never
 * returned, regardless of authentication.
 *
 * Unlisted events are accessible by direct slug only — `find()`
 * looks them up but `paginate()` excludes them from the catalog.
 */
class PublicEventQuery
{
    /**
     * Paginated event list with the usual storefront filters. All
     * filter values are optional; nullable params skip their clause.
     */
    public function paginate(
        ?string $search = null,
        ?string $city = null,
        ?string $countryCode = null,
        ?string $categorySlug = null,
        ?string $organizerSlug = null,
        ?string $startsAfter = null,
        ?string $startsBefore = null,
        ?string $sort = 'starts_at',
        int $perPage = 20,
    ): LengthAwarePaginator {
        return $this->publicQuery()
            ->when($search, fn (Builder $q, $s) => $q->where(function (Builder $w) use ($s) {
                $w->where('name', 'like', "%{$s}%")
                    ->orWhere('venue_name', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%");
            }))
            ->when($city, fn (Builder $q, $v) => $q->where('city', 'like', "%{$v}%"))
            ->when($countryCode, fn (Builder $q, $v) => $q->where('country_code', strtoupper($v)))
            ->when($categorySlug, fn (Builder $q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($organizerSlug, fn (Builder $q, $slug) => $q->whereIn(
                'organisation_id',
                Organization::query()->where('slug', $slug)->pluck('uuid')
            ))
            ->when($startsAfter, fn (Builder $q, $d) => $q->where('starts_at', '>=', $d))
            ->when($startsBefore, fn (Builder $q, $d) => $q->where('starts_at', '<=', $d))
            ->when(true, fn (Builder $q) => $this->applySort($q, $sort ?? 'starts_at'))
            ->paginate($perPage);
    }

    /**
     * Detail lookup by slug — includes unlisted events so anyone who
     * has the share-link can still pull the page.
     */
    public function findPublic(string $slug): ?Event
    {
        return Event::query()
            ->where('slug', $slug)
            ->whereIn('status', array_map(
                fn (EventStatus $s) => $s->value,
                array_filter(EventStatus::cases(), fn ($s) => $s->isPubliclyVisible())
            ))
            ->whereIn('visibility', [EventVisibility::Public->value, EventVisibility::Unlisted->value])
            ->with([
                'ticketCategories' => fn ($q) => $q->where('is_visible', true)->orderBy('sort_order'),
                'ticketCategories.currencyPrices',
                'category',
                'lineupArtists',
                'agendaEntries',
                'mediaItems',
                'amenities',
                'sponsors',
                'organisation',
            ])
            ->first();
    }

    public function featured(int $limit = 6): Collection
    {
        return $this->publicQuery()
            ->where('is_featured', true)
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();
    }

    public function byOrganizer(Organization $org, int $perPage = 20): LengthAwarePaginator
    {
        return $this->publicQuery()
            ->where('organisation_id', $org->uuid)
            ->orderBy('starts_at')
            ->paginate($perPage);
    }

    /**
     * Base query — apply the public visibility + status gate. All
     * discovery methods compose on top of this so the rules can't
     * silently drift between endpoints.
     */
    protected function publicQuery(): Builder
    {
        $visibleStatuses = array_map(
            fn (EventStatus $s) => $s->value,
            array_filter(EventStatus::cases(), fn ($s) => $s->isPubliclyVisible())
        );

        return Event::query()
            ->whereIn('status', $visibleStatuses)
            ->where('visibility', EventVisibility::Public->value);
    }

    protected function applySort(Builder $q, string $sort): Builder
    {
        return match ($sort) {
            'starts_at' => $q->orderBy('starts_at'),
            '-starts_at' => $q->orderByDesc('starts_at'),
            'name' => $q->orderBy('name'),
            'popular' => $q->orderByDesc('tickets_sold_count'),
            default => $q->orderBy('starts_at'),
        };
    }
}
