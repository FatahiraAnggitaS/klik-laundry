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

    public function updateMaximumServiceRadius(int $kilometers, int $actorId): PlatformSettingsData
    {
        $setting = PlatformSetting::query()->findOrFail(PlatformSetting::GLOBAL_KEY);
        $setting->forceFill([
            'max_service_radius_km' => $kilometers,
            'updated_by_user_id' => $actorId,
            'version' => $setting->version + 1,
        ])->save();

        return new PlatformSettingsData(
            maxServiceRadiusKm: $setting->max_service_radius_km,
            paymentMaintenanceEnabled: $setting->payment_maintenance_enabled,
            version: $setting->version,
            updatedAt: $setting->updated_at,
        );
    }
}
