<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'description', 'is_active', 'sort_order'])]
class EventCategory extends Model
{
    use HasFactory;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (EventCategory $category) {
            if (empty($category->slug) && ! empty($category->name)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'category_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
