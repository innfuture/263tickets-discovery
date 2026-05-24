<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One published / pending version of an Extension. Manifest contains
 * the full declaration (permissions, webhooks, UI extension points,
 * config schema) — see ExtensionManifestValidator for the shape.
 *
 * `signature` is the developer's RSA-SHA256 over the canonical-JSON
 * encoding of `manifest`. ExtensionSigner verifies before this row
 * transitions to `approved`.
 */
class ExtensionVersion extends Model
{
    use HasFactory;

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'extension_id', 'version', 'manifest', 'declared_permissions',
        'signature', 'status', 'review_notes', 'reviewed_at',
        'published_at',
    ];

    protected $casts = [
        'manifest' => 'array',
        'declared_permissions' => 'array',
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function extension(): BelongsTo
    {
        return $this->belongsTo(Extension::class);
    }
}
