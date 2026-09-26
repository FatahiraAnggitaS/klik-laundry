<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Finance\DriverPayoutData;
use App\Enums\DriverCommissionStatus;
use App\Enums\PayoutStatus;
use App\Enums\UserRole;
use App\Models\DriverCommission;
use App\Models\DriverPayout;
use App\Models\DriverPayoutItem;
use App\Models\User;
use App\Repositories\Contracts\DriverPayoutRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentDriverPayoutRepository implements DriverPayoutRepositoryInterface
{
    public function findDriverForTenant(int $tenantId, string $driverPublicId): ?array
    {
        $driver = User::query()->where('tenant_id', $tenantId)->where('public_id', $driverPublicId)->where('role', UserRole::Driver)->first();

        return $driver === null ? null : ['id' => $driver->id, 'publicId' => $driver->public_id, 'name' => $driver->name, 'tenantId' => $tenantId];
    }

    public function lockCommissionCandidates(int $tenantId, int $driverId, string $cutoffAt): array
    {
        $commissions = DriverCommission::query()->with('task.order')->where('tenant_id', $tenantId)->where('driver_id', $driverId)
            ->where('earned_at', '<=', $cutoffAt)->lockForUpdate()->get();
        $claimed = DriverPayoutItem::query()->whereIn('driver_commission_id', $commissions->pluck('id'))
            ->where(fn ($query) => $query->whereNotNull('active_commission_key')->orWhereNotNull('finalized_commission_key'))
            ->pluck('driver_commission_id')->flip();

        return $commissions->map(fn (DriverCommission $commission): array => [
            'id' => $commission->id, 'publicId' => $commission->public_id, 'amount' => $commission->amount,
            'status' => $commission->status->value, 'earnedAt' => $commission->earned_at->toIso8601String(),
            'taskPublicId' => $commission->task->public_id, 'orderNumber' => $commission->task->order->order_number,
            'taskType' => $commission->task->type->value, 'claimed' => $claimed->has($commission->id),
        ])->all();
    }

    public function create(int $tenantId, int $driverId, string $cutoffAt, array $commissions, int $actorId): DriverPayoutData
    {
        $publicId = (string) Str::ulid();
        $payout = DriverPayout::query()->create([
            'public_id' => $publicId, 'batch_reference' => 'DP-'.now('Asia/Jakarta')->format('Ymd').'-'.substr($publicId, -10),
            'tenant_id' => $tenantId, 'driver_id' => $driverId, 'status' => PayoutStatus::PendingTransfer,
            'cutoff_at' => $cutoffAt, 'total_amount' => array_sum(array_column($commissions, 'amount')),
            'pending_driver_key' => 'driver:'.$driverId, 'created_by' => $actorId,
        ]);
        foreach ($commissions as $commission) {
            DriverPayoutItem::query()->create([
                'driver_payout_id' => $payout->id, 'driver_commission_id' => $commission['id'],
                'amount' => $commission['amount'], 'active_commission_key' => 'commission:'.$commission['id'],
            ]);
        }

        return $this->map($payout);
    }

    public function lockForTenant(int $tenantId, string $publicId): ?DriverPayoutData
    {
        $payout = DriverPayout::query()->where('tenant_id', $tenantId)->where('public_id', $publicId)->lockForUpdate()->first();

        return $payout === null ? null : $this->map($payout);
    }

    public function findForDriver(int $driverId, string $publicId): ?DriverPayoutData
    {
        $payout = DriverPayout::query()->where('driver_id', $driverId)->where('public_id', $publicId)->first();

        return $payout === null ? null : $this->map($payout);
    }

    public function sourcesAreFinalizable(int $payoutId): bool
    {
        $items = DriverPayoutItem::query()->where('driver_payout_id', $payoutId)->lockForUpdate()->get();
        $commissions = DriverCommission::query()->whereIn('id', $items->pluck('driver_commission_id'))->lockForUpdate()->get()->keyBy('id');
        foreach ($items as $item) {
            $commission = $commissions->get($item->driver_commission_id);
            if ($commission === null
                || $item->active_commission_key !== 'commission:'.$item->driver_commission_id
                || $item->finalized_commission_key !== null
                || $commission->status !== DriverCommissionStatus::Earned
                || $commission->amount !== $item->amount) {
                return false;
            }
        }

        return $items->isNotEmpty();
    }

    public function finalize(int $id, int $actorId, string $method, ?string $reference, ?string $note): DriverPayoutData
    {
        DriverPayout::query()->whereKey($id)->update([
            'status' => PayoutStatus::Finalized, 'pending_driver_key' => null, 'transfer_method' => $method,
            'external_reference' => $reference, 'note' => $note, 'finalized_by' => $actorId, 'finalized_at' => now(),
        ]);
        $items = DriverPayoutItem::query()->where('driver_payout_id', $id)->get();
        foreach ($items as $item) {
            $item->update(['active_commission_key' => null, 'finalized_commission_key' => 'commission:'.$item->driver_commission_id]);
            DriverCommission::query()->whereKey($item->driver_commission_id)->update(['status' => DriverCommissionStatus::Paid, 'paid_at' => now()]);
        }

        return $this->map(DriverPayout::query()->findOrFail($id));
    }

    public function void(int $id, int $actorId, string $reason): DriverPayoutData
    {
        DriverPayout::query()->whereKey($id)->update(['status' => PayoutStatus::Voided, 'pending_driver_key' => null, 'void_reason' => $reason, 'voided_by' => $actorId, 'voided_at' => now()]);
        DriverPayoutItem::query()->where('driver_payout_id', $id)->update(['active_commission_key' => null]);

        return $this->map(DriverPayout::query()->findOrFail($id));
    }

    public function listForTenant(int $tenantId, int $limit = 20): array
    {
        return DriverPayout::query()->where('tenant_id', $tenantId)->latest('id')->limit($limit)->get()->map(fn (DriverPayout $payout): array => $this->map($payout)->toArray())->all();
    }

    public function listForDriver(int $driverId, int $limit = 20): array
    {
        return DriverPayout::query()->where('driver_id', $driverId)->latest('id')->limit($limit)->get()->map(fn (DriverPayout $payout): array => $this->map($payout)->toArray())->all();
    }

    public function hasOpenObligations(int $tenantId): bool
    {
        return DriverPayout::query()->where('tenant_id', $tenantId)->where('status', PayoutStatus::PendingTransfer)->exists()
            || DriverCommission::query()->where('tenant_id', $tenantId)->where('status', DriverCommissionStatus::Earned)->exists();
    }

    private function map(DriverPayout $payout): DriverPayoutData
    {
        $driver = User::query()->findOrFail($payout->driver_id);
        $items = DB::table('driver_payout_items')->where('driver_payout_id', $payout->id)
            ->join('driver_commissions', 'driver_commissions.id', '=', 'driver_payout_items.driver_commission_id')
            ->join('delivery_tasks', 'delivery_tasks.id', '=', 'driver_commissions.task_id')
            ->join('orders', 'orders.id', '=', 'delivery_tasks.order_id')->orderBy('driver_payout_items.id')
            ->get(['driver_commissions.public_id', 'delivery_tasks.public_id as task_public_id', 'delivery_tasks.type', 'orders.order_number', 'driver_payout_items.amount'])
            ->map(fn ($item): array => ['commissionPublicId' => $item->public_id, 'taskPublicId' => $item->task_public_id, 'taskType' => $item->type, 'orderNumber' => $item->order_number, 'amount' => (int) $item->amount])->all();

        return new DriverPayoutData(
            $payout->id, $payout->public_id, $payout->batch_reference, $payout->tenant_id, $payout->driver_id,
            $driver->public_id, $driver->name, $payout->status->value, $payout->cutoff_at->toIso8601String(),
            $payout->total_amount, $payout->transfer_method, $payout->external_reference, $payout->note,
            $payout->finalized_at?->toIso8601String(), $payout->void_reason, $items,
        );
    }
}
