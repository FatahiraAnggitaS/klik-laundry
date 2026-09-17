<?php

namespace App\Http\Requests\Outlets;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class OperatingHoursRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-own-tenant') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'hours' => ['required', 'array', 'min:1', 'max:7'],
            'hours.*.day_of_week' => ['required', 'integer', 'between:0,6', 'distinct'],
            'hours.*.opens_at' => ['required', 'date_format:H:i'],
            'hours.*.closes_at' => ['required', 'date_format:H:i', 'after:hours.*.opens_at'],
        ];
    }
}
