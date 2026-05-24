<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ScannerDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'scanner_profile_id',
        'token_hash',
        'token_prefix',
        'device_label',
        'platform',
        'app_version',
        'hardware_id',
        'last_seen_at',
        'last_known_ip',
        'last_known_lat',
        'last_known_lng',
        'status',
        'revoked_at',
        'revoked_reason',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_known_lat' => 'decimal:7',
        'last_known_lng' => 'decimal:7',
    ];

    protected $hidden = ['token_hash'];

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
        return 'uuid';
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ScannerProfile::class, 'scanner_profile_id');
    }

    public function scanEvents(): HasMany
    {
        return $this->hasMany(ScanEvent::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->profile?->status === 'active';
    }
}
