<?php

use App\DTOs\Catalog\PackageInputData;
use App\DTOs\Customers\CustomerAddressInputData;
use App\DTOs\Orders\OrderInputData;
use App\DTOs\Tenancy\TenantRegistrationData;
use App\Enums\DriverAvailability;
use App\Enums\DriverTaskStatus;
use App\Enums\DriverTaskType;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderIndicatorType;
use App\Enums\PaymentStatus;
use App\Enums\PayoutAccountStatus;
use App\Enums\PricingType;
use App\Enums\ResourceStatus;
use App\Enums\SlotType;
use App\Enums\TenantOnboardingStatus;
use App\Enums\TenantOperationalStatus;
use App\Enums\UserRole;
use App\Events\DispatchLifecycleEvent;
use App\Events\UserActivityBroadcast;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Listeners\PersistAndBroadcastLifecycleNotification;
use App\Models\DeliveryTask;
use App\Models\DriverCommission;
use App\Models\DriverTaskHistory;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\OutletSlot;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Models\TenantPayoutAccount;
use App\Models\User;
use App\Repositories\Contracts\DriverRepositoryInterface;
use App\Services\Catalog\ChangePackageStatusService;
use App\Services\Catalog\ManagePackageService;
use App\Services\Customers\ManageCustomerAddressService;
use App\Services\Dispatch\GetDriverTaskDashboardService;
use App\Services\Dispatch\GetPrivateProofUrlService;
use App\Services\Dispatch\ManageDriverService;
use App\Services\Dispatch\OfferDriverTaskService;
use App\Services\Dispatch\ProgressDriverTaskService;
use App\Services\Dispatch\RespondDriverOfferService;
use App\Services\Dispatch\RevokeExpiredProofAccessService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\ManageDeliveryLifecycleService;
use App\Services\Orders\MonitorOrderIndicatorsService;
use App\Services\Outlets\ChangeOutletStatusService;
use App\Services\Outlets\ManageOutletScheduleService;
use App\Services\Tenancy\RegisterTenantService;
use App\Services\Tenancy\ReviewPayoutAccountService;
use App\Services\Tenancy\ReviewTenantApplicationService;
use App\Services\Tenancy\SubmitPayoutAccountService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function m7Context(PricingType $pricingType = PricingType::PerKg): array
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-20 08:00', 'Asia/Jakarta'));
    $suffix = $pricingType->value;
    $identity = app(RegisterTenantService::class)->handle(new TenantRegistrationData(
        'Laundry M7 '.$suffix, 'Pemilik M7', "owner-m7-{$suffix}@example.test", '081234567890', 'StrongPassword123',
        'Outlet M7', 'Jl. M7 1', 'Bandung', 'Coblong', '-6.8915', '107.6107',
    ));
    $owner = User::query()->findOrFail($identity->databaseId());
    $owner->forceFill(['email_verified_at' => now(), 'two_factor_confirmed_at' => now()])->save();
    $superUser = User::factory()->create(['role' => UserRole::SuperUser, 'role_slot' => 'super-user:'.$suffix, 'two_factor_confirmed_at' => now()]);
    $tenant = Tenant::query()->where('name', 'Laundry M7 '.$suffix)->firstOrFail();
    app(ReviewTenantApplicationService::class)->handle($superUser, $tenant->public_id, TenantOnboardingStatus::Approved, 'Data lengkap.');
    app(SubmitPayoutAccountService::class)->handle($owner, 'Bank Uji', 'Pemilik M7', '1234567890');
    $account = TenantPayoutAccount::query()->where('tenant_id', $tenant->id)->firstOrFail();
    app(ReviewPayoutAccountService::class)->handle($superUser, $account->public_id, PayoutAccountStatus::Verified, 'Rekening valid.');

    $packagePublicId = app(ManagePackageService::class)->create($owner, new PackageInputData(
        'Paket M7', null, $pricingType, $pricingType === PricingType::Fixed ? 25_000 : 18_000,
        $pricingType === PricingType::Fixed ? 1 : null,
        $pricingType === PricingType::PerKg ? 3000 : null,
        120,
    ));
    app(ChangePackageStatusService::class)->handle($owner, $packagePublicId, ResourceStatus::Active);
    $outlet = Outlet::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $schedule = app(ManageOutletScheduleService::class);
    $hours = array_map(fn (int $day): array => ['day_of_week' => $day, 'opens_at' => '08:00', 'closes_at' => '18:00'], range(0, 6));
    $schedule->replaceHours($owner, $outlet->public_id, $hours);
    $schedule->createSlot($owner, $outlet->public_id, SlotType::Pickup, 0, '10:00', '12:00');
    foreach (range(0, 6) as $day) {
        $schedule->createSlot($owner, $outlet->public_id, SlotType::Delivery, $day, '10:00', '12:00');
    }
    app(ChangeOutletStatusService::class)->handle($owner, $outlet->public_id, ResourceStatus::Active);

    $customer = User::factory()->create();
    $addressPublicId = app(ManageCustomerAddressService::class)->create($customer, new CustomerAddressInputData('Rumah', 'Customer M7', '081111111111', 'Jl. Dekat Outlet', 'Bandung', 'Coblong', '-6.8915', '107.6107', true));
    $package = ServicePackage::query()->where('public_id', $packagePublicId)->firstOrFail();
    $pickupSlot = OutletSlot::query()->where('outlet_id', $outlet->id)->where('type', SlotType::Pickup)->firstOrFail();
    $order = app(CreateOrderService::class)->handle($customer, new OrderInputData(
        $outlet->public_id, $package->public_id, $addressPublicId, null, $pickupSlot->public_id,
        '2026-09-20', $pricingType === PricingType::Fixed ? 2 : null,
        $pricingType === PricingType::PerKg ? 3241 : null, (string) Str::uuid(),
    ), CarbonImmutable::parse('2026-09-20 06:00', 'Asia/Jakarta'));

    $driver = app(DriverRepositoryInterface::class)->createDriver($tenant->id, 'Driver M7', "driver-m7-{$suffix}@example.test", '081222222222', Hash::make('StrongPassword123'));
    app(DriverRepositoryInterface::class)->updateAvailability($driver->id, DriverAvailability::Available);
    app(ManageDriverService::class)->updateSettings($owner, 12_000, 15_000);

    return compact('owner', 'tenant', 'outlet', 'customer', 'package', 'order', 'driver') + [
        'driverUser' => User::query()->findOrFail($driver->id),
    ];
}

function m7MoveToProcessing(array $context): Order
{
    $order = Order::query()->where('public_id', $context['order']->publicId)->firstOrFail();
    $order->update([
        'fulfillment_status' => FulfillmentStatus::Processing,
        'payment_status' => PaymentStatus::Paid,
        'processing_started_at' => now(),
        'estimated_ready_at' => now()->addMinutes(120),
    ]);

    return $order->refresh();
}

it('enforces the paid processing gate and customer delivery scheduling boundaries', function () {
    $context = m7Context();
    $lifecycle = app(ManageDeliveryLifecycleService::class);
    $order = Order::query()->where('public_id', $context['order']->publicId)->firstOrFail();
    $order->update(['fulfillment_status' => FulfillmentStatus::Processing, 'payment_status' => PaymentStatus::Unpaid]);

    expect(fn () => $lifecycle->markReady($context['owner'], $order->public_id))->toThrow(DomainActionConflict::class);
    $order->update(['payment_status' => PaymentStatus::Paid, 'processing_started_at' => now(), 'estimated_ready_at' => now()->subMinute()]);
    $ready = $lifecycle->markReady($context['owner'], $order->public_id);
    expect($ready->fulfillmentStatus)->toBe(FulfillmentStatus::ReadyForDelivery->value);

    $deliverySlot = OutletSlot::query()->where('outlet_id', $context['outlet']->id)->where('type', SlotType::Delivery)->where('day_of_week', 0)->firstOrFail();
    $scheduled = $lifecycle->schedule($context['customer'], $order->public_id, $deliverySlot->public_id, '2026-09-20', null, CarbonImmutable::now());
    expect(CarbonImmutable::parse($scheduled->deliveryStartsAt)->setTimezone('Asia/Jakarta')->format('H:i'))->toBe('10:00')
        ->and($scheduled->scheduleHistory)->toHaveCount(1);

    $otherCustomer = User::factory()->create();
    expect(fn () => $lifecycle->schedule($otherCustomer, $order->public_id, $deliverySlot->public_id, '2026-09-20', null, CarbonImmutable::now()))->toThrow(DomainRecordNotFound::class);
});

it('lets tenant coordinate a late initial slot only after the customer deadline', function () {
    $context = m7Context();
    $order = m7MoveToProcessing($context);
    $lifecycle = app(ManageDeliveryLifecycleService::class);
    $lifecycle->markReady($context['owner'], $order->public_id);
    $slot = OutletSlot::query()->where('outlet_id', $context['outlet']->id)->where('type', SlotType::Delivery)->where('day_of_week', 0)->firstOrFail();

    expect(fn () => $lifecycle->schedule($context['owner'], $order->public_id, $slot->public_id, '2026-09-20', 'Koordinasi manual.', CarbonImmutable::now()))->toThrow(DomainActionConflict::class);

    $late = CarbonImmutable::now()->addDays(8);
    $lateSlot = OutletSlot::query()->where('outlet_id', $context['outlet']->id)->where('type', SlotType::Delivery)->where('day_of_week', $late->dayOfWeek)->firstOrFail();
    $scheduled = $lifecycle->schedule($context['owner'], $order->public_id, $lateSlot->public_id, $late->toDateString(), 'Customer sudah dihubungi.', $late);

    expect($scheduled->deliveryStartsAt)->not->toBeNull()
        ->and(DB::table('activity_logs')->where('action', 'order.delivery_scheduled')->where('reason', 'Customer sudah dihubungi.')->exists())->toBeTrue();
});

it('lets a suspended or closing tenant finish existing delivery work', function () {
    $context = m7Context();
    $order = m7MoveToProcessing($context);
    $lifecycle = app(ManageDeliveryLifecycleService::class);
    Tenant::query()->whereKey($context['tenant']->id)->update(['operational_status' => TenantOperationalStatus::Suspended]);

    $ready = $lifecycle->markReady($context['owner'], $order->public_id);
    expect($ready->fulfillmentStatus)->toBe(FulfillmentStatus::ReadyForDelivery->value);

    Tenant::query()->whereKey($context['tenant']->id)->update([
        'operational_status' => TenantOperationalStatus::Active,
        'closure_requested_at' => now(),
    ]);
    $late = CarbonImmutable::now()->addDays(8);
    $slot = OutletSlot::query()->where('outlet_id', $context['outlet']->id)
        ->where('type', SlotType::Delivery)
        ->where('day_of_week', $late->dayOfWeek)
        ->firstOrFail();
    $lifecycle->schedule($context['owner'], $order->public_id, $slot->public_id, $late->toDateString(), 'Koordinasi saat penutupan.', $late);

    $offer = app(OfferDriverTaskService::class)->handle(
        $context['owner'],
        $order->public_id,
        $context['driver']->publicId,
        DriverTaskType::Delivery,
        $late,
    );

    expect($offer->task->type)->toBe(DriverTaskType::Delivery->value);
});

it('moves a paid fixed order into processing after pickup completion', function () {
    $context = m7Context(PricingType::Fixed);
    Order::query()->where('public_id', $context['order']->publicId)->update([
        'payment_status' => PaymentStatus::Paid,
        'fulfillment_status' => FulfillmentStatus::AwaitingPickup,
    ]);

    $offer = app(OfferDriverTaskService::class)->handle(
        $context['owner'],
        $context['order']->publicId,
        $context['driver']->publicId,
        DriverTaskType::Pickup,
        CarbonImmutable::now(),
    );
    app(RespondDriverOfferService::class)->handle($context['driverUser'], $offer->publicId, true, CarbonImmutable::now());
    $task = app(ProgressDriverTaskService::class)->start($context['driverUser'], $offer->task->publicId);
    Event::fake([DispatchLifecycleEvent::class]);
    app(ProgressDriverTaskService::class)->complete($context['driverUser'], $task->publicId, null, null);

    $stored = Order::query()->where('public_id', $context['order']->publicId)->firstOrFail();
    expect($stored->fulfillment_status)->toBe(FulfillmentStatus::Processing)
        ->and($stored->processing_started_at)->not->toBeNull()
        ->and($stored->estimated_ready_at)->not->toBeNull()
        ->and(OrderStatusHistory::query()->where('order_id', $stored->id)->where('to_status', FulfillmentStatus::PickedUp)->count())->toBe(1)
        ->and(OrderStatusHistory::query()->where('order_id', $stored->id)->where('to_status', FulfillmentStatus::Processing)->count())->toBe(1);
    Event::assertDispatched(fn (DispatchLifecycleEvent $event): bool => $event->name === 'order.processing');
});

it('detects awaiting-customer and assigned-delivery delays idempotently', function () {
    $context = m7Context(PricingType::Fixed);
    $order = m7MoveToProcessing($context);
    $lifecycle = app(ManageDeliveryLifecycleService::class);
    $lifecycle->markReady($context['owner'], $order->public_id);
    Order::query()->whereKey($order->id)->update(['ready_at' => CarbonImmutable::now()->subDays(7)]);
    Event::fake([DispatchLifecycleEvent::class]);

    $monitor = app(MonitorOrderIndicatorsService::class);
    $monitor->handle(CarbonImmutable::now());
    $monitor->handle(CarbonImmutable::now());

    expect(DB::table('order_indicators')->where('order_id', $order->id)->where('type', OrderIndicatorType::AwaitingCustomer)->whereNotNull('active_key')->count())->toBe(1);
    Event::assertDispatchedTimes(DispatchLifecycleEvent::class, 1);

    Event::fake([DispatchLifecycleEvent::class]);
    Order::query()->whereKey($order->id)->update(['ready_at' => CarbonImmutable::now()]);
    $slot = OutletSlot::query()->where('outlet_id', $context['outlet']->id)->where('type', SlotType::Delivery)->where('day_of_week', 0)->firstOrFail();
    $lifecycle->schedule($context['customer'], $order->public_id, $slot->public_id, '2026-09-20', null, CarbonImmutable::now());
    $offer = app(OfferDriverTaskService::class)->handle($context['owner'], $order->public_id, $context['driver']->publicId, DriverTaskType::Delivery, CarbonImmutable::now());
    app(RespondDriverOfferService::class)->handle($context['driverUser'], $offer->publicId, true, CarbonImmutable::now());
    $monitor->handle(CarbonImmutable::parse('2026-09-20 12:01', 'Asia/Jakarta'));

    expect(DB::table('order_indicators')->where('order_id', $order->id)->where('type', OrderIndicatorType::DeliveryDelayed)->whereNotNull('active_key')->count())->toBe(1);
});

it('completes delivery order commission and proof retention atomically and idempotently', function () {
    Storage::fake('local');
    $context = m7Context(PricingType::Fixed);
    $order = m7MoveToProcessing($context);
    $lifecycle = app(ManageDeliveryLifecycleService::class);
    $lifecycle->markReady($context['owner'], $order->public_id);
    $slot = OutletSlot::query()->where('outlet_id', $context['outlet']->id)->where('type', SlotType::Delivery)->where('day_of_week', 0)->firstOrFail();
    $lifecycle->schedule($context['customer'], $order->public_id, $slot->public_id, '2026-09-20', null, CarbonImmutable::now());

    $offer = app(OfferDriverTaskService::class)->handle($context['owner'], $order->public_id, $context['driver']->publicId, DriverTaskType::Delivery, CarbonImmutable::now());
    app(RespondDriverOfferService::class)->handle($context['driverUser'], $offer->publicId, true, CarbonImmutable::now());
    $task = app(ProgressDriverTaskService::class)->start($context['driverUser'], $offer->task->publicId);
    $proof = UploadedFile::fake()->image('delivery.webp', 800, 600)->size(100);
    $completed = app(ProgressDriverTaskService::class)->complete($context['driverUser'], $task->publicId, 'Diterima Customer.', $proof);
    app(ProgressDriverTaskService::class)->complete($context['driverUser'], $task->publicId, null, null);

    $storedOrder = Order::query()->where('public_id', $order->public_id)->firstOrFail();
    $storedTask = DeliveryTask::query()->where('public_id', $completed->publicId)->firstOrFail();
    expect($storedOrder->fulfillment_status)->toBe(FulfillmentStatus::Completed)
        ->and($storedOrder->completed_at)->not->toBeNull()
        ->and(abs((float) $storedTask->proof_expires_at?->diffInDays($storedOrder->completed_at)))->toBe(90.0)
        ->and(DriverCommission::query()->where('task_id', $storedTask->id)->count())->toBe(1)
        ->and(DriverTaskHistory::query()->where('task_id', $storedTask->id)->where('to_status', DriverTaskStatus::Completed)->count())->toBe(1)
        ->and(OrderStatusHistory::query()->where('order_id', $storedOrder->id)->where('to_status', FulfillmentStatus::Completed)->count())->toBe(1);

    $dashboard = app(GetDriverTaskDashboardService::class)->handle($context['driverUser']);
    $terminal = collect($dashboard['tasks'])->firstWhere('publicId', $storedTask->public_id);
    expect($terminal['contactName'])->toBeNull()->and($terminal['address'])->toBeNull();

    $proofPath = $storedTask->proof_key;
    CarbonImmutable::setTestNow($storedTask->proof_expires_at->copy()->subSecond());
    expect(app(GetPrivateProofUrlService::class)->task($context['driverUser'], $storedTask->public_id))->toBeString();
    CarbonImmutable::setTestNow($storedTask->proof_expires_at);
    expect(fn () => app(GetPrivateProofUrlService::class)->task($context['driverUser'], $storedTask->public_id))->toThrow(DomainRecordNotFound::class);
    expect(app(RevokeExpiredProofAccessService::class)->handle())->toBe(1);
    Storage::disk('local')->assertExists((string) $proofPath);
});

it('persists deduplicated safe notifications and authorizes only the owner private channel', function () {
    $context = m7Context();
    $eventId = (string) Str::uuid();
    $event = new DispatchLifecycleEvent('order.ready_for_delivery', null, $context['order']->publicId, $context['tenant']->id, customerId: $context['customer']->id, eventId: $eventId);
    $broadcast = new UserActivityBroadcast($context['customer']->public_id, ['event' => 'order.ready_for_delivery']);
    $listener = app(PersistAndBroadcastLifecycleNotification::class);
    Event::fake([UserActivityBroadcast::class]);
    $listener->handle($event);
    $listener->handle($event);

    $customerRows = DB::table('notifications')->where('notifiable_id', $context['customer']->id)->get();
    expect($customerRows)->toHaveCount(1)
        ->and($customerRows->first()->data)->not->toContain('081111111111')->not->toContain('Jl. Dekat Outlet')->not->toContain('paymentUrl');
    Event::assertDispatchedTimes(UserActivityBroadcast::class, 2);

    $this->actingAs($context['customer'])->withSession(['auth.version' => $context['customer']->auth_version])
        ->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page->component('notifications/index')->has('notifications.items', 1));

    $other = User::factory()->create();
    $notificationId = (string) $customerRows->first()->id;
    $this->actingAs($other)->withSession(['auth.version' => $other->auth_version])
        ->patch("/notifications/{$notificationId}/read")
        ->assertNotFound();
    $this->actingAs($context['customer'])->withSession(['auth.version' => $context['customer']->auth_version])
        ->patch("/notifications/{$notificationId}/read")
        ->assertRedirect();
    $this->patch("/notifications/{$notificationId}/read")->assertRedirect();
    expect(DB::table('notifications')->where('id', $notificationId)->value('read_at'))->not->toBeNull();

    $authorization = Broadcast::getChannels()->get('users.{publicId}');
    expect($authorization)->toBeCallable()
        ->and($authorization($context['customer'], $context['customer']->public_id))->toBeTrue()
        ->and($authorization($other, $context['customer']->public_id))->toBeFalse()
        ->and($event)->toBeInstanceOf(ShouldDispatchAfterCommit::class)
        ->and($broadcast)->toBeInstanceOf(ShouldRescue::class);
});
