<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\PricingType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $outlet_id
 * @property int $customer_id
 * @property int $pickup_slot_id
 * @property string $public_id
 * @property string $order_number
 * @property string $request_fingerprint
 * @property string $tenant_name
 * @property string $outlet_name
 * @property PricingType $pricing_type
 * @property FulfillmentStatus $fulfillment_status
 * @property PaymentStatus $payment_status
 * @property CarbonImmutable $pickup_starts_at
 * @property CarbonImmutable $pickup_ends_at
 * @property CarbonImmutable|null $delivery_starts_at
 * @property CarbonImmutable|null $delivery_ends_at
 * @property CarbonImmutable|null $estimated_ready_at
 * @property CarbonImmutable|null $processing_started_at
 * @property CarbonImmutable|null $ready_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property int|null $items_subtotal
 * @property int|null $estimated_items_subtotal
 * @property int $pickup_fee
 * @property int $delivery_fee
 * @property int|null $grand_total
 * @property int|null $estimated_grand_total
 * @property string|null $cancellation_reason
 * @property-read Tenant $tenant
 * @property-read Outlet $outlet
 * @property-read User $customer
 * @property-read OutletSlot $pickupSlot
 * @property-read OrderItem $item
 * @property-read Collection<int, OrderAddress> $addresses
 * @property-read Collection<int, OrderStatusHistory> $statusHistories
 * @property-read Collection<int, OrderScheduleHistory> $scheduleHistories
 * @property-read Collection<int, OrderIndicator> $indicators
 */
#[Fillable([
    'public_id', 'order_number', 'tenant_id', 'outlet_id', 'customer_id', 'tenant_name', 'outlet_name', 'idempotency_key',
    'request_fingerprint', 'pricing_type', 'fulfillment_status', 'payment_status',
    'pickup_slot_id', 'pickup_starts_at', 'pickup_ends_at', 'delivery_slot_id',
    'delivery_starts_at', 'delivery_ends_at', 'items_subtotal', 'estimated_items_subtotal',
    'pickup_fee', 'delivery_fee', 'grand_total', 'estimated_grand_total',
    'estimated_ready_at', 'ready_at', 'completed_at', 'cancelled_at', 'cancelled_by',
    'processing_started_at',
    'cancellation_reason',
])]
final class Order extends Model
{
    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Outlet, $this> */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<OutletSlot, $this> */
    public function pickupSlot(): BelongsTo
    {
        return $this->belongsTo(OutletSlot::class, 'pickup_slot_id');
    }

    /** @return HasOne<OrderItem, $this> */
    public function item(): HasOne
    {
        return $this->hasOne(OrderItem::class);
    }

    /** @return HasMany<OrderAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /** @return HasMany<OrderScheduleHistory, $this> */
    public function scheduleHistories(): HasMany
    {
        return $this->hasMany(OrderScheduleHistory::class);
    }

    /** @return HasMany<OrderIndicator, $this> */
    public function indicators(): HasMany
    {
        return $this->hasMany(OrderIndicator::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'pricing_type' => PricingType::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'payment_status' => PaymentStatus::class,
            'pickup_starts_at' => 'immutable_datetime',
            'pickup_ends_at' => 'immutable_datetime',
            'delivery_starts_at' => 'immutable_datetime',
            'delivery_ends_at' => 'immutable_datetime',
            'estimated_ready_at' => 'immutable_datetime',
            'processing_started_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'items_subtotal' => 'integer',
            'estimated_items_subtotal' => 'integer',
            'pickup_fee' => 'integer',
            'delivery_fee' => 'integer',
            'grand_total' => 'integer',
            'estimated_grand_total' => 'integer',
        ];
    }
}
