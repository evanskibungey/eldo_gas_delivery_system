<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerStreak extends Model
{
    protected $fillable = [
        'customer_id',
        'current_streak',
        'longest_streak',
        'order_count',
        'last_order_month',
    ];

    protected $casts = [
        'last_order_month' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
