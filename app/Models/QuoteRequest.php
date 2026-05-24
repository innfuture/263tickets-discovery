<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Group / corporate sales lead. Created from the public surface;
 * organizer staff responds out-of-band, and (optionally) converts
 * the lead into a checkout session pre-filled with the quoted price.
 */
class QuoteRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_QUOTED = 'quoted';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'uuid', 'organisation_id', 'event_id', 'ticket_category_id',
        'company_name', 'contact_name', 'contact_email', 'contact_phone',
        'quantity_requested', 'notes', 'status', 'quoted_unit_price_cents',
        'quoted_currency', 'converted_order_id', 'responded_at',
        'expires_at', 'ip_address',
    ];

    protected $casts = [
        'quantity_requested' => 'integer',
        'quoted_unit_price_cents' => 'integer',
        'responded_at' => 'datetime',
        'expires_at' => 'datetime',
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
        return 'uuid';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }
}
