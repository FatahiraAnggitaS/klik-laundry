<?php

namespace App\Http\Requests\Customers;

use App\Enums\UserRole;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class CustomerAddressActionRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->identity()->role() === UserRole::Customer;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [];
    }
}
