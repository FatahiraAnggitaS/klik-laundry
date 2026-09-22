<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class CreatePaymentInvoiceRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'channel_code' => ['required', 'string', 'max:16'],
        ];
    }
}
