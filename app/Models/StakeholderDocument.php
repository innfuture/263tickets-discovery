<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StakeholderDocument extends Model
{
    use HasFactory;

    public const KIND_CONTRACT = 'contract';

    public const KIND_INSURANCE = 'insurance';

    public const KIND_TAX_FORM = 'tax_form';

    public const KIND_PERMIT = 'permit';

    public const KIND_NDA = 'nda';

    public const KIND_OTHER = 'other';

    protected $fillable = [
        'uuid', 'stakeholder_id', 'event_stakeholder_engagement_id',
        'kind', 'name', 'storage_path', 'size_bytes', 'mime_type',
        'expires_at', 'signed_at', 'uploaded_by_user_id',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'expires_at' => 'datetime',
        'signed_at' => 'datetime',
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

    public function stakeholder(): BelongsTo
    {
        return $this->belongsTo(Stakeholder::class);
    }
}
