<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CylinderPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'size_id',
        'gas_refill_price',
        'new_cylinder_price',
        'new_gas_fill_price',
        'delivery_fee',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'gas_refill_price'   => 'integer',
            'new_cylinder_price' => 'integer',
            'new_gas_fill_price' => 'integer',
            'delivery_fee'       => 'integer',
        ];
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(CylinderSize::class, 'size_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }
}
