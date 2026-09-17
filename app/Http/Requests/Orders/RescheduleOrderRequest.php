<?php

namespace App\Http\Requests\Orders;

use App\Enums\UserRole;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class RescheduleOrderRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->identity()->role() === ($this->routeIs('tenant.orders.*') ? UserRole::TenantOwner : UserRole::Customer);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['pickup_slot_public_id' => ['required', 'string', 'size:26'], 'pickup_date' => ['required', 'date_format:Y-m-d'], 'reason' => ['nullable', 'string', 'max:1000']];
    }
}
