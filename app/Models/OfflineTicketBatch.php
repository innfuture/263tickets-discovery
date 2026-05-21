<?php

namespace App\Models;

use App\Enums\BatchOperation;
use App\Enums\BatchStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One adjustment to a TicketCategory's offline ticket inventory.
 *
 * Created via TicketService — never directly. The service holds a row lock
 * on the parent category while creating a batch so `batch_number` is
 * collision-free under concurrent adjustments.
 */
#[Fillable([
    'ticket_category_id',
    'batch_number',
    'operation',
    'quantity',
    'actual_quantity',
    'status',
    'progress',
    'reason',
    'actor_user_id',
    'error',
    'started_at',
    'completed_at',
])]
class OfflineTicketBatch extends Model
{
    use HasFactory;

    protected $table = 'offline_ticket_batches';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operation' => BatchOperation::class,
            'status' => BatchStatus::class,
            'quantity' => 'integer',
            'actual_quantity' => 'integer',
            'progress' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TicketCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return HasMany<OfflineTicket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(OfflineTicket::class, 'batch_id');
    }
}
