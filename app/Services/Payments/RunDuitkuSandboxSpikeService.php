<?php

namespace App\Services\Payments;

use App\DTOs\Payments\DuitkuSandboxCreateResult;
use App\DTOs\Payments\DuitkuSandboxInquiryResult;
use App\Gateways\Payments\DuitkuSandboxGateway;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class RunDuitkuSandboxSpikeService
{
    public function __construct(
        private readonly DuitkuSandboxGateway $gateway,
    ) {}

    public function create(int $amount, string $paymentMethod, string $email): DuitkuSandboxCreateResult
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
            'email' => $email,
            'callbackUrl' => $callbackUrl,
            'returnUrl' => $returnUrl,
            'expiresAt' => $now->modify('+60 minutes'),
        ]);
    }

    public function inquire(string $merchantOrderId): DuitkuSandboxInquiryResult
    {
        if (preg_match('/^KL-SBX-[0-9A-Z]{26}$/', $merchantOrderId) !== 1) {
            throw new InvalidArgumentException('Merchant order ID tidak sesuai format sandbox Klik Laundry.');
        }

        return $this->gateway->inquire($merchantOrderId);
    }

    private function isHttpsUrl(mixed $url): bool
    {
        return is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https';
    }
}
