<?php

namespace App\DTOs\MilestoneZero;

use App\Enums\UserRole;

final readonly class WireflowPreviewData
{
    /**
     * @param  list<array{value: string, label: string, description: string}>  $roles
     * @param  array{title: string, summary: string, outcome: string, steps: list<array<string, mixed>>}  $wireflow
     * @param  list<array{title: string, description: string, severity: string}>  $riskNotices
     */
    public function __construct(
        public UserRole $activeRole,
        public array $roles,
        public array $wireflow,
        public int $activeStepIndex,
        public array $riskNotices,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $steps = $this->wireflow['steps'];

        return [
            'activeRole' => [
                'value' => $this->activeRole->value,
                'label' => $this->activeRole->label(),
            ],
            'roles' => $this->roles,
            'wireflow' => $this->wireflow,
            'activeStep' => $steps[$this->activeStepIndex],
            'activeStepIndex' => $this->activeStepIndex,
            'previousStepId' => $steps[$this->activeStepIndex - 1]['id'] ?? null,
            'nextStepId' => $steps[$this->activeStepIndex + 1]['id'] ?? null,
            'riskNotices' => $this->riskNotices,
        ];
    }
}
