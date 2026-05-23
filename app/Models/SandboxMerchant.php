<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class SandboxMerchant extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'slug',
        'name',
        'user_id',
        'environment',
        'emulate_default',
        'default_currency',
        'webhook_endpoint',
        'webhook_signing_secret',
        'webhook_signing_secret_previous',
        'webhook_secret_rotated_at',
        'rules',
        'is_private',
        'webhooks_parallel',
    ];

    protected $casts = [
        'rules' => 'array',
        'is_private' => 'bool',
        'webhooks_parallel' => 'bool',
        'webhook_secret_rotated_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
            if (empty($row->webhook_signing_secret)) {
                $row->webhook_signing_secret = 'whsec_sbx_'.bin2hex(random_bytes(24));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SandboxTransaction::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(SandboxWebhookOutbox::class);
    }

    public function clock(): HasOne
    {
        return $this->hasOne(SandboxClockRow::class);
    }
}
