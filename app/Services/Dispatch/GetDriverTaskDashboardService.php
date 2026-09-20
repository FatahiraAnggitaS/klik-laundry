<?php

namespace App\Services\Dispatch;

use App\Contracts\IdentityUser;
use App\Enums\DriverTaskStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\DispatchRepositoryInterface;
use App\Repositories\Contracts\DriverRepositoryInterface;
use App\Services\Outlets\CalculateDistanceService;

final readonly class GetDriverTaskDashboardService
{
    public function __construct(private DispatchRepositoryInterface $dispatch, private DriverRepositoryInterface $drivers, private CalculateDistanceService $distance) {}

    /** @return array<string, mixed> */
    public function handle(IdentityUser $actor): array
    {
        if ($actor->role() !== UserRole::Driver || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }
        $driver = $this->drivers->findDriver($actor->databaseId()) ?? throw new DomainRecordNotFound;
        $offers = array_map(function ($offer): array {
            $payload = $offer->toArray();
            $payload['task']['approximateDistanceKm'] = round($this->distance->kilometers($offer->task->outletLatitude, $offer->task->outletLongitude, $offer->task->addressLatitude, $offer->task->addressLongitude), 1);

            return $payload;
        }, $this->dispatch->offersForDriver($driver->id));
        $tasks = array_map(fn ($task): array => $task->toArray(in_array($task->status, [DriverTaskStatus::Accepted->value, DriverTaskStatus::InProgress->value], true)), $this->dispatch->tasksForDriver($driver->id));

        return ['driver' => $driver->toArray(), 'offers' => $offers, 'tasks' => $tasks];
    }
}
