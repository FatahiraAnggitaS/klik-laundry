<?php

namespace App\DTOs\Dispatch;

final readonly class DriverTaskData
{
    /** @param list<array<string, mixed>> $history */
    public function __construct(
        public int $id,
        public int $tenantId,
        public int $orderId,
        public int $outletId,
        public string $publicId,
        public string $orderPublicId,
        public string $orderNumber,
        public string $pricingType,
        public string $paymentStatus,
        public string $orderStatus,
        public string $type,
        public string $status,
        public ?int $assigneeId,
        public ?string $assigneePublicId,
        public ?string $assigneeName,
        public int $commissionAmount,
        public string $outletName,
        public string $scheduledStartsAt,
        public string $scheduledEndsAt,
        public string $area,
        public float $outletLatitude,
        public float $outletLongitude,
        public float $addressLatitude,
        public float $addressLongitude,
        public ?string $contactName,
        public ?string $contactPhone,
        public ?string $address,
        public ?string $note,
        public bool $hasProof,
        public ?string $acceptedAt,
        public ?string $startedAt,
        public ?string $completedAt,
        public array $history,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(bool $includePii = false): array
    {
        return [
            'publicId' => $this->publicId,
            'orderPublicId' => $this->orderPublicId,
            'orderNumber' => $this->orderNumber,
            'pricingType' => $this->pricingType,
            'paymentStatus' => $this->paymentStatus,
            'orderStatus' => $this->orderStatus,
            'type' => $this->type,
            'status' => $this->status,
            'assigneePublicId' => $this->assigneePublicId,
            'assigneeName' => $this->assigneeName,
            'commissionAmount' => $this->commissionAmount,
            'outletName' => $this->outletName,
            'scheduledStartsAt' => $this->scheduledStartsAt,
            'scheduledEndsAt' => $this->scheduledEndsAt,
            'area' => $this->area,
            'contactName' => $includePii ? $this->contactName : null,
            'contactPhone' => $includePii ? $this->contactPhone : null,
            'address' => $includePii ? $this->address : null,
            'note' => $this->note,
            'hasProof' => $this->hasProof,
            'acceptedAt' => $this->acceptedAt,
            'startedAt' => $this->startedAt,
            'completedAt' => $this->completedAt,
            'history' => $this->history,
        ];
    }
}
