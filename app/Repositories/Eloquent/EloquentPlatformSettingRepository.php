<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Foundation\PlatformSettingsData;
use App\Models\PlatformSetting;
use App\Repositories\Contracts\PlatformSettingRepositoryInterface;

final class EloquentPlatformSettingRepository implements PlatformSettingRepositoryInterface
{
    public function findGlobal(): ?PlatformSettingsData
    {
        $setting = PlatformSetting::query()->find(PlatformSetting::GLOBAL_KEY);

        if ($setting === null) {
            return null;
        }

        return new PlatformSettingsData(
            maxServiceRadiusKm: $setting->max_service_radius_km,
            paymentMaintenanceEnabled: $setting->payment_maintenance_enabled,
            version: $setting->version,
            updatedAt: $setting->updated_at,
        );
    }
}
