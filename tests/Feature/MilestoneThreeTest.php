<?php

use App\DTOs\Catalog\PackageInputData;
use App\DTOs\Customers\CustomerAddressInputData;
use App\DTOs\Tenancy\TenantRegistrationData;
use App\Enums\PayoutAccountStatus;
use App\Enums\PricingType;
use App\Enums\ResourceStatus;
use App\Enums\SlotType;
use App\Enums\TenantOnboardingStatus;
use App\Enums\TenantOperationalStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Models\ActivityLog;
use App\Models\CustomerAddress;
use App\Models\Outlet;
use App\Models\Tenant;
use App\Models\TenantPayoutAccount;
use App\Models\User;
use App\Repositories\Contracts\OutletRepositoryInterface;
use App\Services\Catalog\ChangePackageStatusService;
use App\Services\Catalog\ManagePackageService;
use App\Services\Customers\ManageCustomerAddressService;
use App\Services\Foundation\UpdateMaximumServiceRadiusService;
use App\Services\Outlets\ChangeOutletStatusService;
use App\Services\Outlets\GetAvailableScheduleService;
use App\Services\Outlets\ManageOutletScheduleService;
use App\Services\Tenancy\ChangeTenantOperationalStatusService;
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

/** @return array{owner: User, superUser: User, tenant: Tenant, outlet: Outlet} */
function m3ApprovedTenant(string $email = 'm3-owner@example.test', string $business = 'Laundry M3', float $latitude = -6.8915, float $longitude = 107.6107): array
{
    $identity = app(RegisterTenantService::class)->handle(new TenantRegistrationData(
        businessName: $business,
        ownerName: "Owner {$business}",
        email: $email,
        phone: '081234567890',
        password: 'StrongPassword123',
        outletName: "Outlet {$business}",
        outletAddress: 'Jl. Milestone Tiga 10',
        city: 'Bandung',
        area: 'Coblong',
        latitude: (string) $latitude,
        longitude: (string) $longitude,
    ));
    $owner = User::query()->findOrFail($identity->databaseId());
    $owner->forceFill(['email_verified_at' => now(), 'two_factor_confirmed_at' => now()])->save();
    $superUser = User::query()->where('role', UserRole::SuperUser)->first()
        ?? User::factory()->create(['role' => UserRole::SuperUser, 'role_slot' => 'super-user:primary', 'two_factor_confirmed_at' => now()]);
    $tenant = Tenant::query()->where('name', $business)->firstOrFail();
    app(ReviewTenantApplicationService::class)->handle($superUser, $tenant->public_id, TenantOnboardingStatus::Approved, 'Data usaha lengkap.');

    return ['owner' => $owner->refresh(), 'superUser' => $superUser, 'tenant' => $tenant->refresh(), 'outlet' => Outlet::query()->where('tenant_id', $tenant->id)->firstOrFail()];
}

/** @param array{owner: User, superUser: User, tenant: Tenant, outlet: Outlet} $context */
function m3VerifyPayout(array $context): void
{
    app(SubmitPayoutAccountService::class)->handle($context['owner'], 'Bank Uji', 'Pemilik Uji', '1234567890');
    $account = TenantPayoutAccount::query()->where('tenant_id', $context['tenant']->id)->firstOrFail();
    app(ReviewPayoutAccountService::class)->handle($context['superUser'], $account->public_id, PayoutAccountStatus::Verified, 'Rekening sesuai.');
}

/** @param array{owner: User, superUser: User, tenant: Tenant, outlet: Outlet} $context */
function m3MakeOutletReady(array $context): string
{
    m3VerifyPayout($context);
    $packageId = app(ManagePackageService::class)->create($context['owner'], new PackageInputData('Cuci Reguler', null, PricingType::Fixed, 20000, 1, null, 1440));
    app(ChangePackageStatusService::class)->handle($context['owner'], $packageId, ResourceStatus::Active);
    app(ManageOutletScheduleService::class)->replaceHours($context['owner'], $context['outlet']->public_id, [['day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '18:00']]);
    app(ManageOutletScheduleService::class)->createSlot($context['owner'], $context['outlet']->public_id, SlotType::Pickup, 1, '09:00', '12:00');
    app(ManageOutletScheduleService::class)->createSlot($context['owner'], $context['outlet']->public_id, SlotType::Delivery, 1, '13:00', '16:00');
    app(ChangeOutletStatusService::class)->handle($context['owner'], $context['outlet']->public_id, ResourceStatus::Active);

    return $packageId;
}

it('creates the onboarding outlet as a draft without leaking its pii to audit', function () {
    $context = m3ApprovedTenant();

    expect($context['outlet']->status)->toBe(ResourceStatus::Draft)
        ->and(Outlet::query()->where('tenant_id', $context['tenant']->id)->count())->toBe(1);

    $audit = ActivityLog::query()->get(['before', 'after', 'reason'])->toJson();
    expect($audit)->not->toContain('081234567890')->not->toContain('Jl. Milestone Tiga');
});

it('requires every readiness condition before outlet activation', function () {
    $context = m3ApprovedTenant();

    expect(fn () => app(ChangeOutletStatusService::class)->handle($context['owner'], $context['outlet']->public_id, ResourceStatus::Active))
        ->toThrow(DomainActionConflict::class);

    m3MakeOutletReady($context);

    expect($context['outlet']->refresh()->status)->toBe(ResourceStatus::Active);
});

it('enforces pricing contracts and protects the last active package', function () {
    $context = m3ApprovedTenant();
    $packages = app(ManagePackageService::class);

    expect(fn () => $packages->create($context['owner'], new PackageInputData('Kilo Salah', null, PricingType::PerKg, 12001, null, 3000, 120)))
        ->toThrow(DomainActionConflict::class);

    $packageId = m3MakeOutletReady($context);
    expect(fn () => app(ChangePackageStatusService::class)->handle($context['owner'], $packageId, ResourceStatus::Draft))
        ->toThrow(DomainActionConflict::class);
});

it('keeps customer addresses isolated and maintains exactly one default', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $service = app(ManageCustomerAddressService::class);
    $input = new CustomerAddressInputData('Rumah', 'Customer', '081111111111', 'Jl. Rumah', 'Bandung', 'Dago', '-6.8915', '107.6107', false);
    $firstId = $service->create($first, $input);
    $secondId = $service->create($first, new CustomerAddressInputData('Kantor', 'Customer', '081111111111', 'Jl. Kantor', 'Bandung', 'Setiabudi', '-6.8800', '107.6000', true));

    expect(CustomerAddress::query()->where('customer_id', $first->id)->whereNotNull('default_customer_id')->count())->toBe(1)
        ->and(CustomerAddress::query()->where('public_id', $secondId)->value('default_customer_id'))->toBe($first->id);
    expect(fn () => $service->delete($second, $firstId))->toThrow(DomainRecordNotFound::class);
});

it('requires explicit location consent before persisting a customer address', function () {
    $customer = User::factory()->create();
    $payload = [
        'label' => 'Rumah',
        'contact_name' => 'Customer',
        'contact_phone' => '081111111111',
        'address' => 'Jl. Rumah',
        'city' => 'Bandung',
        'area' => 'Dago',
        'latitude' => '-6.8915',
        'longitude' => '107.6107',
    ];

    $this->actingAs($customer)->withSession(['auth.version' => $customer->auth_version])
        ->post('/customer/addresses', $payload)
        ->assertSessionHasErrors('location_consent');
    $this->assertDatabaseCount('customer_addresses', 0);

    $this->post('/customer/addresses', [...$payload, 'location_consent' => true])
        ->assertSessionHasNoErrors();
    expect(CustomerAddress::query()->sole()->location_consented_at)->not->toBeNull();
});

it('filters discovery by eligibility pricing and distance', function () {
    $near = m3ApprovedTenant('near@example.test', 'Laundry Dekat');
    m3MakeOutletReady($near);

    $response = $this->get('/outlets?pricing_type=fixed&latitude=-6.8915&longitude=107.6107');
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('outlets/index')
        ->has('outlets.items', 1)
        ->where('outlets.items.0.name', 'Outlet Laundry Dekat')
        ->missing('outlets.items.0.contactPhone')
        ->missing('outlets.items.0.latitude')
        ->where('filters.usingLocation', true));
});

it('includes the service-radius boundary and excludes locations beyond it', function () {
    $context = m3ApprovedTenant();
    m3MakeOutletReady($context);

    $this->get('/outlets?latitude=-6.8825068&longitude=107.6107')
        ->assertInertia(fn (Assert $page) => $page->has('outlets.items', 1));
    $this->get('/outlets?latitude=-6.8824&longitude=107.6107')
        ->assertInertia(fn (Assert $page) => $page->has('outlets.items', 0));
});

it('validates both saved address candidates against outlet radius', function () {
    $context = m3ApprovedTenant();
    m3MakeOutletReady($context);
    $customer = User::factory()->create();
    $addresses = app(ManageCustomerAddressService::class);
    $pickup = $addresses->create($customer, new CustomerAddressInputData('Pickup', 'A', '0812', 'Dekat', 'Bandung', 'Dago', '-6.8915', '107.6107', true));
    $delivery = $addresses->create($customer, new CustomerAddressInputData('Delivery', 'A', '0812', 'Dekat', 'Bandung', 'Dago', '-6.8916', '107.6108', false));

    $this->actingAs($customer)->withSession(['auth.version' => $customer->auth_version])
        ->get("/outlets/{$context['outlet']->public_id}?pickup_address={$pickup}&delivery_address={$delivery}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('coverage.ready', true)
            ->missing('outlet.latitude')
            ->missing('outlet.blackouts'));
});

it('applies lead time horizon weekday and blackout to schedule previews', function () {
    $context = m3ApprovedTenant();
    $repository = app(OutletRepositoryInterface::class);
    $repository->replaceOperatingHours($context['outlet']->id, [['day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '18:00']]);
    $repository->createSlot($context['outlet']->id, SlotType::Pickup, 1, '09:00', '12:00');
    $outlet = $repository->findOwned($context['tenant']->id, $context['outlet']->public_id);

    $slots = app(GetAvailableScheduleService::class)->handle($outlet, CarbonImmutable::parse('2026-09-21 07:00', 'Asia/Jakarta'));
    expect($slots)->toHaveCount(1);
    expect(app(GetAvailableScheduleService::class)->handle($outlet, CarbonImmutable::parse('2026-09-21 08:00', 'Asia/Jakarta')))->toBe([]);
    expect(fn () => app(ManageOutletScheduleService::class)->createSlot($context['owner'], $context['outlet']->public_id, SlotType::Delivery, 1, '07:00', '09:00'))
        ->toThrow(DomainActionConflict::class);

    $repository->createBlackout($context['outlet']->id, '2026-09-21', 'Libur operasional', $context['owner']->id);
    $outlet = $repository->findOwned($context['tenant']->id, $context['outlet']->public_id);
    expect(app(GetAvailableScheduleService::class)->handle($outlet, CarbonImmutable::parse('2026-09-21 06:00', 'Asia/Jakarta')))->toBe([]);
});

it('makes suspended tenant operations read only and hides outlets from discovery', function () {
    $context = m3ApprovedTenant();
    m3MakeOutletReady($context);
    app(ChangeTenantOperationalStatusService::class)->handle($context['superUser'], $context['tenant']->public_id, TenantOperationalStatus::Suspended, 'Pemeriksaan keamanan.');

    expect(fn () => app(ManagePackageService::class)->create($context['owner'], new PackageInputData('Baru', null, PricingType::Fixed, 10000, 1, null, 60)))
        ->toThrow(DomainActionConflict::class);
    $this->get('/outlets')->assertInertia(fn (Assert $page) => $page->has('outlets.items', 0));
});

it('deactivates active outlets when payout account is replaced', function () {
    $context = m3ApprovedTenant();
    m3MakeOutletReady($context);

    app(SubmitPayoutAccountService::class)->handle($context['owner'], 'Bank Baru', 'Pemilik Baru', '9988776655');

    $audit = ActivityLog::query()->latest('id')->firstOrFail()->toJson();
    expect($context['outlet']->refresh()->status)->toBe(ResourceStatus::Draft)
        ->and($audit)->not->toContain('Bank Baru')->not->toContain('Pemilik Baru')->not->toContain('9988776655');
});

it('rejects platform radius reductions below configured outlets', function () {
    $context = m3ApprovedTenant();
    $context['outlet']->update(['service_radius_m' => 10_000]);

    expect(fn () => app(UpdateMaximumServiceRadiusService::class)->handle($context['superUser'], 5, 'Menurunkan batas layanan.'))
        ->toThrow(DomainActionConflict::class);
});

it('does not expose cross tenant outlet mutations over http', function () {
    $a = m3ApprovedTenant('tenant-a@example.test', 'Tenant A');
    $b = m3ApprovedTenant('tenant-b@example.test', 'Tenant B');

    $this->actingAs($a['owner'])->withSession(['auth.version' => $a['owner']->auth_version])
        ->delete("/tenant/outlets/{$b['outlet']->public_id}")
        ->assertNotFound();
});

it('fails closed for rejected closing and wrong-role operational access', function () {
    $context = m3ApprovedTenant();

    $context['tenant']->update([
        'onboarding_status' => TenantOnboardingStatus::Rejected,
        'operational_status' => TenantOperationalStatus::Inactive,
    ]);
    $this->actingAs($context['owner'])->withSession(['auth.version' => $context['owner']->auth_version])
        ->get('/tenant/operations')
        ->assertNotFound();

    $context['tenant']->update([
        'onboarding_status' => TenantOnboardingStatus::Approved,
        'operational_status' => TenantOperationalStatus::Active,
        'closure_requested_at' => now(),
    ]);
    $this->actingAs($context['owner'])->withSession(['auth.version' => $context['owner']->auth_version])
        ->get('/tenant/operations')
        ->assertInertia(fn (Assert $page) => $page->where('tenant.canMutate', false));
    expect(fn () => app(ManagePackageService::class)->create($context['owner'], new PackageInputData('Ditolak', null, PricingType::Fixed, 10000, 1, null, 60)))
        ->toThrow(DomainActionConflict::class);

    $customer = User::factory()->create();
    $this->actingAs($customer)->withSession(['auth.version' => $customer->auth_version])
        ->get('/tenant/operations')
        ->assertForbidden();
});

it('validates and persists operating hours through the http boundary', function () {
    $context = m3ApprovedTenant();

    $this->actingAs($context['owner'])->withSession(['auth.version' => $context['owner']->auth_version])
        ->put("/tenant/outlets/{$context['outlet']->public_id}/hours", [
            'hours' => [['day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '17:00']],
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('outlet_operating_hours', [
        'outlet_id' => $context['outlet']->id,
        'day_of_week' => 1,
        'opens_at' => '08:00',
        'closes_at' => '17:00',
    ]);
});

it('keeps discovery pagination and query count bounded for ten tenants and twenty outlets', function () {
    foreach (range(1, 10) as $index) {
        $context = m3ApprovedTenant("query-{$index}@example.test", "Query Tenant {$index}");
        m3MakeOutletReady($context);
        $template = $context['outlet']->fresh()->toArray();
        unset($template['id'], $template['public_id'], $template['created_at'], $template['updated_at']);
        Outlet::query()->create([...$template, 'public_id' => (string) Str::ulid(), 'name' => "Outlet tambahan {$index}"]);
    }

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $this->get('/outlets')->assertInertia(fn (Assert $page) => $page
        ->has('outlets.items', 12)
        ->where('outlets.meta.total', 20));

    expect($queries)->toBeLessThanOrEqual(8);
});
