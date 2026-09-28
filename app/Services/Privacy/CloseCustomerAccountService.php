<?php

namespace App\Services\Privacy;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\PrivacyRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final readonly class CloseCustomerAccountService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PrivacyRepositoryInterface $privacy,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor): void
    {
        if ($actor->role() !== UserRole::Customer) {
            throw new DomainRecordNotFound;
        }

        $this->transactions->run(function () use ($actor): void {
            $locked = $this->users->lockByPublicId($actor->publicId());
            if ($locked === null || $locked->role() !== UserRole::Customer) {
                throw new DomainRecordNotFound;
            }
            if ($locked->status() === UserStatus::Closed) {
                return;
            }

            $readiness = $this->privacy->accountClosureReadiness($locked->databaseId());
            if (! $readiness->canClose()) {
                throw new DomainActionConflict('Active obligations block account closure.', 'Akun belum dapat ditutup karena masih memiliki order, pembayaran, atau refund aktif.');
            }

            $this->privacy->anonymizeCustomer(
                $locked->databaseId(),
                'closed+'.$locked->publicId().'@invalid.example',
                Hash::make(Str::random(64)),
            );
            $this->activityLogs->record(new ActivityLogData(
                tenantId: null,
                actorId: $locked->databaseId(),
                action: 'identity.customer_anonymized',
                subjectType: 'user',
                subjectId: $locked->publicId(),
                after: ['closed' => true, 'anonymized' => true],
            ));
        });
    }
}
