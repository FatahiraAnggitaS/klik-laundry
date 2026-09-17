<?php

namespace App\Services\Outlets;

use App\DTOs\Outlets\OutletData;
use App\Enums\PayoutAccountStatus;
use App\Enums\TenantOnboardingStatus;
use App\Enums\TenantOperationalStatus;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\PayoutAccountRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;

final readonly class EvaluateOutletReadinessService
{
    public function __construct(
        private TenantRepositoryInterface $tenants,
        private PayoutAccountRepositoryInterface $payoutAccounts,
        private PackageRepositoryInterface $packages,
    ) {}

    /** @return list<array{code: string, label: string}> */
    public function handle(int $tenantId, OutletData $outlet): array
    {
        return $this->outletBlockers($outlet->toArray(), $this->globalBlockers($tenantId));
    }

    /** @return list<array{code: string, label: string}> */
    public function globalBlockers(int $tenantId): array
    {
        $tenant = $this->tenants->findOwnedByTenantId($tenantId);
        $account = $this->payoutAccounts->findCurrentForTenant($tenantId);
        $blockers = [];

        if ($tenant === null
            || $tenant->onboardingStatus !== TenantOnboardingStatus::Approved->value
            || $tenant->operationalStatus !== TenantOperationalStatus::Active->value
            || $tenant->closureRequested) {
            $blockers[] = ['code' => 'tenant', 'label' => 'Tenant harus approved, aktif, dan tidak dalam proses penutupan.'];
        }

        if ($account === null || $account->verificationStatus !== PayoutAccountStatus::Verified->value) {
            $blockers[] = ['code' => 'payout', 'label' => 'Rekening payout harus terverifikasi.'];
        }

        if ($this->packages->countActiveForTenant($tenantId) === 0) {
            $blockers[] = ['code' => 'package', 'label' => 'Minimal satu paket aktif harus tersedia.'];
        }

        return $blockers;
    }

    /**
     * @param  array<string, mixed>  $outlet
     * @param  list<array{code: string, label: string}>  $globalBlockers
     * @return list<array{code: string, label: string}>
     */
    public function outletBlockers(array $outlet, array $globalBlockers): array
    {
        $blockers = $globalBlockers;

        if ($outlet['operatingHours'] === []) {
            $blockers[] = ['code' => 'hours', 'label' => 'Minimal satu hari operasional harus tersedia.'];
        }

        if (! collect($outlet['slots'])->contains(fn (array $slot): bool => $slot['type'] === 'pickup' && $slot['active'])) {
            $blockers[] = ['code' => 'pickup_slot', 'label' => 'Minimal satu slot pickup aktif harus tersedia.'];
        }

        if (! collect($outlet['slots'])->contains(fn (array $slot): bool => $slot['type'] === 'delivery' && $slot['active'])) {
            $blockers[] = ['code' => 'delivery_slot', 'label' => 'Minimal satu slot delivery aktif harus tersedia.'];
        }

        return $blockers;
    }
}
