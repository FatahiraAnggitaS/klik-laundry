<?php

namespace App\Repositories\Contracts;

use App\DTOs\Foundation\PlatformSettingsData;

interface PlatformSettingRepositoryInterface
{
    public function findGlobal(): ?PlatformSettingsData;

    public function updateMaximumServiceRadius(int $kilometers, int $actorId): PlatformSettingsData;

    public function updatePaymentMaintenance(bool $enabled, int $actorId): PlatformSettingsData;
}
