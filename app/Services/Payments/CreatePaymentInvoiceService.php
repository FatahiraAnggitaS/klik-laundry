<?php

namespace App\Services\Payments;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Payments\PaymentData;
use App\DTOs\Payments\PaymentInvoiceRequest;
use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Gateways\Payments\PaymentGatewayInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Foundation\GetPlatformSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class CreatePaymentInvoiceService
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private PaymentRepositoryInterface $payments,
        private PaymentGatewayInterface $gateway,
        private GetPlatformSettingsService $platformSettings,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $customer, string $orderPublicId, string $channelCode, ?CarbonImmutable $now = null): PaymentData
    {
        if ($customer->role() !== UserRole::Customer || $customer->status() !== UserStatus::Active || ! $customer->hasVerifiedEmailAddress()) {
            throw new DomainRecordNotFound;
        }

        $now = $now ?? CarbonImmutable::now('Asia/Jakarta');

        $prepared = $this->transactions->run(function () use ($customer, $orderPublicId, $channelCode, $now): array {
            $order = $this->orders->lockCustomerOrder($customer->databaseId(), $orderPublicId) ?? throw new DomainRecordNotFound;

            if ($order->fulfillmentStatus !== FulfillmentStatus::AwaitingPayment->value || $order->grandTotal === null) {
                throw new DomainActionConflict('Order tidak dalam kondisi dapat dibayar.', 'Order ini belum dapat dibayar. Periksa status order.');
            }

            if ($this->platformSettings->handle()->paymentMaintenanceEnabled) {
                throw new DomainActionConflict('Pembayaran dalam maintenance.', 'Pembayaran sedang maintenance. Coba lagi nanti.');
            }

            $channel = $this->payments->findChannel($channelCode);

            if ($channel === null || ! $channel['isActive']) {
                throw new DomainActionConflict('Channel tidak tersedia.', 'Metode pembayaran tidak tersedia. Pilih channel lain.');
            }

            $existing = $this->payments->findPendingForOrder($order->id);

            if ($existing !== null) {
                $expiresAt = CarbonImmutable::parse($existing->expiresAt->format('Y-m-d H:i:s'), 'Asia/Jakarta');

                if ($expiresAt > $now) {
                    throw new DomainActionConflict('Attempt aktif sudah ada.', 'Tagihan aktif sudah ada. Selesaikan pembayaran tersebut.');
                }

                $this->payments->markTerminal($existing->merchantOrderId, PaymentStatus::Expired->value);
                $this->orders->syncPaymentStatus($order->id, PaymentStatus::Expired->value);
            }

            $merchantOrderId = 'KL-'.((string) Str::ulid());
            $expiresAt = $now->addMinutes(60);
            $attempt = $this->payments->createAttempt($order->tenantId, $order->id, $merchantOrderId, $channelCode, $order->grandTotal, $expiresAt);
            $this->orders->syncPaymentStatus($order->id, PaymentStatus::Pending->value);

            return [$attempt, $order, $customer->emailAddress()];
        });

        [$attempt, $order, $customerEmail] = $prepared;

        try {
            $result = $this->gateway->createInvoice(new PaymentInvoiceRequest(
                merchantOrderId: $attempt->merchantOrderId,
                amount: $attempt->amount,
                channelCode: $attempt->channelCode,
                customerEmail: $customerEmail,
                callbackUrl: (string) config('services.duitku.callback_url'),
                returnUrl: (string) config('services.duitku.return_url'),
                expiresAt: $attempt->expiresAt,
            ));
        } catch (RequestException|ConnectionException|RuntimeException $exception) {
            $this->payments->markUncertain($attempt->merchantOrderId);

            throw new DomainActionConflict('Respons provider tidak pasti: '.$exception->getMessage(), 'Status pembayaran sedang diverifikasi. Jangan buat tagihan baru.');
        }

        $this->payments->storeProviderResult($result->merchantOrderId, $result->providerReference, $result->paymentUrl);

        return $this->payments->lockByMerchantOrderId($result->merchantOrderId) ?? $attempt;
    }
}
