<?php

namespace App\Services\Dispatch;

use App\Contracts\IdentityUser;
use App\Contracts\PrivateProofStorageInterface;
use App\Enums\UserRole;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\DispatchRepositoryInterface;
use Carbon\CarbonImmutable;

final readonly class GetPrivateProofUrlService
{
    public function __construct(private DispatchRepositoryInterface $dispatch, private PrivateProofStorageInterface $proofs) {}

    public function task(IdentityUser $actor, string $taskPublicId): string
    {
        $proof = match ($actor->role()) {
            UserRole::TenantOwner => $actor->tenantId() === null ? null : $this->dispatch->taskProof($actor->tenantId(), $taskPublicId),
            UserRole::Driver => $this->dispatch->driverTaskProof($actor->databaseId(), $taskPublicId),
            default => null,
        };
        if ($proof === null) {
            throw new DomainRecordNotFound;
        }

        return $this->temporaryUrl($proof);
    }

    public function weight(IdentityUser $actor, string $orderPublicId): string
    {
        if ($actor->role() !== UserRole::TenantOwner || $actor->tenantId() === null) {
            throw new DomainRecordNotFound;
        }
        $proof = $this->dispatch->weightProof($actor->tenantId(), $orderPublicId) ?? throw new DomainRecordNotFound;

        return $this->temporaryUrl($proof);
    }

    /** @param array{disk: string, key: string, expiresAt: string|null, revokedAt: string|null} $proof */
    private function temporaryUrl(array $proof): string
    {
        $now = CarbonImmutable::now();
        if ($proof['revokedAt'] !== null || ($proof['expiresAt'] !== null && CarbonImmutable::parse($proof['expiresAt'])->lessThanOrEqualTo($now))) {
            throw new DomainRecordNotFound;
        }

        $expiresAt = $proof['expiresAt'] === null
            ? $now->addMinutes(5)
            : CarbonImmutable::parse($proof['expiresAt'])->min($now->addMinutes(5));

        return $this->proofs->temporaryUrl($proof['disk'], $proof['key'], $expiresAt);
    }
}
