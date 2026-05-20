<?php

namespace App\Models;

use App\Enums\AdmissionType;
use App\Enums\PassType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'ticket_category_id',
    'event_id',
    'organisation_id',
    'ticket_number',
    'qr_payload',
    'serial',
    'pass_type',
    'admission_type',
    'scan_count',
    'scanned_at',
    'device_id',
    'log_count',
    'is_voided',
    'voided_at',
    'void_reason',
])]
class OfflineTicket extends Model
{
    use HasFactory, SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (OfflineTicket $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'pass_type' => PassType::class,
            'admission_type' => AdmissionType::class,
            'scan_count' => 'integer',
            'log_count' => 'integer',
            'is_voided' => 'boolean',
            'scanned_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TicketCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
