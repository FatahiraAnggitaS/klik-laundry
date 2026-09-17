<?php

namespace App\Http\Requests\Orders;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;
use Illuminate\Validation\Rule;

final class OrderQueryRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->identity()->role() === ($this->routeIs('tenant.orders.*') ? UserRole::TenantOwner : UserRole::Customer);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'fulfillment_status' => ['nullable', Rule::enum(FulfillmentStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'query' => ['nullable', 'string', 'max:64'],
        ];
    }

    /** @return array{fulfillment_status: string|null, payment_status: string|null, query: string|null} */
    public function filters(): array
    {
        return ['fulfillment_status' => $this->validated('fulfillment_status'), 'payment_status' => $this->validated('payment_status'), 'query' => $this->validated('query')];
    }
}
