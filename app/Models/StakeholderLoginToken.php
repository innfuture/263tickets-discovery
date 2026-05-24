<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StakeholderLoginToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'stakeholder_id', 'email', 'token_hash',
        'expires_at', 'consumed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];
}
