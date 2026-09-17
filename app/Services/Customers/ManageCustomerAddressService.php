<?php

namespace App\Services\Customers;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Customers\CustomerAddressInputData;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\CustomerAddressRepositoryInterface;

final readonly class ManageCustomerAddressService
{
    public function __construct(
        private CustomerAddressRepositoryInterface $addresses,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function create(IdentityUser $actor, CustomerAddressInputData $data): string
    {
        $this->assertCustomer($actor);

        return $this->transactions->run(function () use ($actor, $data): string {
            $address = $this->addresses->create($actor->databaseId(), $data);
            if ($data->makeDefault && ! $address->isDefault) {
                $address = $this->addresses->setDefault($actor->databaseId(), $address->id);
            }
            $this->audit($actor, 'customer_address.created', $address->publicId, [], ['default' => $address->isDefault]);

            return $address->publicId;
        });
    }

    public function update(IdentityUser $actor, string $publicId, CustomerAddressInputData $data): void
    {
        $this->assertCustomer($actor);
        $this->transactions->run(function () use ($actor, $publicId, $data): void {
            $address = $this->addresses->lockOwned($actor->databaseId(), $publicId) ?? throw new DomainRecordNotFound;
            $updated = $this->addresses->update($address->id, $data);
            if ($data->makeDefault) {
                $updated = $this->addresses->setDefault($actor->databaseId(), $address->id);
            }
            $this->audit($actor, 'customer_address.updated', $publicId, ['default' => $address->isDefault], ['default' => $updated->isDefault]);
        });
    }

    public function makeDefault(IdentityUser $actor, string $publicId): void
    {
        $this->assertCustomer($actor);
        $this->transactions->run(function () use ($actor, $publicId): void {
            $address = $this->addresses->lockOwned($actor->databaseId(), $publicId) ?? throw new DomainRecordNotFound;
            $this->addresses->setDefault($actor->databaseId(), $address->id);
            $this->audit($actor, 'customer_address.defaulted', $publicId, ['default' => $address->isDefault], ['default' => true]);
        });
    }

    public function delete(IdentityUser $actor, string $publicId): void
    {
        $this->assertCustomer($actor);
        $this->transactions->run(function () use ($actor, $publicId): void {
            $address = $this->addresses->lockOwned($actor->databaseId(), $publicId) ?? throw new DomainRecordNotFound;
            $this->addresses->delete($address->id);
            if ($address->isDefault) {
                $this->addresses->assignOldestAsDefault($actor->databaseId());
            }
            $this->audit($actor, 'customer_address.deleted', $publicId, ['default' => $address->isDefault], []);
        });
    }

    private function assertCustomer(IdentityUser $actor): void
    {
        if ($actor->role() !== UserRole::Customer || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }
    }

    /** @param array<string, bool|int|string|null> $before @param array<string, bool|int|string|null> $after */
    private function audit(IdentityUser $actor, string $action, string $subjectId, array $before, array $after): void
    {
        $this->activityLogs->record(new ActivityLogData(null, $actor->databaseId(), $action, 'customer_address', $subjectId, before: $before, after: $after));
    }
}
