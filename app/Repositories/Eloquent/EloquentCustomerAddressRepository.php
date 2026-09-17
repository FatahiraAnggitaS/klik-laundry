<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Customers\CustomerAddressData;
use App\DTOs\Customers\CustomerAddressInputData;
use App\Models\CustomerAddress;
use App\Repositories\Contracts\CustomerAddressRepositoryInterface;
use Illuminate\Support\Str;

final class EloquentCustomerAddressRepository implements CustomerAddressRepositoryInterface
{
    public function paginateOwned(int $customerId, int $perPage = 12): array
    {
        $page = CustomerAddress::query()
            ->where('customer_id', $customerId)
            ->orderByRaw('CASE WHEN default_customer_id IS NULL THEN 1 ELSE 0 END')
            ->latest('id')
            ->paginate($perPage);

        return [
            'items' => $page->getCollection()->map(fn (CustomerAddress $address): array => $this->map($address)->toArray())->values()->all(),
            'meta' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
            ],
        ];
    }

    public function listOwned(int $customerId): array
    {
        return CustomerAddress::query()
            ->where('customer_id', $customerId)
            ->orderByRaw('CASE WHEN default_customer_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('label')
            ->get()
            ->map(fn (CustomerAddress $address): array => $this->map($address)->toArray())
            ->all();
    }

    public function findOwned(int $customerId, string $publicId): ?CustomerAddressData
    {
        $address = CustomerAddress::query()->where('customer_id', $customerId)->where('public_id', $publicId)->first();

        return $address === null ? null : $this->map($address);
    }

    public function lockOwned(int $customerId, string $publicId): ?CustomerAddressData
    {
        $address = CustomerAddress::query()
            ->where('customer_id', $customerId)
            ->where('public_id', $publicId)
            ->lockForUpdate()
            ->first();

        return $address === null ? null : $this->map($address);
    }

    public function create(int $customerId, CustomerAddressInputData $data): CustomerAddressData
    {
        $hasAddress = CustomerAddress::query()->where('customer_id', $customerId)->exists();
        $address = CustomerAddress::query()->create([
            'public_id' => (string) Str::ulid(),
            'customer_id' => $customerId,
            'default_customer_id' => ! $hasAddress ? $customerId : null,
            ...$this->attributes($data),
            'location_consented_at' => now(),
        ]);

        return $this->map($address);
    }

    public function update(int $addressId, CustomerAddressInputData $data): CustomerAddressData
    {
        $address = CustomerAddress::query()->findOrFail($addressId);
        $address->update([
            ...$this->attributes($data),
            'location_consented_at' => now(),
        ]);

        return $this->map($address->refresh());
    }

    public function setDefault(int $customerId, int $addressId): CustomerAddressData
    {
        CustomerAddress::query()->where('customer_id', $customerId)->update(['default_customer_id' => null]);
        $address = CustomerAddress::query()->where('customer_id', $customerId)->findOrFail($addressId);
        $address->update(['default_customer_id' => $customerId]);

        return $this->map($address->refresh());
    }

    public function delete(int $addressId): void
    {
        CustomerAddress::query()->findOrFail($addressId)->delete();
    }

    public function assignOldestAsDefault(int $customerId): void
    {
        $address = CustomerAddress::query()->where('customer_id', $customerId)->oldest('id')->first();
        $address?->update(['default_customer_id' => $customerId]);
    }

    /** @return array<string, string> */
    private function attributes(CustomerAddressInputData $data): array
    {
        return [
            'label' => $data->label,
            'contact_name' => $data->contactName,
            'contact_phone' => $data->contactPhone,
            'address' => $data->address,
            'city' => $data->city,
            'area' => $data->area,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
        ];
    }

    private function map(CustomerAddress $address): CustomerAddressData
    {
        return new CustomerAddressData(
            id: (int) $address->getKey(),
            customerId: (int) $address->customer_id,
            publicId: $address->public_id,
            label: $address->label,
            contactName: $address->contact_name,
            contactPhone: $address->contact_phone,
            address: $address->address,
            city: $address->city,
            area: $address->area,
            latitude: (float) $address->latitude,
            longitude: (float) $address->longitude,
            isDefault: $address->default_customer_id !== null,
            locationConsentedAt: $address->location_consented_at->toIso8601String(),
        );
    }
}
