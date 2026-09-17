<?php

namespace App\Http\Requests\Tenancy;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class SubmitPayoutAccountRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-own-tenant') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:120'],
            'account_holder_name' => ['required', 'string', 'max:120'],
            'account_number' => ['required', 'digits_between:6,30'],
        ];
    }
}
