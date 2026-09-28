<?php

namespace App\Services\Privacy;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Privacy\PiiRevealGrantData;
use App\Enums\PiiAccessEvent;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\PrivacyRepositoryInterface;
use Carbon\CarbonImmutable;

final readonly class ManagePiiRevealService
{
    public function __construct(
        private PrivacyRepositoryInterface $privacy,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function grant(IdentityUser $actor, string $orderPublicId, string $sessionId, string $reason): PiiRevealGrantData
    {
        $this->authorize($actor);
        $privacy = $this->privacy->findOrderPrivacy($orderPublicId);
        if ($privacy === null) {
            throw new DomainRecordNotFound;
        }

        return $this->transactions->run(function () use ($actor, $privacy, $sessionId, $reason): PiiRevealGrantData {
            $grant = $this->privacy->createGrant(
                $actor->databaseId(),
                $privacy->orderId,
                $privacy->customerId,
                $actor->authVersion(),
                $this->sessionHash($sessionId),
                trim($reason),
                CarbonImmutable::now()->addMinutes(15)->utc()->format('Y-m-d H:i:s'),
            );
            $this->privacy->recordAccess($grant->id, $actor->databaseId(), $privacy->orderId, $privacy->customerId, PiiAccessEvent::Granted);
            $this->activityLogs->record(new ActivityLogData(
                tenantId: null,
                actorId: $actor->databaseId(),
                action: 'privacy.pii_reveal_granted',
                subjectType: 'order',
                subjectId: $privacy->orderPublicId,
                reason: trim($reason),
                after: ['expiresAt' => $grant->expiresAt],
            ));

            return $grant;
        });
    }

    /** @return array{privacy: array<string, mixed>, grant: array{publicId: string, expiresAt: string}|null} */
    public function view(IdentityUser $actor, string $orderPublicId, string $sessionId): array
    {
        $this->authorize($actor);
        $privacy = $this->privacy->findOrderPrivacy($orderPublicId);
        if ($privacy === null) {
            throw new DomainRecordNotFound;
        }
        $now = CarbonImmutable::now()->utc()->format('Y-m-d H:i:s');
        $this->transactions->run(fn (): int => $this->privacy->revokeStaleGrants($actor->databaseId(), $actor->authVersion(), $now));
        $grant = $this->privacy->findActiveGrant(
            $actor->databaseId(),
            $privacy->orderId,
            $actor->authVersion(),
            $this->sessionHash($sessionId),
            $now,
        );
        if ($grant !== null) {
            $this->privacy->recordAccess($grant->id, $actor->databaseId(), $privacy->orderId, $privacy->customerId, PiiAccessEvent::Accessed);
        } else {
            $this->privacy->recordAccess(null, $actor->databaseId(), $privacy->orderId, $privacy->customerId, PiiAccessEvent::Denied);
        }

        return ['privacy' => $privacy->toArray($grant !== null), 'grant' => $grant?->toArray()];
    }

    private function authorize(IdentityUser $actor): void
    {
        if ($actor->role() !== UserRole::SuperUser || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }
    }

    private function sessionHash(string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, (string) config('app.key'));
    }
}
