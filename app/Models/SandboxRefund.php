<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SandboxRefund extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'sandbox_transaction_id',
        'amount_minor',
        'currency',
        'state',
        'reason_code',
        'reason',
        'initiated_by',
        'response_body',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'response_body' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::ulid();
            }
        });
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SandboxTransaction::class, 'sandbox_transaction_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
