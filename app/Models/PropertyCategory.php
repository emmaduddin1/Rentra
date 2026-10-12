<?php

namespace App\Models;

use App\Enums\PropertyCategoryGroup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'group', 'is_active', 'sort_order'])]
class PropertyCategory extends Model
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'group' => PropertyCategoryGroup::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<Property, $this> */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'category_id');
    }

    /** @param Builder<static> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<static> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
