<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAddon extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'addon_item_id',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function addonItem(): BelongsTo
    {
        return $this->belongsTo(AddonItem::class, 'addon_item_id');
    }
}
