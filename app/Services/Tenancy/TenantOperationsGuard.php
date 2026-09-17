<?php

namespace App\Services\Tenancy;

use App\Contracts\IdentityUser;
use App\DTOs\Tenancy\TenantApplicationData;
use App\Enums\TenantOnboardingStatus;
use App\Enums\TenantOperationalStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\TenantRepositoryInterface;

final readonly class TenantOperationsGuard
{
    public function __construct(private TenantRepositoryInterface $tenants) {}

    public function forRead(IdentityUser $actor): TenantApplicationData
    {
        $tenant = $this->tenant($actor);

        if ($tenant->onboardingStatus !== TenantOnboardingStatus::Approved->value
            || in_array($tenant->operationalStatus, [TenantOperationalStatus::Inactive->value, TenantOperationalStatus::Closed->value], true)) {
            throw new DomainRecordNotFound;
        }

        return $tenant;
    }

    public function forMutation(IdentityUser $actor): TenantApplicationData
    {
        $tenant = $this->tenant($actor);

        if ($tenant->onboardingStatus !== TenantOnboardingStatus::Approved->value
            || $tenant->operationalStatus !== TenantOperationalStatus::Active->value
            || $tenant->closureRequested) {
            throw new DomainActionConflict(
                'Tenant is not allowed to mutate operational resources.',
                'Operasional Tenant sedang tidak dapat diubah.',
            );
        }

        return $tenant;
    }

    private function tenant(IdentityUser $actor): TenantApplicationData
    {
        if ($actor->role() !== UserRole::TenantOwner || $actor->status() !== UserStatus::Active || $actor->tenantId() === null) {
            throw new DomainRecordNotFound;
        }

        return $this->tenants->findOwnedByTenantId($actor->tenantId()) ?? throw new DomainRecordNotFound;
    }
}
