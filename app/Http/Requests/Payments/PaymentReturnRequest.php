<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class PaymentReturnRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['merchantOrderId' => ['required', 'string', 'max:64']];
    }
}
