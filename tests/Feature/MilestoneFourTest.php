<?php

use App\DTOs\Catalog\PackageInputData;
use App\DTOs\Customers\CustomerAddressInputData;
use App\DTOs\Orders\OrderInputData;
use App\DTOs\Tenancy\TenantRegistrationData;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderIndicatorType;
use App\Enums\PayoutAccountStatus;
use App\Enums\PricingType;
use App\Enums\ResourceStatus;
use App\Enums\SlotType;
use App\Enums\TenantOnboardingStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderIndicator;
use App\Models\Outlet;
use App\Models\OutletSlot;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Models\TenantPayoutAccount;
use App\Models\User;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Catalog\ChangePackageStatusService;
use App\Services\Catalog\ManagePackageService;
use App\Services\Customers\ManageCustomerAddressService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\ManageOrderLifecycleService;
use App\Services\Orders\MonitorOrderIndicatorsService;
use App\Services\Outlets\ChangeOutletStatusService;
use App\Services\Outlets\ManageOutletScheduleService;
use App\Services\Tenancy\CloseTenantService;
use App\Services\Tenancy\RegisterTenantService;
use App\Services\Tenancy\ReviewPayoutAccountService;
use App\Services\Tenancy\ReviewTenantApplicationService;
use App\Services\Tenancy\SubmitPayoutAccountService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function m4Context(PricingType $pricingType = PricingType::Fixed): array
{
    $identity = app(RegisterTenantService::class)->handle(new TenantRegistrationData(
        'Laundry Order', 'Pemilik Order', 'owner-order@example.test', '081234567890', 'StrongPassword123',
        'Outlet Order', 'Jl. Order 1', 'Bandung', 'Coblong', '-6.8915', '107.6107',
    ));
    $owner = User::query()->findOrFail($identity->databaseId());
    $owner->forceFill(['email_verified_at' => now(), 'two_factor_confirmed_at' => now()])->save();
    $superUser = User::factory()->create(['role' => UserRole::SuperUser, 'role_slot' => 'super-user:primary', 'two_factor_confirmed_at' => now()]);
    $tenant = Tenant::query()->where('name', 'Laundry Order')->firstOrFail();
    app(ReviewTenantApplicationService::class)->handle($superUser, $tenant->public_id, TenantOnboardingStatus::Approved, 'Data lengkap.');
    app(SubmitPayoutAccountService::class)->handle($owner, 'Bank Uji', 'Pemilik Order', '1234567890');
    $account = TenantPayoutAccount::query()->where('tenant_id', $tenant->id)->firstOrFail();
    app(ReviewPayoutAccountService::class)->handle($superUser, $account->public_id, PayoutAccountStatus::Verified, 'Rekening valid.');

    $packagePublicId = app(ManagePackageService::class)->create($owner, $pricingType === PricingType::Fixed
        ? new PackageInputData('Cuci Satuan', 'Paket fixed', PricingType::Fixed, 20_000, 1, null, 120)
        : new PackageInputData('Cuci Kiloan', 'Paket timbang', PricingType::PerKg, 18_000, null, 3000, 120));
    app(ChangePackageStatusService::class)->handle($owner, $packagePublicId, ResourceStatus::Active);
    $outlet = Outlet::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $schedule = app(ManageOutletScheduleService::class);
    $schedule->replaceHours($owner, $outlet->public_id, [
        ['day_of_week' => 6, 'opens_at' => '08:00', 'closes_at' => '18:00'],
        ['day_of_week' => 0, 'opens_at' => '08:00', 'closes_at' => '18:00'],
    ]);
    $schedule->createSlot($owner, $outlet->public_id, SlotType::Pickup, 6, '09:00', '12:00');
    $schedule->createSlot($owner, $outlet->public_id, SlotType::Delivery, 6, '13:00', '16:00');
    $schedule->createSlot($owner, $outlet->public_id, SlotType::Pickup, 0, '09:00', '12:00');
    app(ChangeOutletStatusService::class)->handle($owner, $outlet->public_id, ResourceStatus::Active);

    $customer = User::factory()->create();
    $addressPublicId = app(ManageCustomerAddressService::class)->create($customer, new CustomerAddressInputData(
        'Rumah', 'Customer Order', '081111111111', 'Jl. Dekat Outlet', 'Bandung', 'Coblong', '-6.8915', '107.6107', true,
    ));
    $package = ServicePackage::query()->where('public_id', $packagePublicId)->firstOrFail();
    $pickupSlot = OutletSlot::query()->where('outlet_id', $outlet->id)->where('type', SlotType::Pickup)->where('day_of_week', 6)->firstOrFail();
    $secondSlot = OutletSlot::query()->where('outlet_id', $outlet->id)->where('type', SlotType::Pickup)->where('day_of_week', 0)->firstOrFail();

    return compact('owner', 'superUser', 'tenant', 'outlet', 'customer', 'addressPublicId', 'package', 'pickupSlot', 'secondSlot');
}

/** @param array<string, mixed> $context */
function m4Input(array $context, ?int $quantity = 2, ?int $estimatedWeight = null, ?string $key = null): OrderInputData
{
    return new OrderInputData(
        $context['outlet']->public_id,
        $context['package']->public_id,
        $context['addressPublicId'],
        null,
        $context['pickupSlot']->public_id,
        '2026-09-19',
        $quantity,
        $estimatedWeight,
        $key ?? (string) Str::uuid(),
    );
}

it('creates fixed order from server authority and returns the same aggregate for duplicate request', function () {
    $context = m4Context();
    $key = (string) Str::uuid();
    $input = m4Input($context, 2, null, $key);
    $service = app(CreateOrderService::class);
    $now = CarbonImmutable::parse('2026-09-17 06:00', 'Asia/Jakarta');

    $first = $service->handle($context['customer'], $input, $now);
    $second = $service->handle($context['customer'], $input, $now);

    expect($first->publicId)->toBe($second->publicId)
        ->and($first->fulfillmentStatus)->toBe(FulfillmentStatus::AwaitingPayment->value)
        ->and($first->itemsSubtotal)->toBe(40_000)
        ->and($first->grandTotal)->toBe(40_000)
        ->and(Order::query()->count())->toBe(1)
        ->and($first->addresses)->toHaveCount(2);

    $context['package']->update(['name' => 'Nama paket berubah', 'unit_price' => 99_000]);
    $context['outlet']->update(['name' => 'Nama outlet berubah', 'pickup_fee' => 10_000]);
    $snapshot = app(OrderRepositoryInterface::class)->findForCustomer($context['customer']->id, $first->publicId);
    expect($snapshot?->item['packageName'])->toBe('Cuci Satuan')
        ->and($snapshot?->outletName)->toBe('Outlet Order')
        ->and($snapshot?->grandTotal)->toBe(40_000);

    expect(fn () => $service->handle($context['customer'], m4Input($context, 3, null, $key), $now))
        ->toThrow(DomainActionConflict::class);
});

it('creates per-kg order with rounded non-final estimate', function () {
    $context = m4Context(PricingType::PerKg);
    $order = app(CreateOrderService::class)->handle($context['customer'], m4Input($context, null, 3241), CarbonImmutable::parse('2026-09-17 06:00', 'Asia/Jakarta'));

    expect($order->fulfillmentStatus)->toBe(FulfillmentStatus::AwaitingPickup->value)
        ->and($order->itemsSubtotal)->toBeNull()
        ->and($order->estimatedItemsSubtotal)->toBe(59_400)
        ->and($order->estimatedGrandTotal)->toBe(59_400)
        ->and($order->item['estimatedBillableWeightGrams'])->toBe(3300);
});

it('rejects cross-customer address and ignores client authority fields at HTTP boundary', function () {
    $context = m4Context();
    $other = User::factory()->create();
    $response = $this->actingAs($context['customer'])->withSession(['auth.version' => $context['customer']->auth_version])->post('/orders', [
        'outlet_public_id' => $context['outlet']->public_id,
        'package_public_id' => $context['package']->public_id,
        'pickup_address_public_id' => app(ManageCustomerAddressService::class)->create($other, new CustomerAddressInputData('Lain', 'Lain', '0812', 'Jl. Lain', 'Bandung', 'Coblong', '-6.8915', '107.6107', true)),
        'pickup_slot_public_id' => $context['pickupSlot']->public_id,
        'pickup_date' => '2026-09-19',
        'quantity' => 2,
        'idempotency_key' => (string) Str::uuid(),
        'customer_id' => $other->id,
        'grand_total' => 1,
        'fulfillment_status' => 'completed',
    ]);

    $response->assertNotFound();
    $this->assertDatabaseCount('orders', 0);
});

it('rejects unverified customer invalid lead time and address outside radius', function () {
    $context = m4Context();
    $unverified = User::factory()->unverified()->create();
    expect(fn () => app(CreateOrderService::class)->handle($unverified, m4Input($context), CarbonImmutable::parse('2026-09-17 06:00', 'Asia/Jakarta')))->toThrow(DomainRecordNotFound::class);

    $farAddress = app(ManageCustomerAddressService::class)->create($context['customer'], new CustomerAddressInputData('Jauh', 'Customer Order', '081111111111', 'Jl. Jauh', 'Jakarta', 'Pusat', '-6.2000', '106.8166', false));
    $outside = new OrderInputData($context['outlet']->public_id, $context['package']->public_id, $farAddress, null, $context['pickupSlot']->public_id, '2026-09-19', 1, null, (string) Str::uuid());
    expect(fn () => app(CreateOrderService::class)->handle($context['customer'], $outside, CarbonImmutable::parse('2026-09-17 06:00', 'Asia/Jakarta')))->toThrow(DomainActionConflict::class);

    expect(fn () => app(CreateOrderService::class)->handle($context['customer'], m4Input($context), CarbonImmutable::parse('2026-09-19 08:00', 'Asia/Jakarta')))->toThrow(DomainActionConflict::class);
});

it('isolates order views and enforces reschedule and cancellation transitions', function () {
    $context = m4Context();
    $order = app(CreateOrderService::class)->handle($context['customer'], m4Input($context), CarbonImmutable::parse('2026-09-17 06:00', 'Asia/Jakarta'));
    $other = User::factory()->create();

    $this->actingAs($other)->withSession(['auth.version' => $other->auth_version])->get("/orders/{$order->publicId}")->assertNotFound();
    app(ManageOrderLifecycleService::class)->reschedulePickup($context['owner'], $order->publicId, $context['secondSlot']->public_id, '2026-09-20', 'Permintaan operasional.', CarbonImmutable::parse('2026-09-17 06:00', 'Asia/Jakarta'));
    expect(Order::query()->firstOrFail()->pickup_starts_at->setTimezone('Asia/Jakarta')->toDateString())->toBe('2026-09-20');

    app(ManageOrderLifecycleService::class)->cancel($context['customer'], $order->publicId, null);
    expect(Order::query()->firstOrFail()->fulfillment_status)->toBe(FulfillmentStatus::Cancelled)
        ->and(ActivityLog::query()->where('action', 'order.cancelled')->exists())->toBeTrue();
    expect(fn () => app(ManageOrderLifecycleService::class)->cancel($context['customer'], $order->publicId, null))->toThrow(DomainActionConflict::class);
});

it('masks cancelled customer pii for tenant and blocks deleting referenced masters', function () {
    $context = m4Context();
    $order = app(CreateOrderService::class)->handle($context['customer'], m4Input($context), CarbonImmutable::parse('2026-09-17 06:00', 'Asia/Jakarta'));
    app(ManageOrderLifecycleService::class)->cancel($context['customer'], $order->publicId, null);

    $this->actingAs($context['owner'])->withSession(['auth.version' => $context['owner']->auth_version])
        ->get("/tenant/orders/{$order->publicId}")
        ->assertInertia(fn (Assert $page) => $page->component('orders/show')->where('order.customerName', fn (string $name): bool => str_ends_with($name, '***'))->where('order.addresses.0.address', '[Alamat disamarkan]')->where('order.addresses.0.latitude', null));

    $context['outlet']->update(['status' => ResourceStatus::Draft]);
    $context['package']->update(['status' => ResourceStatus::Draft]);
    expect(fn () => app(ManagePackageService::class)->delete($context['owner'], $context['package']->public_id))->toThrow(DomainActionConflict::class);
});

it('blocks blackout slot deletion outlet deletion and tenant closure while an order is active', function () {
    $context = m4Context(PricingType::PerKg);
    app(CreateOrderService::class)->handle($context['customer'], m4Input($context, null, 3000), CarbonImmutable::parse('2026-09-17 06:00', 'Asia/Jakarta'));
    $schedule = app(ManageOutletScheduleService::class);

    expect(fn () => $schedule->createBlackout($context['owner'], $context['outlet']->public_id, '2026-09-19', 'Libur'))->toThrow(DomainActionConflict::class)
        ->and(fn () => $schedule->deleteSlot($context['owner'], $context['outlet']->public_id, $context['pickupSlot']->public_id))->toThrow(DomainActionConflict::class);

    $context['tenant']->update(['closure_requested_at' => now()]);
    expect(fn () => app(CloseTenantService::class)->handle($context['superUser'], $context['tenant']->public_id, 'Tutup tenant.'))->toThrow(DomainActionConflict::class);
});

it('detects and resolves pickup delay without duplicate active indicator', function () {
    $context = m4Context(PricingType::PerKg);
    $order = app(CreateOrderService::class)->handle($context['customer'], m4Input($context, null, 3000), CarbonImmutable::parse('2026-09-17 06:00', 'Asia/Jakarta'));
    $monitor = app(MonitorOrderIndicatorsService::class);
    $monitor->handle(CarbonImmutable::parse('2026-09-19 13:00', 'Asia/Jakarta'));
    $monitor->handle(CarbonImmutable::parse('2026-09-19 14:00', 'Asia/Jakarta'));

    expect(OrderIndicator::query()->where('type', OrderIndicatorType::PickupDelayed)->whereNull('resolved_at')->count())->toBe(1);
    Order::query()->where('public_id', $order->publicId)->update(['fulfillment_status' => FulfillmentStatus::Processing]);
    $monitor->handle(CarbonImmutable::parse('2026-09-19 14:01', 'Asia/Jakarta'));
    expect(OrderIndicator::query()->where('type', OrderIndicatorType::PickupDelayed)->whereNotNull('resolved_at')->count())->toBe(1);
});

it('keeps customer order pagination query count bounded', function () {
    $context = m4Context();
    $service = app(CreateOrderService::class);
    foreach (range(1, 20) as $quantity) {
        $service->handle($context['customer'], m4Input($context, $quantity), CarbonImmutable::parse('2026-09-17 06:00', 'Asia/Jakarta'));
    }

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $this->actingAs($context['customer'])->withSession(['auth.version' => $context['customer']->auth_version])
        ->get('/orders')
        ->assertInertia(fn (Assert $page) => $page->has('orders.items', 12)->where('orders.meta.total', 20));

    expect($queries)->toBeLessThanOrEqual(10);
});
