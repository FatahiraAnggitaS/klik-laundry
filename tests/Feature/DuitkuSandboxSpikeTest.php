<?php

use App\DTOs\Payments\PaymentData;
use App\Enums\PaymentStatus;
use App\Enums\SandboxPaymentStatus;
use App\Gateways\Payments\DuitkuCallbackVerifier;
use App\Gateways\Payments\DuitkuSignature;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Payments\RunDuitkuSandboxSpikeService;
use App\Services\Payments\SimulatePaymentStatusTransitionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.duitku', [
        'environment' => 'sandbox',
        'production_enabled' => false,
        'merchant_code' => 'DTEST',
        'api_key' => 'sandbox-test-api-key-not-real',
        'callback_url' => 'https://sandbox-callback.example.test/webhooks/duitku',
        'return_url' => 'https://sandbox-callback.example.test/payments/return',
        'create_invoice_url' => 'https://api-sandbox.duitku.com/api/merchant/createInvoice',
        'inquiry_url' => 'https://sandbox.duitku.com/webapi/api/merchant/transactionStatus',
        'connect_timeout_seconds' => 2,
        'timeout_seconds' => 5,
    ]);
});

/** @return array<string, mixed> */
function validMilestoneZeroEvidenceManifest(): array
{
    $contents = file_get_contents(base_path('docs/milestone-0/evidence-manifest.example.json'));
    if (! is_string($contents)) {
        throw new RuntimeException('Fixture evidence manifest tidak dapat dibaca.');
    }

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

    /** @var array<string, array<string, mixed>> $decisions */
    $decisions = $manifest['decisions'];
    foreach ($decisions as $decisionId => $decision) {
        $decisions[$decisionId]['reviewedAt'] = '2026-09-22';
        $decisions[$decisionId]['evidenceReference'] = 'EXT-'.$decisionId;
    }
    $manifest['decisions'] = $decisions;
    $manifest['retention'] = [
        'financialRecordsPeriod' => 'approved financial retention period',
        'piiPeriod' => 'approved PII retention period',
        'lawfulBasisReference' => 'EXT-RETENTION-LAWFUL-BASIS',
        'deletionRule' => 'approved deletion rule',
        'anonymizationRule' => 'approved anonymization rule',
    ];
    $manifest['staging'] = [
        'reference' => 'EXT-STAGING-001',
        'operatorRole' => 'Engineering',
        'callbackUrl' => config('services.duitku.callback_url'),
        'returnUrl' => config('services.duitku.return_url'),
    ];

    return $manifest;
}

it('creates a sandbox invoice with a unique id, hmac header, and sixty minute expiry', function () {
    Http::fake([
        'api-sandbox.duitku.com/*' => Http::response([
            'merchantCode' => 'DTEST',
            'reference' => 'DTEST-REFERENCE-123456',
            'paymentUrl' => 'https://app-sandbox.duitku.com/redirect_checkout?reference=masked-test',
            'statusCode' => '00',
            'statusMessage' => 'SUCCESS',
        ]),
    ]);

    $before = now('UTC')->addMinutes(59);
    $result = app(RunDuitkuSandboxSpikeService::class)->create(10000, 'NQ', 'Sandbox Customer', 'sandbox@example.test');
    $after = now('UTC')->addMinutes(61);

    expect($result->merchantOrderId)
        ->toStartWith('KL-SBX-')
        ->and($result->status)->toBe('created')
        ->and($result->expiresAt->getTimestamp())->toBeBetween($before->getTimestamp(), $after->getTimestamp());

    Http::assertSent(function (Request $request) use ($result): bool {
        $timestamp = $request->header('x-duitku-timestamp')[0] ?? '';
        $expectedSignature = hash_hmac('sha256', 'DTEST'.$timestamp, 'sandbox-test-api-key-not-real');

        return $request->url() === 'https://api-sandbox.duitku.com/api/merchant/createInvoice'
            && $request->header('x-duitku-merchantcode')[0] === 'DTEST'
            && $request->header('x-duitku-signature')[0] === $expectedSignature
            && $request['merchantOrderId'] === $result->merchantOrderId
            && $request['paymentAmount'] === 10000
            && $request['paymentMethod'] === 'NQ'
            && $request['customerVaName'] === 'Sandbox Customer'
            && $request['expiryPeriod'] === 60
            && ! array_key_exists('phoneNumber', $request->data())
            && ! array_key_exists('customerDetail', $request->data());
    });
});

it('normalizes inquiry status and an integral fee without retrying', function () {
    $merchantOrderId = 'KL-SBX-01JQTEST000000000000000000';

    Http::fake([
        'sandbox.duitku.com/*' => Http::response([
            'merchantOrderId' => $merchantOrderId,
            'reference' => 'DTEST-REFERENCE-123456',
            'amount' => '10000',
            'fee' => '750.00',
            'statusCode' => '00',
            'statusMessage' => 'SUCCESS',
        ]),
    ]);

    $result = app(RunDuitkuSandboxSpikeService::class)->inquire($merchantOrderId, 10000, 'DTEST-REFERENCE-123456');

    expect($result->status)->toBe('paid')
        ->and($result->feeAmount)->toBe(750);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($merchantOrderId): bool {
        $expectedSignature = hash_hmac('sha256', 'DTEST'.$merchantOrderId, 'sandbox-test-api-key-not-real');

        return $request['merchantOrderId'] === $merchantOrderId
            && $request['signature'] === $expectedSignature;
    });
});

it('rejects non-sandbox provider hosts before sending a request', function () {
    config()->set('services.duitku.create_invoice_url', 'https://api-prod.duitku.com/api/merchant/createInvoice');
    Http::fake();

    expect(fn () => app(RunDuitkuSandboxSpikeService::class)->create(10000, 'NQ', 'Sandbox Customer', 'sandbox@example.test'))
        ->toThrow(RuntimeException::class, 'Sandbox spike menolak endpoint provider yang tidak diizinkan.');

    Http::assertNothingSent();
});

it('rejects a provider environment other than sandbox', function () {
    config()->set('services.duitku.environment', 'production');
    Http::fake();

    expect(fn () => app(RunDuitkuSandboxSpikeService::class)->create(10000, 'NQ', 'Sandbox Customer', 'sandbox@example.test'))
        ->toThrow(RuntimeException::class, 'Sandbox spike menolak environment selain sandbox.');

    Http::assertNothingSent();
});

it('fails preflight when production payment is enabled or the callback contract is invalid', function () {
    $manifest = validMilestoneZeroEvidenceManifest();
    Http::fake();

    config()->set('services.duitku.production_enabled', true);
    expect(fn () => app(RunDuitkuSandboxSpikeService::class)->preflight($manifest))
        ->toThrow(RuntimeException::class, 'Sandbox spike menolak production payment flag yang aktif.');

    config()->set('services.duitku.production_enabled', false);
    config()->set('services.duitku.callback_url', 'https://sandbox-callback.example.test/wrong-path');
    $manifest['staging']['callbackUrl'] = config('services.duitku.callback_url');

    expect(fn () => app(RunDuitkuSandboxSpikeService::class)->preflight($manifest))
        ->toThrow(RuntimeException::class, 'Callback dan return URL sandbox wajib menggunakan HTTPS publik.');

    Http::assertNothingSent();
});

it('does not retry an uncertain create response', function () {
    Http::fake([
        'api-sandbox.duitku.com/*' => Http::failedConnection('simulated timeout'),
    ]);

    expect(fn () => app(RunDuitkuSandboxSpikeService::class)->create(10000, 'NQ', 'Sandbox Customer', 'sandbox@example.test'))
        ->toThrow(ConnectionException::class);

    Http::assertSentCount(1);
});

it('rejects malformed provider responses and fractional rupiah fee data', function () {
    $merchantOrderId = 'KL-SBX-01JQTEST000000000000000000';

    Http::fake([
        'sandbox.duitku.com/*' => Http::response([
            'merchantOrderId' => $merchantOrderId,
            'reference' => 'DTEST-REFERENCE-123456',
            'amount' => '10000',
            'fee' => '750.25',
            'statusCode' => '00',
        ]),
    ]);

    expect(fn () => app(RunDuitkuSandboxSpikeService::class)->inquire($merchantOrderId, 10000, 'DTEST-REFERENCE-123456'))
        ->toThrow(RuntimeException::class, 'Fee Duitku bukan nominal integer rupiah yang dapat dinormalisasi.');
});

it('rejects create payment urls outside the sandbox allowlist', function () {
    Http::fake([
        'api-sandbox.duitku.com/*' => Http::response([
            'merchantCode' => 'DTEST',
            'reference' => 'DTEST-REFERENCE-123456',
            'paymentUrl' => 'https://example.invalid/payment',
            'statusCode' => '00',
        ]),
    ]);

    expect(fn () => app(RunDuitkuSandboxSpikeService::class)->create(10000, 'NQ', 'Sandbox Customer', 'sandbox@example.test'))
        ->toThrow(RuntimeException::class, 'Sandbox spike menolak endpoint provider yang tidak diizinkan.');
});

it('rejects an inquiry identity mismatch', function () {
    $merchantOrderId = 'KL-SBX-01JQTEST000000000000000000';
    Http::fake([
        'sandbox.duitku.com/*' => Http::response([
            'merchantOrderId' => $merchantOrderId,
            'reference' => 'DIFFERENT-REFERENCE',
            'amount' => '10000',
            'statusCode' => '00',
        ]),
    ]);

    expect(fn () => app(RunDuitkuSandboxSpikeService::class)->inquire($merchantOrderId, 10000, 'DTEST-REFERENCE-123456'))
        ->toThrow(RuntimeException::class, 'Identitas inquiry Duitku tidak cocok dengan invoice yang diharapkan.');
});

it('validates the private evidence manifest without exposing sensitive fields', function () {
    $result = app(RunDuitkuSandboxSpikeService::class)->preflight(validMilestoneZeroEvidenceManifest());

    expect($result->decisionCount)->toBe(14)
        ->and($result->reviewedAt)->toBe('2026-09-22')
        ->and($result->stagingReference)->toBe('EXT-STAGING-001');

    $unsafe = validMilestoneZeroEvidenceManifest();
    $unsafe['api_key'] = 'forbidden';

    expect(fn () => app(RunDuitkuSandboxSpikeService::class)->preflight($unsafe))
        ->toThrow(InvalidArgumentException::class, 'Evidence manifest memuat field sensitif yang dilarang.');

    $unsafeReference = validMilestoneZeroEvidenceManifest();
    $unsafeReference['staging']['reference'] = 'https://evidence.example.test/run?token=forbidden';

    expect(fn () => app(RunDuitkuSandboxSpikeService::class)->preflight($unsafeReference))
        ->toThrow(InvalidArgumentException::class, 'Evidence manifest staging tidak lengkap atau berbeda dari konfigurasi runtime.');
});

it('posts guarded duplicate and out-of-order callbacks only for paid sandbox payments', function (string $scenario, int $expectedRequests, string $expectedResultCode) {
    $payment = new PaymentData(
        id: 1,
        publicId: '01K5SANDBOXPAYMENT0000000000',
        tenantId: 1,
        orderId: 1,
        orderPublicId: '01K5SANDBOXORDER00000000000',
        orderNumber: 'KL-20260922-SANDBOX',
        merchantOrderId: 'KL-SBX-01JQTEST000000000000000000',
        providerReference: 'DTEST-REFERENCE-123456',
        channelCode: 'NQ',
        channelLabel: 'QRIS Sandbox',
        amount: 10000,
        status: PaymentStatus::Paid->value,
        reconciliation: 'matched',
        paymentUrl: null,
        feeAmount: 750,
        expiresAt: CarbonImmutable::parse('2026-09-22 10:00:00', 'UTC'),
        paidAt: CarbonImmutable::parse('2026-09-22 09:05:00', 'UTC'),
    );
    $repository = Mockery::mock(PaymentRepositoryInterface::class);
    $repository->shouldReceive('findByPublicIdForSupport')->twice()->with($payment->publicId)->andReturn($payment);
    app()->instance(PaymentRepositoryInterface::class, $repository);
    Http::fake([
        'sandbox-callback.example.test/*' => Http::response(['received' => true]),
    ]);

    $result = app(RunDuitkuSandboxSpikeService::class)->simulateCallback($payment->publicId, $scenario);

    expect($result->statusBefore)->toBe(PaymentStatus::Paid->value)
        ->and($result->statusAfter)->toBe(PaymentStatus::Paid->value)
        ->and($result->requestCount)->toBe($expectedRequests);
    Http::assertSentCount($expectedRequests);
    Http::assertSent(fn (Request $request): bool => $request['resultCode'] === $expectedResultCode
        && $request->url() === config('services.duitku.callback_url')
        && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded'));
})->with([
    'duplicate paid callback' => ['duplicate', 2, '00'],
    'out-of-order failure after paid' => ['out-of-order', 1, '01'],
]);

it('validates callback identity, amount, reference, and hmac without mutating state', function () {
    $signature = app(DuitkuSignature::class);
    $verifier = app(DuitkuCallbackVerifier::class);
    $payload = [
        'merchantCode' => 'DTEST',
        'amount' => '10000',
        'merchantOrderId' => 'KL-SBX-01JQTEST000000000000000000',
        'reference' => 'DTEST-REFERENCE-123456',
        'resultCode' => '00',
        'signature' => $signature->forCallback(
            'DTEST',
            '10000',
            'KL-SBX-01JQTEST000000000000000000',
            'sandbox-test-api-key-not-real',
        ),
    ];

    expect($verifier->isValid(
        $payload,
        'DTEST',
        10000,
        'KL-SBX-01JQTEST000000000000000000',
        'DTEST-REFERENCE-123456',
        'sandbox-test-api-key-not-real',
    ))->toBeTrue();

    foreach (['merchantCode', 'amount', 'merchantOrderId', 'reference', 'signature'] as $field) {
        $tampered = $payload;
        $tampered[$field] = 'tampered';

        expect($verifier->isValid(
            $tampered,
            'DTEST',
            10000,
            'KL-SBX-01JQTEST000000000000000000',
            'DTEST-REFERENCE-123456',
            'sandbox-test-api-key-not-real',
        ))->toBeFalse();
    }

    $unsupportedResult = $payload;
    $unsupportedResult['resultCode'] = '99';

    expect($verifier->isValid(
        $unsupportedResult,
        'DTEST',
        10000,
        'KL-SBX-01JQTEST000000000000000000',
        'DTEST-REFERENCE-123456',
        'sandbox-test-api-key-not-real',
    ))->toBeFalse();
});

it('keeps simulated payment transitions monotonic for duplicate and out-of-order events', function (
    SandboxPaymentStatus $current,
    SandboxPaymentStatus $incoming,
    SandboxPaymentStatus $expected,
) {
    $result = app(SimulatePaymentStatusTransitionService::class)->apply($current, $incoming);

    expect($result)->toBe($expected);
})->with([
    'pending to paid' => [SandboxPaymentStatus::Pending, SandboxPaymentStatus::Paid, SandboxPaymentStatus::Paid],
    'duplicate paid' => [SandboxPaymentStatus::Paid, SandboxPaymentStatus::Paid, SandboxPaymentStatus::Paid],
    'paid ignores late failure' => [SandboxPaymentStatus::Paid, SandboxPaymentStatus::Failed, SandboxPaymentStatus::Paid],
    'paid ignores late expiry' => [SandboxPaymentStatus::Paid, SandboxPaymentStatus::Expired, SandboxPaymentStatus::Paid],
    'failed ignores late pending' => [SandboxPaymentStatus::Failed, SandboxPaymentStatus::Pending, SandboxPaymentStatus::Failed],
    'expired ignores late paid' => [SandboxPaymentStatus::Expired, SandboxPaymentStatus::Paid, SandboxPaymentStatus::Expired],
]);
