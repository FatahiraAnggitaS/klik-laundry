<?php

use App\DTOs\Catalog\PackageInputData;
use App\DTOs\Customers\CustomerAddressInputData;
use App\DTOs\Dispatch\DriverOfferData;
use App\DTOs\Orders\OrderInputData;
use App\DTOs\Tenancy\TenantRegistrationData;
use App\Enums\DriverAvailability;
use App\Enums\DriverOfferStatus;
use App\Enums\DriverTaskStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutAccountStatus;
use App\Enums\PricingType;
use App\Enums\ResourceStatus;
use App\Enums\SlotType;
use App\Enums\TenantOnboardingStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\DomainActionConflict;
use App\Models\DeliveryTask;
use App\Models\DriverCommission;
use App\Models\DriverInvitation;
use App\Models\DriverTaskHistory;
use App\Models\DriverTaskOffer;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\OutletSlot;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Models\TenantPayoutAccount;
use App\Models\User;
use App\Models\WeightConfirmation;
use App\Notifications\DriverInvitationNotification;
use App\Repositories\Contracts\DriverRepositoryInterface;
use App\Services\Catalog\ChangePackageStatusService;
use App\Services\Catalog\ManagePackageService;
use App\Services\Customers\ManageCustomerAddressService;
use App\Services\Dispatch\ConfirmLaundryWeightService;
use App\Services\Dispatch\ExpireDriverOffersService;
use App\Services\Dispatch\GetDriverTaskDashboardService;
use App\Services\Dispatch\ManageDriverInvitationService;
use App\Services\Dispatch\ManageDriverService;
use App\Services\Dispatch\OfferDriverTaskService;
use App\Services\Dispatch\ProgressDriverTaskService;
use App\Services\Dispatch\ReassignDriverTaskService;
use App\Services\Dispatch\RespondDriverOfferService;
use App\Services\Orders\CreateOrderService;
use App\Services\Outlets\ChangeOutletStatusService;
use App\Services\Outlets\ManageOutletScheduleService;
use App\Services\Tenancy\RegisterTenantService;
use App\Services\Tenancy\ReviewPayoutAccountService;
use App\Services\Tenancy\ReviewTenantApplicationService;
use App\Services\Tenancy\SubmitPayoutAccountService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function m5Context(): array
{
    $identity = app(RegisterTenantService::class)->handle(new TenantRegistrationData(
        'Laundry Dispatch', 'Pemilik Dispatch', 'owner-dispatch@example.test', '081234567890', 'StrongPassword123',
        'Outlet Dispatch', 'Jl. Dispatch 1', 'Bandung', 'Coblong', '-6.8915', '107.6107',
    ));
    $owner = User::query()->findOrFail($identity->databaseId());
    $owner->forceFill(['email_verified_at' => now(), 'two_factor_confirmed_at' => now()])->save();
    $superUser = User::factory()->create(['role' => UserRole::SuperUser, 'role_slot' => 'super-user:primary', 'two_factor_confirmed_at' => now()]);
    $tenant = Tenant::query()->where('name', 'Laundry Dispatch')->firstOrFail();
    app(ReviewTenantApplicationService::class)->handle($superUser, $tenant->public_id, TenantOnboardingStatus::Approved, 'Data lengkap.');
    app(SubmitPayoutAccountService::class)->handle($owner, 'Bank Uji', 'Pemilik Dispatch', '1234567890');
    $account = TenantPayoutAccount::query()->where('tenant_id', $tenant->id)->firstOrFail();
    app(ReviewPayoutAccountService::class)->handle($superUser, $account->public_id, PayoutAccountStatus::Verified, 'Rekening valid.');
    $packagePublicId = app(ManagePackageService::class)->create($owner, new PackageInputData('Cuci Kiloan', 'Paket timbang', PricingType::PerKg, 18_000, null, 3000, 120));
    app(ChangePackageStatusService::class)->handle($owner, $packagePublicId, ResourceStatus::Active);
    $outlet = Outlet::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $schedule = app(ManageOutletScheduleService::class);
    $schedule->replaceHours($owner, $outlet->public_id, [['day_of_week' => 6, 'opens_at' => '08:00', 'closes_at' => '18:00']]);
    $schedule->createSlot($owner, $outlet->public_id, SlotType::Pickup, 6, '09:00', '12:00');
    $schedule->createSlot($owner, $outlet->public_id, SlotType::Delivery, 6, '13:00', '16:00');
    app(ChangeOutletStatusService::class)->handle($owner, $outlet->public_id, ResourceStatus::Active);
    $customer = User::factory()->create();
    $addressPublicId = app(ManageCustomerAddressService::class)->create($customer, new CustomerAddressInputData('Rumah', 'Customer Dispatch', '081111111111', 'Jl. Dekat Outlet', 'Bandung', 'Coblong', '-6.8915', '107.6107', true));
    $package = ServicePackage::query()->where('public_id', $packagePublicId)->firstOrFail();
    $pickupSlot = OutletSlot::query()->where('outlet_id', $outlet->id)->where('type', SlotType::Pickup)->firstOrFail();
    $context = compact('owner', 'superUser', 'tenant', 'outlet', 'customer', 'addressPublicId', 'package', 'pickupSlot');
    $order = app(CreateOrderService::class)->handle(
        $customer,
        m5Input($context, 3241),
        CarbonImmutable::parse('2026-09-17 06:00', 'Asia/Jakarta'),
    );
    $driver = app(DriverRepositoryInterface::class)->createDriver(
        $context['tenant']->id,
        'Driver Satu',
        'driver-one@example.test',
        '081222222222',
        Hash::make('StrongPassword123'),
    );
    app(DriverRepositoryInterface::class)->updateAvailability($driver->id, DriverAvailability::Available);
    app(ManageDriverService::class)->updateSettings($context['owner'], 12_000, 15_000);
    $driverUser = User::query()->findOrFail($driver->id);

    return [...$context, 'order' => $order, 'driver' => $driver, 'driverUser' => $driverUser];
}

/** @param array<string, mixed> $context */
function m5Input(array $context, ?int $estimatedWeight = null): OrderInputData
{
    return new OrderInputData(
        $context['outlet']->public_id,
        $context['package']->public_id,
        $context['addressPublicId'],
        null,
        $context['pickupSlot']->public_id,
        '2026-09-19',
        null,
        $estimatedWeight,
        (string) Str::uuid(),
    );
}

function m5Offer(array $context): DriverOfferData
{
    return app(OfferDriverTaskService::class)->handle(
        $context['owner'],
        $context['order']->publicId,
        $context['driver']->publicId,
        now: CarbonImmutable::parse('2026-09-18 08:00', 'Asia/Jakarta'),
    );
}

it('creates a hashed 48-hour invitation and accepts it once through a clean session grant', function () {
    $context = m5Context();
    Notification::fake();
    $invitation = app(ManageDriverInvitationService::class)->invite($context['owner'], 'new-driver@example.test', '081233344455');

    $stored = DriverInvitation::query()->where('public_id', $invitation->publicId)->firstOrFail();
    expect($stored->token_hash)->toHaveLength(64)
        ->and($stored->token_hash)->not->toBe('new-driver@example.test')
        ->and($stored->created_at->diffInHours($stored->expires_at))->toBe(48.0);
    Notification::assertSentOnDemand(DriverInvitationNotification::class);

    $token = Str::random(64);
    $direct = app(DriverRepositoryInterface::class)->createInvitation($context['tenant']->id, $context['owner']->id, 'accepted-driver@example.test', '081299999999', hash('sha256', $token), now()->addHours(48)->toIso8601String());
    $this->get("/driver/invitations/{$direct->publicId}/{$token}")->assertRedirect('/driver/invitation/accept');
    $this->get('/driver/invitation/accept')->assertInertia(fn (Assert $page) => $page->component('auth/accept-driver-invitation')->where('invitation.tenantName', 'Laundry Dispatch')->missing('invitation.token'));
    $this->post('/driver/invitation/accept', ['name' => 'Driver Accepted', 'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123'])->assertRedirect('/login');

    $driver = User::query()->where('email', 'accepted-driver@example.test')->firstOrFail();
    expect($driver->role)->toBe(UserRole::Driver)
        ->and($driver->email_verified_at)->not->toBeNull()
        ->and($driver->driverProfile?->availability)->toBe(DriverAvailability::Unavailable)
        ->and(DriverInvitation::query()->whereKey($direct->id)->value('accepted_at'))->not->toBeNull();
    $this->withSession(['driver.invitation' => ['publicId' => $direct->publicId, 'tokenHash' => hash('sha256', $token)]])
        ->post('/driver/invitation/accept', ['name' => 'Duplicate', 'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123'])
        ->assertConflict();
});

it('offers and accepts atomically while exposing pii only inside the active privacy window', function () {
    $context = m5Context();
    $offer = m5Offer($context);
    $this->travelTo(CarbonImmutable::parse('2026-09-18 08:01', 'Asia/Jakarta'));

    $before = app(GetDriverTaskDashboardService::class)->handle($context['driverUser']);
    expect($before['offers'][0]['task']['contactPhone'])->toBeNull()
        ->and($before['offers'][0]['task']['approximateDistanceKm'])->toBe(0.0);

    $task = app(RespondDriverOfferService::class)->handle($context['driverUser'], $offer->publicId, true, CarbonImmutable::parse('2026-09-18 08:05', 'Asia/Jakarta'));
    expect($task->status)->toBe(DriverTaskStatus::Accepted->value)
        ->and(Order::query()->where('public_id', $context['order']->publicId)->value('fulfillment_status'))->toBe(FulfillmentStatus::PickupAssigned)
        ->and(fn () => app(RespondDriverOfferService::class)->handle($context['driverUser'], $offer->publicId, true))->toThrow(DomainActionConflict::class);

    $active = app(GetDriverTaskDashboardService::class)->handle($context['driverUser']);
    expect($active['tasks'][0]['contactPhone'])->toBe('081111111111');

    app(ProgressDriverTaskService::class)->start($context['driverUser'], $task->publicId);
    app(ManageDriverService::class)->updateSettings($context['owner'], 99_000, 99_000);
    $completed = app(ProgressDriverTaskService::class)->complete($context['driverUser'], $task->publicId, 'Pickup selesai.', null);
    app(ProgressDriverTaskService::class)->complete($context['driverUser'], $task->publicId, 'Retry.', null);

    expect($completed->status)->toBe(DriverTaskStatus::Completed->value)
        ->and(DriverCommission::query()->where('task_id', $completed->id)->value('amount'))->toBe(12_000)
        ->and(DriverCommission::query()->where('task_id', $completed->id)->count())->toBe(1)
        ->and(DriverTaskHistory::query()->where('task_id', $completed->id)->where('to_status', DriverTaskStatus::Completed)->count())->toBe(1)
        ->and(Order::query()->where('public_id', $context['order']->publicId)->value('fulfillment_status'))->toBe(FulfillmentStatus::AwaitingWeight);
    $history = app(GetDriverTaskDashboardService::class)->handle($context['driverUser']);
    expect($history['tasks'][0]['contactPhone'])->toBeNull();
});

it('prevents a second active task and handles expiry and reassignment', function () {
    $context = m5Context();
    $offer = m5Offer($context);
    app(RespondDriverOfferService::class)->handle($context['driverUser'], $offer->publicId, true, CarbonImmutable::parse('2026-09-18 08:05', 'Asia/Jakarta'));

    $secondOrder = app(CreateOrderService::class)->handle(
        $context['customer'],
        m5Input($context, 3000),
        CarbonImmutable::parse('2026-09-17 06:00', 'Asia/Jakarta'),
    );
    expect(fn () => app(OfferDriverTaskService::class)->handle($context['owner'], $secondOrder->publicId, $context['driver']->publicId))->toThrow(DomainActionConflict::class);

    $replacement = app(DriverRepositoryInterface::class)->createDriver($context['tenant']->id, 'Driver Dua', 'driver-two@example.test', '081277777777', Hash::make('StrongPassword123'));
    app(DriverRepositoryInterface::class)->updateAvailability($replacement->id, DriverAvailability::Available);
    $newOffer = app(ReassignDriverTaskService::class)->handle($context['owner'], $offer->task->publicId, $replacement->publicId, 'Driver pertama berhalangan.');
    expect($newOffer->driverId)->toBe($replacement->id)
        ->and(DeliveryTask::query()->whereKey($offer->taskId)->value('active_driver_key'))->toBeNull();

    app(ExpireDriverOffersService::class)->handle(CarbonImmutable::parse($newOffer->expiresAt)->addSecond());
    expect(DriverTaskOffer::query()->whereKey($newOffer->id)->value('status'))->toBe(DriverOfferStatus::Expired)
        ->and(DeliveryTask::query()->whereKey($newOffer->taskId)->value('status'))->toBe(DriverTaskStatus::Pending);
});

it('calculates and corrects per kilogram totals then locks weight after payment leaves unpaid', function () {
    $context = m5Context();
    $offer = m5Offer($context);
    $task = app(RespondDriverOfferService::class)->handle($context['driverUser'], $offer->publicId, true, CarbonImmutable::parse('2026-09-18 08:05', 'Asia/Jakarta'));
    app(ProgressDriverTaskService::class)->start($context['driverUser'], $task->publicId);
    app(ProgressDriverTaskService::class)->complete($context['driverUser'], $task->publicId, null, null);

    $first = app(ConfirmLaundryWeightService::class)->handle($context['owner'], $context['order']->publicId, 3241, null, null);
    expect($first->billableGrams)->toBe(3300)->and($first->itemsSubtotal)->toBe(59_400)->and($first->grandTotal)->toBe(59_400);
    $corrected = app(ConfirmLaundryWeightService::class)->handle($context['owner'], $context['order']->publicId, 2999, 'Timbangan dikalibrasi ulang.', null);
    expect($corrected->billableGrams)->toBe(3000)->and($corrected->grandTotal)->toBe(54_000)
        ->and(WeightConfirmation::query()->where('order_id', $context['order']->id)->count())->toBe(2)
        ->and(WeightConfirmation::query()->whereNotNull('current_order_key')->count())->toBe(1);

    Order::query()->where('public_id', $context['order']->publicId)->update(['payment_status' => PaymentStatus::Pending]);
    expect(fn () => app(ConfirmLaundryWeightService::class)->handle($context['owner'], $context['order']->publicId, 4000, 'Koreksi lagi.', null))->toThrow(DomainActionConflict::class);
});

it('stores proofs privately and rejects unauthorized proof access', function () {
    Storage::fake('local');
    $context = m5Context();
    $offer = m5Offer($context);
    $task = app(RespondDriverOfferService::class)->handle($context['driverUser'], $offer->publicId, true, CarbonImmutable::parse('2026-09-18 08:05', 'Asia/Jakarta'));
    app(ProgressDriverTaskService::class)->start($context['driverUser'], $task->publicId);
    $completed = app(ProgressDriverTaskService::class)->complete($context['driverUser'], $task->publicId, null, UploadedFile::fake()->image('proof.jpg', 640, 480)->size(100));

    $stored = DeliveryTask::query()->findOrFail($completed->id);
    expect($stored->proof_key)->toStartWith('dispatch/task-proofs/')
        ->and($stored->proof_key)->not->toContain('proof.jpg');
    Storage::disk('local')->assertExists($stored->proof_key);

    $other = User::factory()->create();
    $this->actingAs($other)->withSession(['auth.version' => $other->auth_version])->get("/private-proofs/tasks/{$completed->publicId}")->assertNotFound();
});

it('renders isolated tenant and driver inertia workspaces', function () {
    $context = m5Context();
    $this->travelTo(CarbonImmutable::parse('2026-09-18 08:01', 'Asia/Jakarta'));
    m5Offer($context);

    $this->actingAs($context['owner'])->withSession(['auth.version' => $context['owner']->auth_version])
        ->get('/tenant/drivers')->assertInertia(fn (Assert $page) => $page->component('tenant/drivers')->has('drivers.items', 1)->where('settings.pickupCommission', 12_000));
    $this->actingAs($context['owner'])->withSession(['auth.version' => $context['owner']->auth_version])
        ->get('/tenant/dispatch')->assertInertia(fn (Assert $page) => $page->component('tenant/dispatch')->has('tasks.items', 1));
    $this->actingAs($context['driverUser'])->withSession(['auth.version' => $context['driverUser']->auth_version])
        ->get('/driver/tasks')->assertInertia(fn (Assert $page) => $page->component('driver/tasks')->has('offers', 1)->where('offers.0.task.contactPhone', null));
});
