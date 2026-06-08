<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_id',
        'rider_id',
        'size_id',
        'brand_id',
        'order_type',
        'status',
        'gas_price',
        'cylinder_price',
        'delivery_fee',
        'addons_total',
        'gaspoints_redeemed',
        'gaspoints_discount',
        'total_amount',
        'payment_method',
        'payment_status',
        'delivery_lat',
        'delivery_lng',
        'delivery_notes',
        'idempotency_key',
        'rider_assigned_at',
        'picked_up_at',
        'on_the_way_at',
        'delivered_at',
        'cancelled_at',
        'cancel_reason',
        'cancelled_by',
        'has_issue',
        'issue_type',
        'issue_description',
        'issue_resolved',
        'safety_checklist',
        'delivery_photo_path',
    ];

    protected function casts(): array
    {
        return [
            'rider_assigned_at' => 'datetime',
            'picked_up_at'      => 'datetime',
            'on_the_way_at'     => 'datetime',
            'delivered_at'      => 'datetime',
            'cancelled_at'      => 'datetime',
            'has_issue'         => 'boolean',
            'issue_resolved'    => 'boolean',
            'delivery_lat'      => 'float',
            'delivery_lng'      => 'float',
            'safety_checklist'  => 'array',
            'gas_price'           => 'integer',
            'cylinder_price'      => 'integer',
            'delivery_fee'        => 'integer',
            'addons_total'        => 'integer',
            'gaspoints_redeemed'  => 'integer',
            'gaspoints_discount'  => 'integer',
            'total_amount'        => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(CylinderSize::class, 'size_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(GasBrand::class, 'brand_id');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(OrderAddon::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function rating(): HasOne
    {
        return $this->hasOne(OrderRating::class);
    }

    public function getFormattedTotalAttribute(): string
    {
        return 'KES ' . number_format($this->total_amount);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['delivered', 'cancelled']);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function isActive(): bool
    {
        return ! in_array($this->status, ['delivered', 'cancelled']);
    }

    public function canBeCancelledByCustomer(): bool
    {
        return in_array($this->status, ['pending', 'rider_assigned']);
    }

    public function canBeReorderedByCustomer(): bool
    {
        return $this->status === 'delivered';
    }

    public function isReportableIssue(): bool
    {
        return ! in_array($this->status, ['delivered', 'cancelled']);
    }
}
