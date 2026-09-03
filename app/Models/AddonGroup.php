<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddonGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'size_id',
        'name',
        'selection_type',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(CylinderSize::class, 'size_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AddonItem::class, 'group_id')->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /// A group with no size belongs to no particular cylinder: it is offered
    /// alongside every size, and is what an accessory-only order draws from.
    public function scopeUniversal(Builder $query): Builder
    {
        return $query->whereNull('size_id');
    }

    public function isUniversal(): bool
    {
        return $this->size_id === null;
    }
}
