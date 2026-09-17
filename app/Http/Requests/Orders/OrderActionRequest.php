<?php

namespace App\Http\Requests\Orders;

use App\Enums\UserRole;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class OrderActionRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->identity()->role() === ($this->routeIs('tenant.orders.*') ? UserRole::TenantOwner : UserRole::Customer);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:1000']];
    }
}
