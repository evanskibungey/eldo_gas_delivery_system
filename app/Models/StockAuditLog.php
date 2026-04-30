<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'size_id',
        'change_type',
        'change_amount',
        'new_count',
        'order_id',
        'admin_id',
        'note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'change_amount' => 'integer',
            'new_count'     => 'integer',
            'created_at'    => 'datetime',
        ];
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(CylinderSize::class, 'size_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
