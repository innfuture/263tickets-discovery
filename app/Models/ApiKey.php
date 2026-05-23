<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Server-to-server API key scoped to an organization. The raw secret
 * is shown exactly once at creation; we persist only its SHA-256 hash
 * and a short prefix used for identification in logs and the UI.
 */
class ApiKey extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scopes' => 'array',
        'ip_allowlist' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public const PREFIX = 'sk_live_';

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Mint a fresh key. Returns ['model' => …, 'plain' => …] — the
     * plain secret is the only chance the caller has to surface it.
     *
     * @return array{model: self, plain: string}
     */
    public static function mint(array $attributes): array
    {
        $body = Str::random(40);
        $plain = self::PREFIX.$body;
        $prefix = substr($plain, 0, 12);

        /** @var self $model */
        $model = static::create([
            ...$attributes,
            'prefix' => $prefix,
            'token_hash' => hash('sha256', $plain),
        ]);

        return ['model' => $model, 'plain' => $plain];
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
