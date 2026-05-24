<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Marketplace listing for a third-party extension. Each `Extension`
 * has one or more `ExtensionVersion` rows (semver); the currently-
 * published version is what new installs pick up.
 */
class Extension extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'uuid', 'extension_developer_id', 'slug', 'name', 'description',
        'icon_url', 'status', 'category', 'tags', 'homepage_url',
        'install_count', 'avg_rating', 'first_approved_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'install_count' => 'integer',
        'avg_rating' => 'decimal:2',
        'first_approved_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function developer(): BelongsTo
    {
        return $this->belongsTo(ExtensionDeveloper::class, 'extension_developer_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ExtensionVersion::class);
    }

    public function publishedVersion(): ?ExtensionVersion
    {
        return $this->versions()
            ->where('status', ExtensionVersion::STATUS_PUBLISHED)
            ->orderByDesc('published_at')
            ->first();
    }
}
