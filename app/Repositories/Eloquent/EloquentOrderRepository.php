<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Orders\OrderCreationData;
use App\DTOs\Orders\OrderData;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderAddressType;
use App\Enums\OrderIndicatorType;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderIndicator;
use App\Models\OrderItem;
use App\Models\OrderScheduleHistory;
use App\Models\OrderStatusHistory;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function createOrFind(OrderCreationData $data): array
    {
        $order = Order::query()->createOrFirst(
            ['customer_id' => $data->customerId, 'idempotency_key' => $data->idempotencyKey],
            [
                'public_id' => $data->publicId,
                'order_number' => $data->orderNumber,
                'tenant_id' => $data->tenantId,
                'outlet_id' => $data->outletId,
                'tenant_name' => $data->tenantName,
                'outlet_name' => $data->outletName,
                'request_fingerprint' => $data->requestFingerprint,
                'pricing_type' => $data->pricingType,
                'fulfillment_status' => $data->fulfillmentStatus,
                'payment_status' => $data->paymentStatus,
                'pickup_slot_id' => $data->pickupSlotId,
                'pickup_starts_at' => $data->pickupStartsAt,
                'pickup_ends_at' => $data->pickupEndsAt,
                'items_subtotal' => $data->itemsSubtotal,
                'estimated_items_subtotal' => $data->estimatedItemsSubtotal,
                'pickup_fee' => $data->pickupFee,
                'delivery_fee' => $data->deliveryFee,
                'grand_total' => $data->grandTotal,
                'estimated_grand_total' => $data->estimatedGrandTotal,
                'estimated_ready_at' => $data->estimatedReadyAt,
            ],
        );
        $created = $order->wasRecentlyCreated;

        if ($created) {
            OrderItem::query()->create(['order_id' => $order->id, ...$data->item]);
            foreach ($data->addresses as $address) {
                OrderAddress::query()->create(['order_id' => $order->id, ...$address]);
            }
            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => $data->fulfillmentStatus,
                'actor_id' => $data->customerId,
                'occurred_at' => now(),
            ]);
        }

        return ['order' => $this->map($this->fresh((int) $order->id)), 'created' => $created];
    }

    public function findIdempotent(int $customerId, string $idempotencyKey): ?OrderData
    {
        $order = $this->query()->where('customer_id', $customerId)->where('idempotency_key', $idempotencyKey)->first();

        return $order === null ? null : $this->map($order);
    }

    public function findForCustomer(int $customerId, string $publicId): ?OrderData
    {
        $order = $this->query()->where('customer_id', $customerId)->where('public_id', $publicId)->first();

        return $order === null ? null : $this->map($order);
    }

    public function findForTenant(int $tenantId, string $publicId): ?OrderData
    {
        $order = $this->query()->where('tenant_id', $tenantId)->where('public_id', $publicId)->first();

        return $order === null ? null : $this->map($order);
    }

    public function lockByPublicId(string $publicId): ?OrderData
    {
        $order = Order::query()->where('public_id', $publicId)->lockForUpdate()->first();

        return $order === null ? null : $this->map($order->load($this->relations()));
    }

    public function paginateForCustomer(int $customerId, array $filters, int $perPage = 12): array
    {
        return $this->paginate($this->query()->where('customer_id', $customerId), $filters, $perPage, true);
    }

    public function paginateForTenant(int $tenantId, array $filters, int $perPage = 12): array
    {
        return $this->paginate($this->query()->where('tenant_id', $tenantId), $filters, $perPage, false);
    }

    public function reschedulePickup(int $orderId, int $slotId, string $startsAt, string $endsAt, int $actorId, ?string $reason): OrderData
    {
        $order = Order::query()->findOrFail($orderId);
        OrderScheduleHistory::query()->create([
            'order_id' => $order->id,
            'schedule_type' => 'pickup',
            'old_slot_id' => $order->pickup_slot_id,
            'old_starts_at' => $order->pickup_starts_at,
            'old_ends_at' => $order->pickup_ends_at,
            'new_slot_id' => $slotId,
            'new_starts_at' => $startsAt,
            'new_ends_at' => $endsAt,
            'actor_id' => $actorId,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
        $order->update(['pickup_slot_id' => $slotId, 'pickup_starts_at' => $startsAt, 'pickup_ends_at' => $endsAt]);

        return $this->map($this->fresh($orderId));
    }

    public function cancel(int $orderId, int $actorId, ?string $reason): OrderData
    {
        $order = Order::query()->findOrFail($orderId);
        $from = $order->fulfillment_status->value;
        $order->update(['fulfillment_status' => FulfillmentStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by' => $actorId, 'cancellation_reason' => $reason]);
        OrderStatusHistory::query()->create(['order_id' => $orderId, 'from_status' => $from, 'to_status' => FulfillmentStatus::Cancelled, 'actor_id' => $actorId, 'reason' => $reason, 'occurred_at' => now()]);

        return $this->map($this->fresh($orderId));
    }

    public function hasOrdersForOutlet(int $outletId): bool
    {
        return Order::query()->where('outlet_id', $outletId)->exists();
    }

    public function hasOrdersForPackage(int $packageId): bool
    {
        return OrderItem::query()->where('package_id', $packageId)->exists();
    }

    public function hasOrdersForSlot(int $slotId): bool
    {
        return Order::query()->where('pickup_slot_id', $slotId)->orWhere('delivery_slot_id', $slotId)->exists();
    }

    public function hasScheduledOrderOnDate(int $outletId, string $date): bool
    {
        return Order::query()->where('outlet_id', $outletId)->whereDate('pickup_starts_at', $date)->whereNotIn('fulfillment_status', [FulfillmentStatus::Completed, FulfillmentStatus::Cancelled])->exists();
    }

    public function hasNonTerminalForTenant(int $tenantId): bool
    {
        return Order::query()->where('tenant_id', $tenantId)->whereNotIn('fulfillment_status', [FulfillmentStatus::Completed, FulfillmentStatus::Cancelled])->exists();
    }

    public function monitoringCandidates(): array
    {
        return $this->query()->whereNotIn('fulfillment_status', [FulfillmentStatus::Completed, FulfillmentStatus::Cancelled])->get()->map(fn (Order $order): OrderData => $this->map($order))->all();
    }

    public function syncIndicator(int $orderId, OrderIndicatorType $type, bool $active, string $occurredAt, array $context = []): bool
    {
        $key = "{$orderId}:{$type->value}";
        if ($active) {
            $indicator = OrderIndicator::query()->firstOrCreate(
                ['active_key' => $key],
                ['order_id' => $orderId, 'type' => $type, 'context' => $context, 'detected_at' => $occurredAt],
            );

            return $indicator->wasRecentlyCreated;
        }

        return OrderIndicator::query()->where('active_key', $key)->update(['active_key' => null, 'resolved_at' => $occurredAt, 'updated_at' => now()]) > 0;
    }

    /** @param Builder<Order> $query @param array{fulfillment_status?: string|null, payment_status?: string|null, query?: string|null} $filters */
    private function paginate(Builder $query, array $filters, int $perPage, bool $includePii): array
    {
        if (($filters['fulfillment_status'] ?? null) !== null) {
            $query->where('fulfillment_status', $filters['fulfillment_status']);
        }
        if (($filters['payment_status'] ?? null) !== null) {
            $query->where('payment_status', $filters['payment_status']);
        }
        if (($filters['query'] ?? null) !== null) {
            $query->whereLike('order_number', '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['query']).'%');
        }

        $page = $query->latest('orders.id')->paginate($perPage)->withQueryString();

        return $this->page($page, $includePii);
    }

    /** @return Builder<Order> */
    private function query(): Builder
    {
        return Order::query()->with($this->relations());
    }

    /** @return array<int|string, string|callable> */
    private function relations(): array
    {
        return ['tenant:id,name', 'outlet:id,public_id,name', 'pickupSlot:id,public_id', 'item', 'addresses', 'statusHistories' => fn ($query) => $query->orderBy('occurred_at'), 'scheduleHistories' => fn ($query) => $query->orderBy('occurred_at'), 'indicators' => fn ($query) => $query->orderBy('detected_at')];
    }

    private function fresh(int $id): Order
    {
        return Order::query()->with($this->relations())->findOrFail($id);
    }

    /** @param LengthAwarePaginator<int, Order> $page */
    private function page(LengthAwarePaginator $page, bool $includePii): array
    {
        return [
            'items' => $page->getCollection()->map(fn (Order $order): array => $this->map($order)->toSummaryArray($includePii))->values()->all(),
            'meta' => ['currentPage' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'perPage' => $page->perPage(), 'total' => $page->total()],
        ];
    }

    private function map(Order $order): OrderData
    {
        $item = $order->item;
        $pickupAddress = $order->addresses->first(fn (OrderAddress $address): bool => $address->type === OrderAddressType::Pickup);

        return new OrderData(
            id: (int) $order->id, tenantId: (int) $order->tenant_id, outletId: (int) $order->outlet_id, customerId: (int) $order->customer_id,
            publicId: $order->public_id, orderNumber: $order->order_number, requestFingerprint: $order->request_fingerprint,
            pricingType: $order->pricing_type->value, fulfillmentStatus: $order->fulfillment_status->value, paymentStatus: $order->payment_status->value,
            pickupSlotId: (int) $order->pickup_slot_id, pickupSlotPublicId: $order->pickupSlot->public_id,
            pickupStartsAt: $order->pickup_starts_at->toIso8601String(), pickupEndsAt: $order->pickup_ends_at->toIso8601String(),
            deliveryStartsAt: $order->delivery_starts_at?->toIso8601String(), deliveryEndsAt: $order->delivery_ends_at?->toIso8601String(),
            itemsSubtotal: $order->items_subtotal, estimatedItemsSubtotal: $order->estimated_items_subtotal,
            pickupFee: (int) $order->pickup_fee, deliveryFee: (int) $order->delivery_fee, grandTotal: $order->grand_total, estimatedGrandTotal: $order->estimated_grand_total,
            estimatedReadyAt: $order->estimated_ready_at?->toIso8601String(), readyAt: $order->ready_at?->toIso8601String(), completedAt: $order->completed_at?->toIso8601String(), cancelledAt: $order->cancelled_at?->toIso8601String(), cancellationReason: $order->cancellation_reason,
            outletName: $order->outlet_name, outletPublicId: $order->outlet->public_id, tenantName: $order->tenant_name, customerName: $pickupAddress->contact_name,
            item: ['packageName' => $item->package_name, 'packageDescription' => $item->package_description, 'pricingType' => $item->pricing_type->value, 'unitPrice' => $item->unit_price, 'minimumQuantity' => $item->minimum_quantity, 'minimumWeightGrams' => $item->minimum_weight_grams, 'estimatedDurationMinutes' => $item->estimated_duration_minutes, 'quantity' => $item->quantity, 'estimatedWeightGrams' => $item->estimated_weight_grams, 'estimatedBillableWeightGrams' => $item->estimated_billable_weight_grams, 'actualWeightGrams' => $item->actual_weight_grams, 'billableWeightGrams' => $item->billable_weight_grams],
            addresses: $order->addresses->map(fn (OrderAddress $address): array => ['type' => $address->type->value, 'label' => $address->label, 'contactName' => $address->contact_name, 'contactPhone' => $address->contact_phone, 'address' => $address->address, 'city' => $address->city, 'area' => $address->area, 'latitude' => (float) $address->latitude, 'longitude' => (float) $address->longitude])->all(),
            statusHistory: $order->statusHistories->map(fn (OrderStatusHistory $history): array => ['from' => $history->from_status?->value, 'to' => $history->to_status->value, 'reason' => $history->reason, 'occurredAt' => $history->occurred_at->toIso8601String()])->all(),
            scheduleHistory: $order->scheduleHistories->map(fn (OrderScheduleHistory $history): array => ['type' => $history->schedule_type, 'oldStartsAt' => $history->old_starts_at->toIso8601String(), 'oldEndsAt' => $history->old_ends_at->toIso8601String(), 'newStartsAt' => $history->new_starts_at->toIso8601String(), 'newEndsAt' => $history->new_ends_at->toIso8601String(), 'reason' => $history->reason, 'occurredAt' => $history->occurred_at->toIso8601String()])->all(),
            indicators: $order->indicators->map(fn (OrderIndicator $indicator): array => ['type' => $indicator->type->value, 'context' => $indicator->context, 'detectedAt' => $indicator->detected_at->toIso8601String(), 'resolvedAt' => $indicator->resolved_at?->toIso8601String()])->all(),
            createdAt: $order->created_at->toIso8601String(),
        );
    }
}
