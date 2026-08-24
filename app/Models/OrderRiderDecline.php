<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rider who declined an order, or let the acceptance window lapse. Rows
 * live for the life of the order so re-assignment never hands it back to
 * someone who has already turned it down.
 */
class OrderRiderDecline extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'rider_id',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    /**
     * Idempotent — the unique index on (order_id, rider_id) means a rider
     * bouncing the same order twice is recorded once.
     */
    public static function record(int $orderId, int $riderId, string $reason): void
    {
        static::firstOrCreate(
            ['order_id' => $orderId, 'rider_id' => $riderId],
            ['reason' => $reason, 'created_at' => now()],
        );
    }

    /**
     * @return array<int>
     */
    public static function riderIdsFor(int $orderId): array
    {
        return static::where('order_id', $orderId)->pluck('rider_id')->all();
    }
}
