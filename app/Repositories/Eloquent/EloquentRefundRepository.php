<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Finance\RefundData;
use App\Enums\AdjustmentType;
use App\Enums\RefundStatus;
use App\Enums\SettlementStatus;
use App\Models\FinancialAdjustment;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Repositories\Contracts\RefundRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class EloquentRefundRepository implements RefundRepositoryInterface
{
    public function lockSourceForTenant(int $tenantId, string $paymentPublicId): ?array
    {
        $payment = Payment::query()->with('order:id,tenant_id,order_number,completed_at')
            ->where('tenant_id', $tenantId)->where('public_id', $paymentPublicId)->lockForUpdate()->first();
        if ($payment === null) {
            return null;
        }

        return [
            'id' => $payment->id,
            'tenantId' => $payment->tenant_id,
            'orderId' => $payment->order_id,
            'paymentId' => $payment->id,
            'paymentPublicId' => $payment->public_id,
            'orderNumber' => $payment->order->order_number,
            'paymentStatus' => $payment->status->value,
            'amount' => $payment->amount,
            'completedAt' => $payment->order->completed_at?->toIso8601String(),
        ];
    }

    public function hasActiveOrCompletedForPayment(int $paymentId): bool
    {
        return RefundRequest::query()->where('payment_id', $paymentId)
            ->whereIn('status', [RefundStatus::Submitted, RefundStatus::Approved, RefundStatus::Completed])->exists();
    }

    public function create(int $tenantId, int $orderId, int $paymentId, int $amount, string $reason, int $actorId): RefundData
    {
        $refund = RefundRequest::query()->create([
            'public_id' => (string) Str::ulid(), 'tenant_id' => $tenantId, 'order_id' => $orderId,
            'payment_id' => $paymentId, 'status' => RefundStatus::Submitted, 'amount' => $amount,
            'reason' => $reason, 'active_payment_key' => 'payment:'.$paymentId, 'submitted_by' => $actorId,
        ]);

        return $this->map($this->fresh($refund->id));
    }

    public function lockForTenant(int $tenantId, string $publicId): ?RefundData
    {
        $refund = RefundRequest::query()->where('tenant_id', $tenantId)->where('public_id', $publicId)->lockForUpdate()->first();

        return $refund === null ? null : $this->map($refund->load(['order:id,order_number', 'payment:id,public_id']));
    }

    public function lockForSupport(string $publicId): ?RefundData
    {
        $refund = RefundRequest::query()->where('public_id', $publicId)->lockForUpdate()->first();

        return $refund === null ? null : $this->map($refund->load(['order:id,order_number', 'payment:id,public_id']));
    }

    public function review(int $id, RefundStatus $status, int $actorId, string $reason): RefundData
    {
        $values = ['status' => $status, 'reviewed_by' => $actorId, 'review_reason' => $reason, 'reviewed_at' => now()];
        if ($status === RefundStatus::Rejected) {
            $values['active_payment_key'] = null;
        }
        RefundRequest::query()->whereKey($id)->update($values);

        return $this->map($this->fresh($id));
    }

    public function complete(int $id, int $actorId, string $method, string $reference, string $reason): RefundData
    {
        $refund = RefundRequest::query()->findOrFail($id);
        $refund->update([
            'status' => RefundStatus::Completed, 'active_payment_key' => null,
            'completed_payment_key' => 'payment:'.$refund->payment_id, 'completed_by' => $actorId,
            'transfer_method' => $method, 'external_reference' => $reference, 'completed_at' => now(),
        ]);
        FinancialAdjustment::query()->create([
            'public_id' => (string) Str::ulid(), 'tenant_id' => $refund->tenant_id,
            'payment_id' => $refund->payment_id, 'refund_request_id' => $refund->id,
            'type' => AdjustmentType::FullRefund, 'amount' => -$refund->amount, 'reason' => $reason,
            'settlement_status' => SettlementStatus::Unsettled, 'created_by' => $actorId, 'occurred_at' => now(),
        ]);

        return $this->map($this->fresh($id));
    }

    public function paginateForTenant(int $tenantId, int $perPage = 10): array
    {
        return $this->paginate(RefundRequest::query()->where('tenant_id', $tenantId), $perPage);
    }

    public function paginateForSupport(int $perPage = 10): array
    {
        return $this->paginate(RefundRequest::query()->with('payment.tenant:id,name'), $perPage);
    }

    public function hasOpenObligations(int $tenantId): bool
    {
        return RefundRequest::query()->where('tenant_id', $tenantId)->whereIn('status', [RefundStatus::Submitted, RefundStatus::Approved])->exists();
    }

    private function fresh(int $id): RefundRequest
    {
        return RefundRequest::query()->with(['order:id,order_number', 'payment:id,public_id'])->findOrFail($id);
    }

    private function map(RefundRequest $refund): RefundData
    {
        return new RefundData(
            $refund->id, $refund->public_id, $refund->tenant_id, $refund->order_id, $refund->payment_id,
            $refund->order->order_number, $refund->payment->public_id, $refund->status->value, $refund->amount,
            $refund->reason, $refund->created_at->toIso8601String(), $refund->review_reason,
            $refund->reviewed_at?->toIso8601String(), $refund->transfer_method, $refund->external_reference,
            $refund->completed_at?->toIso8601String(),
        );
    }

    /** @return array{items: list<array<string, int|string|null>>, meta: array<string, int>} */
    private function paginate(Builder $query, int $perPage): array
    {
        /** @var LengthAwarePaginator<int, RefundRequest> $page */
        $page = $query->with(['order:id,order_number', 'payment:id,public_id'])->latest('id')->paginate($perPage, ['*'], 'refunds')->withQueryString();

        return [
            'items' => $page->getCollection()->map(fn (RefundRequest $refund): array => $this->map($refund)->toArray())->all(),
            'meta' => ['currentPage' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'perPage' => $page->perPage(), 'total' => $page->total()],
        ];
    }
}
