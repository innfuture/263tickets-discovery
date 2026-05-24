<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Per-organization installation of an Extension at a specific Version.
 * `api_key_hash` is the sha256 of the bearer token the extension uses
 * to call back into our `/api/v1/extensions/*` runtime — the plaintext
 * is only shown once at install time.
 *
 * `granted_permissions` is whatever the org admin actually consented to
 * at install (which is usually = manifest.declared_permissions, but
 * may be a subset).
 */
class ExtensionInstallation extends Model
{
    use HasFactory;

    public const KEY_PREFIX = 'ext_';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    public const STATUS_UNINSTALLED = 'uninstalled';

    protected $fillable = [
        'uuid', 'extension_id', 'extension_version_id', 'organization_id',
        'installed_by_user_id', 'api_key_hash', 'api_key_prefix',
        'granted_permissions', 'config', 'status',
        'installed_at', 'disabled_at', 'uninstalled_at',
    ];

    protected $casts = [
        'granted_permissions' => 'array',
        'config' => 'array',
        'installed_at' => 'datetime',
        'disabled_at' => 'datetime',
        'uninstalled_at' => 'datetime',
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

    public function extension(): BelongsTo
    {
        return $this->belongsTo(Extension::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ExtensionVersion::class, 'extension_version_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function hasPermission(string $key): bool
    {
        $granted = (array) ($this->granted_permissions ?? []);

        return in_array($key, $granted, true) || in_array('*', $granted, true);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
