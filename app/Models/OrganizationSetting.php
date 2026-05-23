<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single JSON-backed bag of small settings keyed by organization.
 *
 * Many of the settings pages — brand kit, domain config, public
 * profile, ticket templates, email identity, taxes, retention, date
 * formats — store a handful of fields each. Rather than minting a
 * dedicated column per group, they all serialize into this one row's
 * `data` JSON, namespaced by feature.
 *
 * Access via `OrganizationSetting::for($org)->get('brand.colors')`
 * and `->set('brand.colors', [...])`.
 */
class OrganizationSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public static function for(Organization $org): self
    {
        return static::firstOrCreate(
            ['organization_id' => $org->id],
            ['data' => []],
        );
    }

    /**
     * Dot-path read with optional default. `brand.colors.primary`.
     */
    public function get(string $path, mixed $default = null): mixed
    {
        return data_get($this->data ?? [], $path, $default);
    }

    /**
     * Dot-path write that persists. Returns the model for chaining.
     */
    public function set(string $path, mixed $value): self
    {
        $data = $this->data ?? [];
        data_set($data, $path, $value);
        $this->data = $data;
        $this->save();

        return $this;
    }

    /**
     * Merge a namespace ("brand", "domain", …) of partial state.
     */
    public function merge(string $namespace, array $value): self
    {
        $data = $this->data ?? [];
        $existing = (array) ($data[$namespace] ?? []);
        $data[$namespace] = array_replace_recursive($existing, $value);
        $this->data = $data;
        $this->save();

        return $this;
    }
}
