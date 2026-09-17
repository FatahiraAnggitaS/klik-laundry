<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Outlets\OutletData;
use App\DTOs\Outlets\OutletInputData;
use App\DTOs\Outlets\OutletSearchCriteria;
use App\Enums\PayoutAccountStatus;
use App\Enums\ResourceStatus;
use App\Enums\SlotType;
use App\Enums\TenantOnboardingStatus;
use App\Enums\TenantOperationalStatus;
use App\Models\Outlet;
use App\Models\OutletBlackout;
use App\Models\OutletOperatingHour;
use App\Models\OutletSlot;
use App\Models\ServicePackage;
use App\Repositories\Contracts\OutletRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Str;

final class EloquentOutletRepository implements OutletRepositoryInterface
{
    public function paginateOwned(int $tenantId, int $perPage = 12, string $pageName = 'page'): array
    {
        $page = $this->ownedQuery($tenantId)->latest('id')->paginate($perPage, ['*'], $pageName);

        return $this->page($page);
    }

    public function findOwned(int $tenantId, string $publicId): ?OutletData
    {
        $outlet = $this->ownedQuery($tenantId)->where('public_id', $publicId)->first();

        return $outlet === null ? null : $this->map($outlet);
    }

    public function lockOwned(int $tenantId, string $publicId): ?OutletData
    {
        $outlet = Outlet::query()
            ->where('tenant_id', $tenantId)
            ->where('public_id', $publicId)
            ->lockForUpdate()
            ->first();

        return $outlet === null ? null : $this->map($outlet->load($this->relations()));
    }

    public function create(int $tenantId, OutletInputData $data): OutletData
    {
        $outlet = Outlet::query()->create($this->attributes($data, $tenantId));

        return $this->map($outlet->refresh()->load($this->relations()));
    }

    public function update(int $outletId, OutletInputData $data): OutletData
    {
        $outlet = Outlet::query()->findOrFail($outletId);
        $outlet->update($this->attributes($data));

        return $this->map($outlet->refresh()->load($this->relations()));
    }

    public function syncInitialDraft(int $tenantId, OutletInputData $data): void
    {
        $outlet = Outlet::query()
            ->where('tenant_id', $tenantId)
            ->where('status', ResourceStatus::Draft)
            ->oldest('id')
            ->first();

        $outlet?->update($this->attributes($data));
    }

    public function delete(int $outletId): void
    {
        Outlet::query()->findOrFail($outletId)->delete();
    }

    public function replaceOperatingHours(int $outletId, array $hours): OutletData
    {
        OutletOperatingHour::query()->where('outlet_id', $outletId)->delete();

        foreach ($hours as $hour) {
            OutletOperatingHour::query()->create(['outlet_id' => $outletId, ...$hour]);
        }

        return $this->fresh($outletId);
    }

    public function createSlot(int $outletId, SlotType $type, int $dayOfWeek, string $startsAt, string $endsAt): OutletData
    {
        OutletSlot::query()->create([
            'public_id' => (string) Str::ulid(),
            'outlet_id' => $outletId,
            'type' => $type,
            'day_of_week' => $dayOfWeek,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_active' => true,
        ]);

        return $this->fresh($outletId);
    }

    public function updateSlot(int $outletId, string $slotPublicId, SlotType $type, int $dayOfWeek, string $startsAt, string $endsAt, bool $active): OutletData
    {
        OutletSlot::query()
            ->where('outlet_id', $outletId)
            ->where('public_id', $slotPublicId)
            ->firstOrFail()
            ->update([
                'type' => $type,
                'day_of_week' => $dayOfWeek,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'is_active' => $active,
            ]);

        return $this->fresh($outletId);
    }

    public function deleteSlot(int $outletId, string $slotPublicId): OutletData
    {
        OutletSlot::query()->where('outlet_id', $outletId)->where('public_id', $slotPublicId)->firstOrFail()->delete();

        return $this->fresh($outletId);
    }

    public function createBlackout(int $outletId, string $date, string $reason, int $actorId): OutletData
    {
        OutletBlackout::query()->create([
            'public_id' => (string) Str::ulid(),
            'outlet_id' => $outletId,
            'date' => $date,
            'reason' => $reason,
            'created_by' => $actorId,
        ]);

        return $this->fresh($outletId);
    }

    public function deleteBlackout(int $outletId, string $blackoutPublicId): OutletData
    {
        OutletBlackout::query()->where('outlet_id', $outletId)->where('public_id', $blackoutPublicId)->firstOrFail()->delete();

        return $this->fresh($outletId);
    }

    public function setStatus(int $outletId, ResourceStatus $status): OutletData
    {
        $outlet = Outlet::query()->findOrFail($outletId);
        $outlet->update([
            'status' => $status,
            'archived_at' => $status === ResourceStatus::Archived ? now() : null,
        ]);

        return $this->map($outlet->refresh()->load($this->relations()));
    }

    public function hasActiveForTenant(int $tenantId): bool
    {
        return Outlet::query()->where('tenant_id', $tenantId)->where('status', ResourceStatus::Active)->exists();
    }

    public function deactivateAllForTenant(int $tenantId): int
    {
        return Outlet::query()
            ->where('tenant_id', $tenantId)
            ->where('status', ResourceStatus::Active)
            ->update(['status' => ResourceStatus::Draft, 'updated_at' => now()]);
    }

    public function anyRadiusAbove(int $maximumRadiusMeters): bool
    {
        return Outlet::query()->where('service_radius_m', '>', $maximumRadiusMeters)->exists();
    }

    public function search(OutletSearchCriteria $criteria): array
    {
        $query = $this->discoverableQuery($criteria);

        if ($criteria->query !== null) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $criteria->query).'%';
            $query->where(function (Builder $query) use ($term): void {
                $query->whereLike('outlets.name', $term)
                    ->orWhereLike('outlets.city', $term)
                    ->orWhereLike('outlets.area', $term);
            });
        }

        if ($criteria->pricingType !== null) {
            $query->whereHas('tenant.packages', fn (Builder $query) => $query
                ->where('status', ResourceStatus::Active)
                ->where('pricing_type', $criteria->pricingType));
        }

        if ($criteria->latitude !== null && $criteria->longitude !== null) {
            $latitudeDelta = $criteria->maximumRadiusMeters / 111_320;
            $longitudeScale = max(cos(deg2rad($criteria->latitude)), 0.01);
            $longitudeDelta = $criteria->maximumRadiusMeters / (111_320 * $longitudeScale);
            $query->whereBetween('latitude', [$criteria->latitude - $latitudeDelta, $criteria->latitude + $latitudeDelta])
                ->whereBetween('longitude', [$criteria->longitude - $longitudeDelta, $criteria->longitude + $longitudeDelta]);

            if ($query->getModel()->getConnection()->getDriverName() === 'sqlite') {
                return $this->searchSqlite($query, $criteria);
            }

            $distanceSql = '6371 * 2 * asin(sqrt(power(sin(radians(latitude - ?) / 2), 2) + cos(radians(?)) * cos(radians(latitude)) * power(sin(radians(longitude - ?) / 2), 2)))';
            $bindings = [$criteria->latitude, $criteria->latitude, $criteria->longitude];
            $query->selectRaw("outlets.*, {$distanceSql} as distance_km", $bindings)
                ->whereRaw("({$distanceSql}) * 1000 <= outlets.service_radius_m", $bindings)
                ->orderBy('distance_km');
        } else {
            $query->orderBy('area')->orderBy('name');
        }

        $page = $query->orderBy('outlets.id')->paginate($criteria->perPage)->withQueryString();

        return $this->discoveryPage($page);
    }

    /** @param Builder<Outlet> $query */
    private function searchSqlite(Builder $query, OutletSearchCriteria $criteria): array
    {
        $latitude = $criteria->latitude ?? 0.0;
        $longitude = $criteria->longitude ?? 0.0;
        $items = $query->get()
            ->map(function (Outlet $outlet) use ($latitude, $longitude): Outlet {
                $outlet->setAttribute('distance_km', $this->distanceKilometers($latitude, $longitude, (float) $outlet->latitude, (float) $outlet->longitude));

                return $outlet;
            })
            ->filter(fn (Outlet $outlet): bool => ((float) $outlet->getAttribute('distance_km') * 1000) <= (int) $outlet->service_radius_m)
            ->sortBy([['distance_km', 'asc'], ['id', 'asc']])
            ->values();
        $pageNumber = Paginator::resolveCurrentPage();
        $pageItems = $items->slice(($pageNumber - 1) * $criteria->perPage, $criteria->perPage)->values();
        $page = new LengthAwarePaginator($pageItems, $items->count(), $criteria->perPage, $pageNumber, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => request()->query(),
        ]);

        return $this->discoveryPage($page);
    }

    private function distanceKilometers(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * asin(min(1.0, sqrt($a)));
    }

    public function findDiscoverable(string $publicId, int $maximumRadiusMeters): ?OutletData
    {
        $criteria = new OutletSearchCriteria(null, null, null, null, $maximumRadiusMeters);
        $outlet = $this->discoverableQuery($criteria)->where('outlets.public_id', $publicId)->first();

        return $outlet === null ? null : $this->map($outlet);
    }

    public function findSlotForOutlet(int $outletId, string $slotPublicId): ?array
    {
        $slot = OutletSlot::query()->where('outlet_id', $outletId)->where('public_id', $slotPublicId)->first();

        return $slot === null ? null : [
            'id' => (int) $slot->id,
            'publicId' => $slot->public_id,
            'type' => $slot->type->value,
            'dayOfWeek' => (int) $slot->day_of_week,
            'startsAt' => substr($slot->starts_at, 0, 5),
            'endsAt' => substr($slot->ends_at, 0, 5),
            'active' => (bool) $slot->is_active,
        ];
    }

    public function findSlotIdForOutlet(int $outletId, string $slotPublicId): ?int
    {
        $id = OutletSlot::query()->where('outlet_id', $outletId)->where('public_id', $slotPublicId)->value('id');

        return $id === null ? null : (int) $id;
    }

    /** @return Builder<Outlet> */
    private function ownedQuery(int $tenantId): Builder
    {
        return Outlet::query()->with($this->relations())->where('tenant_id', $tenantId);
    }

    /** @return Builder<Outlet> */
    private function discoverableQuery(OutletSearchCriteria $criteria): Builder
    {
        return Outlet::query()
            ->with([
                'tenant:id,name',
                'tenant.packages' => fn ($query) => $query->where('status', ResourceStatus::Active)->orderBy('name'),
                'operatingHours' => fn ($query) => $query->orderBy('day_of_week'),
                'slots' => fn ($query) => $query->where('is_active', true)->orderBy('day_of_week')->orderBy('starts_at'),
                'blackouts' => fn ($query) => $query->whereDate('date', '>=', now('Asia/Jakarta')->toDateString())->orderBy('date'),
            ])
            ->where('outlets.status', ResourceStatus::Active)
            ->where('outlets.service_radius_m', '<=', $criteria->maximumRadiusMeters)
            ->whereHas('tenant', fn (Builder $query) => $query
                ->where('onboarding_status', TenantOnboardingStatus::Approved)
                ->where('operational_status', TenantOperationalStatus::Active)
                ->whereNull('closure_requested_at'))
            ->whereHas('tenant.packages', fn (Builder $query) => $query->where('status', ResourceStatus::Active))
            ->whereHas('tenant.payoutAccounts', fn (Builder $query) => $query
                ->whereColumn('current_tenant_id', 'tenants.id')
                ->where('verification_status', PayoutAccountStatus::Verified));
    }

    /** @return array<int|string, callable|string> */
    private function relations(): array
    {
        return [
            'tenant:id,name',
            'operatingHours' => fn ($query) => $query->orderBy('day_of_week'),
            'slots' => fn ($query) => $query->orderBy('day_of_week')->orderBy('starts_at'),
            'blackouts' => fn ($query) => $query->orderBy('date'),
        ];
    }

    /** @return array<string, mixed> */
    private function attributes(OutletInputData $data, ?int $tenantId = null): array
    {
        return array_filter([
            'public_id' => $tenantId === null ? null : (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'name' => $data->name,
            'contact_phone' => $data->contactPhone,
            'address' => $data->address,
            'city' => $data->city,
            'area' => $data->area,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'service_radius_m' => $data->serviceRadiusKm * 1000,
            'pickup_fee' => $data->pickupFee,
            'delivery_fee' => $data->deliveryFee,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function fresh(int $outletId): OutletData
    {
        return $this->map(Outlet::query()->with($this->relations())->findOrFail($outletId));
    }

    /** @param LengthAwarePaginator<int, Outlet> $page */
    private function page(LengthAwarePaginator $page): array
    {
        return [
            'items' => $page->getCollection()->map(fn (Outlet $outlet): array => $this->map($outlet)->toArray())->values()->all(),
            'meta' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
            ],
        ];
    }

    /** @param LengthAwarePaginator<int, Outlet> $page */
    private function discoveryPage(LengthAwarePaginator $page): array
    {
        return [
            'items' => $page->getCollection()->map(function (Outlet $outlet): array {
                $data = $this->map($outlet)->toArray();

                return array_intersect_key($data, array_flip([
                    'publicId', 'name', 'city', 'area', 'pickupFee', 'deliveryFee',
                    'packages', 'tenantName', 'distanceKm',
                ]));
            })->values()->all(),
            'meta' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
            ],
        ];
    }

    private function map(Outlet $outlet): OutletData
    {
        return new OutletData(
            id: (int) $outlet->getKey(),
            tenantId: (int) $outlet->tenant_id,
            publicId: $outlet->public_id,
            name: $outlet->name,
            contactPhone: $outlet->contact_phone,
            address: $outlet->address,
            city: $outlet->city,
            area: $outlet->area,
            latitude: (float) $outlet->latitude,
            longitude: (float) $outlet->longitude,
            serviceRadiusMeters: (int) $outlet->service_radius_m,
            pickupFee: (int) $outlet->pickup_fee,
            deliveryFee: (int) $outlet->delivery_fee,
            status: $outlet->status->value,
            operatingHours: $outlet->relationLoaded('operatingHours') ? $outlet->operatingHours->map(fn (OutletOperatingHour $hour): array => [
                'dayOfWeek' => $hour->day_of_week,
                'opensAt' => substr($hour->opens_at, 0, 5),
                'closesAt' => substr($hour->closes_at, 0, 5),
            ])->all() : [],
            slots: $outlet->relationLoaded('slots') ? $outlet->slots->map(fn (OutletSlot $slot): array => [
                'publicId' => $slot->public_id,
                'type' => $slot->type->value,
                'dayOfWeek' => $slot->day_of_week,
                'startsAt' => substr($slot->starts_at, 0, 5),
                'endsAt' => substr($slot->ends_at, 0, 5),
                'active' => $slot->is_active,
            ])->all() : [],
            blackouts: $outlet->relationLoaded('blackouts') ? $outlet->blackouts->map(fn (OutletBlackout $blackout): array => [
                'publicId' => $blackout->public_id,
                'date' => $blackout->date->toDateString(),
                'reason' => $blackout->reason,
            ])->all() : [],
            packages: $outlet->relationLoaded('tenant') && $outlet->tenant->relationLoaded('packages')
                ? $outlet->tenant->packages->map(fn (ServicePackage $package): array => [
                    'publicId' => $package->public_id,
                    'name' => $package->name,
                    'description' => $package->description,
                    'pricingType' => $package->pricing_type->value,
                    'unitPrice' => (int) $package->unit_price,
                    'minimumQuantity' => $package->minimum_quantity,
                    'minimumWeightGrams' => $package->minimum_weight_grams,
                    'estimatedDurationMinutes' => (int) $package->estimated_duration_minutes,
                    'status' => $package->status->value,
                ])->all()
                : [],
            tenantName: $outlet->relationLoaded('tenant') ? $outlet->tenant->name : null,
            distanceKilometers: $outlet->getAttribute('distance_km') === null ? null : round((float) $outlet->getAttribute('distance_km'), 2),
        );
    }
}
