<?php

namespace App\Http\Requests\Tenancy;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class ResubmitTenantApplicationRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-own-tenant') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'outlet_name' => ['required', 'string', 'max:120'],
            'outlet_address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:120'],
            'area' => ['required', 'string', 'max:120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
