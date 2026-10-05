<?php

use App\Enums\ResourceStatus;
use App\Enums\TenantOperationalStatus;
use App\Models\CustomerAddress;
use App\Models\Outlet;
use App\Models\OutletSlot;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Models\TenantPayoutAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PortfolioDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('creates ten discoverable demo tenants without duplicating records on rerun', function (): void {
    putenv('PORTFOLIO_DEMO_PASSWORD=portfolio-demo-password');

    try {
        $this->seed(PortfolioDemoSeeder::class);
        $this->seed(PortfolioDemoSeeder::class);
    } finally {
        putenv('PORTFOLIO_DEMO_PASSWORD');
    }

    expect(Tenant::query()->where('operational_status', TenantOperationalStatus::Active)->count())->toBe(10)
        ->and(Outlet::query()->where('status', ResourceStatus::Active)->count())->toBe(20)
        ->and(ServicePackage::query()->where('status', ResourceStatus::Active)->count())->toBe(20)
        ->and(TenantPayoutAccount::query()->count())->toBe(10)
        ->and(User::query()->count())->toBe(21);

    $this->get('/outlets?area=Tebet')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('outlets/index')
        ->where('outlets.meta.total', 20));

    $customer = User::query()->where('email', 'customer@portfolio.example.test')->sole();
    $address = CustomerAddress::query()->where('customer_id', $customer->id)->sole();
    $outlet = Outlet::query()->where('status', ResourceStatus::Active)->firstOrFail();
    $package = ServicePackage::query()->where('tenant_id', $outlet->tenant_id)->where('name', 'Seprai Satuan')->sole();
    $pickupDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2);
    $slot = OutletSlot::query()->where('outlet_id', $outlet->id)->where('type', 'pickup')->where('day_of_week', $pickupDate->dayOfWeek)->firstOrFail();

    $this->actingAs($customer)->withSession(['auth.version' => $customer->auth_version])
        ->post('/orders', [
            'outlet_public_id' => $outlet->public_id,
            'package_public_id' => $package->public_id,
            'pickup_address_public_id' => $address->public_id,
            'delivery_address_public_id' => $address->public_id,
            'pickup_slot_public_id' => $slot->public_id,
            'pickup_date' => $pickupDate->format('Y-m-d'),
            'quantity' => 1,
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertSessionHasNoErrors();
});
