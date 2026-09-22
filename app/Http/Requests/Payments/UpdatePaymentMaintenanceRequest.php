<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class UpdatePaymentMaintenanceRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-platform') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
