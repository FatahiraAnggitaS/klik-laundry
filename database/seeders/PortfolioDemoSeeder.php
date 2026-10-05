<?php

namespace Database\Seeders;

use App\Enums\PayoutAccountStatus;
use App\Enums\PricingType;
use App\Enums\ResourceStatus;
use App\Enums\SlotType;
use App\Enums\TenantOnboardingStatus;
use App\Enums\TenantOperationalStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CustomerAddress;
use App\Models\DriverProfile;
use App\Models\Outlet;
use App\Models\OutletOperatingHour;
use App\Models\OutletSlot;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Models\TenantDriverSetting;
use App\Models\TenantPayoutAccount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class PortfolioDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Portfolio demo data must never be seeded in production.');
        }

        $password = getenv('PORTFOLIO_DEMO_PASSWORD');
        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Set PORTFOLIO_DEMO_PASSWORD to at least 12 characters before seeding.');
        }

        DB::transaction(function () use ($password): void {
            $customer = $this->demoUser('customer@portfolio.example.test', 'Customer Demo', UserRole::Customer, null, $password);

            CustomerAddress::query()->firstOrCreate(
                ['customer_id' => $customer->id, 'label' => 'Rumah Demo'],
                [
                    'public_id' => (string) Str::ulid(),
                    'default_customer_id' => $customer->id,
                    'contact_name' => $customer->name,
                    'contact_phone' => '080000000001',
                    'address' => 'Jalan Demo 1',
                    'city' => 'Jakarta Selatan',
                    'area' => 'Tebet',
                    'latitude' => '-6.2297000',
                    'longitude' => '106.8520000',
                    'location_consented_at' => now(),
                ],
            );

            for ($index = 1; $index <= 10; $index++) {
                $number = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
                $latitude = -6.2297 + (($index - 1) * 0.002);
                $longitude = 106.852 + (($index - 1) * 0.002);
                $name = "Laundry Demo {$number}";
                $ownerEmail = "owner-{$number}@portfolio.example.test";

                $tenant = Tenant::query()->firstOrCreate(['slug' => "portfolio-demo-{$number}"], [
                    'public_id' => (string) Str::ulid(),
                    'name' => $name,
                    'phone' => "08000001{$number}",
                    'onboarding_status' => TenantOnboardingStatus::Approved,
                    'operational_status' => TenantOperationalStatus::Active,
                    'reviewed_at' => now(),
                    'initial_outlet_name' => "{$name} Tebet",
                    'initial_outlet_address' => "Jalan Demo {$number}",
                    'initial_outlet_city' => 'Jakarta Selatan',
                    'initial_outlet_area' => 'Tebet',
                    'initial_outlet_latitude' => $latitude,
                    'initial_outlet_longitude' => $longitude,
                ]);

                $owner = $this->demoUser($ownerEmail, "Owner Demo {$number}", UserRole::TenantOwner, $tenant->id, $password);
                $driver = $this->demoUser("driver-{$number}@portfolio.example.test", "Driver Demo {$number}", UserRole::Driver, $tenant->id, $password);

                DriverProfile::query()->firstOrCreate(
                    ['user_id' => $driver->id],
                    ['tenant_id' => $tenant->id, 'availability' => 'available'],
                );
                TenantDriverSetting::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id],
                    ['pickup_commission' => 7000, 'delivery_commission' => 7000, 'updated_by' => $owner->id],
                );
                TenantPayoutAccount::query()->firstOrCreate(
                    ['current_tenant_id' => $tenant->id],
                    [
                        'public_id' => (string) Str::ulid(),
                        'tenant_id' => $tenant->id,
                        'bank_name' => 'Bank Demo',
                        'account_holder_name' => $owner->name,
                        'account_number' => "0000000000{$number}",
                        'masked_account_number' => "••••{$number}",
                        'verification_status' => PayoutAccountStatus::Verified,
                        'submitted_by' => $owner->id,
                        'reviewed_at' => now(),
                    ],
                );

                ServicePackage::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => 'Cuci Setrika Reguler'],
                    [
                        'public_id' => (string) Str::ulid(),
                        'description' => 'Layanan kiloan untuk simulasi portfolio.',
                        'pricing_type' => PricingType::PerKg,
                        'unit_price' => 12000,
                        'minimum_weight_grams' => 1000,
                        'estimated_duration_minutes' => 1440,
                        'status' => ResourceStatus::Active,
                    ],
                );
                ServicePackage::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => 'Seprai Satuan'],
                    [
                        'public_id' => (string) Str::ulid(),
                        'description' => 'Layanan satuan untuk simulasi portfolio.',
                        'pricing_type' => PricingType::Fixed,
                        'unit_price' => 25000,
                        'minimum_quantity' => 1,
                        'estimated_duration_minutes' => 1440,
                        'status' => ResourceStatus::Active,
                    ],
                );

                foreach (range(1, 2) as $outletIndex) {
                    $outlet = Outlet::query()->firstOrCreate(
                        ['tenant_id' => $tenant->id, 'name' => "{$name} Outlet {$outletIndex}"],
                        [
                            'public_id' => (string) Str::ulid(),
                            'contact_phone' => "08000002{$number}",
                            'address' => "Jalan Demo {$number} Blok {$outletIndex}",
                            'city' => 'Jakarta Selatan',
                            'area' => 'Tebet',
                            'latitude' => $latitude + ($outletIndex === 2 ? 0.001 : 0),
                            'longitude' => $longitude + ($outletIndex === 2 ? 0.001 : 0),
                            'service_radius_m' => 3000,
                            'pickup_fee' => 5000,
                            'delivery_fee' => 5000,
                            'status' => ResourceStatus::Active,
                        ],
                    );

                    foreach (range(0, 6) as $day) {
                        OutletOperatingHour::query()->firstOrCreate(
                            ['outlet_id' => $outlet->id, 'day_of_week' => $day],
                            ['opens_at' => '08:00', 'closes_at' => '18:00'],
                        );
                        foreach ([SlotType::Pickup, SlotType::Delivery] as $type) {
                            $startsAt = $type === SlotType::Pickup ? '09:00' : '14:00';
                            $endsAt = $type === SlotType::Pickup ? '12:00' : '17:00';
                            OutletSlot::query()->firstOrCreate(
                                ['outlet_id' => $outlet->id, 'type' => $type, 'day_of_week' => $day, 'starts_at' => $startsAt, 'ends_at' => $endsAt],
                                ['public_id' => (string) Str::ulid(), 'is_active' => true],
                            );
                        }
                    }
                }
            }
        });
    }

    private function demoUser(string $email, string $name, UserRole $role, ?int $tenantId, string $password): User
    {
        $user = User::query()->firstOrCreate(['email' => $email], [
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'role' => $role,
            'status' => UserStatus::Active,
            'auth_version' => 1,
            'name' => $name,
            'phone' => '080000000000',
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        if ($user->role !== $role || $user->tenant_id !== $tenantId) {
            throw new RuntimeException('Portfolio demo email collides with another user.');
        }

        return $user;
    }
}
