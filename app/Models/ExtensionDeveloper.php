<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Developer account for the extension marketplace. Identity is the
 * email; trust is the pinned public key. Every extension manifest
 * version is signed with the developer's private key and verified
 * against `public_key` at submission time.
 */
class ExtensionDeveloper extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'email', 'name', 'company', 'website',
        'public_key', 'public_key_fingerprint',
        'is_verified', 'verified_at', 'metadata',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
            $row->email = strtolower((string) $row->email);
        });
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(Extension::class);
    }
}
