<?php

namespace App\Http\Requests\Outlets;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class TenantOperationRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-own-tenant') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [];
    }
}
