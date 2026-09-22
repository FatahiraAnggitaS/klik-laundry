<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Payments\PaymentData;
use App\Enums\PaymentReconciliation;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentChannel;
use App\Models\PaymentEvent;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class EloquentPaymentRepository implements PaymentRepositoryInterface
{
    public function createAttempt(int $tenantId, int $orderId, string $merchantOrderId, string $channelCode, int $amount, DateTimeInterface $expiresAt): PaymentData
    {
        $payment = Payment::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'order_id' => $orderId,
            'active_order_key' => 'order:'.$orderId,
            'merchant_order_id' => $merchantOrderId,
            'channel_code' => $channelCode,
            'amount' => $amount,
            'status' => PaymentStatus::Pending,
            'reconciliation' => PaymentReconciliation::NotRequired,
            'expires_at' => $expiresAt,
        ]);

        return $this->map($this->fresh($payment->id));
    }

    public function findPendingForOrder(int $orderId): ?PaymentData
    {
        $payment = Payment::query()->where('order_id', $orderId)->where('status', PaymentStatus::Pending)->latest('id')->first();

        return $payment === null ? null : $this->map($payment);
    }

    public function lockByMerchantOrderId(string $merchantOrderId): ?PaymentData
    {
        $payment = Payment::query()->where('merchant_order_id', $merchantOrderId)->lockForUpdate()->first();

        return $payment === null ? null : $this->map($payment);
    }

    public function findByMerchantOrderId(string $merchantOrderId): ?PaymentData
    {
        $payment = Payment::query()->where('merchant_order_id', $merchantOrderId)->first();

        return $payment === null ? null : $this->map($payment);
    }

    public function findByPublicIdForSupport(string $publicId): ?PaymentData
    {
        $payment = Payment::query()->where('public_id', $publicId)->first();

        return $payment === null ? null : $this->map($payment);
    }

    public function claimInquiry(string $merchantOrderId, DateTimeInterface $availableBefore, DateTimeInterface $startedAt): bool
    {
        return Payment::query()
            ->where('merchant_order_id', $merchantOrderId)
            ->where(function (Builder $query) use ($availableBefore): void {
                $query->whereNull('last_inquired_at')->orWhere('last_inquired_at', '<=', $availableBefore);
            })
            ->update(['last_inquired_at' => $startedAt]) > 0;
    }

    public function lockByPublicIdForCustomer(int $customerId, string $publicId): ?PaymentData
    {
        $payment = Payment::query()->where('public_id', $publicId)->whereHas('order', fn ($query) => $query->where('customer_id', $customerId))->lockForUpdate()->first();

        return $payment === null ? null : $this->map($payment);
    }

    public function findByMerchantOrderIdForCustomer(int $customerId, string $merchantOrderId): ?PaymentData
    {
        $payment = Payment::query()->where('merchant_order_id', $merchantOrderId)
            ->whereHas('order', fn (Builder $query) => $query->where('customer_id', $customerId))->first();

        return $payment === null ? null : $this->map($payment);
    }

    public function storeProviderResult(string $merchantOrderId, string $providerReference, string $paymentUrl): void
    {
        Payment::query()->where('merchant_order_id', $merchantOrderId)->update([
            'provider_reference' => $providerReference,
            'provider_payment_url' => $paymentUrl,
        ]);
    }

    public function markUncertain(string $merchantOrderId): void
    {
        Payment::query()->where('merchant_order_id', $merchantOrderId)->where('status', PaymentStatus::Pending)->update([
            'reconciliation' => PaymentReconciliation::NeedsInquiry,
        ]);
    }

    public function applyPaid(string $merchantOrderId, string $providerReference, ?int $feeAmount): bool
    {
        return Payment::query()->where('merchant_order_id', $merchantOrderId)->where('status', PaymentStatus::Pending)->update([
            'status' => PaymentStatus::Paid,
            'active_order_key' => null,
            'provider_reference' => $providerReference,
            'fee_amount' => $feeAmount,
            'reconciliation' => PaymentReconciliation::Matched,
            'paid_at' => now(),
            'terminal_at' => now(),
        ]) > 0;
    }

    public function markTerminal(string $merchantOrderId, string $status): bool
    {
        return Payment::query()->where('merchant_order_id', $merchantOrderId)->where('status', PaymentStatus::Pending)->update([
            'status' => $status,
            'active_order_key' => null,
            'terminal_at' => now(),
        ]) > 0;
    }

    public function flagMismatch(string $merchantOrderId): void
    {
        Payment::query()->where('merchant_order_id', $merchantOrderId)->update([
            'reconciliation' => PaymentReconciliation::Mismatch,
        ]);
    }

    public function storePaidFee(string $merchantOrderId, int $feeAmount): void
    {
        Payment::query()->where('merchant_order_id', $merchantOrderId)->where('status', PaymentStatus::Paid)->update([
            'fee_amount' => $feeAmount,
            'reconciliation' => PaymentReconciliation::Matched,
        ]);
    }

    public function recordEvent(?int $paymentId, string $fingerprint, string $providerStatus, ?string $providerReference, ?int $amount, bool $signatureOk): bool
    {
        $event = PaymentEvent::query()->createOrFirst(
            ['fingerprint' => $fingerprint],
            [
                'payment_id' => $paymentId,
                'provider_status' => $providerStatus,
                'provider_reference' => $providerReference,
                'amount' => $amount,
                'signature_ok' => $signatureOk,
                'processed_at' => now(),
            ],
        );

        return $event->wasRecentlyCreated;
    }

    public function overdueMerchantOrderIds(DateTimeInterface $now): array
    {
        return Payment::query()->where('status', PaymentStatus::Pending)->where('expires_at', '<=', $now)
            ->orderBy('id')->limit(500)->pluck('merchant_order_id')->all();
    }

    public function activeChannels(): array
    {
        return PaymentChannel::query()->where('is_active', true)->orderBy('label')->get()->map(fn (PaymentChannel $channel): array => [
            'code' => $channel->channel_code,
            'label' => $channel->label,
            'category' => $channel->category,
        ])->all();
    }

    public function findChannel(string $channelCode): ?array
    {
        $channel = PaymentChannel::query()->where('channel_code', $channelCode)->first();

        if ($channel === null) {
            return null;
        }

        return ['code' => $channel->channel_code, 'label' => $channel->label, 'category' => $channel->category, 'isActive' => $channel->is_active];
    }

    public function setChannelActive(string $channelCode, bool $active): void
    {
        PaymentChannel::query()->where('channel_code', $channelCode)->update([
            'is_active' => $active,
            'verified_at' => $active ? now() : null,
        ]);
    }

    public function channels(): array
    {
        return PaymentChannel::query()->orderBy('category')->orderBy('label')->get()->map(fn (PaymentChannel $channel): array => [
            'code' => $channel->channel_code,
            'label' => $channel->label,
            'category' => $channel->category,
            'isActive' => $channel->is_active,
            'verifiedAt' => $channel->verified_at?->toIso8601String(),
        ])->all();
    }

    public function paginateForTenant(int $tenantId, array $filters = [], int $perPage = 12): array
    {
        $paginator = Payment::query()->where('tenant_id', $tenantId)->with('order')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['reconciliation'] ?? null, fn (Builder $query, string $state) => $query->where('reconciliation', $state))
            ->when($filters['query'] ?? null, function (Builder $query, string $term): void {
                $query->where(function (Builder $nested) use ($term): void {
                    $nested->where('merchant_order_id', 'like', '%'.$term.'%')
                        ->orWhereHas('order', fn (Builder $order) => $order->where('order_number', 'like', '%'.$term.'%'));
                });
            })
            ->latest('id')->paginate($perPage)->withQueryString();

        return [
            'items' => $paginator->getCollection()->map(fn (Payment $payment): array => $this->map($payment)->toOperationalArray())->all(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function paginateForSupport(array $filters, int $perPage = 12): array
    {
        $query = Payment::query()->with(['order', 'tenant:id,name'])
            ->when($filters['status'] ?? null, fn (Builder $builder, string $status) => $builder->where('status', $status))
            ->when($filters['reconciliation'] ?? null, fn (Builder $builder, string $state) => $builder->where('reconciliation', $state))
            ->when($filters['query'] ?? null, function (Builder $builder, string $term): void {
                $builder->where(function (Builder $nested) use ($term): void {
                    $nested->where('merchant_order_id', 'like', '%'.$term.'%')
                        ->orWhereHas('order', fn (Builder $order) => $order->where('order_number', 'like', '%'.$term.'%'));
                });
            })
            ->latest('id');
        $paginator = $query->paginate($perPage)->withQueryString();

        return [
            'items' => $paginator->getCollection()->map(function (Payment $payment): array {
                return [
                    ...$this->map($payment)->toOperationalArray(),
                    'tenantName' => $payment->tenant->name,
                ];
            })->all(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function fresh(int $id): Payment
    {
        return Payment::query()->with('order')->findOrFail($id);
    }

    private function map(Payment $payment): PaymentData
    {
        $channel = PaymentChannel::query()->where('channel_code', $payment->channel_code)->first();
        $channelLabel = $channel instanceof PaymentChannel ? $channel->label : $payment->channel_code;

        return new PaymentData(
            id: $payment->id,
            publicId: $payment->public_id,
            tenantId: $payment->tenant_id,
            orderId: $payment->order_id,
            orderPublicId: $payment->order->public_id,
            orderNumber: $payment->order->order_number,
            merchantOrderId: $payment->merchant_order_id,
            providerReference: $payment->provider_reference,
            channelCode: $payment->channel_code,
            channelLabel: $channelLabel,
            amount: $payment->amount,
            status: $payment->status->value,
            reconciliation: $payment->reconciliation->value,
            paymentUrl: $payment->provider_payment_url,
            feeAmount: $payment->fee_amount,
            expiresAt: $payment->expires_at,
            paidAt: $payment->paid_at,
        );
    }
}
