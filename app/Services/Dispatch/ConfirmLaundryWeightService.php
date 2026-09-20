<?php

namespace App\Services\Dispatch;

use App\Contracts\IdentityUser;
use App\Contracts\PrivateProofStorageInterface;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Dispatch\WeightConfirmationData;
use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\PricingType;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\DispatchRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;
use Illuminate\Http\UploadedFile;

final readonly class ConfirmLaundryWeightService
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private DispatchRepositoryInterface $dispatch,
        private TenantOperationsGuard $guard,
        private ActivityLogRepositoryInterface $activityLogs,
        private PrivateProofStorageInterface $proofs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, string $orderPublicId, int $actualGrams, ?string $reason, ?UploadedFile $proof): WeightConfirmationData
    {
        $tenant = $this->guard->forRead($actor);
        $stored = $proof === null ? null : $this->proofs->store('dispatch/weight-proofs', $proof);
        try {
            return $this->transactions->run(function () use ($actor, $tenant, $orderPublicId, $actualGrams, $reason, $stored): WeightConfirmationData {
                $order = $this->orders->lockByPublicId($orderPublicId) ?? throw new DomainRecordNotFound;
                if ($order->tenantId !== $tenant->id || $order->pricingType !== PricingType::PerKg->value) {
                    throw new DomainRecordNotFound;
                }
                if (! in_array($order->fulfillmentStatus, [FulfillmentStatus::AwaitingWeight->value, FulfillmentStatus::AwaitingPayment->value], true)
                    || $order->paymentStatus !== PaymentStatus::Unpaid->value) {
                    throw new DomainActionConflict('Weight can no longer be changed.', 'Berat tidak dapat diubah setelah invoice dibuat atau pada status ini.');
                }
                $current = $this->dispatch->currentWeight($order->id);
                if ($current !== null && ($reason === null || trim($reason) === '')) {
                    throw new DomainActionConflict('Correction reason is required.', 'Alasan koreksi berat wajib diisi.');
                }
                $minimum = (int) $order->item['minimumWeightGrams'];
                $billable = (int) (ceil(max($actualGrams, $minimum) / 100) * 100);
                $unitPrice = (int) $order->item['unitPrice'];
                $subtotal = intdiv($unitPrice, 10) * intdiv($billable, 100);
                $grandTotal = $subtotal + $order->pickupFee + $order->deliveryFee;
                $confirmation = $this->dispatch->confirmWeight($order, $actor->databaseId(), $actualGrams, $billable, $subtotal, $grandTotal, $reason, $stored);
                $this->activityLogs->record(new ActivityLogData($tenant->id, $actor->databaseId(), $current === null ? 'order.weight_confirmed' : 'order.weight_corrected', 'order', $order->publicId, $reason, before: $current === null ? [] : ['actualGrams' => $current->actualGrams, 'billableGrams' => $current->billableGrams, 'grandTotal' => $current->grandTotal], after: ['actualGrams' => $actualGrams, 'billableGrams' => $billable, 'grandTotal' => $grandTotal]));

                return $confirmation;
            });
        } catch (\Throwable $exception) {
            if ($stored !== null) {
                $this->proofs->delete($stored['disk'], $stored['key']);
            }
            throw $exception;
        }
    }
}
