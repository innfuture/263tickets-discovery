<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SandboxStateLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'sandbox_payment_state_log';

    protected $fillable = [
        'sandbox_transaction_id',
        'from_state',
        'to_state',
        'reason',
        'actor',
        'context',
        'occurred_at',
        'virtual_time_at',
    ];

    protected $casts = [
        'context' => 'array',
        'occurred_at' => 'datetime',
        'virtual_time_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SandboxTransaction::class, 'sandbox_transaction_id');
    }
}
