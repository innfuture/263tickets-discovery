<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StakeholderService extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'stakeholder_id', 'name', 'description',
        'base_price_cents', 'currency', 'pricing_model',
        'attributes', 'is_active',
    ];

    protected $casts = [
        'base_price_cents' => 'integer',
        'attributes' => 'array',
        'is_active' => 'boolean',
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
