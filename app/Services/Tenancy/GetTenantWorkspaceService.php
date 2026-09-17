<?php

namespace App\Services\Tenancy;

use App\Contracts\IdentityUser;
use App\Enums\UserRole;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\PayoutAccountRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\Identity\GetIdentitySummaryService;

final readonly class GetTenantWorkspaceService
{
    public function __construct(
        private GetIdentitySummaryService $identitySummary,
        private TenantRepositoryInterface $tenants,
        private PayoutAccountRepositoryInterface $payoutAccounts,
        private ActivityLogRepositoryInterface $activityLogs,
    ) {}

    /** @return array<string, mixed> */
    public function handle(IdentityUser $actor): array
    {
        $data = ['identity' => $this->identitySummary->handle($actor)->toArray()];

        if ($actor->role() !== UserRole::TenantOwner) {
            return [...$data, 'tenant' => null, 'payoutAccount' => null, 'activity' => []];
        }

        $tenantId = $actor->tenantId() ?? throw new DomainRecordNotFound;
        $tenant = $this->tenants->findOwnedByTenantId($tenantId) ?? throw new DomainRecordNotFound;

        return [
            ...$data,
            'tenant' => $tenant->toArray(),
            'payoutAccount' => $this->payoutAccounts->findCurrentForTenant($tenantId)?->toArray(),
            'activity' => $this->activityLogs->latestForTenant($tenantId),
        ];
    }
}
