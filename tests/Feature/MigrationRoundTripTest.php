<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('migrates forward and rolls back on an isolated sqlite database', function () {
    $database = tempnam(sys_get_temp_dir(), 'klik-laundry-m2-');

    expect($database)->not->toBeFalse();

    Config::set('database.connections.m2_roundtrip', [
        'driver' => 'sqlite',
        'database' => $database,
        'prefix' => '',
        'foreign_key_constraints' => true,
        'busy_timeout' => 5000,
        'journal_mode' => null,
        'synchronous' => null,
        'transaction_mode' => 'DEFERRED',
    ]);

    try {
        expect(Artisan::call('migrate', ['--database' => 'm2_roundtrip', '--force' => true]))->toBe(0)
            ->and(Schema::connection('m2_roundtrip')->hasColumns('users', ['public_id', 'tenant_id', 'role', 'status', 'auth_version', 'two_factor_secret']))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('tenants'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('tenant_payout_accounts'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('activity_logs'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('outlets'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('outlet_operating_hours'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('outlet_slots'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('outlet_blackouts'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('packages'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('customer_addresses'))->toBeTrue();
        expect(Schema::connection('m2_roundtrip')->hasTable('orders'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('order_items'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('order_addresses'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('order_status_histories'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('order_schedule_histories'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('order_indicators'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('driver_invitations'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('driver_profiles'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('delivery_tasks'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('driver_task_offers'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('driver_commissions'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('weight_confirmations'))->toBeTrue();
        expect(Schema::connection('m2_roundtrip')->hasTable('payment_channels'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('payments'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasTable('payment_events'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasColumn('payments', 'active_order_key'))->toBeTrue()
            ->and(Schema::connection('m2_roundtrip')->hasColumn('payments', 'last_inquired_at'))->toBeTrue();

        expect(Artisan::call('migrate:reset', ['--database' => 'm2_roundtrip', '--force' => true]))->toBe(0)
            ->and(Schema::connection('m2_roundtrip')->hasTable('tenants'))->toBeFalse()
            ->and(Schema::connection('m2_roundtrip')->hasTable('users'))->toBeFalse();
    } finally {
        DB::purge('m2_roundtrip');
        if (is_string($database) && file_exists($database)) {
            unlink($database);
        }
    }
});

it('backfills onboarding snapshots and preserves them when milestone three rolls back', function () {
    $database = tempnam(sys_get_temp_dir(), 'klik-laundry-m3-');
    expect($database)->not->toBeFalse();

    Config::set('database.connections.m3_backfill', [
        'driver' => 'sqlite',
        'database' => $database,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    $basePaths = [
        'database/migrations/0001_01_01_000000_create_users_table.php',
        'database/migrations/0001_01_01_000001_create_cache_table.php',
        'database/migrations/0001_01_01_000002_create_jobs_table.php',
        'database/migrations/2026_09_16_000003_create_platform_settings_table.php',
        'database/migrations/2026_09_16_000004_create_tenants_table.php',
        'database/migrations/2026_09_16_000005_add_identity_columns_to_users_table.php',
        'database/migrations/2026_09_16_000006_create_tenancy_security_tables.php',
    ];

    try {
        expect(Artisan::call('migrate', ['--database' => 'm3_backfill', '--path' => $basePaths, '--force' => true]))->toBe(0);
        DB::connection('m3_backfill')->table('tenants')->insert([
            'public_id' => '01K5B7M3BACKFILL0000000000',
            'name' => 'Tenant Existing',
            'slug' => 'tenant-existing',
            'phone' => '081234567890',
            'initial_outlet_name' => 'Outlet Existing',
            'initial_outlet_address' => 'Jl. Lama 1',
            'initial_outlet_city' => 'Bandung',
            'initial_outlet_area' => 'Dago',
            'initial_outlet_latitude' => '-6.8915000',
            'initial_outlet_longitude' => '107.6107000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(Artisan::call('migrate', ['--database' => 'm3_backfill', '--path' => 'database/migrations/2026_09_17_000007_create_outlet_catalog_and_address_tables.php', '--force' => true]))->toBe(0)
            ->and(DB::connection('m3_backfill')->table('outlets')->value('name'))->toBe('Outlet Existing');

        expect(Artisan::call('migrate:rollback', ['--database' => 'm3_backfill', '--step' => 1, '--force' => true]))->toBe(0)
            ->and(Schema::connection('m3_backfill')->hasTable('outlets'))->toBeFalse()
            ->and(DB::connection('m3_backfill')->table('tenants')->value('initial_outlet_name'))->toBe('Outlet Existing');
    } finally {
        DB::purge('m3_backfill');
        if (is_string($database) && file_exists($database)) {
            unlink($database);
        }
    }
});
