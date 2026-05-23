<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SandboxDispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'sandbox_transaction_id',
        'amount_minor',
        'currency',
        'status',
        'reason_code',
        'evidence',
        'evidence_due_at',
        'resolved_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'evidence' => 'array',
        'evidence_due_at' => 'datetime',
        'resolved_at' => 'datetime',
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
}
