<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerBadge extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'badge_key',
        'earned_at',
    ];

    protected $casts = [
        'earned_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
