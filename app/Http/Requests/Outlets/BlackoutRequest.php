<?php

namespace App\Http\Requests\Outlets;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class BlackoutRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-own-tenant') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
