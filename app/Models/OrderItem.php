<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One cylinder configuration on an order: a size, a brand, a type, and how
 * many of them.
 *
 * Prices are per unit and snapshotted at checkout, so this reads back what
 * the customer actually agreed to rather than what the catalogue says today.
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'size_id',
        'brand_id',
        'order_type',
        'quantity',
        'gas_price',
        'cylinder_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'gas_price' => 'integer',
            'cylinder_price' => 'integer',
            'line_total' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(CylinderSize::class, 'size_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(GasBrand::class);
    }

    public function isSwap(): bool
    {
        return $this->order_type === 'swap';
    }

    /**
     * What this line costs, from its own parts.
     *
     * Kept separate from the stored `line_total` so a caller can assert the
     * two agree. They diverge only if something wrote the column by hand.
     */
    public function computedTotal(): int
    {
        return ($this->gas_price + $this->cylinder_price) * $this->quantity;
    }

    /** "13kg · ProGas ×2", for an SMS or an admin row. */
    public function label(): string
    {
        $parts = array_filter([$this->size?->name, $this->brand?->name]);
        $name = $parts ? implode(' · ', $parts) : 'Cylinder';

        return $this->quantity > 1 ? "{$name} ×{$this->quantity}" : $name;
    }
}
