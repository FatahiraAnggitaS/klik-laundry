<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class CreateDriverPayoutRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-own-tenant') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['driver_public_id' => ['required', 'string', 'size:26'], 'cutoff' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']];
    }
}
