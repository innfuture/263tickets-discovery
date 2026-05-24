<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScannerPairingCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'scanner_profile_id',
        'code',
        'hint_label',
        'expires_at',
        'used_at',
        'used_by_device_id',
        'created_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ScannerProfile::class, 'scanner_profile_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ScannerDevice::class, 'used_by_device_id');
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at > now();
    }

    /**
     * Crockford-style 8-char code (omits ambiguous chars). Short enough
     * for an operator to read off a screen and type into a phone, long
     * enough to resist trivial brute force inside the 30-minute TTL
     * (32^8 ≈ 10^12 combinations).
     */
    public static function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < 8; $i++) {
            $out .= $alphabet[random_int(0, 31)];
        }

        return $out;
    }
}
