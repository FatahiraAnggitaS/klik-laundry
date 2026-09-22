<?php

namespace App\Services\Payments;

use App\DTOs\Payments\DuitkuSandboxCallbackResult;
use App\DTOs\Payments\DuitkuSandboxCreateResult;
use App\DTOs\Payments\DuitkuSandboxInquiryResult;
use App\DTOs\Payments\MilestoneZeroPreflightResult;
use App\Enums\PaymentStatus;
use App\Gateways\Payments\DuitkuSandboxGateway;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\MilestoneZero\ValidateEvidenceManifestService;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class RunDuitkuSandboxSpikeService
{
    public function __construct(
        private readonly DuitkuSandboxGateway $gateway,
        private readonly PaymentRepositoryInterface $payments,
        private readonly ValidateEvidenceManifestService $manifestValidator,
    ) {}

    public function create(int $amount, string $paymentMethod, string $customerName, string $email): DuitkuSandboxCreateResult
    {
        if ($amount < 1) {
            throw new InvalidArgumentException('Nominal sandbox harus lebih besar dari nol.');
        }

        if (preg_match('/^[A-Z0-9]{2}$/', $paymentMethod) !== 1) {
            throw new InvalidArgumentException('Kode payment method harus terdiri dari dua karakter uppercase/alphanumeric.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Email test tidak valid.');
        }

        if (trim($customerName) === '') {
            throw new InvalidArgumentException('Nama Customer sandbox wajib diisi.');
        }

        $callbackUrl = config('services.duitku.callback_url');
        $returnUrl = config('services.duitku.return_url');

        if (! $this->isHttpsUrl($callbackUrl) || ! $this->isHttpsUrl($returnUrl)) {
            throw new InvalidArgumentException('Callback dan return URL sandbox wajib menggunakan HTTPS.');
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $this->gateway->createInvoice([
            'merchantOrderId' => 'KL-SBX-'.Str::upper((string) Str::ulid()),
            'amount' => $amount,
            'paymentMethod' => $paymentMethod,
            'customerName' => $customerName,
            'email' => $email,
            'callbackUrl' => $callbackUrl,
            'returnUrl' => $returnUrl,
            'expiresAt' => $now->modify('+60 minutes'),
        ]);
    }

    public function inquire(string $merchantOrderId, int $expectedAmount, string $expectedProviderReference): DuitkuSandboxInquiryResult
    {
        if (preg_match('/^KL-SBX-[0-9A-Z]{26}$/', $merchantOrderId) !== 1) {
            throw new InvalidArgumentException('Merchant order ID tidak sesuai format sandbox Klik Laundry.');
        }

        if ($expectedAmount < 1 || trim($expectedProviderReference) === '') {
            throw new InvalidArgumentException('Expected amount dan provider reference inquiry wajib diisi.');
        }

        return $this->gateway->inquire($merchantOrderId, $expectedAmount, $expectedProviderReference);
    }

    /** @param array<string, mixed> $manifest */
    public function preflight(array $manifest): MilestoneZeroPreflightResult
    {
        $this->gateway->assertReady();

        return $this->manifestValidator->handle(
            $manifest,
            (string) config('services.duitku.callback_url'),
            (string) config('services.duitku.return_url'),
        );
    }

    public function simulateCallback(string $paymentPublicId, string $scenario): DuitkuSandboxCallbackResult
    {
        if (! in_array($scenario, ['duplicate', 'out-of-order'], true)) {
            throw new InvalidArgumentException('Scenario callback harus duplicate atau out-of-order.');
        }

        $payment = $this->payments->findByPublicIdForSupport($paymentPublicId);
        if ($payment === null) {
            throw new InvalidArgumentException('Payment sandbox tidak ditemukan.');
        }
        if ($payment->status !== PaymentStatus::Paid->value) {
            throw new InvalidArgumentException('Callback simulator hanya menerima payment sandbox yang sudah paid.');
        }

        $statuses = $this->gateway->simulateCallback($payment, $scenario);
        $after = $this->payments->findByPublicIdForSupport($paymentPublicId);

        if ($after === null || $after->status !== PaymentStatus::Paid->value) {
            throw new InvalidArgumentException('Callback simulator mendeteksi pelanggaran monotonic payment state.');
        }

        return new DuitkuSandboxCallbackResult(
            paymentPublicId: $payment->publicId,
            scenario: $scenario,
            statusBefore: $payment->status,
            statusAfter: $after->status,
            requestCount: count($statuses),
        );
    }

    private function isHttpsUrl(mixed $url): bool
    {
        return is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https';
    }
}
