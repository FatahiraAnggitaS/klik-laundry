<?php

namespace App\Http\Requests\Orders;

use App\Enums\UserRole;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;
use Illuminate\Validation\Rule;

final class DeliveryScheduleRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return in_array($this->identity()->role(), [UserRole::Customer, UserRole::TenantOwner], true);
    }

    public function rules(): array
    {
        return [
            'delivery_slot_public_id' => ['required', 'string', 'size:26'],
            'delivery_date' => ['required', 'date_format:Y-m-d'],
            'reason' => [Rule::requiredIf($this->identity()->role() === UserRole::TenantOwner), 'nullable', 'string', 'min:5', 'max:500'],
        ];
    }
}
