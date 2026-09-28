<?php

namespace App\DTOs\Privacy;

final readonly class AccountClosureReadinessData
{
    public function __construct(
        public int $activeOrders,
        public int $pendingPayments,
        public int $activeRefunds,
    ) {}

    public function canClose(): bool
    {
        return $this->activeOrders === 0 && $this->pendingPayments === 0 && $this->activeRefunds === 0;
    }

    /** @return array{canClose: bool, blockers: array{activeOrders: int, pendingPayments: int, activeRefunds: int}} */
    public function toArray(): array
    {
        return [
            'canClose' => $this->canClose(),
            'blockers' => [
                'activeOrders' => $this->activeOrders,
                'pendingPayments' => $this->pendingPayments,
                'activeRefunds' => $this->activeRefunds,
            ],
        ];
    }
}
