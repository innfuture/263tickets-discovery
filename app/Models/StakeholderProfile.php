<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StakeholderProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'stakeholder_id', 'bio', 'logo_path', 'banner_path', 'website',
        'social_links', 'portfolio_items', 'case_studies',
        'service_areas', 'languages', 'tags',
        'accepting_invitations', 'listed_in_marketplace',
    ];

    protected $casts = [
        'social_links' => 'array',
        'portfolio_items' => 'array',
        'case_studies' => 'array',
        'service_areas' => 'array',
        'languages' => 'array',
        'tags' => 'array',
        'accepting_invitations' => 'boolean',
        'listed_in_marketplace' => 'boolean',
    ];

    public function stakeholder(): BelongsTo
    {
        return $this->belongsTo(Stakeholder::class);
    }
}
