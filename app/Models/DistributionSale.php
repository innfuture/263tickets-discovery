<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Point-of-sale record from a distributor selling a physical ticket
 * to a final customer. Mirrors the custody ledger's `sold` entry but
 * carries the operational fields (customer, money) the ledger does
 * not need to know about. One sale per ticket — enforced by a unique
 * constraint on ticket_uuid.
 */
class DistributionSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'distributor_id', 'distributor_device_id',
        'offline_ticket_id', 'ticket_uuid', 'event_id',
        'customer_phone', 'customer_name', 'customer_email',
        'amount_cents', 'currency',
        'gps_lat', 'gps_lng', 'outside_geofence',
        'flagged_anomaly', 'anomaly_flags', 'sold_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'gps_lat' => 'float',
        'gps_lng' => 'float',
        'outside_geofence' => 'boolean',
        'flagged_anomaly' => 'boolean',
        'anomaly_flags' => 'array',
        'sold_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $row): void {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Distributor, $this> */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /** @return BelongsTo<DistributorDevice, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(DistributorDevice::class, 'distributor_device_id');
    }

    /** @return BelongsTo<OfflineTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(OfflineTicket::class, 'offline_ticket_id');
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
