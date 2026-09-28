<?php

namespace App\Services\Privacy;

use App\Contracts\TransactionManagerInterface;
use App\Repositories\Contracts\PrivacyRepositoryInterface;
use Carbon\CarbonImmutable;

final readonly class ExpirePiiGrantsService
{
    public function __construct(
        private PrivacyRepositoryInterface $privacy,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(int $limit = 500, ?CarbonImmutable $now = null): int
    {
        $timestamp = ($now ?? CarbonImmutable::now())->utc()->format('Y-m-d H:i:s');

        return $this->transactions->run(
            fn (): int => $this->privacy->expireGrants($timestamp, max(1, min($limit, 1000))),
        );
    }
}
