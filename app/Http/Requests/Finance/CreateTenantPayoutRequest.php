<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class CreateTenantPayoutRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-platform') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['tenant_public_id' => ['required', 'string', 'size:26'], 'cutoff' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']];
    }
}
