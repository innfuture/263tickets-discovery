<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily rollup row per developer_api_key. Written by
 * TrackDeveloperApiUsage middleware on every request. Lets the
 * dashboard render usage charts without aggregating raw logs.
 */
class DeveloperApiUsage extends Model
{
    use HasFactory;

    protected $table = 'developer_api_usage_daily';

    protected $fillable = [
        'developer_api_key_id', 'date',
        'request_count', 'success_count', 'error_count', 'rate_limited_count',
    ];

    protected $casts = [
        'date' => 'date',
        'request_count' => 'integer',
        'success_count' => 'integer',
        'error_count' => 'integer',
        'rate_limited_count' => 'integer',
    ];

    public function key(): BelongsTo
    {
        return $this->belongsTo(DeveloperApiKey::class, 'developer_api_key_id');
    }
}
