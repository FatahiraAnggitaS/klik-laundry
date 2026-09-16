<?php

namespace App\Services\Foundation;

use App\DTOs\Foundation\PlatformSettingsData;
use App\Exceptions\Domain\PlatformSettingsUnavailable;
use App\Repositories\Contracts\PlatformSettingRepositoryInterface;

final readonly class GetPlatformSettingsService
{
    public function __construct(
        private PlatformSettingRepositoryInterface $settings,
    ) {}

    public function handle(): PlatformSettingsData
    {
        return $this->settings->findGlobal()
            ?? throw new PlatformSettingsUnavailable;
    }
}
