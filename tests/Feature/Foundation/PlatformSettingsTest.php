<?php

use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders persisted global platform settings through the reference slice', function () {
    $this->get(route('foundation.platform-settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('foundation/platform-settings')
            ->where('maxServiceRadiusKm', 20)
            ->where('paymentMaintenanceEnabled', false)
            ->where('version', 1)
            ->where('updatedAt', fn (mixed $value): bool => is_string($value))
        );
});

it('maps a missing platform configuration to a safe inertia error', function () {
    PlatformSetting::query()->delete();

    $this->get(route('foundation.platform-settings'))
        ->assertServiceUnavailable()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/domain')
            ->where('status', 503)
            ->where('message', 'Konfigurasi platform sedang tidak tersedia. Coba lagi beberapa saat.')
        );
});

it('returns only the safe domain message to json clients', function () {
    PlatformSetting::query()->delete();

    $this->getJson(route('foundation.platform-settings'))
        ->assertServiceUnavailable()
        ->assertExactJson([
            'message' => 'Konfigurasi platform sedang tidak tersedia. Coba lagi beberapa saat.',
        ])
        ->assertDontSee('global platform settings record', false);
});
