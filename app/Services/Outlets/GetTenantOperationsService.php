<?php

namespace App\Services\Outlets;

use App\Contracts\IdentityUser;
use App\Repositories\Contracts\OutletRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\PlatformSettingRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;

final readonly class GetTenantOperationsService
{
    public function __construct(
        private TenantOperationsGuard $guard,
        private OutletRepositoryInterface $outlets,
        private PackageRepositoryInterface $packages,
        private PlatformSettingRepositoryInterface $settings,
        private EvaluateOutletReadinessService $readiness,
    ) {}

    /** @return array<string, mixed> */
    public function handle(IdentityUser $actor): array
    {
        $tenant = $this->guard->forRead($actor);
        $outlets = $this->outlets->paginateOwned($tenant->id, pageName: 'outlets');
        $globalBlockers = $this->readiness->globalBlockers($tenant->id);
        $outlets['items'] = array_map(function (array $outlet) use ($globalBlockers): array {
            $blockers = $this->readiness->outletBlockers($outlet, $globalBlockers);

            return [...$outlet, 'readiness' => ['ready' => $blockers === [], 'blockers' => $blockers]];
        }, $outlets['items']);

        $settings = $this->settings->findGlobal();

        return [
            'tenant' => [
                'name' => $tenant->name,
                'operationalStatus' => $tenant->operationalStatus,
                'closureRequested' => $tenant->closureRequested,
                'canMutate' => $tenant->operationalStatus === 'active' && ! $tenant->closureRequested,
            ],
            'outlets' => $outlets,
            'packages' => $this->packages->paginateOwned($tenant->id, pageName: 'packages'),
            'maximumServiceRadiusKm' => $settings === null ? 20 : $settings->maxServiceRadiusKm,
        ];
    }
}
