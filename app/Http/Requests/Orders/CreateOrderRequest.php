<?php

namespace App\Http\Requests\Orders;

use App\DTOs\Orders\OrderInputData;
use App\Enums\UserRole;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class CreateOrderRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->identity()->role() === UserRole::Customer;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'outlet_public_id' => ['required', 'string', 'size:26'],
            'package_public_id' => ['required', 'string', 'size:26'],
            'pickup_address_public_id' => ['required', 'string', 'size:26'],
            'delivery_address_public_id' => ['nullable', 'string', 'size:26'],
            'pickup_slot_public_id' => ['required', 'string', 'size:26'],
            'pickup_date' => ['required', 'date_format:Y-m-d'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'estimated_weight_grams' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function toDto(): OrderInputData
    {
        return new OrderInputData(
            $this->string('outlet_public_id')->toString(),
            $this->string('package_public_id')->toString(),
            $this->string('pickup_address_public_id')->toString(),
            $this->validated('delivery_address_public_id'),
            $this->string('pickup_slot_public_id')->toString(),
            $this->string('pickup_date')->toString(),
            $this->validated('quantity'),
            $this->validated('estimated_weight_grams'),
            $this->string('idempotency_key')->toString(),
        );
    }
}
