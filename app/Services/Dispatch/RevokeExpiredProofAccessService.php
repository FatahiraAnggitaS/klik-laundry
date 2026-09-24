<?php

namespace App\Services\Dispatch;

use App\Contracts\TransactionManagerInterface;
use App\Repositories\Contracts\DispatchRepositoryInterface;
use Carbon\CarbonImmutable;

final readonly class RevokeExpiredProofAccessService
{
    public function __construct(private DispatchRepositoryInterface $dispatch, private TransactionManagerInterface $transactions) {}

    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        return $this->transactions->run(fn (): int => $this->dispatch->revokeExpiredProofAccess($now->utc()->toIso8601String()));
    }
}
