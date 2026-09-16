<?php

use App\Enums\SandboxPaymentStatus;
use App\Gateways\Payments\DuitkuCallbackVerifier;
use App\Gateways\Payments\DuitkuSignature;
use App\Services\Payments\RunDuitkuSandboxSpikeService;
use App\Services\Payments\SimulatePaymentStatusTransitionService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.duitku', [
        'environment' => 'sandbox',
        'merchant_code' => 'DTEST',
        'api_key' => 'sandbox-test-api-key-not-real',
        'callback_url' => 'https://sandbox-callback.example.test/duitku',
        'return_url' => 'https://sandbox-callback.example.test/return',
        'create_invoice_url' => 'https://api-sandbox.duitku.com/api/merchant/createInvoice',
        'inquiry_url' => 'https://sandbox.duitku.com/webapi/api/merchant/transactionStatus',
        'connect_timeout_seconds' => 2,
        'timeout_seconds' => 5,
    ]);
});

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
    $result = app(RunDuitkuSandboxSpikeService::class)->create(10000, 'NQ', 'sandbox@example.test');
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

    $result = app(RunDuitkuSandboxSpikeService::class)->inquire($merchantOrderId);

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

    expect(fn () => app(RunDuitkuSandboxSpikeService::class)->create(10000, 'NQ', 'sandbox@example.test'))
        ->toThrow(RuntimeException::class, 'Sandbox spike menolak endpoint provider yang tidak diizinkan.');

    Http::assertNothingSent();
});

it('rejects a provider environment other than sandbox', function () {
    config()->set('services.duitku.environment', 'production');
    Http::fake();

    expect(fn () => app(RunDuitkuSandboxSpikeService::class)->create(10000, 'NQ', 'sandbox@example.test'))
        ->toThrow(RuntimeException::class, 'Sandbox spike menolak environment selain sandbox.');

    Http::assertNothingSent();
});

it('does not retry an uncertain create response', function () {
    Http::fake([
        'api-sandbox.duitku.com/*' => Http::failedConnection('simulated timeout'),
    ]);

    expect(fn () => app(RunDuitkuSandboxSpikeService::class)->create(10000, 'NQ', 'sandbox@example.test'))
        ->toThrow(ConnectionException::class);

    Http::assertSentCount(1);
});

it('rejects malformed provider responses and fractional rupiah fee data', function () {
    $merchantOrderId = 'KL-SBX-01JQTEST000000000000000000';

    Http::fake([
        'sandbox.duitku.com/*' => Http::response([
            'reference' => 'DTEST-REFERENCE-123456',
            'fee' => '750.25',
            'statusCode' => '00',
        ]),
    ]);

    expect(fn () => app(RunDuitkuSandboxSpikeService::class)->inquire($merchantOrderId))
        ->toThrow(RuntimeException::class, 'Fee Duitku bukan nominal integer rupiah yang dapat dinormalisasi.');
});

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
    'expired can reconcile to paid' => [SandboxPaymentStatus::Expired, SandboxPaymentStatus::Paid, SandboxPaymentStatus::Paid],
]);
