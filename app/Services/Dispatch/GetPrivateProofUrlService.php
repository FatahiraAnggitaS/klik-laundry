<?php

namespace App\Services\Dispatch;

use App\Contracts\IdentityUser;
use App\Contracts\PrivateProofStorageInterface;
use App\Enums\UserRole;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\DispatchRepositoryInterface;

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

        return $this->proofs->temporaryUrl($proof['disk'], $proof['key']);
    }

    public function weight(IdentityUser $actor, string $orderPublicId): string
    {
        if ($actor->role() !== UserRole::TenantOwner || $actor->tenantId() === null) {
            throw new DomainRecordNotFound;
        }
        $proof = $this->dispatch->weightProof($actor->tenantId(), $orderPublicId) ?? throw new DomainRecordNotFound;

        return $this->proofs->temporaryUrl($proof['disk'], $proof['key']);
    }
}
