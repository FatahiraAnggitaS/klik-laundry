<?php

namespace App\Services\Finance;

use App\Contracts\IdentityUser;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\DriverPayoutRepositoryInterface;
use App\Repositories\Contracts\FinanceReportRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Repositories\Contracts\TenantPayoutRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;

final readonly class GetFinanceDashboardService
{
    public function __construct(
        private FinanceReportRepositoryInterface $reports,
        private RefundRepositoryInterface $refunds,
        private TenantPayoutRepositoryInterface $tenantPayouts,
        private DriverPayoutRepositoryInterface $driverPayouts,
        private TenantOperationsGuard $guard,
        private FinancePeriodService $periods,
    ) {}

    /** @return array<string, mixed> */
    public function tenant(IdentityUser $actor, ?string $from, ?string $to, ?string $outletPublicId): array
    {
        $tenant = $this->guard->forRead($actor);
        $period = $this->periods->normalize($from, $to);

        return [
            ...$this->reports->tenantReport($tenant->id, $period['from'], $period['to'], $outletPublicId),
            'refunds' => $this->refunds->paginateForTenant($tenant->id),
            'tenantPayouts' => $this->tenantPayouts->listForTenant($tenant->id),
            'driverPayouts' => $this->driverPayouts->listForTenant($tenant->id),
            'drivers' => $this->reports->driversForTenant($tenant->id),
            'outlets' => $this->reports->outletsForTenant($tenant->id),
            'filters' => ['from' => $period['fromDate'], 'to' => $period['toDate'], 'outlet' => $outletPublicId],
        ];
    }

    /** @return array<string, mixed> */
    public function driver(IdentityUser $actor, ?string $from, ?string $to): array
    {
        if ($actor->role() !== UserRole::Driver || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }
        $period = $this->periods->normalize($from, $to);

        return [...$this->reports->driverReport($actor->databaseId(), $period['from'], $period['to']), 'payouts' => $this->driverPayouts->listForDriver($actor->databaseId()), 'filters' => ['from' => $period['fromDate'], 'to' => $period['toDate']]];
    }

    /** @return array<string, mixed> */
    public function support(IdentityUser $actor, ?string $from, ?string $to): array
    {
        if ($actor->role() !== UserRole::SuperUser || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }
        $period = $this->periods->normalize($from, $to);

        return ['metrics' => $this->reports->supportMetrics($period['from'], $period['to']), 'refunds' => $this->refunds->paginateForSupport(), 'tenantPayouts' => $this->tenantPayouts->listForSupport(), 'tenants' => $this->reports->tenantsForSupport(), 'filters' => ['from' => $period['fromDate'], 'to' => $period['toDate']]];
    }

    /** @return array<string, mixed> */
    public function refundDetail(IdentityUser $actor, string $publicId): array
    {
        if ($actor->role() === UserRole::SuperUser && $actor->status() === UserStatus::Active) {
            return ($this->refunds->lockForSupport($publicId) ?? throw new DomainRecordNotFound)->toArray();
        }
        $tenant = $this->guard->forRead($actor);

        return ($this->refunds->lockForTenant($tenant->id, $publicId) ?? throw new DomainRecordNotFound)->toArray();
    }

    /** @return array<string, mixed> */
    public function tenantPayoutDetail(IdentityUser $actor, string $publicId): array
    {
        if ($actor->role() === UserRole::SuperUser && $actor->status() === UserStatus::Active) {
            return ($this->tenantPayouts->findForSupport($publicId) ?? throw new DomainRecordNotFound)->toArray();
        }
        $tenant = $this->guard->forRead($actor);

        return ($this->tenantPayouts->findForTenant($tenant->id, $publicId) ?? throw new DomainRecordNotFound)->toArray();
    }

    /** @return array<string, mixed> */
    public function driverPayoutDetail(IdentityUser $actor, string $publicId): array
    {
        if ($actor->role() === UserRole::Driver && $actor->status() === UserStatus::Active) {
            return ($this->driverPayouts->findForDriver($actor->databaseId(), $publicId) ?? throw new DomainRecordNotFound)->toArray();
        }
        $tenant = $this->guard->forRead($actor);

        return ($this->driverPayouts->lockForTenant($tenant->id, $publicId) ?? throw new DomainRecordNotFound)->toArray();
    }
}
