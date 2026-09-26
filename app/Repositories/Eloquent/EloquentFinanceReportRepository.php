<?php

namespace App\Repositories\Eloquent;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\DriverCommission;
use App\Models\DriverPayout;
use App\Models\FinancialAdjustment;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\Tenant;
use App\Models\TenantPayout;
use App\Models\User;
use App\Repositories\Contracts\FinanceReportRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

final class EloquentFinanceReportRepository implements FinanceReportRepositoryInterface
{
    public function tenantReport(int $tenantId, string $from, string $to, ?string $outletPublicId): array
    {
        $payments = $this->paymentQuery($tenantId, $from, $to, $outletPublicId);
        $gross = (int) (clone $payments)->sum('amount');
        $knownFee = (int) (clone $payments)->whereNotNull('fee_amount')->sum('fee_amount');
        $unknownFeeCount = (int) (clone $payments)->whereNull('fee_amount')->count();
        $commissions = $this->commissionQuery($tenantId, $from, $to, $outletPublicId);
        $commission = (int) (clone $commissions)->sum('amount');
        $adjustments = $this->adjustmentQuery($tenantId, $from, $to, $outletPublicId);
        $adjustment = (int) (clone $adjustments)->sum('financial_adjustments.amount');
        $isFinal = $unknownFeeCount === 0;

        return [
            'summary' => [
                'grossPaid' => $gross, 'gatewayFeeActual' => $knownFee, 'unknownFeeCount' => $unknownFeeCount,
                'netAfterGatewayFee' => $isFinal ? $gross - $knownFee : null, 'driverCommission' => $commission,
                'financialAdjustment' => $adjustment, 'netOperational' => $isFinal ? $gross - $knownFee - $commission : null,
                'settlementMovement' => $isFinal ? $gross - $knownFee + $adjustment : null, 'isFinal' => $isFinal,
            ],
            'payments' => $payments->with('order:id,order_number,outlet_name')->latest('paid_at')->limit(30)->get()->map(fn (Payment $payment): array => [
                'publicId' => $payment->public_id, 'orderNumber' => $payment->order->order_number,
                'outletName' => $payment->order->outlet_name, 'grossAmount' => $payment->amount,
                'feeAmount' => $payment->fee_amount, 'netAmount' => $payment->fee_amount === null ? null : $payment->amount - $payment->fee_amount,
                'paidAt' => $payment->paid_at?->toIso8601String(), 'feeFinal' => $payment->fee_amount !== null,
            ])->all(),
        ];
    }

    public function driverReport(int $driverId, string $from, string $to): array
    {
        $query = DriverCommission::query()->where('driver_id', $driverId)->whereBetween('earned_at', [$from, $to]);

        return [
            'summary' => ['earnedAmount' => (int) (clone $query)->sum('amount'), 'earnedCount' => (int) (clone $query)->count(), 'paidAmount' => (int) (clone $query)->where('status', 'paid')->sum('amount')],
            'commissions' => $query->with('task.order')->latest('earned_at')->limit(50)->get()->map(fn (DriverCommission $commission): array => [
                'publicId' => $commission->public_id, 'orderNumber' => $commission->task->order->order_number,
                'taskPublicId' => $commission->task->public_id, 'taskType' => $commission->task->type->value,
                'amount' => $commission->amount, 'status' => $commission->status->value,
                'earnedAt' => $commission->earned_at->toIso8601String(), 'paidAt' => $commission->paid_at?->toIso8601String(),
            ])->all(),
        ];
    }

    public function supportMetrics(string $from, string $to): array
    {
        return [
            'grossPaid' => (int) Payment::query()->where('status', PaymentStatus::Paid)->whereBetween('paid_at', [$from, $to])->sum('amount'),
            'unknownFeeCount' => Payment::query()->where('status', PaymentStatus::Paid)->whereBetween('paid_at', [$from, $to])->whereNull('fee_amount')->count(),
            'reconciliationMismatchCount' => Payment::query()->where('reconciliation', 'mismatch')->count(),
            'activeRefundCount' => RefundRequest::query()->whereIn('status', ['submitted', 'approved'])->count(),
            'pendingTenantPayoutCount' => TenantPayout::query()->where('status', 'pending_transfer')->count(),
            'pendingDriverPayoutCount' => DriverPayout::query()->where('status', 'pending_transfer')->count(),
            'payoutHoldCount' => Tenant::query()->where('payout_hold', true)->count(),
            'unsettledAdjustmentAmount' => (int) FinancialAdjustment::query()->where('settlement_status', 'unsettled')->sum('amount'),
        ];
    }

    public function driversForTenant(int $tenantId): array
    {
        return User::query()->where('tenant_id', $tenantId)->where('role', UserRole::Driver)->orderBy('name')->get(['public_id', 'name'])
            ->map(fn (User $driver): array => ['publicId' => $driver->public_id, 'name' => $driver->name])->all();
    }

    public function outletsForTenant(int $tenantId): array
    {
        return Outlet::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['public_id', 'name'])
            ->map(fn (Outlet $outlet): array => ['publicId' => $outlet->public_id, 'name' => $outlet->name])->all();
    }

    public function tenantsForSupport(): array
    {
        return Tenant::query()->where('onboarding_status', 'approved')->where('operational_status', '!=', 'closed')
            ->orderBy('name')->get(['public_id', 'name'])->map(fn (Tenant $tenant): array => ['publicId' => $tenant->public_id, 'name' => $tenant->name])->all();
    }

    public function countTenantExportRows(int $tenantId, string $from, string $to, ?string $outletPublicId): int
    {
        return $this->paymentQuery($tenantId, $from, $to, $outletPublicId)->count()
            + $this->commissionQuery($tenantId, $from, $to, $outletPublicId)->count()
            + $this->adjustmentQuery($tenantId, $from, $to, $outletPublicId)->count()
            + TenantPayout::query()->where('tenant_id', $tenantId)->whereBetween('created_at', [$from, $to])->count();
    }

    public function tenantExportRows(int $tenantId, string $from, string $to, ?string $outletPublicId): iterable
    {
        foreach ($this->paymentQuery($tenantId, $from, $to, $outletPublicId)->with('order:id,order_number,outlet_name')->lazyById(250) as $payment) {
            yield ['payment', $payment->paid_at?->timezone('Asia/Jakarta')->toDateTimeString(), $payment->order->outlet_name, $payment->order->order_number, $payment->public_id, $payment->amount, $payment->fee_amount, $payment->fee_amount === null ? 'belum_direkonsiliasi' : $payment->amount - $payment->fee_amount, null, null, $payment->status->value];
        }
        foreach ($this->commissionQuery($tenantId, $from, $to, $outletPublicId)->with('task.order:id,order_number,outlet_name')->lazyById(250) as $commission) {
            yield ['commission', $commission->earned_at->timezone('Asia/Jakarta')->toDateTimeString(), $commission->task->order->outlet_name, $commission->task->order->order_number, $commission->public_id, null, null, null, $commission->amount, null, $commission->status->value];
        }
        foreach ($this->adjustmentQuery($tenantId, $from, $to, $outletPublicId)->with('payment.order:id,order_number,outlet_name')->lazyById(250, 'financial_adjustments.id') as $adjustment) {
            yield ['adjustment', $adjustment->occurred_at->timezone('Asia/Jakarta')->toDateTimeString(), $adjustment->payment->order->outlet_name, $adjustment->payment->order->order_number, $adjustment->public_id, null, null, null, null, $adjustment->amount, $adjustment->settlement_status->value];
        }
        foreach (TenantPayout::query()->where('tenant_id', $tenantId)->whereBetween('created_at', [$from, $to])->lazyById(250) as $payout) {
            yield ['tenant_payout', $payout->created_at->timezone('Asia/Jakarta')->toDateTimeString(), null, null, $payout->public_id, $payout->gross_amount, $payout->fee_amount, $payout->net_amount, null, $payout->adjustment_amount, $payout->status->value];
        }
    }

    public function countDriverExportRows(int $driverId, string $from, string $to): int
    {
        return DriverCommission::query()->where('driver_id', $driverId)->whereBetween('earned_at', [$from, $to])->count();
    }

    public function driverExportRows(int $driverId, string $from, string $to): iterable
    {
        foreach (DriverCommission::query()->where('driver_id', $driverId)->whereBetween('earned_at', [$from, $to])->with('task.order')->lazyById(250) as $commission) {
            yield [$commission->public_id, $commission->task->public_id, $commission->task->type->value, $commission->task->order->order_number, $commission->amount, $commission->status->value, $commission->earned_at->timezone('Asia/Jakarta')->toDateTimeString(), $commission->paid_at?->timezone('Asia/Jakarta')->toDateTimeString()];
        }
    }

    public function countSupportExportRows(string $from, string $to): int
    {
        return Payment::query()->where('status', PaymentStatus::Paid)->whereBetween('paid_at', [$from, $to])->count()
            + RefundRequest::query()->whereBetween('created_at', [$from, $to])->count()
            + TenantPayout::query()->whereBetween('created_at', [$from, $to])->count()
            + FinancialAdjustment::query()->whereBetween('occurred_at', [$from, $to])->count();
    }

    public function supportExportRows(string $from, string $to): iterable
    {
        foreach (Payment::query()->where('status', PaymentStatus::Paid)->whereBetween('paid_at', [$from, $to])->with(['tenant:id,name', 'order:id,order_number'])->lazyById(250) as $payment) {
            yield ['payment', $payment->paid_at?->timezone('Asia/Jakarta')->toDateTimeString(), $payment->tenant->name, $payment->order->order_number, $payment->public_id, $payment->amount, $payment->fee_amount, $payment->status->value, $this->mask($payment->provider_reference)];
        }
        foreach (RefundRequest::query()->whereBetween('created_at', [$from, $to])->with(['payment.tenant:id,name', 'order:id,order_number'])->lazyById(250) as $refund) {
            yield ['refund', $refund->created_at->timezone('Asia/Jakarta')->toDateTimeString(), $refund->payment->tenant->name, $refund->order->order_number, $refund->public_id, $refund->amount, null, $refund->status->value, $this->mask($refund->external_reference)];
        }
        foreach (TenantPayout::query()->whereBetween('created_at', [$from, $to])->with('tenant:id,name')->lazyById(250) as $payout) {
            yield ['tenant_payout', $payout->created_at->timezone('Asia/Jakarta')->toDateTimeString(), $payout->tenant->name, null, $payout->public_id, $payout->net_amount, $payout->fee_amount, $payout->status->value, $this->mask($payout->external_reference)];
        }
        foreach (FinancialAdjustment::query()->whereBetween('occurred_at', [$from, $to])->with(['payment.tenant:id,name', 'payment.order:id,order_number'])->lazyById(250) as $adjustment) {
            yield ['adjustment', $adjustment->occurred_at->timezone('Asia/Jakarta')->toDateTimeString(), $adjustment->payment->tenant->name, $adjustment->payment->order->order_number, $adjustment->public_id, $adjustment->amount, null, $adjustment->settlement_status->value, null];
        }
    }

    /** @return Builder<Payment> */
    private function paymentQuery(int $tenantId, string $from, string $to, ?string $outletPublicId): Builder
    {
        return Payment::query()->where('tenant_id', $tenantId)->where('status', PaymentStatus::Paid)->whereBetween('paid_at', [$from, $to])
            ->when($outletPublicId, fn (Builder $query, string $publicId) => $query->whereHas('order.outlet', fn (Builder $outlet) => $outlet->where('public_id', $publicId)));
    }

    /** @return Builder<DriverCommission> */
    private function commissionQuery(int $tenantId, string $from, string $to, ?string $outletPublicId): Builder
    {
        return DriverCommission::query()->where('tenant_id', $tenantId)->whereBetween('earned_at', [$from, $to])
            ->when($outletPublicId, fn (Builder $query, string $publicId) => $query->whereHas('task.order.outlet', fn (Builder $outlet) => $outlet->where('public_id', $publicId)));
    }

    /** @return Builder<FinancialAdjustment> */
    private function adjustmentQuery(int $tenantId, string $from, string $to, ?string $outletPublicId): Builder
    {
        return FinancialAdjustment::query()->where('financial_adjustments.tenant_id', $tenantId)->whereBetween('occurred_at', [$from, $to])
            ->when($outletPublicId, fn (Builder $query, string $publicId) => $query->whereHas('payment.order.outlet', fn (Builder $outlet) => $outlet->where('public_id', $publicId)));
    }

    private function mask(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return str_repeat('*', max(strlen($value) - 4, 4)).substr($value, -4);
    }
}
