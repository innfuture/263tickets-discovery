<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtensionAuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'extension_installation_id', 'action', 'resource_type',
        'resource_id', 'context', 'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function installation(): BelongsTo
    {
        return $this->belongsTo(ExtensionInstallation::class, 'extension_installation_id');
    }
}
