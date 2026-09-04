<?php

namespace App\Models;

use App\Support\OrderLifecycle;
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
        'delivery_label',
        'delivery_address',
        'delivery_notes',
        'idempotency_key',
        'rider_assigned_at',
        'rider_acceptance_deadline',
        'rider_accepted_at',
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
        'mpesa_checkout_request_id',
        'mpesa_merchant_request_id',
    ];

    protected function casts(): array
    {
        return [
            'rider_assigned_at' => 'datetime',
            'rider_acceptance_deadline' => 'datetime',
            'rider_accepted_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'on_the_way_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'has_issue' => 'boolean',
            'issue_resolved' => 'boolean',
            'delivery_lat' => 'float',
            'delivery_lng' => 'float',
            'safety_checklist' => 'array',
            'gas_price' => 'integer',
            'cylinder_price' => 'integer',
            'delivery_fee' => 'integer',
            'addons_total' => 'integer',
            'gaspoints_redeemed' => 'integer',
            'gaspoints_discount' => 'integer',
            'total_amount' => 'integer',
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
        return $this->belongsTo(GasBrand::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(OrderAddon::class);
    }

    /**
     * The cylinders on this order.
     *
     * Empty for an accessory order, which carries none. Until the read paths
     * move across, `size_id` and `brand_id` on this row still mirror the
     * first item and remain the columns most of the codebase reads.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Total cylinders across every line — four means four to load. */
    public function cylinderCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /** "13kg · ProGas ×2, 6kg · Total", for an SMS or an admin row. */
    public function itemsSummary(): string
    {
        return $this->items->map(fn (OrderItem $i) => $i->label())->implode(', ');
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
        return $query->whereIn('status', OrderLifecycle::activeStatuses());
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function isActive(): bool
    {
        return OrderLifecycle::isActive($this->status);
    }

    public function canBeCancelledByCustomer(): bool
    {
        return OrderLifecycle::canCustomerCancel($this->status);
    }

    public function canBeReorderedByCustomer(): bool
    {
        return $this->status === OrderLifecycle::STATUS_DELIVERED;
    }

    public function isReportableIssue(): bool
    {
        return OrderLifecycle::isActive($this->status);
    }

    public function needsPaymentConfirmation(): bool
    {
        return $this->status === OrderLifecycle::STATUS_DELIVERED && $this->payment_status === 'pending';
    }
}
