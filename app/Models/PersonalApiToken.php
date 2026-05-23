<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * User-scoped personal API token. Inherits the user's effective
 * permissions; revocable at any time.
 */
class PersonalApiToken extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public const PREFIX = 'pat_';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
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
