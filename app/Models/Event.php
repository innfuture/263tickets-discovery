<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Exceptions\InvalidEventStatusTransition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'organisation_id',
    'created_by_user_id',
    'category_id',
    'name',
    'description',
    'short_description',
    'status',
    'visibility',
    'is_featured',
    'published_at',
    'starts_at',
    'ends_at',
    'doors_open_at',
    'timezone',
    'sales_start_at',
    'sales_end_at',
    'venue_name',
    'address_line_1',
    'address_line_2',
    'city',
    'region',
    'country_code',
    'postal_code',
    'latitude',
    'longitude',
    'is_online',
    'online_url',
    'banner_image_path',
    'og_image_path',
    'og_title',
    'og_description',
    'twitter_card',
    'twitter_creator',
    'canonical_url',
    'meta_title',
    'meta_description',
    'tags',
    'capacity',
    'minimum_age',
    'parking_info',
    'age_requirement_details',
    'refund_policy',
    'terms',
    'contact_email',
    'contact_phone',
    'seo_keywords',
    'robots_directive',
    'og_type',
    'og_locale',
    'og_site_name',
    'twitter_title',
    'twitter_description',
    'twitter_image',
    'schema_markup',
])]
class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Event $event) {
            if (empty($event->event_id)) {
                $event->event_id = (string) Str::uuid();
            }

            if (empty($event->slug) && ! empty($event->name)) {
                $event->slug = static::generateUniqueSlug($event->name);
            }
        });

        static::updating(function (Event $event) {
            if ($event->isDirty('name') && ! $event->isDirty('slug')) {
                $event->slug = static::generateUniqueSlug($event->name, $event->id);
            }
        });

        static::saving(function (Event $event) {
            if ($event->isDirty('status')
                && $event->status === EventStatus::Published
                && empty($event->published_at)
            ) {
                $event->published_at = now();
            }
        });
    }

    protected static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);

        $exists = static::query()
            ->where('slug', $base)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if (! $exists) {
            return $base;
        }

        return $base.'-'.Str::lower(Str::random(6));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'visibility' => EventVisibility::class,
            'is_featured' => 'boolean',
            'is_online' => 'boolean',
            'published_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sales_start_at' => 'datetime',
            'sales_end_at' => 'datetime',
            'doors_open_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'tags' => 'array',
            'capacity' => 'integer',
            'minimum_age' => 'integer',
            'tickets_sold_count' => 'integer',
            'views_count' => 'integer',
            'schema_markup' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'organisation_id', 'uuid');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<EventCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    /**
     * @return HasMany<EventLineupArtist, $this>
     */
    public function lineupArtists(): HasMany
    {
        return $this->hasMany(EventLineupArtist::class)
            ->orderByDesc('is_headliner')
            ->orderBy('sort_order');
    }

    /**
     * @return HasMany<EventAgendaEntry, $this>
     */
    public function agendaEntries(): HasMany
    {
        return $this->hasMany(EventAgendaEntry::class)
            ->orderBy('starts_at')
            ->orderBy('sort_order');
    }

    /**
     * @return HasMany<EventMediaItem, $this>
     */
    public function mediaItems(): HasMany
    {
        return $this->hasMany(EventMediaItem::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order');
    }

    /**
     * @return HasMany<TicketCategory, $this>
     */
    public function ticketCategories(): HasMany
    {
        return $this->hasMany(TicketCategory::class)
            ->orderBy('sort_order');
    }

    /**
     * Sponsors backing the event, ordered by tier prominence then sort_order.
     * Tier rank is applied in PHP after fetch (see EventController payload).
     *
     * @return HasMany<EventSponsor, $this>
     */
    public function sponsors(): HasMany
    {
        return $this->hasMany(EventSponsor::class)
            ->orderBy('sort_order');
    }

    /**
     * @return HasMany<EventAmenity, $this>
     */
    public function amenities(): HasMany
    {
        return $this->hasMany(EventAmenity::class)
            ->orderByDesc('is_highlighted')
            ->orderBy('sort_order');
    }

    /**
     * Event-scoped ad campaigns. Uses the dedicated `event_id` FK (kept for
     * legacy queries). New code on other resources should use the polymorphic
     * `morphMany(AdCampaign::class, 'owner')` instead.
     *
     * @return HasMany<AdCampaign, $this>
     */
    public function adCampaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class);
    }

    /**
     * Polymorphic accessor for ad campaigns. Equivalent to `adCampaigns()` for
     * events but available to any model via the polymorphic owner columns.
     *
     * @return MorphMany<AdCampaign, $this>
     */
    public function ownedAdCampaigns(): MorphMany
    {
        return $this->morphMany(AdCampaign::class, 'owner');
    }

    /**
     * @return HasMany<EventPageView, $this>
     */
    public function pageViews(): HasMany
    {
        return $this->hasMany(EventPageView::class);
    }

    public function transitionTo(EventStatus $to): self
    {
        if ($this->status === $to) {
            return $this;
        }

        if (! $this->status->canTransitionTo($to)) {
            throw new InvalidEventStatusTransition($this->status, $to);
        }

        $this->status = $to;
        $this->save();

        return $this;
    }

    public function incrementTicketsSold(int $count = 1): void
    {
        $this->increment('tickets_sold_count', $count);
    }

    public function decrementTicketsSold(int $count = 1): void
    {
        $this->decrement('tickets_sold_count', $count);
    }

    /**
     * Overwrite the cached counter with an authoritative count.
     * Intended for a periodic reconciliation job once the tickets table exists.
     */
    public function reconcileTicketsSold(int $authoritativeCount): void
    {
        $this->forceFill(['tickets_sold_count' => max(0, $authoritativeCount)])->save();
    }

    public function isSoldOut(): bool
    {
        return $this->capacity !== null && $this->tickets_sold_count >= $this->capacity;
    }

    public function ticketsAvailable(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->tickets_sold_count);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
