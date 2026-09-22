<?php

namespace App\Gateways\Payments;

use App\DTOs\Payments\PaymentInquiryResult;
use App\DTOs\Payments\PaymentInvoiceRequest;
use App\DTOs\Payments\PaymentInvoiceResult;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use RuntimeException;

final class DuitkuGateway implements PaymentGatewayInterface
{
    private const HOSTS = [
        'sandbox' => [
            'create' => 'api-sandbox.duitku.com',
            'inquiry' => 'sandbox.duitku.com',
        ],
        'production' => [
            'create' => 'api-prod.duitku.com',
            'inquiry' => 'passport.duitku.com',
        ],
    ];

    public function __construct(
        private readonly HttpFactory $http,
        private readonly DuitkuSignature $signature,
        private readonly DuitkuCallbackVerifier $callbackVerifier,
    ) {}

    public function createInvoice(PaymentInvoiceRequest $invoice): PaymentInvoiceResult
    {
        $configuration = $this->configuration();
        $url = $configuration['createUrl'];
        $this->guardProviderUrl($url, self::HOSTS[$configuration['environment']]['create']);

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
                'paymentAmount' => $invoice->amount,
                'merchantOrderId' => $invoice->merchantOrderId,
                'productDetails' => 'Klik Laundry order payment',
                'paymentMethod' => $invoice->channelCode,
                'email' => $invoice->customerEmail,
                'callbackUrl' => $invoice->callbackUrl,
                'returnUrl' => $invoice->returnUrl,
                'expiryPeriod' => 60,
            ]);

        $latencyMilliseconds = $this->elapsedMilliseconds($startedAt);
        $payload = $this->validJson($response);

        foreach (['reference', 'paymentUrl', 'statusCode'] as $key) {
            if (! isset($payload[$key]) || ! is_string($payload[$key]) || $payload[$key] === '') {
                throw new RuntimeException('Respons create invoice Duitku tidak memiliki kontrak yang diharapkan.');
            }
        }

        if ($payload['statusCode'] !== '00') {
            throw new RuntimeException('Create invoice Duitku ditolak provider.');
        }

        return new PaymentInvoiceResult(
            merchantOrderId: $invoice->merchantOrderId,
            providerReference: $payload['reference'],
            paymentUrl: $payload['paymentUrl'],
            latencyMilliseconds: $latencyMilliseconds,
            expiresAt: $invoice->expiresAt,
        );
    }

    public function inquire(string $merchantOrderId): PaymentInquiryResult
    {
        $configuration = $this->configuration();
        $url = $configuration['inquiryUrl'];
        $this->guardProviderUrl($url, self::HOSTS[$configuration['environment']]['inquiry']);

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

        foreach (['reference', 'statusCode'] as $key) {
            if (! isset($payload[$key]) || ! is_string($payload[$key]) || $payload[$key] === '') {
                throw new RuntimeException('Respons inquiry Duitku tidak memiliki kontrak yang diharapkan.');
            }
        }

        return new PaymentInquiryResult(
            merchantOrderId: $merchantOrderId,
            providerReference: $payload['reference'],
            status: $this->normalizeInquiryStatus($payload['statusCode']),
            feeAmount: $this->normalizeFee($payload['fee'] ?? null),
            latencyMilliseconds: $latencyMilliseconds,
        );
    }

    public function verifyCallbackSignature(array $payload, string $merchantCode, int $amount, string $merchantOrderId, string $apiKey, ?string $expectedProviderReference = null): bool
    {
        return $this->callbackVerifier->isValid(
            $payload,
            $merchantCode,
            $amount,
            $merchantOrderId,
            $expectedProviderReference,
            $apiKey,
        );
    }

    /**
     * @return array{environment: string, merchantCode: string, apiKey: string, createUrl: string, inquiryUrl: string, connectTimeout: int, timeout: int}
     */
    private function configuration(): array
    {
        $environment = (string) config('services.duitku.environment', 'sandbox');

        if (! isset(self::HOSTS[$environment])) {
            throw new RuntimeException('Environment Duitku tidak dikenali.');
        }

        $merchantCode = config('services.duitku.merchant_code');
        $apiKey = config('services.duitku.api_key');

        if (! is_string($merchantCode) || $merchantCode === '' || ! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Credential Duitku belum dikonfigurasi.');
        }

        return [
            'environment' => $environment,
            'merchantCode' => $merchantCode,
            'apiKey' => $apiKey,
            'createUrl' => (string) config('services.duitku.create_invoice_url'),
            'inquiryUrl' => (string) config('services.duitku.inquiry_url'),
            'connectTimeout' => (int) config('services.duitku.connect_timeout_seconds', 5),
            'timeout' => (int) config('services.duitku.timeout_seconds', 15),
        ];
    }

    private function guardProviderUrl(string $url, string $expectedHost): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if ($scheme !== 'https' || $host !== $expectedHost) {
            throw new RuntimeException('Gateway menolak endpoint provider yang tidak diizinkan.');
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
