<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Catalog\PackageData;
use App\DTOs\Catalog\PackageInputData;
use App\Enums\ResourceStatus;
use App\Models\ServicePackage;
use App\Repositories\Contracts\PackageRepositoryInterface;
use Illuminate\Support\Str;

final class EloquentPackageRepository implements PackageRepositoryInterface
{
    public function paginateOwned(int $tenantId, int $perPage = 12, string $pageName = 'page'): array
    {
        $page = ServicePackage::query()->where('tenant_id', $tenantId)->latest('id')->paginate($perPage, ['*'], $pageName);

        return [
            'items' => $page->getCollection()->map(fn (ServicePackage $package): array => $this->map($package)->toArray())->values()->all(),
            'meta' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
            ],
        ];
    }

    public function activeForTenant(int $tenantId): array
    {
        return ServicePackage::query()
            ->where('tenant_id', $tenantId)
            ->where('status', ResourceStatus::Active)
            ->orderBy('name')
            ->get()
            ->map(fn (ServicePackage $package): array => $this->map($package)->toArray())
            ->all();
    }

    public function findOwned(int $tenantId, string $publicId): ?PackageData
    {
        $package = ServicePackage::query()->where('tenant_id', $tenantId)->where('public_id', $publicId)->first();

        return $package === null ? null : $this->map($package);
    }

    public function lockOwned(int $tenantId, string $publicId): ?PackageData
    {
        $package = ServicePackage::query()
            ->where('tenant_id', $tenantId)
            ->where('public_id', $publicId)
            ->lockForUpdate()
            ->first();

        return $package === null ? null : $this->map($package);
    }

    public function create(int $tenantId, PackageInputData $data): PackageData
    {
        return $this->map(ServicePackage::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            ...$this->attributes($data),
            'status' => ResourceStatus::Draft,
        ]));
    }

    public function update(int $packageId, PackageInputData $data): PackageData
    {
        $package = ServicePackage::query()->findOrFail($packageId);
        $package->update($this->attributes($data));

        return $this->map($package->refresh());
    }

    public function delete(int $packageId): void
    {
        ServicePackage::query()->findOrFail($packageId)->delete();
    }

    public function setStatus(int $packageId, ResourceStatus $status): PackageData
    {
        $package = ServicePackage::query()->findOrFail($packageId);
        $package->update([
            'status' => $status,
            'archived_at' => $status === ResourceStatus::Archived ? now() : null,
        ]);

        return $this->map($package->refresh());
    }

    public function countActiveForTenant(int $tenantId): int
    {
        return ServicePackage::query()->where('tenant_id', $tenantId)->where('status', ResourceStatus::Active)->count();
    }

    /** @return array<string, mixed> */
    private function attributes(PackageInputData $data): array
    {
        return [
            'name' => $data->name,
            'description' => $data->description,
            'pricing_type' => $data->pricingType,
            'unit_price' => $data->unitPrice,
            'minimum_quantity' => $data->minimumQuantity,
            'minimum_weight_grams' => $data->minimumWeightGrams,
            'estimated_duration_minutes' => $data->estimatedDurationMinutes,
        ];
    }

    private function map(ServicePackage $package): PackageData
    {
        return new PackageData(
            id: (int) $package->getKey(),
            tenantId: (int) $package->tenant_id,
            publicId: $package->public_id,
            name: $package->name,
            description: $package->description,
            pricingType: $package->pricing_type->value,
            unitPrice: (int) $package->unit_price,
            minimumQuantity: $package->minimum_quantity,
            minimumWeightGrams: $package->minimum_weight_grams,
            estimatedDurationMinutes: (int) $package->estimated_duration_minutes,
            status: $package->status->value,
        );
    }
}
