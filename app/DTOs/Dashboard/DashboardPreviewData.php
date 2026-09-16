<?php

namespace App\DTOs\Dashboard;

use App\Enums\UserRole;

final readonly class DashboardPreviewData
{
    /**
     * @param  list<array{value: string, label: string, description: string}>  $roles
     * @param  list<array{label: string, icon: string}>  $navigation
     * @param  array{eyebrow: string, title: string, description: string, primaryAction: string, secondaryAction: string}  $hero
     * @param  list<array{label: string, value: string, change: string, tone: string, icon: string}>  $metrics
     * @param  array{label: string, title: string, description: string, meta: string, progress: int, action: string, icon: string}  $focus
     * @param  list<array{id: string, title: string, subtitle: string, status: string, statusTone: string, meta: string}>  $workItems
     * @param  list<array{title: string, description: string, status: string}>  $milestones
     */
    public function __construct(
        public UserRole $activeRole,
        public array $roles,
        public array $navigation,
        public array $hero,
        public array $metrics,
        public array $focus,
        public array $workItems,
        public array $milestones,
    ) {}

    /**
     * @return array{
     *     activeRole: array{value: string, label: string},
     *     roles: list<array{value: string, label: string, description: string}>,
     *     navigation: list<array{label: string, icon: string}>,
     *     hero: array{eyebrow: string, title: string, description: string, primaryAction: string, secondaryAction: string},
     *     metrics: list<array{label: string, value: string, change: string, tone: string, icon: string}>,
     *     focus: array{label: string, title: string, description: string, meta: string, progress: int, action: string, icon: string},
     *     workItems: list<array{id: string, title: string, subtitle: string, status: string, statusTone: string, meta: string}>,
     *     milestones: list<array{title: string, description: string, status: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'activeRole' => [
                'value' => $this->activeRole->value,
                'label' => $this->activeRole->label(),
            ],
            'roles' => $this->roles,
            'navigation' => $this->navigation,
            'hero' => $this->hero,
            'metrics' => $this->metrics,
            'focus' => $this->focus,
            'workItems' => $this->workItems,
            'milestones' => $this->milestones,
        ];
    }
}
