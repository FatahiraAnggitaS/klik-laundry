<?php

namespace App\Services\Payments;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;

final readonly class ManagePaymentChannelsService
{
    public function __construct(
        private PaymentRepositoryInterface $payments,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    /** @return list<array{code: string, label: string, category: string}> */
    public function activeChannels(): array
    {
        return $this->payments->activeChannels();
    }

    /** @return list<array{code: string, label: string, category: string, isActive: bool, verifiedAt: ?string}> */
    public function channels(IdentityUser $actor): array
    {
        $this->assertSuperUser($actor);

        return $this->payments->channels();
    }

    public function setActive(IdentityUser $actor, string $channelCode, bool $active, string $reason): void
    {
        $this->assertSuperUser($actor);

        $this->transactions->run(function () use ($actor, $channelCode, $active, $reason): void {
            $channel = $this->payments->findChannel($channelCode);
            if ($channel === null) {
                throw new DomainActionConflict('Channel tidak dikenal.', 'Channel pembayaran tidak dikenal.');
            }

            $this->payments->setChannelActive($channelCode, $active);
            $this->activityLogs->record(new ActivityLogData(
                tenantId: null,
                actorId: $actor->databaseId(),
                action: 'payment.channel_updated',
                subjectType: 'payment_channel',
                subjectId: $channelCode,
                reason: $reason,
                before: ['isActive' => $channel['isActive']],
                after: ['isActive' => $active],
            ));
        });
    }

    private function assertSuperUser(IdentityUser $actor): void
    {
        if ($actor->role() !== UserRole::SuperUser || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }
    }
}
