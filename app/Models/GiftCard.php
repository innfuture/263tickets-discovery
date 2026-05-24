<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Pre-funded store credit, redeemed against checkout totals. Codes
 * are alphabetic + numeric, no I/O/0/1 ambiguity. Balance is mutated
 * by GiftCardManager — never write to `balance_cents` directly from
 * outside the manager.
 */
class GiftCard extends Model
{
    use HasFactory, SoftDeletes;

    public const SOURCE_PURCHASE = 'purchase';

    public const SOURCE_COMP = 'comp';

    public const SOURCE_PROMO = 'promo';

    public const SOURCE_REFUND_CREDIT = 'refund_credit';

    protected $fillable = [
        'uuid', 'organisation_id', 'code', 'initial_balance_cents',
        'balance_cents', 'currency', 'purchaser_email', 'recipient_email',
        'recipient_name', 'message', 'source', 'valid_from', 'expires_at',
        'redeemed_at',
    ];

    protected $casts = [
        'initial_balance_cents' => 'integer',
        'balance_cents' => 'integer',
        'valid_from' => 'datetime',
        'expires_at' => 'datetime',
        'redeemed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
            if (empty($row->code)) {
                $row->code = static::generateCode();
            }
            if (! isset($row->balance_cents) && isset($row->initial_balance_cents)) {
                $row->balance_cents = $row->initial_balance_cents;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(GiftCardRedemption::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organisation_id', 'uuid');
    }

    public function isActive(): bool
    {
        if ($this->balance_cents <= 0) {
            return false;
        }
        if ($this->valid_from instanceof Carbon && $this->valid_from->isFuture()) {
            return false;
        }
        if ($this->expires_at instanceof Carbon && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Generates a 16-character code like `GC-AB23-CD4F-XYZP`. Caller
     * must check for collisions and regenerate on rare conflict.
     */
    public static function generateCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // omit I/O/0/1
        $segment = fn () => collect(range(1, 4))
            ->map(fn () => $chars[random_int(0, 31)])
            ->implode('');

        return 'GC-'.$segment().'-'.$segment().'-'.$segment();
    }
}
