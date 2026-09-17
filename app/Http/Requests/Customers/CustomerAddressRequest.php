<?php

namespace App\Http\Requests\Customers;

use App\DTOs\Customers\CustomerAddressInputData;
use App\Enums\UserRole;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class CustomerAddressRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->identity()->role() === UserRole::Customer;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:80'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:120'],
            'area' => ['required', 'string', 'max:120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_consent' => ['accepted'],
            'make_default' => ['sometimes', 'boolean'],
        ];
    }

    public function toDto(): CustomerAddressInputData
    {
        return new CustomerAddressInputData(
            label: $this->string('label')->toString(),
            contactName: $this->string('contact_name')->toString(),
            contactPhone: $this->string('contact_phone')->toString(),
            address: $this->string('address')->toString(),
            city: $this->string('city')->toString(),
            area: $this->string('area')->toString(),
            latitude: (string) $this->input('latitude'),
            longitude: (string) $this->input('longitude'),
            makeDefault: $this->boolean('make_default'),
        );
    }
}
