<?php

namespace App\DTOs\Dispatch;

final readonly class DriverOfferData
{
    public function __construct(
        public int $id,
        public int $taskId,
        public int $driverId,
        public string $publicId,
        public string $status,
        public string $expiresAt,
        public DriverTaskData $task,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'publicId' => $this->publicId,
            'status' => $this->status,
            'expiresAt' => $this->expiresAt,
            'task' => $this->task->toArray(false),
        ];
    }
}
