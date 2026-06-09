<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CylinderSize extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'weight_kg',
        'sort_order',
        'is_commercial',
        'is_active',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg'     => 'float',
            'is_commercial' => 'boolean',
            'is_active'     => 'boolean',
        ];
    }

    public function addonGroups(): HasMany
    {
        return $this->hasMany(AddonGroup::class, 'size_id');
    }

    public function price(): HasOne
    {
        return $this->hasOne(CylinderPrice::class, 'size_id');
    }

    public function stockLevel(): HasOne
    {
        return $this->hasOne(StockLevel::class, 'size_id');
    }

    public function brands(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(GasBrand::class, 'brand_size_availability', 'size_id', 'brand_id')
            ->withPivot('image_path');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
