<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'size_id',
        'filled_count',
        'empty_count',
        'low_stock_threshold',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'filled_count'        => 'integer',
            'empty_count'         => 'integer',
            'low_stock_threshold' => 'integer',
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

    public function isEmpty(): bool
    {
        return $this->filled_count === 0;
    }

    public function isLow(): bool
    {
        return $this->filled_count > 0 && $this->filled_count <= $this->low_stock_threshold;
    }

    public function isCritical(): bool
    {
        return $this->filled_count > 0 && $this->filled_count <= 2;
    }

    public function getStatusAttribute(): string
    {
        if ($this->isEmpty()) return 'out';
        if ($this->isCritical()) return 'critical';
        if ($this->isLow()) return 'low';
        return 'ok';
    }
}
