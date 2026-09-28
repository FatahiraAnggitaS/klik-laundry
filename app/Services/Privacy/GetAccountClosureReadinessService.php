<?php

namespace App\Services\Privacy;

use App\Contracts\IdentityUser;
use App\DTOs\Privacy\AccountClosureReadinessData;
use App\Enums\UserRole;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\PrivacyRepositoryInterface;

final readonly class GetAccountClosureReadinessService
{
    public function __construct(private PrivacyRepositoryInterface $privacy) {}

    public function handle(IdentityUser $actor): AccountClosureReadinessData
    {
        if ($actor->role() !== UserRole::Customer) {
            throw new DomainRecordNotFound;
        }

        return $this->privacy->accountClosureReadiness($actor->databaseId());
    }
}
