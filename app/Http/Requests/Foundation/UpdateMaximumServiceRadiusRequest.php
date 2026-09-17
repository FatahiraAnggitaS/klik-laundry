<?php

namespace App\Http\Requests\Foundation;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class UpdateMaximumServiceRadiusRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-platform') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'maximum_service_radius_km' => ['required', 'integer', 'between:1,100'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
