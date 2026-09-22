<?php

use App\DTOs\Catalog\PackageInputData;
use App\DTOs\Customers\CustomerAddressInputData;
use App\DTOs\Orders\OrderInputData;
use App\DTOs\Payments\PaymentData;
use App\DTOs\Payments\PaymentInquiryResult;
use App\DTOs\Tenancy\TenantRegistrationData;
use App\Enums\FulfillmentStatus;
use App\Enums\PaymentReconciliation;
use App\Enums\PaymentStatus;
use App\Enums\PayoutAccountStatus;
use App\Enums\PricingType;
use App\Enums\ResourceStatus;
use App\Enums\SlotType;
use App\Enums\TenantOnboardingStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Gateways\Payments\DuitkuSignature;
use App\Gateways\Payments\PaymentGatewayInterface;
use App\Jobs\ExpirePaymentAttemptsJob;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\OutletSlot;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PlatformSetting;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Models\TenantPayoutAccount;
use App\Models\User;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Catalog\ChangePackageStatusService;
use App\Services\Catalog\ManagePackageService;
use App\Services\Customers\ManageCustomerAddressService;
use App\Services\Orders\CreateOrderService;
use App\Services\Outlets\ChangeOutletStatusService;
use App\Services\Outlets\ManageOutletScheduleService;
use App\Services\Payments\CreatePaymentInvoiceService;
use App\Services\Payments\ExpirePaymentAttemptsService;
use App\Services\Payments\InquirePaymentService;
use App\Services\Payments\ManagePaymentChannelsService;
use App\Services\Payments\ProcessDuitkuCallbackService;
use App\Services\Payments\UpdatePaymentMaintenanceService;
use App\Services\Tenancy\RegisterTenantService;
use App\Services\Tenancy\ReviewPayoutAccountService;
use App\Services\Tenancy\ReviewTenantApplicationService;
use App\Services\Tenancy\SubmitPayoutAccountService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function m6Context(PricingType $pricingType = PricingType::Fixed, string $suffix = ''): array
{
    $identity = app(RegisterTenantService::class)->handle(new TenantRegistrationData(
        'Laundry Bayar'.$suffix, 'Pemilik Bayar', 'owner-bayar'.$suffix.'@example.test', '081234567890', 'StrongPassword123',
        'Outlet Bayar', 'Jl. Bayar 1', 'Bandung', 'Coblong', '-6.8915', '107.6107',
    ));
    $owner = User::query()->findOrFail($identity->databaseId());
    $owner->forceFill(['email_verified_at' => now(), 'two_factor_confirmed_at' => now()])->save();
    $superUser = User::factory()->create(['role' => UserRole::SuperUser, 'role_slot' => 'super-user:primary'.$suffix, 'two_factor_confirmed_at' => now()]);
    $tenant = Tenant::query()->where('name', 'Laundry Bayar'.$suffix)->firstOrFail();
    app(ReviewTenantApplicationService::class)->handle($superUser, $tenant->public_id, TenantOnboardingStatus::Approved, 'Data lengkap.');
    app(SubmitPayoutAccountService::class)->handle($owner, 'Bank Uji', 'Pemilik Bayar', '1234567890');
    $account = TenantPayoutAccount::query()->where('tenant_id', $tenant->id)->firstOrFail();
    app(ReviewPayoutAccountService::class)->handle($superUser, $account->public_id, PayoutAccountStatus::Verified, 'Rekening valid.');
    $packagePublicId = app(ManagePackageService::class)->create($owner, $pricingType === PricingType::Fixed
        ? new PackageInputData('Cuci Satuan', 'Paket fixed', PricingType::Fixed, 20_000, 1, null, 120)
        : new PackageInputData('Cuci Kiloan', 'Paket timbang', PricingType::PerKg, 18_000, null, 3000, 120));
    app(ChangePackageStatusService::class)->handle($owner, $packagePublicId, ResourceStatus::Active);
    $outlet = Outlet::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $schedule = app(ManageOutletScheduleService::class);
    $schedule->replaceHours($owner, $outlet->public_id, [['day_of_week' => 6, 'opens_at' => '08:00', 'closes_at' => '18:00']]);
    $schedule->createSlot($owner, $outlet->public_id, SlotType::Pickup, 6, '09:00', '12:00');
    $schedule->createSlot($owner, $outlet->public_id, SlotType::Delivery, 6, '13:00', '16:00');
    app(ChangeOutletStatusService::class)->handle($owner, $outlet->public_id, ResourceStatus::Active);
    $customer = User::factory()->create();
    $addressPublicId = app(ManageCustomerAddressService::class)->create($customer, new CustomerAddressInputData(
        'Rumah', 'Customer Bayar', '081111111111', 'Jl. Dekat Outlet', 'Bandung', 'Coblong', '-6.8915', '107.6107', true,
    ));
    $package = ServicePackage::query()->where('public_id', $packagePublicId)->firstOrFail();
    $pickupSlot = OutletSlot::query()->where('outlet_id', $outlet->id)->where('type', SlotType::Pickup)->firstOrFail();
    $order = app(CreateOrderService::class)->handle(
        $customer,
        new OrderInputData($outlet->public_id, $package->public_id, $addressPublicId, null, $pickupSlot->public_id, '2026-09-19', $pricingType === PricingType::Fixed ? 2 : null, null, (string) Str::uuid()),
        CarbonImmutable::parse('2026-09-17 06:00', 'Asia/Jakarta'),
    );

    config()->set('services.duitku.merchant_code', 'DTEST01');
    config()->set('services.duitku.api_key', 'test-api-key');
    config()->set('services.duitku.callback_url', 'https://callback.example.test/webhooks/duitku');
    config()->set('services.duitku.return_url', 'https://app.example.test/payments/return');
    app(ManagePaymentChannelsService::class)->setActive($superUser, 'NQ', true, 'Aktif untuk pengujian.');

    return compact('owner', 'superUser', 'tenant', 'outlet', 'customer', 'order');
}

/** @return array<string, string> */
function m6CallbackPayload(string $merchantOrderId, int $amount, string $resultCode, string $reference): array
{
    $signature = app(DuitkuSignature::class)->forCallback('DTEST01', (string) $amount, $merchantOrderId, 'test-api-key');

    return [
        'merchantCode' => 'DTEST01',
        'amount' => (string) $amount,
        'merchantOrderId' => $merchantOrderId,
        'reference' => $reference,
        'signature' => $signature,
        'resultCode' => $resultCode,
    ];
}

function m6FakeCreate(string $inquiryStatus = '01', ?int $inquiryFee = null, int $amount = 40_000): void
{
    Http::fakeSequence((string) config('services.duitku.create_invoice_url'))
        ->push(['reference' => 'REF-TEST-1', 'paymentUrl' => 'https://app-sandbox.duitku.com/pay/REF-TEST-1', 'statusCode' => '00'], 200)
        ->push(['reference' => 'REF-TEST-2', 'paymentUrl' => 'https://app-sandbox.duitku.com/pay/REF-TEST-2', 'statusCode' => '00'], 200);
    Http::fake([
        (string) config('services.duitku.inquiry_url') => function ($request) use ($inquiryStatus, $inquiryFee, $amount) {
            return Http::response([
                'merchantOrderId' => $request['merchantOrderId'],
                'reference' => 'REF-TEST-1',
                'amount' => (string) $amount,
                'statusCode' => $inquiryStatus,
                'fee' => $inquiryFee,
            ], 200);
        },
    ]);
}

function m6Invoice(array $context, string $inquiryStatus = '01', ?int $inquiryFee = null): PaymentData
{
    m6FakeCreate($inquiryStatus, $inquiryFee, (int) $context['order']->grandTotal);

    return app(CreatePaymentInvoiceService::class)->handle(
        $context['customer'],
        $context['order']->publicId,
        'NQ',
        CarbonImmutable::parse('2026-09-18 08:00', 'Asia/Jakarta'),
    );
}

it('creates a fixed invoice with a unique merchant order id and sixty minute expiry', function () {
    $context = m6Context();
    $attempt = m6Invoice($context);

    expect($attempt->merchantOrderId)->toStartWith('KL-')
        ->and($attempt->amount)->toBe(40_000)
        ->and($attempt->status)->toBe(PaymentStatus::Pending->value)
        ->and($attempt->providerReference)->toBe('REF-TEST-1')
        ->and($attempt->paymentUrl)->toContain('https://')
        ->and(CarbonImmutable::instance($attempt->expiresAt)->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'))->toBe('2026-09-18 09:00')
        ->and(Payment::query()->where('order_id', $context['order']->id)->count())->toBe(1)
        ->and(Order::query()->where('public_id', $context['order']->publicId)->value('payment_status'))->toBe(PaymentStatus::Pending);
});

it('rejects a second active attempt but allows a new one after expiry', function () {
    $context = m6Context();
    m6Invoice($context);

    expect(fn () => app(CreatePaymentInvoiceService::class)->handle($context['customer'], $context['order']->publicId, 'NQ', CarbonImmutable::parse('2026-09-18 08:30', 'Asia/Jakarta')))
        ->toThrow(DomainActionConflict::class);

    $second = app(CreatePaymentInvoiceService::class)->handle($context['customer'], $context['order']->publicId, 'NQ', CarbonImmutable::parse('2026-09-18 09:01', 'Asia/Jakarta'));

    expect(Payment::query()->where('order_id', $context['order']->id)->count())->toBe(2)
        ->and($second->merchantOrderId)->not->toBe('');
});

it('rejects unknown or inactive channels and payment maintenance mode', function () {
    $context = m6Context();
    $now = CarbonImmutable::parse('2026-09-18 08:00', 'Asia/Jakarta');

    expect(fn () => app(CreatePaymentInvoiceService::class)->handle($context['customer'], $context['order']->publicId, 'XX', $now))
        ->toThrow(DomainActionConflict::class);
    expect(fn () => app(CreatePaymentInvoiceService::class)->handle($context['customer'], $context['order']->publicId, 'OV', $now))
        ->toThrow(DomainActionConflict::class);

    PlatformSetting::query()->where('key', 'global')->update(['payment_maintenance_enabled' => true]);

    expect(fn () => app(CreatePaymentInvoiceService::class)->handle($context['customer'], $context['order']->publicId, 'NQ', $now))
        ->toThrow(DomainActionConflict::class);
});

it('marks uncertain reconciliation when the provider response is lost', function () {
    $context = m6Context();
    Http::fake([(string) config('services.duitku.create_invoice_url') => Http::response(null, 500)]);

    expect(fn () => app(CreatePaymentInvoiceService::class)->handle($context['customer'], $context['order']->publicId, 'NQ', CarbonImmutable::parse('2026-09-18 08:00', 'Asia/Jakarta')))
        ->toThrow(DomainActionConflict::class);

    expect(Payment::query()->where('order_id', $context['order']->id)->value('reconciliation'))->toBe(PaymentReconciliation::NeedsInquiry);
});

it('applies a valid callback once and moves a fixed order to awaiting pickup', function () {
    $context = m6Context();
    $attempt = m6Invoice($context);
    $payload = m6CallbackPayload($attempt->merchantOrderId, 40_000, '00', 'REF-TEST-1');
    $service = app(ProcessDuitkuCallbackService::class);

    expect($service->handle($payload))->toBe('paid')
        ->and($service->handle($payload))->toBe('paid')
        ->and(Payment::query()->where('merchant_order_id', $attempt->merchantOrderId)->value('status'))->toBe(PaymentStatus::Paid)
        ->and(Order::query()->where('public_id', $context['order']->publicId)->value('fulfillment_status'))->toBe(FulfillmentStatus::AwaitingPickup)
        ->and(Order::query()->where('public_id', $context['order']->publicId)->value('payment_status'))->toBe(PaymentStatus::Paid)
        ->and(OrderStatusHistory::query()->where('order_id', $context['order']->id)->where('to_status', FulfillmentStatus::AwaitingPickup)->count())->toBe(1)
        ->and(PaymentEvent::query()->where('payment_id', Payment::query()->where('merchant_order_id', $attempt->merchantOrderId)->value('id'))->count())->toBe(1);
});

it('moves a per-kg order to processing after weight confirmation and provider payment', function () {
    $context = m6Context(PricingType::PerKg);
    Order::query()->where('public_id', $context['order']->publicId)->update([
        'fulfillment_status' => FulfillmentStatus::AwaitingPayment,
        'items_subtotal' => 54_000,
        'grand_total' => 54_000,
    ]);

    $attempt = m6Invoice($context);
    $result = app(ProcessDuitkuCallbackService::class)->handle(m6CallbackPayload($attempt->merchantOrderId, 54_000, '00', 'REF-TEST-1'));

    expect($result)->toBe('paid')
        ->and(Order::query()->where('public_id', $context['order']->publicId)->value('fulfillment_status'))->toBe(FulfillmentStatus::Processing);
});

it('rejects tampered callbacks without mutating payment state', function () {
    $context = m6Context();
    $attempt = m6Invoice($context);
    $payload = m6CallbackPayload($attempt->merchantOrderId, 40_000, '00', 'REF-TEST-1');
    $payload['amount'] = '39000';

    expect(app(ProcessDuitkuCallbackService::class)->handle($payload))->toBe('invalid')
        ->and(Payment::query()->where('merchant_order_id', $attempt->merchantOrderId)->value('status'))->toBe(PaymentStatus::Pending)
        ->and(Order::query()->where('public_id', $context['order']->publicId)->value('payment_status'))->toBe(PaymentStatus::Pending)
        ->and(Payment::query()->where('merchant_order_id', $attempt->merchantOrderId)->value('reconciliation'))->toBe(PaymentReconciliation::Mismatch);
});

it('keeps paid state on out-of-order failure callbacks', function () {
    $context = m6Context();
    $attempt = m6Invoice($context);
    $service = app(ProcessDuitkuCallbackService::class);
    $service->handle(m6CallbackPayload($attempt->merchantOrderId, 40_000, '00', 'REF-TEST-1'));

    expect($service->handle(m6CallbackPayload($attempt->merchantOrderId, 40_000, '01', 'REF-TEST-1')))->toBe('paid')
        ->and(Payment::query()->where('merchant_order_id', $attempt->merchantOrderId)->value('status'))->toBe(PaymentStatus::Paid);
});

it('fails an attempt on provider failure callbacks', function () {
    $context = m6Context();
    $attempt = m6Invoice($context);

    expect(app(ProcessDuitkuCallbackService::class)->handle(m6CallbackPayload($attempt->merchantOrderId, 40_000, '01', 'REF-TEST-1')))->toBe('failed')
        ->and(Payment::query()->where('merchant_order_id', $attempt->merchantOrderId)->value('status'))->toBe(PaymentStatus::Failed);
});

it('applies controlled inquiry results and stores actual fees', function () {
    $context = m6Context();
    $attempt = m6Invoice($context, '00', 1200);

    $result = app(InquirePaymentService::class)->handle($context['superUser'], $attempt->publicId);

    expect($result->status)->toBe(PaymentStatus::Paid->value)
        ->and($result->feeAmount)->toBe(1200)
        ->and(Order::query()->where('public_id', $context['order']->publicId)->value('fulfillment_status'))->toBe(FulfillmentStatus::AwaitingPickup);
});

it('rate limits inquiry in the domain and audits each provider request', function () {
    $context = m6Context();
    $attempt = m6Invoice($context);
    $now = CarbonImmutable::parse('2026-09-18 08:05', 'Asia/Jakarta');
    $service = app(InquirePaymentService::class);

    $service->handle($context['superUser'], $attempt->publicId, $now);

    expect(fn () => $service->handle($context['superUser'], $attempt->publicId, $now->addSeconds(29)))
        ->toThrow(DomainActionConflict::class);

    $service->handle($context['superUser'], $attempt->publicId, $now->addSeconds(30));

    expect(ActivityLog::query()->where('action', 'payment.inquiry_requested')->count())->toBe(2)
        ->and(Payment::query()->where('id', $attempt->id)->value('last_inquired_at'))->not->toBeNull();
});

it('keeps tenant and customer isolation on payment boundaries', function () {
    $context = m6Context();
    $other = User::factory()->create();
    $now = CarbonImmutable::parse('2026-09-18 08:00', 'Asia/Jakarta');

    expect(fn () => app(CreatePaymentInvoiceService::class)->handle($other, $context['order']->publicId, 'NQ', $now))
        ->toThrow(DomainRecordNotFound::class);
    expect(fn () => app(CreatePaymentInvoiceService::class)->handle($context['owner'], $context['order']->publicId, 'NQ', $now))
        ->toThrow(DomainRecordNotFound::class);

    $this->actingAs($other)->withSession(['auth.version' => $other->auth_version])
        ->get("/payments/{$context['order']->publicId}")
        ->assertNotFound();
});

it('renders checkout and receipt without provider secrets', function () {
    $context = m6Context();
    $attempt = m6Invoice($context);

    $this->actingAs($context['customer'])->withSession(['auth.version' => $context['customer']->auth_version])
        ->get("/orders/{$context['order']->publicId}/payments/create")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('payments/create')
            ->where('order.grandTotal', 40_000)
            ->has('channels', 1));

    $this->actingAs($context['customer'])->withSession(['auth.version' => $context['customer']->auth_version])
        ->get("/payments/{$attempt->publicId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('payments/show')
            ->where('payment.status', PaymentStatus::Pending->value)
            ->where('payment.paymentUrl', fn (mixed $value): bool => is_string($value)));

    app(ProcessDuitkuCallbackService::class)->handle(m6CallbackPayload($attempt->merchantOrderId, 40_000, '00', 'REF-TEST-1'));

    $this->actingAs($context['customer'])->withSession(['auth.version' => $context['customer']->auth_version])
        ->get("/payments/{$attempt->publicId}/receipt")
        ->assertOk()
        ->assertDontSee('signature', false)
        ->assertDontSee('test-api-key', false);
});

it('accepts provider callbacks without csrf while return urls never mutate', function () {
    $context = m6Context();
    $attempt = m6Invoice($context);

    $this->post('/webhooks/duitku', m6CallbackPayload($attempt->merchantOrderId, 40_000, '00', 'REF-TEST-1'))
        ->assertOk()
        ->assertExactJson(['received' => true]);

    expect(Payment::query()->where('merchant_order_id', $attempt->merchantOrderId)->value('status'))->toBe(PaymentStatus::Paid);

    $second = m6Context(PricingType::Fixed, '-dua');
    $pending = m6Invoice($second);
    $before = Payment::query()->where('merchant_order_id', $pending->merchantOrderId)->value('status');

    $this->actingAs($second['customer'])->withSession(['auth.version' => $second['customer']->auth_version])
        ->get("/payments/{$pending->publicId}");

    expect(Payment::query()->where('merchant_order_id', $pending->merchantOrderId)->value('status'))->toBe($before);
});

it('expires overdue attempts through the scheduled job', function () {
    $context = m6Context();
    $attempt = m6Invoice($context);
    $this->travelTo(CarbonImmutable::parse('2026-09-18 08:30', 'Asia/Jakarta'));

    app(ExpirePaymentAttemptsJob::class)->handle(app(ExpirePaymentAttemptsService::class));

    expect(Payment::query()->where('merchant_order_id', $attempt->merchantOrderId)->value('status'))->toBe(PaymentStatus::Pending);

    $this->travelTo(CarbonImmutable::parse('2026-09-18 09:01', 'Asia/Jakarta'));
    app(ExpirePaymentAttemptsJob::class)->handle(app(ExpirePaymentAttemptsService::class));

    expect(Payment::query()->where('merchant_order_id', $attempt->merchantOrderId)->value('status'))->toBe(PaymentStatus::Expired)
        ->and(Payment::query()->where('merchant_order_id', $attempt->merchantOrderId)->value('active_order_key'))->toBeNull()
        ->and(Order::query()->where('public_id', $context['order']->publicId)->value('payment_status'))->toBe(PaymentStatus::Expired);
});

it('enforces one active payment attempt per order at database level', function () {
    $context = m6Context();
    $attempt = m6Invoice($context);

    expect(fn () => app(PaymentRepositoryInterface::class)->createAttempt(
        $context['tenant']->id,
        $context['order']->id,
        'KL-'.Str::ulid(),
        'NQ',
        40_000,
        CarbonImmutable::instance($attempt->expiresAt)->addHour(),
    ))->toThrow(QueryException::class);
});

it('sends only required customer identity to the provider', function () {
    $context = m6Context();
    m6Invoice($context);

    Http::assertSent(function ($request) use ($context): bool {
        return $request->url() === config('services.duitku.create_invoice_url')
            && $request['customerVaName'] === mb_substr($context['customer']->name, 0, 20)
            && $request['email'] === $context['customer']->email
            && ! array_key_exists('phoneNumber', $request->data())
            && ! array_key_exists('customerDetail', $request->data())
            && $request->hasHeader('x-duitku-signature');
    });

});

it('rejects a payment url outside the Duitku allowlist', function () {
    $context = m6Context();
    Http::fake([(string) config('services.duitku.create_invoice_url') => Http::response([
        'reference' => 'REF-HOST',
        'paymentUrl' => 'https://evil.example.test/pay/REF-HOST',
        'statusCode' => '00',
    ])]);

    expect(fn () => app(CreatePaymentInvoiceService::class)->handle($context['customer'], $context['order']->publicId, 'NQ', CarbonImmutable::parse('2026-09-18 08:00', 'Asia/Jakarta')))
        ->toThrow(DomainActionConflict::class);
    expect(Payment::query()->where('order_id', $context['order']->id)->value('provider_payment_url'))->toBeNull();
});

it('keeps failed and expired attempts terminal when a late paid callback arrives', function () {
    $context = m6Context();
    $attempt = m6Invoice($context);
    $service = app(ProcessDuitkuCallbackService::class);
    $service->handle(m6CallbackPayload($attempt->merchantOrderId, 40_000, '01', 'REF-TEST-1'));

    expect($service->handle(m6CallbackPayload($attempt->merchantOrderId, 40_000, '00', 'REF-TEST-1')))->toBe('ignored')
        ->and(Payment::query()->where('merchant_order_id', $attempt->merchantOrderId)->value('status'))->toBe(PaymentStatus::Failed)
        ->and(Payment::query()->where('merchant_order_id', $attempt->merchantOrderId)->value('reconciliation'))->toBe(PaymentReconciliation::Mismatch);
});

it('enriches fee on an already paid attempt', function () {
    $context = m6Context();
    $attempt = m6Invoice($context);
    app(ProcessDuitkuCallbackService::class)->handle(m6CallbackPayload($attempt->merchantOrderId, 40_000, '00', 'REF-TEST-1'));
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('inquire')->once()->andReturn(new PaymentInquiryResult($attempt->merchantOrderId, 'REF-TEST-1', 40_000, 'paid', 1200, 10));
    app()->instance(PaymentGatewayInterface::class, $gateway);
    $paid = app(InquirePaymentService::class)->handle($context['superUser'], $attempt->publicId);
    expect($paid->feeAmount)->toBe(1200);
});

it('rejects an inquiry identity mismatch', function () {
    $context = m6Context();
    $mismatched = m6Invoice($context);
    $mismatchGateway = Mockery::mock(PaymentGatewayInterface::class);
    $mismatchGateway->shouldReceive('inquire')->once()->andReturn(new PaymentInquiryResult($mismatched->merchantOrderId, 'REF-TEST-1', 39_999, 'paid', 0, 10));
    app()->instance(PaymentGatewayInterface::class, $mismatchGateway);
    expect(fn () => app(InquirePaymentService::class)->handle($context['superUser'], $mismatched->publicId))->toThrow(DomainActionConflict::class);
    expect(Payment::query()->where('merchant_order_id', $mismatched->merchantOrderId)->value('reconciliation'))->toBe(PaymentReconciliation::Mismatch);
});

it('treats browser return and json callback as non-authoritative', function () {
    $context = m6Context();
    $attempt = m6Invoice($context);
    $payload = m6CallbackPayload($attempt->merchantOrderId, 40_000, '00', 'REF-TEST-1');

    $this->postJson('/webhooks/duitku', $payload)->assertOk()->assertExactJson(['received' => true]);
    expect(Payment::query()->where('merchant_order_id', $attempt->merchantOrderId)->value('status'))->toBe(PaymentStatus::Pending);

    $this->post('/webhooks/duitku', ['merchantOrderId' => [$attempt->merchantOrderId], 'amount' => 'invalid'])
        ->assertOk()
        ->assertExactJson(['received' => true]);
    expect(Payment::query()->where('merchant_order_id', $attempt->merchantOrderId)->value('status'))->toBe(PaymentStatus::Pending);

    $this->actingAs($context['customer'])->withSession(['auth.version' => $context['customer']->auth_version])
        ->get('/payments/return?merchantOrderId='.$attempt->merchantOrderId.'&resultCode=00')
        ->assertRedirect(route('payments.show', $attempt->publicId));
    expect(Payment::query()->where('merchant_order_id', $attempt->merchantOrderId)->value('status'))->toBe(PaymentStatus::Pending);

    $other = User::factory()->create();
    $this->actingAs($other)->withSession(['auth.version' => $other->auth_version])
        ->get('/payments/return?merchantOrderId='.$attempt->merchantOrderId)
        ->assertNotFound();
});

it('keeps payment urls out of tenant and support props', function () {
    $context = m6Context();
    m6Invoice($context);

    $this->actingAs($context['owner'])->withSession(['auth.version' => $context['owner']->auth_version])
        ->get('/tenant/payments')->assertInertia(fn (Assert $page) => $page->component('tenant/payments')->missing('items.0.paymentUrl'));
    $this->actingAs($context['superUser'])->withSession(['auth.version' => $context['superUser']->auth_version])
        ->get('/super-user/payments')->assertInertia(fn (Assert $page) => $page->component('super-user/payments')->missing('items.0.paymentUrl'));
});

it('audits payment channel and maintenance controls without secrets', function () {
    $context = m6Context();
    app(ManagePaymentChannelsService::class)->setActive($context['superUser'], 'NQ', false, 'Channel sedang ditinjau.');
    app(UpdatePaymentMaintenanceService::class)->handle($context['superUser'], true, 'Pemeliharaan provider terjadwal.');

    expect(PlatformSetting::query()->where('key', 'global')->value('payment_maintenance_enabled'))->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'payment.channel_updated')->where('reason', 'Channel sedang ditinjau.')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'payment.maintenance_updated')->value('reason'))->toBe('Pemeliharaan provider terjadwal.');
    $auditPayload = ActivityLog::query()->whereIn('action', ['payment.channel_updated', 'payment.maintenance_updated'])->get()->toJson();
    expect($auditPayload)->not->toContain('test-api-key')->not->toContain('paymentUrl')->not->toContain('signature');
});

it('keeps production gateway disabled without an explicit release switch', function () {
    $context = m6Context();
    config()->set('services.duitku.environment', 'production');
    config()->set('services.duitku.production_enabled', false);
    config()->set('services.duitku.create_invoice_url', 'https://api-prod.duitku.com/api/merchant/createInvoice');
    Http::fake();

    expect(fn () => app(CreatePaymentInvoiceService::class)->handle($context['customer'], $context['order']->publicId, 'NQ', CarbonImmutable::parse('2026-09-18 08:00', 'Asia/Jakarta')))
        ->toThrow(DomainActionConflict::class);
    Http::assertNothingSent();
});
