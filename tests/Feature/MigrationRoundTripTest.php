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
            ->and(Schema::connection('m2_roundtrip')->hasTable('activity_logs'))->toBeTrue();

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
