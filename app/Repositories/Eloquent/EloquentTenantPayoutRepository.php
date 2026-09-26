<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Finance\TenantPayoutData;
use App\Enums\PaymentReconciliation;
use App\Enums\PaymentStatus;
use App\Enums\PayoutAccountStatus;
use App\Enums\PayoutStatus;
use App\Enums\RefundStatus;
use App\Enums\SettlementStatus;
use App\Models\FinancialAdjustment;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\TenantPayout;
use App\Models\TenantPayoutAccount;
use App\Models\TenantPayoutAdjustment;
use App\Models\TenantPayoutItem;
use App\Repositories\Contracts\TenantPayoutRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentTenantPayoutRepository implements TenantPayoutRepositoryInterface
{
    public function lockPaymentCandidates(int $tenantId, string $cutoffAt): array
    {
        $payments = Payment::query()->with('order:id,order_number,completed_at')->where('tenant_id', $tenantId)
            ->where('paid_at', '<=', $cutoffAt)->lockForUpdate()->get();
        $ids = $payments->pluck('id');
        $activeRefunds = RefundRequest::query()->whereIn('payment_id', $ids)
            ->whereIn('status', [RefundStatus::Submitted, RefundStatus::Approved])->pluck('payment_id')->flip();
        $claimed = TenantPayoutItem::query()->whereIn('payment_id', $ids)
            ->where(fn ($query) => $query->whereNotNull('active_payment_key')->orWhereNotNull('finalized_payment_key'))
            ->pluck('payment_id')->flip();

        return $payments->map(fn (Payment $payment): array => [
            'id' => $payment->id, 'publicId' => $payment->public_id, 'orderNumber' => $payment->order->order_number,
            'amount' => $payment->amount, 'feeAmount' => $payment->fee_amount, 'status' => $payment->status->value,
            'reconciliation' => $payment->reconciliation->value, 'paidAt' => $payment->paid_at?->toIso8601String(),
            'completedAt' => $payment->order->completed_at?->toIso8601String(),
            'hasActiveRefund' => $activeRefunds->has($payment->id), 'claimed' => $claimed->has($payment->id),
        ])->all();
    }

    public function lockAdjustmentCandidates(int $tenantId, string $cutoffAt): array
    {
        $adjustments = FinancialAdjustment::query()->where('tenant_id', $tenantId)->where('occurred_at', '<=', $cutoffAt)
            ->where('settlement_status', SettlementStatus::Unsettled)->lockForUpdate()->get();
        $claimed = TenantPayoutAdjustment::query()->whereIn('financial_adjustment_id', $adjustments->pluck('id'))
            ->where(fn ($query) => $query->whereNotNull('active_adjustment_key')->orWhereNotNull('finalized_adjustment_key'))
            ->pluck('financial_adjustment_id')->flip();

        return $adjustments->map(fn (FinancialAdjustment $adjustment): array => [
            'id' => $adjustment->id, 'publicId' => $adjustment->public_id, 'amount' => $adjustment->amount,
            'occurredAt' => $adjustment->occurred_at->toIso8601String(), 'claimed' => $claimed->has($adjustment->id),
        ])->all();
    }

    public function lockVerifiedAccount(int $tenantId): ?array
    {
        $account = TenantPayoutAccount::query()->where('current_tenant_id', $tenantId)
            ->where('verification_status', PayoutAccountStatus::Verified)->lockForUpdate()->first();
        if ($account === null) {
            return null;
        }

        return ['id' => $account->id, 'bankName' => $account->bank_name, 'holderName' => $account->account_holder_name, 'accountNumber' => $account->account_number, 'maskedAccountNumber' => $account->masked_account_number];
    }

    public function create(int $tenantId, int $accountId, string $cutoffAt, array $payments, array $adjustments, array $account, int $actorId): TenantPayoutData
    {
        $gross = array_sum(array_column($payments, 'amount'));
        $fee = array_sum(array_column($payments, 'feeAmount'));
        $adjustment = array_sum(array_column($adjustments, 'amount'));
        $publicId = (string) Str::ulid();
        $payout = TenantPayout::query()->create([
            'public_id' => $publicId, 'batch_reference' => 'TP-'.now('Asia/Jakarta')->format('Ymd').'-'.substr($publicId, -10),
            'tenant_id' => $tenantId, 'payout_account_id' => $accountId, 'status' => PayoutStatus::PendingTransfer,
            'cutoff_at' => $cutoffAt, 'gross_amount' => $gross, 'fee_amount' => $fee,
            'adjustment_amount' => $adjustment, 'net_amount' => $gross - $fee + $adjustment,
            'pending_tenant_key' => 'tenant:'.$tenantId, 'bank_name' => $account['bankName'],
            'account_holder_name' => $account['holderName'], 'account_number' => $account['accountNumber'],
            'masked_account_number' => $account['maskedAccountNumber'], 'created_by' => $actorId,
        ]);
        foreach ($payments as $payment) {
            TenantPayoutItem::query()->create([
                'tenant_payout_id' => $payout->id, 'payment_id' => $payment['id'], 'gross_amount' => $payment['amount'],
                'fee_amount' => $payment['feeAmount'], 'net_amount' => $payment['amount'] - $payment['feeAmount'],
                'active_payment_key' => 'payment:'.$payment['id'],
            ]);
        }
        foreach ($adjustments as $item) {
            TenantPayoutAdjustment::query()->create([
                'tenant_payout_id' => $payout->id, 'financial_adjustment_id' => $item['id'], 'amount' => $item['amount'],
                'active_adjustment_key' => 'adjustment:'.$item['id'],
            ]);
        }

        return $this->map($payout);
    }

    public function lockForSupport(string $publicId): ?TenantPayoutData
    {
        $payout = TenantPayout::query()->where('public_id', $publicId)->lockForUpdate()->first();

        return $payout === null ? null : $this->map($payout);
    }

    public function findForSupport(string $publicId): ?TenantPayoutData
    {
        $payout = TenantPayout::query()->where('public_id', $publicId)->first();

        return $payout === null ? null : $this->map($payout);
    }

    public function findForTenant(int $tenantId, string $publicId): ?TenantPayoutData
    {
        $payout = TenantPayout::query()->where('tenant_id', $tenantId)->where('public_id', $publicId)->first();

        return $payout === null ? null : $this->map($payout);
    }

    public function accountIsCurrentAndVerified(int $tenantId, int $accountId): bool
    {
        return TenantPayoutAccount::query()->whereKey($accountId)->where('current_tenant_id', $tenantId)
            ->where('verification_status', PayoutAccountStatus::Verified)->exists();
    }

    public function sourcesAreFinalizable(int $payoutId): bool
    {
        $items = TenantPayoutItem::query()->where('tenant_payout_id', $payoutId)->lockForUpdate()->get();
        $payments = Payment::query()->whereIn('id', $items->pluck('payment_id'))->lockForUpdate()->get()->keyBy('id');
        $activeRefundPaymentIds = RefundRequest::query()->whereIn('payment_id', $items->pluck('payment_id'))
            ->whereIn('status', [RefundStatus::Submitted, RefundStatus::Approved])->pluck('payment_id')->flip();

        foreach ($items as $item) {
            $payment = $payments->get($item->payment_id);
            if ($payment === null
                || $item->active_payment_key !== 'payment:'.$item->payment_id
                || $item->finalized_payment_key !== null
                || $payment->status !== PaymentStatus::Paid
                || $payment->reconciliation !== PaymentReconciliation::Matched
                || $payment->fee_amount === null
                || $payment->amount !== $item->gross_amount
                || $payment->fee_amount !== $item->fee_amount
                || $activeRefundPaymentIds->has($item->payment_id)) {
                return false;
            }
        }

        $adjustmentItems = TenantPayoutAdjustment::query()->where('tenant_payout_id', $payoutId)->lockForUpdate()->get();
        $adjustments = FinancialAdjustment::query()->whereIn('id', $adjustmentItems->pluck('financial_adjustment_id'))->lockForUpdate()->get()->keyBy('id');
        foreach ($adjustmentItems as $item) {
            $adjustment = $adjustments->get($item->financial_adjustment_id);
            if ($adjustment === null
                || $item->active_adjustment_key !== 'adjustment:'.$item->financial_adjustment_id
                || $item->finalized_adjustment_key !== null
                || $adjustment->settlement_status !== SettlementStatus::Unsettled
                || $adjustment->amount !== $item->amount) {
                return false;
            }
        }

        return $items->isNotEmpty() || $adjustmentItems->isNotEmpty();
    }

    public function finalize(int $id, int $actorId, string $method, string $reference): TenantPayoutData
    {
        TenantPayout::query()->whereKey($id)->update([
            'status' => PayoutStatus::Finalized, 'pending_tenant_key' => null, 'transfer_method' => $method,
            'external_reference' => $reference, 'finalized_by' => $actorId, 'finalized_at' => now(),
        ]);
        $items = TenantPayoutItem::query()->where('tenant_payout_id', $id)->get();
        foreach ($items as $item) {
            $item->update(['active_payment_key' => null, 'finalized_payment_key' => 'payment:'.$item->payment_id]);
        }
        $adjustments = TenantPayoutAdjustment::query()->where('tenant_payout_id', $id)->get();
        foreach ($adjustments as $item) {
            $item->update(['active_adjustment_key' => null, 'finalized_adjustment_key' => 'adjustment:'.$item->financial_adjustment_id]);
            FinancialAdjustment::query()->whereKey($item->financial_adjustment_id)->update(['settlement_status' => SettlementStatus::Settled, 'settled_at' => now()]);
        }

        return $this->map(TenantPayout::query()->findOrFail($id));
    }

    public function void(int $id, int $actorId, string $reason): TenantPayoutData
    {
        TenantPayout::query()->whereKey($id)->update(['status' => PayoutStatus::Voided, 'pending_tenant_key' => null, 'void_reason' => $reason, 'voided_by' => $actorId, 'voided_at' => now()]);
        TenantPayoutItem::query()->where('tenant_payout_id', $id)->update(['active_payment_key' => null]);
        TenantPayoutAdjustment::query()->where('tenant_payout_id', $id)->update(['active_adjustment_key' => null]);

        return $this->map(TenantPayout::query()->findOrFail($id));
    }

    public function voidPendingForAccountChange(int $tenantId, int $actorId): array
    {
        $payouts = TenantPayout::query()->where('tenant_id', $tenantId)->where('status', PayoutStatus::PendingTransfer)->lockForUpdate()->get();
        $ids = [];
        foreach ($payouts as $payout) {
            $ids[] = $payout->public_id;
            $this->void($payout->id, $actorId, 'Rekening payout diubah sebelum transfer diselesaikan.');
        }

        return $ids;
    }

    public function listForTenant(int $tenantId, int $limit = 20): array
    {
        return TenantPayout::query()->where('tenant_id', $tenantId)->latest('id')->limit($limit)->get()->map(fn (TenantPayout $payout): array => $this->map($payout)->toArray())->all();
    }

    public function listForSupport(int $limit = 30): array
    {
        return TenantPayout::query()->latest('id')->limit($limit)->get()->map(fn (TenantPayout $payout): array => $this->map($payout)->toArray())->all();
    }

    public function hasOpenObligations(int $tenantId): bool
    {
        if (TenantPayout::query()->where('tenant_id', $tenantId)->where('status', PayoutStatus::PendingTransfer)->exists()
            || FinancialAdjustment::query()->where('tenant_id', $tenantId)->where('settlement_status', SettlementStatus::Unsettled)->exists()) {
            return true;
        }

        return Payment::query()->where('tenant_id', $tenantId)->where('status', 'paid')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('tenant_payout_items')->whereColumn('tenant_payout_items.payment_id', 'payments.id')->whereNotNull('finalized_payment_key'))
            ->exists();
    }

    private function map(TenantPayout $payout): TenantPayoutData
    {
        $payments = DB::table('tenant_payout_items')->where('tenant_payout_id', $payout->id)->join('payments', 'payments.id', '=', 'tenant_payout_items.payment_id')
            ->join('orders', 'orders.id', '=', 'payments.order_id')->orderBy('tenant_payout_items.id')->get([
                'payments.public_id as payment_public_id', 'orders.order_number', 'tenant_payout_items.gross_amount',
                'tenant_payout_items.fee_amount', 'tenant_payout_items.net_amount',
            ])->map(fn ($item): array => ['paymentPublicId' => $item->payment_public_id, 'orderNumber' => $item->order_number, 'grossAmount' => (int) $item->gross_amount, 'feeAmount' => (int) $item->fee_amount, 'netAmount' => (int) $item->net_amount])->all();
        $adjustments = DB::table('tenant_payout_adjustments')->where('tenant_payout_id', $payout->id)->join('financial_adjustments', 'financial_adjustments.id', '=', 'tenant_payout_adjustments.financial_adjustment_id')
            ->orderBy('tenant_payout_adjustments.id')->get(['financial_adjustments.public_id', 'financial_adjustments.type', 'tenant_payout_adjustments.amount'])
            ->map(fn ($item): array => ['publicId' => $item->public_id, 'type' => $item->type, 'amount' => (int) $item->amount])->all();

        return new TenantPayoutData(
            $payout->id, $payout->public_id, $payout->batch_reference, $payout->tenant_id, $payout->payout_account_id,
            $payout->status->value, $payout->cutoff_at->toIso8601String(), $payout->gross_amount, $payout->fee_amount,
            $payout->adjustment_amount, $payout->net_amount, $payout->bank_name, $payout->masked_account_number,
            $payout->transfer_method, $payout->external_reference, $payout->finalized_at?->toIso8601String(),
            $payout->void_reason, $payments, $adjustments,
        );
    }
}
