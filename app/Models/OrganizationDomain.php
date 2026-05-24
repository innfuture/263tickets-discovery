<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Custom hostnames bound to an organization — `tickets.acmecon.com`.
 * The middleware `ResolveOrganizationFromHost` reads the request Host
 * header against this table to set the active org for public surface
 * requests served outside our canonical app domain.
 *
 * DNS-verified via TXT record (`example-app-verify=<token>`). Unverified
 * rows are visible in the dashboard but not used for routing.
 */
class OrganizationDomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'hostname', 'verification_token',
        'verified', 'verified_at', 'is_primary',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'verified_at' => 'datetime',
        'is_primary' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $row) {
            if (empty($row->verification_token)) {
                $row->verification_token = (string) Str::random(48);
            }
            $row->hostname = strtolower((string) $row->hostname);
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
