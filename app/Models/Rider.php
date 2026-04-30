<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class Rider extends Authenticatable
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'name',
        'phone',
        'photo_path',
        'national_id',
        'is_safety_certified',
        'certification_date',
        'is_active',
        'is_available',
        'current_latitude',
        'current_longitude',
        'location_updated_at',
        'heading',
        'avg_rating',
        'total_deliveries',
        'incident_count',
    ];

    protected function casts(): array
    {
        return [
            'is_safety_certified'  => 'boolean',
            'is_active'            => 'boolean',
            'is_available'         => 'boolean',
            'certification_date'   => 'date',
            'location_updated_at'  => 'datetime',
            'avg_rating'           => 'float',
            'current_latitude'     => 'float',
            'current_longitude'    => 'float',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(OrderRating::class);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::url($this->photo_path) : null;
    }

    public function isCertificationValid(): bool
    {
        if (! $this->is_safety_certified || ! $this->certification_date) {
            return false;
        }
        return $this->certification_date->addYear()->isFuture();
    }
}
