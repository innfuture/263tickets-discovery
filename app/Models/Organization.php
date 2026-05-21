<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueOrganizationSlugs;
use App\Enums\OrganizerType;
use App\Enums\TeamRole;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Top of the tenancy hierarchy: Organization → Teams → Members. The
 * organization owns the brand profile (logo, banner, social, address,
 * tax/locale) and is the parent of every Event, AdCampaign,
 * TicketCategory, and sub-Team in its scope.
 *
 * The `uuid` column is the canonical cross-table identifier — every
 * `organisation_id` FK in the schema (events, ad_campaigns,
 * ticket_categories, offline_tickets, …) stores this UUID. It was
 * carried over from the legacy `teams.uuid` during the split migration
 * so that no downstream row had to be rewritten.
 */
#[Fillable([
    'name',
    'brand_name',
    'slug',
    'is_personal',
    'tagline',
    'description',
    'organizer_type',
    'logo_path',
    'banner_path',
    'contact_email',
    'support_email',
    'contact_phone',
    'website_url',
    'twitter_url',
    'instagram_url',
    'facebook_url',
    'linkedin_url',
    'tiktok_url',
    'youtube_url',
    'address_line_1',
    'address_line_2',
    'city',
    'region',
    'country_code',
    'postal_code',
    'latitude',
    'longitude',
    'tax_id',
    'default_currency',
    'default_timezone',
    'founded_year',
    'is_verified',
    'verified_at',
])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use GeneratesUniqueOrganizationSlugs, HasFactory, SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Organization $org) {
            if (empty($org->slug)) {
                $org->slug = static::generateUniqueOrganizationSlug($org->name);
            }

            if (empty($org->uuid)) {
                $org->uuid = (string) Str::uuid();
            }
        });

        static::updating(function (Organization $org) {
            if ($org->isDirty('name')) {
                $org->slug = static::generateUniqueOrganizationSlug($org->name, $org->id);
            }
        });
    }

    /**
     * Org-level membership. Every user who can see this organization is
     * here; sub-team assignments are an internal subset via the `teams`
     * table.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members', 'organization_id', 'user_id')
            ->using(OrganizationMembership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<OrganizationMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /**
     * The owner is whichever member holds the Owner role.
     */
    public function owner(): ?User
    {
        return $this->members()
            ->wherePivot('role', TeamRole::Owner->value)
            ->first();
    }

    /**
     * Sub-teams under this organization. Each team is a scoped grouping
     * of members; not every org member needs to be on a team.
     *
     * @return HasMany<Team, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * @return HasMany<OrganizationInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'organisation_id', 'uuid');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
            'is_verified' => 'boolean',
            'organizer_type' => OrganizerType::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'verified_at' => 'datetime',
        ];
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path
            ? (str_starts_with($this->logo_path, 'http')
                ? $this->logo_path
                : Storage::url($this->logo_path))
            : null;
    }

    public function bannerUrl(): ?string
    {
        return $this->banner_path
            ? (str_starts_with($this->banner_path, 'http')
                ? $this->banner_path
                : Storage::url($this->banner_path))
            : null;
    }

    /**
     * Populated social URLs, keyed by platform — drives the public
     * profile's icon row (only icons with a backing URL render).
     *
     * @return array<string, string>
     */
    public function socialLinks(): array
    {
        return array_filter([
            'twitter' => $this->twitter_url,
            'instagram' => $this->instagram_url,
            'facebook' => $this->facebook_url,
            'linkedin' => $this->linkedin_url,
            'tiktok' => $this->tiktok_url,
            'youtube' => $this->youtube_url,
            'website' => $this->website_url,
        ], fn ($v) => ! empty($v));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
