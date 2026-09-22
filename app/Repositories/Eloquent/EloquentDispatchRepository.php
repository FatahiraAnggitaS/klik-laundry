<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Dispatch\DriverOfferData;
use App\DTOs\Dispatch\DriverTaskData;
use App\DTOs\Dispatch\WeightConfirmationData;
use App\DTOs\Orders\OrderData;
use App\Enums\DriverCommissionStatus;
use App\Enums\DriverOfferStatus;
use App\Enums\DriverTaskStatus;
use App\Enums\DriverTaskType;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderAddressType;
use App\Models\DeliveryTask;
use App\Models\DriverCommission;
use App\Models\DriverTaskHistory;
use App\Models\DriverTaskOffer;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\WeightConfirmation;
use App\Repositories\Contracts\DispatchRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class EloquentDispatchRepository implements DispatchRepositoryInterface
{
    public function createTask(OrderData $order, DriverTaskType $type, int $commissionAmount): DriverTaskData
    {
        $startsAt = $type === DriverTaskType::Pickup ? $order->pickupStartsAt : $order->deliveryStartsAt;
        $endsAt = $type === DriverTaskType::Pickup ? $order->pickupEndsAt : $order->deliveryEndsAt;
        if ($startsAt === null || $endsAt === null) {
            throw new \LogicException('Task schedule is required.');
        }

        $task = DeliveryTask::query()->firstOrCreate(
            ['order_id' => $order->id, 'type' => $type],
            [
                'public_id' => (string) Str::ulid(), 'tenant_id' => $order->tenantId,
                'outlet_id' => $order->outletId, 'status' => DriverTaskStatus::Pending,
                'commission_amount' => $commissionAmount, 'scheduled_starts_at' => $startsAt,
                'scheduled_ends_at' => $endsAt,
            ],
        );
        if ($task->wasRecentlyCreated) {
            DriverTaskHistory::query()->create(['task_id' => $task->id, 'from_status' => null, 'to_status' => DriverTaskStatus::Pending, 'occurred_at' => now()]);
        }

        return $this->mapTask($this->freshTask((int) $task->id));
    }

    public function findTaskForTenant(int $tenantId, string $publicId, bool $lock = false): ?DriverTaskData
    {
        $query = DeliveryTask::query()->where('tenant_id', $tenantId)->where('public_id', $publicId);
        $task = $lock ? $query->lockForUpdate()->first() : $query->first();

        return $task === null ? null : $this->mapTask($task->load($this->taskRelations()));
    }

    public function findTaskForDriver(int $driverId, string $publicId, bool $lock = false): ?DriverTaskData
    {
        $query = DeliveryTask::query()->where('assignee_id', $driverId)->where('public_id', $publicId);
        $task = $lock ? $query->lockForUpdate()->first() : $query->first();

        return $task === null ? null : $this->mapTask($task->load($this->taskRelations()));
    }

    public function createOffer(int $taskId, int $driverId, string $expiresAt, ?int $actorId): DriverOfferData
    {
        $task = DeliveryTask::query()->findOrFail($taskId);
        $from = $task->status;
        $task->update(['status' => DriverTaskStatus::Offered, 'offered_at' => now()]);
        DriverTaskHistory::query()->create(['task_id' => $taskId, 'from_status' => $from, 'to_status' => DriverTaskStatus::Offered, 'actor_id' => $actorId, 'occurred_at' => now()]);
        $offer = DriverTaskOffer::query()->create([
            'public_id' => (string) Str::ulid(), 'task_id' => $taskId, 'driver_id' => $driverId,
            'status' => DriverOfferStatus::Offered, 'active_task_key' => 'task:'.$taskId,
            'offered_at' => now(), 'expires_at' => $expiresAt,
        ]);

        return $this->mapOffer($offer->load(['task' => fn ($query) => $query->with($this->taskRelations())]));
    }

    public function findOfferForDriver(int $driverId, string $publicId, bool $lock = false): ?DriverOfferData
    {
        $query = DriverTaskOffer::query()->where('driver_id', $driverId)->where('public_id', $publicId);
        $offer = $lock ? $query->lockForUpdate()->first() : $query->first();
        if ($offer === null) {
            return null;
        }

        return $this->mapOffer($offer->load(['task' => fn ($query) => $query->with($this->taskRelations())]));
    }

    public function hasActiveTask(int $driverId, ?int $exceptTaskId = null): bool
    {
        return DeliveryTask::query()->where('assignee_id', $driverId)
            ->whereIn('status', [DriverTaskStatus::Accepted, DriverTaskStatus::InProgress])
            ->when($exceptTaskId !== null, fn (Builder $query) => $query->whereKeyNot($exceptTaskId))
            ->exists();
    }

    public function acceptOffer(int $offerId, int $taskId, int $driverId): DriverTaskData
    {
        $offer = DriverTaskOffer::query()->findOrFail($offerId);
        $offer->update(['status' => DriverOfferStatus::Accepted, 'active_task_key' => null, 'responded_at' => now()]);
        $task = DeliveryTask::query()->findOrFail($taskId);
        $from = $task->status;
        $task->update([
            'status' => DriverTaskStatus::Accepted, 'assignee_id' => $driverId,
            'active_driver_key' => 'driver:'.$driverId, 'accepted_at' => now(),
        ]);
        DriverTaskHistory::query()->create(['task_id' => $taskId, 'from_status' => $from, 'to_status' => DriverTaskStatus::Accepted, 'actor_id' => $driverId, 'occurred_at' => now()]);
        $order = Order::query()->findOrFail($task->order_id);
        $target = $task->type === DriverTaskType::Pickup ? FulfillmentStatus::PickupAssigned : FulfillmentStatus::DeliveryAssigned;
        $this->transitionOrder($order, $target, $driverId);

        return $this->mapTask($this->freshTask($taskId));
    }

    public function rejectOffer(int $offerId, int $taskId, int $driverId): DriverTaskData
    {
        DriverTaskOffer::query()->whereKey($offerId)->update(['status' => DriverOfferStatus::Rejected, 'active_task_key' => null, 'responded_at' => now(), 'updated_at' => now()]);
        $task = DeliveryTask::query()->findOrFail($taskId);
        $from = $task->status;
        $task->update(['status' => DriverTaskStatus::Pending]);
        DriverTaskHistory::query()->create(['task_id' => $taskId, 'from_status' => $from, 'to_status' => DriverTaskStatus::Pending, 'actor_id' => $driverId, 'reason' => 'Offer ditolak Driver.', 'occurred_at' => now()]);

        return $this->mapTask($this->freshTask($taskId));
    }

    public function startTask(int $taskId, int $driverId): DriverTaskData
    {
        $task = DeliveryTask::query()->findOrFail($taskId);
        $from = $task->status;
        $task->update(['status' => DriverTaskStatus::InProgress, 'started_at' => now()]);
        DriverTaskHistory::query()->create(['task_id' => $taskId, 'from_status' => $from, 'to_status' => DriverTaskStatus::InProgress, 'actor_id' => $driverId, 'occurred_at' => now()]);

        return $this->mapTask($this->freshTask($taskId));
    }

    public function completePickupTask(int $taskId, int $driverId, ?string $note, ?array $proof): DriverTaskData
    {
        $task = DeliveryTask::query()->findOrFail($taskId);
        if ($task->status === DriverTaskStatus::Completed) {
            return $this->mapTask($this->freshTask($taskId));
        }
        $attributes = ['status' => DriverTaskStatus::Completed, 'active_driver_key' => null, 'note' => $note, 'completed_at' => now()];
        if ($proof !== null) {
            $attributes += ['proof_disk' => $proof['disk'], 'proof_key' => $proof['key'], 'proof_mime' => $proof['mime'], 'proof_size' => $proof['size']];
        }
        $from = $task->status;
        $task->update($attributes);
        DriverTaskHistory::query()->create(['task_id' => $taskId, 'from_status' => $from, 'to_status' => DriverTaskStatus::Completed, 'actor_id' => $driverId, 'occurred_at' => now()]);
        DriverCommission::query()->firstOrCreate(
            ['task_id' => $taskId],
            ['public_id' => (string) Str::ulid(), 'tenant_id' => $task->tenant_id, 'driver_id' => $driverId, 'amount' => $task->commission_amount, 'status' => DriverCommissionStatus::Earned, 'earned_at' => now()],
        );

        $order = Order::query()->findOrFail($task->order_id);
        $this->transitionOrder($order, FulfillmentStatus::PickedUp, $driverId);
        if ($order->pricing_type->value === 'per_kg') {
            $this->transitionOrder($order->refresh(), FulfillmentStatus::AwaitingWeight, $driverId);
        }

        return $this->mapTask($this->freshTask($taskId));
    }

    public function resetForReassignment(int $taskId, int $actorId, string $reason): DriverTaskData
    {
        DriverTaskOffer::query()->where('task_id', $taskId)->where('status', DriverOfferStatus::Offered)->update(['status' => DriverOfferStatus::Withdrawn, 'active_task_key' => null, 'responded_at' => now(), 'updated_at' => now()]);
        $task = DeliveryTask::query()->findOrFail($taskId);
        $from = $task->status;
        $task->update(['status' => DriverTaskStatus::Pending, 'assignee_id' => null, 'active_driver_key' => null, 'accepted_at' => null, 'started_at' => null]);
        DriverTaskHistory::query()->create(['task_id' => $taskId, 'from_status' => $from, 'to_status' => DriverTaskStatus::Pending, 'actor_id' => $actorId, 'reason' => $reason, 'occurred_at' => now()]);
        $order = Order::query()->findOrFail($task->order_id);
        $target = $task->type === DriverTaskType::Pickup ? FulfillmentStatus::AwaitingPickup : FulfillmentStatus::ReadyForDelivery;
        if ($order->fulfillment_status !== $target) {
            $this->transitionOrder($order, $target, $actorId, $reason);
        }

        return $this->mapTask($this->freshTask($taskId));
    }

    public function cancelForOrder(int $orderId, int $actorId, string $reason): void
    {
        $tasks = DeliveryTask::query()->where('order_id', $orderId)->whereNotIn('status', [DriverTaskStatus::Completed, DriverTaskStatus::Cancelled])->lockForUpdate()->get();
        foreach ($tasks as $task) {
            DriverTaskOffer::query()->where('task_id', $task->id)->where('status', DriverOfferStatus::Offered)->update(['status' => DriverOfferStatus::Withdrawn, 'active_task_key' => null, 'responded_at' => now(), 'updated_at' => now()]);
            $from = $task->status;
            $task->update(['status' => DriverTaskStatus::Cancelled, 'active_driver_key' => null, 'cancelled_at' => now()]);
            DriverTaskHistory::query()->create(['task_id' => $task->id, 'from_status' => $from, 'to_status' => DriverTaskStatus::Cancelled, 'actor_id' => $actorId, 'reason' => $reason, 'occurred_at' => now()]);
        }
    }

    public function withdrawOffersForDriver(int $driverId, ?int $actorId, string $reason): void
    {
        $offers = DriverTaskOffer::query()->where('driver_id', $driverId)->where('status', DriverOfferStatus::Offered)->lockForUpdate()->get();
        foreach ($offers as $offer) {
            $this->expireOrWithdraw($offer, DriverOfferStatus::Withdrawn, $actorId, $reason);
        }
    }

    public function expiredOffers(string $now): array
    {
        return DriverTaskOffer::query()->where('status', DriverOfferStatus::Offered)->where('expires_at', '<=', $now)->with(['task' => fn ($query) => $query->with($this->taskRelations())])->get()->map(fn (DriverTaskOffer $offer): DriverOfferData => $this->mapOffer($offer))->all();
    }

    public function expireOffer(int $offerId, int $taskId): bool
    {
        $offer = DriverTaskOffer::query()->whereKey($offerId)->where('status', DriverOfferStatus::Offered)->lockForUpdate()->first();
        if ($offer === null) {
            return false;
        }
        $this->expireOrWithdraw($offer, DriverOfferStatus::Expired, null, 'Offer kedaluwarsa.');

        return true;
    }

    public function paginateForTenant(int $tenantId, int $perPage = 12): array
    {
        $page = $this->taskQuery()->where('tenant_id', $tenantId)->latest('id')->paginate($perPage, ['*'], 'tasks')->withQueryString();

        return ['items' => $page->getCollection()->map(function (DeliveryTask $task): array {
            $includePii = in_array($task->status, [DriverTaskStatus::Accepted, DriverTaskStatus::InProgress], true);

            return $this->mapTask($task)->toArray($includePii);
        })->values()->all(), 'meta' => $this->meta($page)];
    }

    public function offersForDriver(int $driverId): array
    {
        return DriverTaskOffer::query()->where('driver_id', $driverId)->where('status', DriverOfferStatus::Offered)->where('expires_at', '>', now())->with(['task' => fn ($query) => $query->with($this->taskRelations())])->latest('id')->get()->map(fn (DriverTaskOffer $offer): DriverOfferData => $this->mapOffer($offer))->all();
    }

    public function tasksForDriver(int $driverId): array
    {
        return $this->taskQuery()->where('assignee_id', $driverId)->latest('id')->limit(30)->get()->map(fn (DeliveryTask $task): DriverTaskData => $this->mapTask($task))->all();
    }

    public function currentWeight(int $orderId): ?WeightConfirmationData
    {
        $weight = WeightConfirmation::query()->where('current_order_key', 'order:'.$orderId)->first();

        return $weight === null ? null : $this->mapWeight($weight);
    }

    public function confirmWeight(OrderData $order, int $actorId, int $actualGrams, int $billableGrams, int $itemsSubtotal, int $grandTotal, ?string $reason, ?array $proof): WeightConfirmationData
    {
        $current = WeightConfirmation::query()->where('current_order_key', 'order:'.$order->id)->lockForUpdate()->first();
        if ($current !== null) {
            $current->update(['current_order_key' => null]);
        }
        $attributes = [
            'public_id' => (string) Str::ulid(), 'order_id' => $order->id,
            'actual_grams' => $actualGrams, 'minimum_grams' => (int) $order->item['minimumWeightGrams'],
            'billable_grams' => $billableGrams, 'items_subtotal' => $itemsSubtotal,
            'grand_total' => $grandTotal, 'confirmed_by' => $actorId, 'reason' => $reason,
            'current_order_key' => 'order:'.$order->id, 'confirmed_at' => now(),
        ];
        if ($proof !== null) {
            $attributes += ['proof_disk' => $proof['disk'], 'proof_key' => $proof['key'], 'proof_mime' => $proof['mime'], 'proof_size' => $proof['size']];
        }
        $weight = WeightConfirmation::query()->create($attributes);
        if ($current !== null) {
            $current->update(['superseded_by' => $weight->id]);
        }
        OrderItem::query()->where('order_id', $order->id)->update(['actual_weight_grams' => $actualGrams, 'billable_weight_grams' => $billableGrams, 'updated_at' => now()]);
        $model = Order::query()->findOrFail($order->id);
        $model->update(['items_subtotal' => $itemsSubtotal, 'grand_total' => $grandTotal]);
        if ($model->fulfillment_status === FulfillmentStatus::AwaitingWeight) {
            $this->transitionOrder($model, FulfillmentStatus::AwaitingPayment, $actorId, $reason);
        }

        return $this->mapWeight($weight);
    }

    public function taskProof(int $tenantId, string $taskPublicId): ?array
    {
        $task = DeliveryTask::query()->where('tenant_id', $tenantId)->where('public_id', $taskPublicId)->whereNotNull('proof_key')->first();

        return $task === null ? null : ['disk' => (string) $task->proof_disk, 'key' => (string) $task->proof_key];
    }

    public function driverTaskProof(int $driverId, string $taskPublicId): ?array
    {
        $task = DeliveryTask::query()->where('assignee_id', $driverId)->where('public_id', $taskPublicId)->whereNotNull('proof_key')->first();

        return $task === null ? null : ['disk' => (string) $task->proof_disk, 'key' => (string) $task->proof_key];
    }

    public function weightProof(int $tenantId, string $orderPublicId): ?array
    {
        $weight = WeightConfirmation::query()->whereHas('order', fn (Builder $query) => $query->where('tenant_id', $tenantId)->where('public_id', $orderPublicId))->whereNotNull('current_order_key')->whereNotNull('proof_key')->first();

        return $weight === null ? null : ['disk' => (string) $weight->proof_disk, 'key' => (string) $weight->proof_key];
    }

    private function expireOrWithdraw(DriverTaskOffer $offer, DriverOfferStatus $status, ?int $actorId, string $reason): void
    {
        $offer->update(['status' => $status, 'active_task_key' => null, 'responded_at' => now()]);
        $task = DeliveryTask::query()->findOrFail($offer->task_id);
        if ($task->status !== DriverTaskStatus::Offered) {
            return;
        }
        $task->update(['status' => DriverTaskStatus::Pending]);
        DriverTaskHistory::query()->create(['task_id' => $task->id, 'from_status' => DriverTaskStatus::Offered, 'to_status' => DriverTaskStatus::Pending, 'actor_id' => $actorId, 'reason' => $reason, 'occurred_at' => now()]);
    }

    private function transitionOrder(Order $order, FulfillmentStatus $target, ?int $actorId, ?string $reason = null): void
    {
        $from = $order->fulfillment_status;
        $order->update(['fulfillment_status' => $target]);
        OrderStatusHistory::query()->create(['order_id' => $order->id, 'from_status' => $from, 'to_status' => $target, 'actor_id' => $actorId, 'reason' => $reason, 'occurred_at' => now()]);
    }

    /** @return Builder<DeliveryTask> */
    private function taskQuery(): Builder
    {
        return DeliveryTask::query()->with($this->taskRelations());
    }

    /** @return array<int|string, string|callable> */
    private function taskRelations(): array
    {
        return [
            'order.outlet:id,name,latitude,longitude', 'order.addresses', 'assignee:id,public_id,name',
            'histories' => fn ($query) => $query->orderBy('occurred_at'),
        ];
    }

    private function freshTask(int $id): DeliveryTask
    {
        return $this->taskQuery()->findOrFail($id);
    }

    private function mapTask(DeliveryTask $task): DriverTaskData
    {
        $order = $task->order;
        $addressType = $task->type === DriverTaskType::Pickup ? OrderAddressType::Pickup : OrderAddressType::Delivery;
        $address = $order->addresses->first(fn (OrderAddress $candidate): bool => $candidate->type === $addressType);

        return new DriverTaskData(
            id: (int) $task->id, tenantId: (int) $task->tenant_id, orderId: (int) $task->order_id, outletId: (int) $task->outlet_id,
            publicId: $task->public_id, orderPublicId: $order->public_id, orderNumber: $order->order_number,
            pricingType: $order->pricing_type->value, paymentStatus: $order->payment_status->value, orderStatus: $order->fulfillment_status->value,
            type: $task->type->value, status: $task->status->value, assigneeId: $task->assignee_id,
            assigneePublicId: $task->assignee?->public_id, assigneeName: $task->assignee?->name,
            commissionAmount: (int) $task->commission_amount, outletName: $order->outlet_name,
            scheduledStartsAt: $task->scheduled_starts_at->toIso8601String(), scheduledEndsAt: $task->scheduled_ends_at->toIso8601String(),
            area: $address->area, outletLatitude: (float) $order->outlet->latitude, outletLongitude: (float) $order->outlet->longitude,
            addressLatitude: (float) $address->latitude, addressLongitude: (float) $address->longitude,
            contactName: $address->contact_name, contactPhone: $address->contact_phone, address: $address->address.', '.$address->area.', '.$address->city,
            note: $task->note, hasProof: $task->proof_key !== null, acceptedAt: $task->accepted_at?->toIso8601String(),
            startedAt: $task->started_at?->toIso8601String(), completedAt: $task->completed_at?->toIso8601String(),
            history: $task->histories->map(fn (DriverTaskHistory $history): array => ['from' => $history->from_status?->value, 'to' => $history->to_status->value, 'reason' => $history->reason, 'occurredAt' => $history->occurred_at->toIso8601String()])->all(),
        );
    }

    private function mapOffer(DriverTaskOffer $offer): DriverOfferData
    {
        return new DriverOfferData((int) $offer->id, (int) $offer->task_id, (int) $offer->driver_id, $offer->public_id, $offer->status->value, $offer->expires_at->toIso8601String(), $this->mapTask($offer->task));
    }

    private function mapWeight(WeightConfirmation $weight): WeightConfirmationData
    {
        return new WeightConfirmationData($weight->public_id, $weight->actual_grams, $weight->minimum_grams, $weight->billable_grams, $weight->items_subtotal, $weight->grand_total, $weight->proof_key !== null, $weight->confirmed_at->toIso8601String());
    }

    /** @param LengthAwarePaginator<int, DeliveryTask> $page @return array{currentPage: int, lastPage: int, perPage: int, total: int} */
    private function meta(LengthAwarePaginator $page): array
    {
        return ['currentPage' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'perPage' => $page->perPage(), 'total' => $page->total()];
    }
}
