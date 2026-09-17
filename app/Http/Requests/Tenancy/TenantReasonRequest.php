<?php

namespace App\Http\Requests\Tenancy;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class TenantReasonRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-own-tenant') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:1000']];
    }
}
