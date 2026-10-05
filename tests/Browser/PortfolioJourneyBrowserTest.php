<?php

use App\Enums\UserRole;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\OutletSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PortfolioDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    putenv('PORTFOLIO_DEMO_PASSWORD=portfolio-demo-password');

    try {
        $this->seed(PortfolioDemoSeeder::class);
    } finally {
        putenv('PORTFOLIO_DEMO_PASSWORD');
    }
});

it('walks customer discovery and cancels a destructive dialog without deleting data', function (): void {
    $customer = User::query()->where('email', 'customer@portfolio.example.test')->sole();
    $outlet = Outlet::query()->where('status', 'active')->firstOrFail();
    $address = CustomerAddress::query()->where('customer_id', $customer->id)->sole();
    $pickupDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2);
    $slot = OutletSlot::query()->where('outlet_id', $outlet->id)->where('type', 'pickup')->where('day_of_week', $pickupDate->dayOfWeek)->firstOrFail();
    $this->actingAs($customer)->withSession(['auth.version' => $customer->auth_version]);

    visit('/outlets?area=Tebet')
        ->assertSee('Laundry Demo')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    visit("/outlets/{$outlet->public_id}")
        ->assertSee('Cuci Setrika Reguler')
        ->click('Seprai Satuan')
        ->select('label:has-text("Alamat pickup") select', $address->public_id)
        ->click('Periksa radius')
        ->assertSee('Kedua alamat berada dalam radius.')
        ->click('Lanjut ke jadwal')
        ->assertSee('2. Jadwal & konfirmasi')
        ->select('label:has-text("Jadwal pickup") select', "{$slot->public_id}|{$pickupDate->format('Y-m-d')}")
        ->type('label:has-text("Quantity") input', '1')
        ->click('form button[type="submit"]')
        ->assertSee('Seprai Satuan')
        ->assertNoJavaScriptErrors();

    expect(Order::query()->where('customer_id', $customer->id)->count())->toBe(1);

    visit('/customer/addresses')
        ->assertScript('document.querySelectorAll(\'header a[href="/notifications"]\').length', 1)
        ->assertScript('document.querySelectorAll(\'nav a[href="/notifications"]\').length', 0)
        ->click('details > summary')
        ->click('Hapus')
        ->assertSee('Hapus alamat?')
        ->click('Batal')
        ->assertNoJavaScriptErrors();

    expect(CustomerAddress::query()->where('customer_id', $customer->id)->count())->toBe(1);
})->group('browser', 'browser-smoke');

it('renders actual workspaces for tenant, driver, and super user', function (UserRole $role, string $email, string $path, string $heading): void {
    $user = $role === UserRole::SuperUser
        ? User::factory()->create(['role' => UserRole::SuperUser, 'role_slot' => 'super-user:primary'])
        : User::query()->where('email', $email)->sole();
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();
    $this->actingAs($user)->withSession(['auth.version' => $user->auth_version]);

    visit($path)
        ->assertSee($heading)
        ->assertScript('document.querySelectorAll(\'header a[href="/notifications"]\').length', 1)
        ->assertScript('document.querySelectorAll(\'nav a[href="/notifications"]\').length', 0)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with([
    [UserRole::TenantOwner, 'owner-01@portfolio.example.test', '/tenant/operations', 'Operasional'],
    [UserRole::Driver, 'driver-01@portfolio.example.test', '/driver/tasks', 'Tugas'],
    [UserRole::SuperUser, '', '/super-user/tenants', 'Tenant review'],
])->group('browser', 'browser-smoke');

it('renders accessible finance charts with Indonesian labels for three roles', function (UserRole $role, string $email, string $path, string $heading, string $metric): void {
    $user = $role === UserRole::SuperUser
        ? User::factory()->create(['role' => UserRole::SuperUser, 'role_slot' => 'super-user:primary'])
        : User::query()->where('email', $email)->sole();
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();
    $this->actingAs($user)->withSession(['auth.version' => $user->auth_version]);

    visit($path)
        ->assertSee($heading)
        ->assertSee($metric)
        ->assertScript('document.querySelectorAll(\'[data-testid="chart-legend-toggle"]\').length > 0', true)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with([
    [UserRole::TenantOwner, 'owner-01@portfolio.example.test', '/tenant/finance', 'Keuangan Tenant', 'Total pembayaran lunas'],
    [UserRole::Driver, 'driver-01@portfolio.example.test', '/driver/finance', 'Komisi Driver', 'Total komisi diperoleh'],
    [UserRole::SuperUser, '', '/super-user/finance', 'Keuangan & Rekonsiliasi', 'Pembayaran Lunas'],
])->group('browser', 'browser-smoke');

it('closes authenticated mobile navigation with escape and returns focus', function (): void {
    $driver = User::query()->where('email', 'driver-01@portfolio.example.test')->sole();
    $this->actingAs($driver)->withSession(['auth.version' => $driver->auth_version]);

    visit('/driver/tasks')
        ->on()->mobile()
        ->click('button[aria-label="Buka menu"]')
        ->assertScript('document.querySelector(\'dialog[aria-label="Menu workspace"]\')?.open', true)
        ->keys('dialog[aria-label="Menu workspace"]', 'Escape')
        ->assertScript('document.querySelector(\'dialog[aria-label="Menu workspace"]\')?.open', false)
        ->assertScript('document.activeElement?.getAttribute(\'aria-label\')', 'Buka menu')
        ->assertNoAccessibilityIssues();
})->group('browser');
