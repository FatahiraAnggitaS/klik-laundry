<?php

namespace App\Gateways\Payments;

use App\DTOs\Payments\DuitkuSandboxCreateResult;
use App\DTOs\Payments\DuitkuSandboxInquiryResult;
use App\DTOs\Payments\PaymentData;
use DateTimeImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use RuntimeException;

final class DuitkuSandboxGateway
{
    private const CREATE_HOST = 'api-sandbox.duitku.com';

    private const INQUIRY_HOST = 'sandbox.duitku.com';

    private const PAYMENT_URL_HOST = 'app-sandbox.duitku.com';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly DuitkuSignature $signature,
    ) {}

    /**
     * @param  array{merchantOrderId: string, amount: int, paymentMethod: string, customerName: string, email: string, callbackUrl: string, returnUrl: string, expiresAt: DateTimeImmutable}  $invoice
     */
    public function createInvoice(array $invoice): DuitkuSandboxCreateResult
    {
        $configuration = $this->configuration();
        $url = $configuration['createUrl'];
        $this->guardSandboxUrl($url, self::CREATE_HOST);
        $this->guardPublicApplicationUrl($invoice['callbackUrl'], '/webhooks/duitku');
        $this->guardPublicApplicationUrl($invoice['returnUrl']);

        $timestamp = (string) ((int) floor(microtime(true) * 1000));
        $startedAt = hrtime(true);
        $response = $this->http
            ->connectTimeout($configuration['connectTimeout'])
            ->timeout($configuration['timeout'])
            ->withOptions(['allow_redirects' => false])
            ->acceptJson()
            ->withHeaders([
                'x-duitku-timestamp' => $timestamp,
                'x-duitku-signature' => $this->signature->forCreateInvoice(
                    $configuration['merchantCode'],
                    $timestamp,
                    $configuration['apiKey'],
                ),
                'x-duitku-merchantcode' => $configuration['merchantCode'],
            ])
            ->post($url, [
                'paymentAmount' => $invoice['amount'],
                'merchantOrderId' => $invoice['merchantOrderId'],
                'productDetails' => 'Klik Laundry sandbox validation',
                'paymentMethod' => $invoice['paymentMethod'],
                'customerVaName' => mb_substr(trim($invoice['customerName']), 0, 20),
                'email' => $invoice['email'],
                'callbackUrl' => $invoice['callbackUrl'],
                'returnUrl' => $invoice['returnUrl'],
                'expiryPeriod' => 60,
            ]);

        $latencyMilliseconds = $this->elapsedMilliseconds($startedAt);
        $payload = $this->validJson($response);

        foreach (['reference', 'paymentUrl', 'statusCode'] as $key) {
            if (! isset($payload[$key]) || ! is_string($payload[$key]) || $payload[$key] === '') {
                throw new RuntimeException('Respons create invoice Duitku tidak memiliki kontrak yang diharapkan.');
            }
        }

        if (($payload['merchantCode'] ?? $configuration['merchantCode']) !== $configuration['merchantCode']) {
            throw new RuntimeException('Identitas merchant pada respons Duitku tidak cocok.');
        }

        $this->guardSandboxUrl($payload['paymentUrl'], self::PAYMENT_URL_HOST);

        return new DuitkuSandboxCreateResult(
            merchantOrderId: $invoice['merchantOrderId'],
            providerReference: $payload['reference'],
            paymentUrl: $payload['paymentUrl'],
            status: $payload['statusCode'] === '00' ? 'created' : 'rejected',
            latencyMilliseconds: $latencyMilliseconds,
            expiresAt: $invoice['expiresAt'],
        );
    }

    public function inquire(string $merchantOrderId, int $expectedAmount, string $expectedProviderReference): DuitkuSandboxInquiryResult
    {
        $configuration = $this->configuration();
        $url = $configuration['inquiryUrl'];
        $this->guardSandboxUrl($url, self::INQUIRY_HOST);

        $startedAt = hrtime(true);
        $response = $this->http
            ->connectTimeout($configuration['connectTimeout'])
            ->timeout($configuration['timeout'])
            ->withOptions(['allow_redirects' => false])
            ->acceptJson()
            ->post($url, [
                'merchantCode' => $configuration['merchantCode'],
                'merchantOrderId' => $merchantOrderId,
                'signature' => $this->signature->forInquiry(
                    $configuration['merchantCode'],
                    $merchantOrderId,
                    $configuration['apiKey'],
                ),
            ]);

        $latencyMilliseconds = $this->elapsedMilliseconds($startedAt);
        $payload = $this->validJson($response);

        foreach (['merchantOrderId', 'reference', 'amount', 'statusCode'] as $key) {
            if (! isset($payload[$key]) || ! is_string($payload[$key]) || $payload[$key] === '') {
                throw new RuntimeException('Respons inquiry Duitku tidak memiliki kontrak yang diharapkan.');
            }
        }

        if (! hash_equals($merchantOrderId, $payload['merchantOrderId'])
            || ! hash_equals($expectedProviderReference, $payload['reference'])
            || preg_match('/^\d+$/', $payload['amount']) !== 1
            || (int) $payload['amount'] !== $expectedAmount) {
            throw new RuntimeException('Identitas inquiry Duitku tidak cocok dengan invoice yang diharapkan.');
        }

        return new DuitkuSandboxInquiryResult(
            merchantOrderId: $merchantOrderId,
            providerReference: $payload['reference'],
            amount: (int) $payload['amount'],
            status: $this->normalizeInquiryStatus($payload['statusCode']),
            feeAmount: $this->normalizeFee($payload['fee'] ?? null),
            latencyMilliseconds: $latencyMilliseconds,
        );
    }

    /** @return list<int> */
    public function simulateCallback(PaymentData $payment, string $scenario): array
    {
        $configuration = $this->configuration();
        $callbackUrl = (string) config('services.duitku.callback_url');
        $this->guardPublicApplicationUrl($callbackUrl, '/webhooks/duitku');

        if ($payment->providerReference === null || $payment->providerReference === '') {
            throw new RuntimeException('Payment sandbox belum memiliki provider reference tervalidasi.');
        }

        $resultCode = $scenario === 'duplicate' ? '00' : '01';
        $amount = (string) $payment->amount;
        $payload = [
            'merchantCode' => $configuration['merchantCode'],
            'amount' => $amount,
            'merchantOrderId' => $payment->merchantOrderId,
            'reference' => $payment->providerReference,
            'resultCode' => $resultCode,
            'signature' => $this->signature->forCallback(
                $configuration['merchantCode'],
                $amount,
                $payment->merchantOrderId,
                $configuration['apiKey'],
            ),
        ];

        $requestCount = $scenario === 'duplicate' ? 2 : 1;
        $statuses = [];

        for ($attempt = 0; $attempt < $requestCount; $attempt++) {
            $response = $this->http
                ->connectTimeout($configuration['connectTimeout'])
                ->timeout($configuration['timeout'])
                ->withOptions(['allow_redirects' => false])
                ->asForm()
                ->acceptJson()
                ->post($callbackUrl, $payload);

            if (! $response->ok() || $response->json('received') !== true) {
                throw new RuntimeException('Callback sandbox tidak mendapat acknowledgement HTTP 200 yang valid.');
            }

            $statuses[] = $response->status();
        }

        return $statuses;
    }

    public function assertReady(): void
    {
        $configuration = $this->configuration();
        $this->guardSandboxUrl($configuration['createUrl'], self::CREATE_HOST);
        $this->guardSandboxUrl($configuration['inquiryUrl'], self::INQUIRY_HOST);
        $this->guardPublicApplicationUrl((string) config('services.duitku.callback_url'), '/webhooks/duitku');
        $this->guardPublicApplicationUrl((string) config('services.duitku.return_url'));
    }

    /**
     * @return array{merchantCode: string, apiKey: string, createUrl: string, inquiryUrl: string, connectTimeout: int, timeout: int}
     */
    private function configuration(): array
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Sandbox spike tidak dapat dijalankan pada application environment production.');
        }

        if (config('services.duitku.environment') !== 'sandbox') {
            throw new RuntimeException('Sandbox spike menolak environment selain sandbox.');
        }

        if (config('services.duitku.production_enabled') === true) {
            throw new RuntimeException('Sandbox spike menolak production payment flag yang aktif.');
        }

        $merchantCode = config('services.duitku.merchant_code');
        $apiKey = config('services.duitku.api_key');

        if (! is_string($merchantCode) || $merchantCode === '' || ! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Credential sandbox Duitku belum dikonfigurasi.');
        }

        return [
            'merchantCode' => $merchantCode,
            'apiKey' => $apiKey,
            'createUrl' => (string) config('services.duitku.create_invoice_url'),
            'inquiryUrl' => (string) config('services.duitku.inquiry_url'),
            'connectTimeout' => (int) config('services.duitku.connect_timeout_seconds', 5),
            'timeout' => (int) config('services.duitku.timeout_seconds', 15),
        ];
    }

    private function guardSandboxUrl(string $url, string $expectedHost): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if ($scheme !== 'https' || $host !== $expectedHost) {
            throw new RuntimeException('Sandbox spike menolak endpoint provider yang tidak diizinkan.');
        }
    }

    private function guardPublicApplicationUrl(string $url, ?string $expectedPath = null): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);

        if ($scheme !== 'https' || ! is_string($host) || $host === '' || ($expectedPath !== null && $path !== $expectedPath)) {
            throw new RuntimeException('Callback dan return URL sandbox wajib menggunakan HTTPS publik.');
        }

        if (app()->environment('testing')) {
            return;
        }

        $normalizedHost = strtolower($host);
        $reservedSuffixes = ['.example', '.invalid', '.local', '.localhost', '.test'];
        $isReservedName = in_array($normalizedHost, ['localhost'], true);

        foreach ($reservedSuffixes as $suffix) {
            $isReservedName = $isReservedName || str_ends_with($normalizedHost, $suffix);
        }

        $isPublicIp = filter_var($normalizedHost, FILTER_VALIDATE_IP) === false
            || filter_var($normalizedHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;

        if ($isReservedName || ! $isPublicIp) {
            throw new RuntimeException('Callback dan return URL sandbox wajib menggunakan HTTPS publik.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validJson(Response $response): array
    {
        $response->throw();
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Respons Duitku bukan JSON object yang valid.');
        }

        return $payload;
    }

    private function normalizeInquiryStatus(string $statusCode): string
    {
        return match ($statusCode) {
            '00' => 'paid',
            '01' => 'pending',
            '02' => 'failed_or_expired',
            default => 'unknown',
        };
    }

    private function normalizeFee(mixed $fee): ?int
    {
        if ($fee === null || $fee === '') {
            return null;
        }

        if (is_int($fee) && $fee >= 0) {
            return $fee;
        }

        if (! is_string($fee) || preg_match('/^\d+(?:\.0{1,2})?$/', $fee) !== 1) {
            throw new RuntimeException('Fee Duitku bukan nominal integer rupiah yang dapat dinormalisasi.');
        }

        return (int) $fee;
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }
}
