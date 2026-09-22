<?php

namespace App\Services\Payments;

use App\Contracts\IdentityUser;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\PaymentRepositoryInterface;

final readonly class ManagePaymentChannelsService
{
    public function __construct(private PaymentRepositoryInterface $payments) {}

    /** @return list<array{code: string, label: string, category: string}> */
    public function activeChannels(): array
    {
        return $this->payments->activeChannels();
    }

    public function setActive(IdentityUser $actor, string $channelCode, bool $active): void
    {
        if ($actor->role() !== UserRole::SuperUser || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }

        if ($this->payments->findChannel($channelCode) === null) {
            throw new DomainActionConflict('Channel tidak dikenal.', 'Channel pembayaran tidak dikenal.');
        }

        $this->payments->setChannelActive($channelCode, $active);
    }
}
