<?php

namespace App\Services\Dispatch;

use App\Contracts\IdentityUser;
use App\Repositories\Contracts\DriverRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;

final readonly class GetDriverManagementService
{
    public function __construct(private DriverRepositoryInterface $drivers, private TenantOperationsGuard $guard) {}

    /** @return array<string, mixed> */
    public function handle(IdentityUser $actor): array
    {
        $tenant = $this->guard->forRead($actor);

        return [
            'tenant' => ['name' => $tenant->name, 'canConfigure' => $tenant->operationalStatus === 'active' && ! $tenant->closureRequested],
            'settings' => $this->drivers->settings($tenant->id)->toArray(),
            'drivers' => $this->drivers->paginateDrivers($tenant->id),
            'invitations' => $this->drivers->pendingInvitations($tenant->id),
        ];
    }
}
