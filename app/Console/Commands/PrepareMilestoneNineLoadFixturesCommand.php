<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PrepareMilestoneNineLoadFixturesCommand extends Command
{
    protected $signature = 'm9:prepare-load-fixtures
        {--run-id= : Non-sensitive idempotency identifier}
        {--date= : Jakarta order date, defaults to today}';

    protected $description = 'Create the fixed synthetic Milestone 9 staging load profile';

    public function handle(): int
    {
        if (! app()->environment('staging')) {
            $this->error('Load fixtures are restricted to staging.');

            return self::FAILURE;
        }

        $runId = (string) $this->option('run-id');
        if (preg_match('/\A[a-zA-Z0-9-]{8,32}\z/', $runId) !== 1) {
            $this->error('Run ID must contain 8-32 letters, numbers, or hyphens.');

            return self::FAILURE;
        }

        try {
            $date = CarbonImmutable::parse((string) ($this->option('date') ?: 'today'), 'Asia/Jakarta')->startOfDay();
        } catch (\Throwable) {
            $this->error('Date must be a valid calendar date.');

            return self::FAILURE;
        }

        DB::transaction(fn () => $this->createProfile(Str::lower($runId), $date));

        $this->info('Synthetic profile ready: 10 tenants, 20 outlets, 50 drivers, and 500 orders for the selected day.');

        return self::SUCCESS;
    }

    private function createProfile(string $runId, CarbonImmutable $date): void
    {
        $password = Hash::make(Str::random(64));
        $customers = [];

        for ($index = 1; $index <= 20; $index++) {
            $email = "m9.{$runId}.customer.{$index}@example.invalid";
            $customerId = DB::table('users')->where('email', $email)->value('id');
            if ($customerId === null) {
                $customerId = DB::table('users')->insertGetId([
                    'public_id' => (string) Str::ulid(),
                    'name' => "M9 Customer {$index}",
                    'email' => $email,
                    'email_verified_at' => now(),
                    'phone' => null,
                    'role' => 'customer',
                    'status' => 'active',
                    'auth_version' => 1,
                    'password' => $password,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $customers[] = (int) $customerId;
        }

        $outlets = [];
        for ($tenantIndex = 1; $tenantIndex <= 10; $tenantIndex++) {
            $slug = "m9-{$runId}-{$tenantIndex}";
            $tenantId = DB::table('tenants')->where('slug', $slug)->value('id');
            if ($tenantId === null) {
                $tenantId = DB::table('tenants')->insertGetId([
                    'public_id' => (string) Str::ulid(),
                    'name' => "M9 Laundry {$tenantIndex}",
                    'slug' => $slug,
                    'phone' => '0800000000',
                    'onboarding_status' => 'approved',
                    'operational_status' => 'active',
                    'reviewed_at' => now(),
                    'initial_outlet_name' => "M9 Outlet {$tenantIndex}-1",
                    'initial_outlet_address' => 'Synthetic staging address',
                    'initial_outlet_city' => 'Jakarta',
                    'initial_outlet_area' => 'Load Test',
                    'initial_outlet_latitude' => -6.2000000,
                    'initial_outlet_longitude' => 106.8166667,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $packageId = DB::table('packages')->where('tenant_id', $tenantId)->where('name', 'M9 Fixed')->value('id');
            if ($packageId === null) {
                $packageId = DB::table('packages')->insertGetId([
                    'public_id' => (string) Str::ulid(),
                    'tenant_id' => $tenantId,
                    'name' => 'M9 Fixed',
                    'description' => 'Synthetic load fixture',
                    'pricing_type' => 'fixed',
                    'unit_price' => 20000,
                    'minimum_quantity' => 1,
                    'minimum_weight_grams' => null,
                    'estimated_duration_minutes' => 120,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            for ($outletIndex = 1; $outletIndex <= 2; $outletIndex++) {
                $name = "M9 Outlet {$tenantIndex}-{$outletIndex}";
                $outletId = DB::table('outlets')->where('tenant_id', $tenantId)->where('name', $name)->value('id');
                if ($outletId === null) {
                    $outletId = DB::table('outlets')->insertGetId([
                        'public_id' => (string) Str::ulid(),
                        'tenant_id' => $tenantId,
                        'name' => $name,
                        'contact_phone' => '0800000000',
                        'address' => 'Synthetic staging address',
                        'city' => 'Jakarta',
                        'area' => 'Load Test',
                        'latitude' => -6.2000000 + ($tenantIndex / 1000),
                        'longitude' => 106.8166667 + ($outletIndex / 1000),
                        'service_radius_m' => 10000,
                        'pickup_fee' => 5000,
                        'delivery_fee' => 5000,
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $slotId = DB::table('outlet_slots')
                    ->where('outlet_id', $outletId)
                    ->where('type', 'pickup')
                    ->where('day_of_week', $date->dayOfWeek)
                    ->value('id');
                if ($slotId === null) {
                    $slotId = DB::table('outlet_slots')->insertGetId([
                        'public_id' => (string) Str::ulid(),
                        'outlet_id' => $outletId,
                        'type' => 'pickup',
                        'day_of_week' => $date->dayOfWeek,
                        'starts_at' => '09:00:00',
                        'ends_at' => '11:00:00',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $outlets[] = [
                    'tenant_id' => (int) $tenantId,
                    'tenant_name' => "M9 Laundry {$tenantIndex}",
                    'outlet_id' => (int) $outletId,
                    'outlet_name' => $name,
                    'slot_id' => (int) $slotId,
                    'package_id' => (int) $packageId,
                ];
            }

            for ($driverIndex = 1; $driverIndex <= 5; $driverIndex++) {
                $email = "m9.{$runId}.driver.{$tenantIndex}.{$driverIndex}@example.invalid";
                $driverId = DB::table('users')->where('email', $email)->value('id');
                if ($driverId === null) {
                    $driverId = DB::table('users')->insertGetId([
                        'public_id' => (string) Str::ulid(),
                        'tenant_id' => $tenantId,
                        'name' => "M9 Driver {$tenantIndex}-{$driverIndex}",
                        'email' => $email,
                        'email_verified_at' => now(),
                        'phone' => null,
                        'role' => 'driver',
                        'status' => 'active',
                        'auth_version' => 1,
                        'password' => $password,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                DB::table('driver_profiles')->updateOrInsert(
                    ['user_id' => $driverId],
                    ['tenant_id' => $tenantId, 'availability' => 'available', 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }

        $prefix = 'KL-M9-'.substr(hash('sha256', $runId.$date->toDateString()), 0, 12);
        for ($index = 1; $index <= 500; $index++) {
            $orderNumber = sprintf('%s-%04d', $prefix, $index);
            if (DB::table('orders')->where('order_number', $orderNumber)->exists()) {
                continue;
            }

            $outlet = $outlets[($index - 1) % count($outlets)];
            $customerId = $customers[($index - 1) % count($customers)];
            $pickupStartsAt = $date->addHours(9)->addMinutes($index % 120);
            $orderId = DB::table('orders')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'order_number' => $orderNumber,
                'tenant_id' => $outlet['tenant_id'],
                'outlet_id' => $outlet['outlet_id'],
                'customer_id' => $customerId,
                'tenant_name' => $outlet['tenant_name'],
                'outlet_name' => $outlet['outlet_name'],
                'idempotency_key' => $this->deterministicUuid("{$runId}:{$index}"),
                'request_fingerprint' => hash('sha256', "{$runId}:{$date->toDateString()}:{$index}"),
                'pricing_type' => 'fixed',
                'fulfillment_status' => 'completed',
                'payment_status' => 'paid',
                'pickup_slot_id' => $outlet['slot_id'],
                'pickup_starts_at' => $pickupStartsAt,
                'pickup_ends_at' => $pickupStartsAt->addHours(2),
                'items_subtotal' => 20000,
                'estimated_items_subtotal' => 20000,
                'pickup_fee' => 5000,
                'delivery_fee' => 5000,
                'grand_total' => 30000,
                'estimated_grand_total' => 30000,
                'estimated_ready_at' => $pickupStartsAt->addHours(4),
                'ready_at' => $pickupStartsAt->addHours(4),
                'completed_at' => $pickupStartsAt->addHours(6),
                'created_at' => $pickupStartsAt,
                'updated_at' => $pickupStartsAt->addHours(6),
            ]);

            DB::table('order_items')->insert([
                'order_id' => $orderId,
                'package_id' => $outlet['package_id'],
                'package_name' => 'M9 Fixed',
                'package_description' => 'Synthetic load fixture',
                'pricing_type' => 'fixed',
                'unit_price' => 20000,
                'minimum_quantity' => 1,
                'estimated_duration_minutes' => 120,
                'quantity' => 1,
                'created_at' => $pickupStartsAt,
                'updated_at' => $pickupStartsAt,
            ]);

            foreach (['pickup', 'delivery'] as $type) {
                DB::table('order_addresses')->insert([
                    'order_id' => $orderId,
                    'type' => $type,
                    'label' => 'Synthetic',
                    'contact_name' => 'Synthetic Customer',
                    'contact_phone' => '0800000000',
                    'address' => 'Synthetic staging address',
                    'city' => 'Jakarta',
                    'area' => 'Load Test',
                    'latitude' => -6.2000000,
                    'longitude' => 106.8166667,
                    'created_at' => $pickupStartsAt,
                    'updated_at' => $pickupStartsAt,
                ]);
            }

            DB::table('order_status_histories')->insert([
                'order_id' => $orderId,
                'from_status' => null,
                'to_status' => 'completed',
                'reason' => 'Synthetic M9 load fixture',
                'occurred_at' => $pickupStartsAt->addHours(6),
                'created_at' => $pickupStartsAt,
                'updated_at' => $pickupStartsAt,
            ]);
        }
    }

    private function deterministicUuid(string $value): string
    {
        $hex = hash('sha256', $value);

        return sprintf('%s-%s-4%s-a%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 13, 3),
            substr($hex, 17, 3),
            substr($hex, 20, 12),
        );
    }
}
