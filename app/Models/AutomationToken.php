<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Bearer token used by n8n / Zapier / Make / curl-from-cron to call
 * `/api/v1/automations/*`. Scopes constrain which endpoints the token
 * can hit; secret is stored as a sha256 hash, the plaintext is only
 * shown once at creation.
 *
 * `display_prefix` is the first 4 chars of the plaintext, used in
 * the dashboard to identify a token without revealing it (mirrors
 * the scanner-token UX `scn_…`).
 */
class AutomationToken extends Model
{
    use HasFactory;

    public const TOKEN_PREFIX = 'aut_';

    public const SCOPE_READ = 'read';

    public const SCOPE_ORDERS_WRITE = 'orders.write';

    public const SCOPE_REFUNDS_WRITE = 'refunds.write';

    public const SCOPE_MESSAGES_WRITE = 'messages.write';

    public const SCOPE_EVENTS_WRITE = 'events.write';

    public const SCOPE_GIFT_CARDS_WRITE = 'gift_cards.write';

    public const SCOPE_ADDONS_WRITE = 'addons.write';

    public const ALL_SCOPES = [
        self::SCOPE_READ,
        self::SCOPE_ORDERS_WRITE,
        self::SCOPE_REFUNDS_WRITE,
        self::SCOPE_MESSAGES_WRITE,
        self::SCOPE_EVENTS_WRITE,
        self::SCOPE_GIFT_CARDS_WRITE,
        self::SCOPE_ADDONS_WRITE,
    ];

    protected $fillable = [
        'uuid', 'organization_id', 'created_by_user_id', 'label',
        'display_prefix', 'secret_hash', 'scopes',
        'last_used_at', 'last_used_ip', 'expires_at', 'revoked_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
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

    /**
     * Mint a fresh token; returns ['model' => …, 'plaintext' => …].
     * The plaintext is the only place the secret ever lives in memory —
     * caller MUST surface it to the user once and discard.
     *
     * @return array{model: self, plaintext: string}
     */
    public static function issue(Organization $org, string $label, array $scopes, ?User $creator = null, ?\DateTimeInterface $expiresAt = null): array
    {
        $plaintext = self::TOKEN_PREFIX.Str::random(48);

        $model = self::create([
            'organization_id' => $org->id,
            'created_by_user_id' => $creator?->id,
            'label' => $label,
            'display_prefix' => substr($plaintext, 0, 8),
            'secret_hash' => hash('sha256', $plaintext),
            'scopes' => array_values(array_unique($scopes)),
            'expires_at' => $expiresAt,
        ]);

        return ['model' => $model, 'plaintext' => $plaintext];
    }

    public function hasScope(string $scope): bool
    {
        $scopes = (array) ($this->scopes ?? []);

        return in_array($scope, $scopes, true) || in_array('*', $scopes, true);
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
