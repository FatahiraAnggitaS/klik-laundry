<?php

namespace App\Http\Requests\Outlets;

use App\DTOs\Outlets\OutletInputData;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class OutletRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-own-tenant') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:120'],
            'area' => ['required', 'string', 'max:120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'service_radius_km' => ['required', 'integer', 'min:1', 'max:100'],
            'pickup_fee' => ['required', 'integer', 'min:0'],
            'delivery_fee' => ['required', 'integer', 'min:0'],
        ];
    }

    public function toDto(): OutletInputData
    {
        return new OutletInputData(
            name: $this->string('name')->toString(),
            contactPhone: $this->string('contact_phone')->toString(),
            address: $this->string('address')->toString(),
            city: $this->string('city')->toString(),
            area: $this->string('area')->toString(),
            latitude: (string) $this->input('latitude'),
            longitude: (string) $this->input('longitude'),
            serviceRadiusKm: $this->integer('service_radius_km'),
            pickupFee: $this->integer('pickup_fee'),
            deliveryFee: $this->integer('delivery_fee'),
        );
    }
}
